<?php

namespace superbig\audit\services;

use Closure;
use Craft;
use craft\helpers\Json;
use RuntimeException;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BatchEndedEvent;
use superbig\audit\events\BatchStartedEvent;
use superbig\audit\records\AuditRecord;
use Throwable;
use yii\base\Component;

/**
 * Public batch processing API.
 *
 * Open a batch, do work, close the batch. Any audit records written between
 * `open()` and `close()` automatically inherit `parentId` from the currently
 * open batch (via {@see AuditRecorder::record()}), giving you a single parent
 * row that aggregates an arbitrary tree of child events.
 *
 * Three call shapes:
 *
 *   // Scoped (preferred): callback boundary handles open/close + failure path.
 *   Audit::$plugin->batch->run('Import products', function () {
 *       // ... record events ...
 *   });
 *
 *   // Manual: caller controls the lifecycle (e.g., across queue jobs).
 *   $id = Audit::$plugin->batch->open('Long-running import');
 *   // ...
 *   Audit::$plugin->batch->close($id);
 *
 *   // Introspection
 *   Audit::$plugin->batch->currentBatchId();   // innermost open batch or null
 *   Audit::$plugin->batch->openBatches();      // stack, outermost first
 *
 * Batches can be nested. The nested batch row inherits the outer batch as its
 * parent, and records inside the nested batch attach to the nested batch.
 *
 * Request-scoped state — the stack is not persisted across requests.
 */
class BatchService extends Component
{
    public const EVENT_BATCH_STARTED = 'batchStarted';
    public const EVENT_BATCH_ENDED = 'batchEnded';

    /** @var int[] Stack of currently-open batch IDs (innermost last). Request-scoped. */
    private array $stack = [];

    /**
     * @var array<int, array{title: string, openedAt: float, metadata: array}>
     *      Per-batch in-memory state for summary computation.
     */
    private array $state = [];

    /**
     * Open a batch, run the callback, close the batch.
     *
     * If the callback throws, the batch is closed with state='failed' before
     * the exception is re-thrown.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function run(string $title, Closure $callback, array $metadata = []): mixed
    {
        $batchId = $this->open($title, $metadata);

        try {
            $result = $callback();
            $this->close($batchId);
            return $result;
        } catch (Throwable $e) {
            $this->close($batchId, failed: true);
            throw $e;
        }
    }

    /**
     * Open a new batch — writes a parent audit row, pushes onto the stack,
     * fires EVENT_BATCH_STARTED, and returns the batch's audit row ID.
     *
     * If a batch is already open, the new batch's parent row inherits it via
     * an explicit `parentId` override (not auto-attach, since the new batch
     * isn't on the stack yet — see the comment below for re-entrancy details).
     */
    public function open(string $title, array $metadata = []): int
    {
        $snapshot = [
            'state' => 'running',
            'openedAt' => date('c'),
            'metadata' => $metadata,
        ];

        // If we're nested, explicitly pass parentId so the new batch row
        // attaches to the currently-open batch. We pass it via overrides
        // rather than relying on auto-attach because at this point the new
        // batch isn't on the stack yet (see below).
        $overrides = [];
        $parent = $this->currentBatchId();
        if ($parent !== null) {
            $overrides['parentId'] = $parent;
        }

        // IMPORTANT — re-entrancy: do NOT push onto $this->stack until AFTER
        // record() returns. The new batch row is created by record(); if we
        // pushed first, AuditRecorder's auto-attach hook would set the new
        // row's parentId to its own (future) id, which is nonsense.
        $model = Audit::$plugin->auditRecorder->record(
            event: AuditEvent::BatchStarted,
            title: $title,
            snapshot: $snapshot,
            overrides: $overrides,
        );

        if ($model === null || $model->id === null) {
            throw new RuntimeException(
                'BatchService::open(): failed to persist parent batch row '
                . '(record() returned null — likely cancelled by a BeforeRecord listener or save failure).'
            );
        }

        $batchId = (int) $model->id;

        // Now safe to push — the parent row is persisted with its correct
        // parentId (either null or the outer batch's id from overrides).
        $this->stack[] = $batchId;
        $this->state[$batchId] = [
            'title' => $title,
            'openedAt' => microtime(true),
            'metadata' => $metadata,
        ];

        $this->trigger(self::EVENT_BATCH_STARTED, new BatchStartedEvent([
            'batchId' => $batchId,
            'title' => $title,
            'metadata' => $metadata,
        ]));

        return $batchId;
    }

    /**
     * Close a batch by ID. Updates the parent row's snapshot with childCount,
     * durationMs, state ('completed' or 'failed'), and closedAt. Pops from
     * the stack. Fires EVENT_BATCH_ENDED.
     *
     * If $batchId isn't in the stack (e.g., double-close), logs a warning
     * and returns without raising. If it's not at the top of the stack
     * (out-of-order close), still closes the targeted batch and logs a
     * warning.
     */
    public function close(int $batchId, array $summary = [], bool $failed = false): void
    {
        $idx = array_search($batchId, $this->stack, true);
        if ($idx === false) {
            Craft::warning(
                "BatchService::close(): batch {$batchId} not found in stack (already closed?)",
                __METHOD__
            );
            return;
        }
        if ($idx !== count($this->stack) - 1) {
            Craft::warning(
                "BatchService::close(): batch {$batchId} closed out of order",
                __METHOD__
            );
        }
        array_splice($this->stack, $idx, 1);

        // Capture state BEFORE unset — the BatchEndedEvent payload needs the title.
        $title = $this->state[$batchId]['title'] ?? '';
        $opened = $this->state[$batchId]['openedAt'] ?? null;
        unset($this->state[$batchId]);

        $durationMs = $opened !== null ? (int) ((microtime(true) - $opened) * 1000) : null;

        // Count children
        $childCount = (int) AuditRecord::find()
            ->where(['parentId' => $batchId])
            ->count();

        // Load the parent row and update its snapshot + event
        $parentRecord = AuditRecord::findOne($batchId);
        if ($parentRecord === null) {
            Craft::warning(
                "BatchService::close(): parent record {$batchId} not found",
                __METHOD__
            );
            return;
        }

        $raw = $parentRecord->snapshot;
        if (is_array($raw)) {
            $snapshot = $raw;
        } else {
            // Legacy compat for doubly-encoded string rows.
            $decoded = Json::decodeIfJson($raw);
            if (is_string($decoded)) {
                $decoded = Json::decodeIfJson($decoded);
            }
            $snapshot = is_array($decoded) ? $decoded : [];
        }

        $state = $failed ? 'failed' : 'completed';
        $snapshot['state'] = $state;
        $snapshot['closedAt'] = date('c');
        $snapshot['durationMs'] = $durationMs;
        $snapshot['childCount'] = $childCount;
        $snapshot['summary'] = $summary;

        $parentRecord->event = $failed
            ? AuditEvent::BatchFailed->value
            : AuditEvent::BatchCompleted->value;
        $parentRecord->snapshot = $snapshot;
        $parentRecord->save(false);

        $this->trigger(self::EVENT_BATCH_ENDED, new BatchEndedEvent([
            'batchId' => $batchId,
            'title' => $title,
            'state' => $state,
            'durationMs' => $durationMs,
            'childCount' => $childCount,
            'summary' => $summary,
        ]));
    }

    /** Returns the innermost open batch ID, or null if no batch is open. */
    public function currentBatchId(): ?int
    {
        if ($this->stack === []) {
            return null;
        }
        return (int) $this->stack[count($this->stack) - 1];
    }

    /**
     * Returns the current stack of open batch IDs (outermost first).
     * For testing and introspection.
     *
     * @return int[]
     */
    public function openBatches(): array
    {
        return $this->stack;
    }
}
