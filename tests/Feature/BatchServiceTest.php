<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BatchEndedEvent;
use superbig\audit\events\BatchStartedEvent;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\BatchService;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('run() executes the callback, returns its value, and persists one parent row with state=completed', function () {
    $batch = Audit::$plugin->batch;

    $result = $batch->run('Happy path batch', function () {
        return 'callback-return-value';
    });

    expect($result)->toBe('callback-return-value');
    expect($batch->currentBatchId())->toBeNull();

    $rows = AuditRecord::find()->all();
    expect($rows)->toHaveCount(1);

    $parent = $rows[0];
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);
    expect($parent->title)->toBe('Happy path batch');

    $snapshot = json_decode($parent->snapshot, true);
    expect($snapshot['state'])->toBe('completed');
    expect($snapshot['childCount'])->toBe(0);
});

it('run() auto-attaches child records written via auditRecorder->record()', function () {
    $batch = Audit::$plugin->batch;

    $capturedBatchId = null;
    $batch->run('Auto-attach test', function () use (&$capturedBatchId, $batch) {
        $capturedBatchId = $batch->currentBatchId();
        Audit::$plugin->auditRecorder->record(
            event: AuditEvent::EntrySaved,
            title: 'Child A',
        );
        Audit::$plugin->auditRecorder->record(
            event: AuditEvent::EntrySaved,
            title: 'Child B',
        );
    });

    expect($capturedBatchId)->not->toBeNull();

    $children = AuditRecord::find()
        ->where(['parentId' => $capturedBatchId])
        ->all();
    expect($children)->toHaveCount(2);
    foreach ($children as $child) {
        expect((int) $child->parentId)->toBe($capturedBatchId);
    }
});

it('run() with exception marks batch as failed and re-throws', function () {
    $batch = Audit::$plugin->batch;

    $batchIdSeen = null;

    expect(function () use ($batch, &$batchIdSeen) {
        $batch->run('Failing batch', function () use ($batch, &$batchIdSeen) {
            $batchIdSeen = $batch->currentBatchId();
            throw new \RuntimeException('boom');
        });
    })->toThrow(\RuntimeException::class, 'boom');

    expect($batchIdSeen)->not->toBeNull();
    expect($batch->currentBatchId())->toBeNull();

    $parent = AuditRecord::findOne($batchIdSeen);
    expect($parent)->not->toBeNull();
    expect($parent->event)->toBe(AuditEvent::BatchFailed->value);

    $snapshot = json_decode($parent->snapshot, true);
    expect($snapshot['state'])->toBe('failed');
});

it('open() + close() manual lifecycle works and auto-attaches in between', function () {
    $batch = Audit::$plugin->batch;

    $id = $batch->open('Manual batch');
    expect($id)->toBeInt();
    expect($id)->toBeGreaterThan(0);
    expect($batch->currentBatchId())->toBe($id);

    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Child inside manual batch',
    );

    $batch->close($id);
    expect($batch->currentBatchId())->toBeNull();

    $children = AuditRecord::find()->where(['parentId' => $id])->all();
    expect($children)->toHaveCount(1);

    $parent = AuditRecord::findOne($id);
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);
});

it('currentBatchId() returns null when nothing is open', function () {
    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    expect(Audit::$plugin->batch->openBatches())->toBe([]);
});

it('nested batches inherit parentId and child records attach to innermost', function () {
    $batch = Audit::$plugin->batch;

    $outerId = $batch->open('Outer');

    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Direct outer child',
    );

    $innerId = $batch->open('Inner');

    // Inner's parent row should point at outer
    $innerRow = AuditRecord::findOne($innerId);
    expect((int) $innerRow->parentId)->toBe($outerId);

    // Record inside inner — should attach to inner, not outer
    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Inner child',
    );

    expect($batch->currentBatchId())->toBe($innerId);

    $batch->close($innerId);
    expect($batch->currentBatchId())->toBe($outerId);

    // After inner closes, records attach back to outer
    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Back-to-outer child',
    );

    $batch->close($outerId);

    $innerChildren = AuditRecord::find()->where(['parentId' => $innerId])->all();
    expect($innerChildren)->toHaveCount(1);
    expect($innerChildren[0]->title)->toBe('Inner child');

    $outerChildren = AuditRecord::find()->where(['parentId' => $outerId])->all();
    // outer has: direct outer child + inner batch row + back-to-outer child = 3
    expect($outerChildren)->toHaveCount(3);
});

it('close() with an unknown batch id is a no-op and logs a warning', function () {
    $batch = Audit::$plugin->batch;

    // Open A then B; close A out of order.
    $a = $batch->open('A');
    $b = $batch->open('B');

    // Out-of-order close — should still work, B stays open.
    $batch->close($a);
    expect($batch->currentBatchId())->toBe($b);

    $batch->close($b);
    expect($batch->currentBatchId())->toBeNull();

    // Both parent rows exist and are marked completed.
    $aRow = AuditRecord::findOne($a);
    $bRow = AuditRecord::findOne($b);
    expect($aRow->event)->toBe(AuditEvent::BatchCompleted->value);
    expect($bRow->event)->toBe(AuditEvent::BatchCompleted->value);
});

it('close() populates childCount and durationMs in the parent snapshot', function () {
    $batch = Audit::$plugin->batch;

    $id = $batch->open('Counted batch');
    Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '1');
    Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '2');
    Audit::$plugin->auditRecorder->record(event: AuditEvent::EntrySaved, title: '3');
    $batch->close($id);

    $parent = AuditRecord::findOne($id);
    $snapshot = json_decode($parent->snapshot, true);

    expect($snapshot['childCount'])->toBe(3);
    expect($snapshot['durationMs'])->toBeInt();
    expect($snapshot['durationMs'])->toBeGreaterThanOrEqual(0);
    expect($snapshot)->toHaveKey('closedAt');
    expect($snapshot)->toHaveKey('summary');
});

it('close() carries the supplied summary into the snapshot', function () {
    $batch = Audit::$plugin->batch;

    $id = $batch->open('With summary');
    $batch->close($id, summary: ['processed' => 42, 'errors' => 0]);

    $parent = AuditRecord::findOne($id);
    $snapshot = json_decode($parent->snapshot, true);
    expect($snapshot['summary'])->toBe(['processed' => 42, 'errors' => 0]);
});

it('EVENT_BATCH_STARTED fires with batchId, title, and metadata', function () {
    $batch = Audit::$plugin->batch;

    $captured = null;
    $batch->on(BatchService::EVENT_BATCH_STARTED, function (BatchStartedEvent $event) use (&$captured) {
        $captured = [
            'batchId' => $event->batchId,
            'title' => $event->title,
            'metadata' => $event->metadata,
        ];
    });

    $id = $batch->open('Started event test', ['key' => 'value']);
    $batch->close($id);

    expect($captured)->not->toBeNull();
    expect($captured['batchId'])->toBe($id);
    expect($captured['title'])->toBe('Started event test');
    expect($captured['metadata'])->toBe(['key' => 'value']);

    $batch->off(BatchService::EVENT_BATCH_STARTED);
});

it('EVENT_BATCH_ENDED fires with state=completed for success and state=failed for exception', function () {
    $batch = Audit::$plugin->batch;

    $captured = [];
    $listener = function (BatchEndedEvent $event) use (&$captured) {
        $captured[] = [
            'batchId' => $event->batchId,
            'title' => $event->title,
            'state' => $event->state,
            'childCount' => $event->childCount,
            'durationMs' => $event->durationMs,
        ];
    };
    $batch->on(BatchService::EVENT_BATCH_ENDED, $listener);

    $batch->run('Happy ended', fn () => null);

    try {
        $batch->run('Failing ended', function () {
            throw new \RuntimeException('x');
        });
    } catch (\RuntimeException) {
        // expected
    }

    $batch->off(BatchService::EVENT_BATCH_ENDED, $listener);

    expect($captured)->toHaveCount(2);
    expect($captured[0]['state'])->toBe('completed');
    expect($captured[0]['title'])->toBe('Happy ended');
    expect($captured[1]['state'])->toBe('failed');
    expect($captured[1]['title'])->toBe('Failing ended');
});

it('explicit parentId override beats batch auto-attach', function () {
    $batch = Audit::$plugin->batch;

    // Create a standalone record we can point at.
    $standalone = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Standalone',
    );
    $standaloneId = (int) $standalone->id;

    $batchId = $batch->open('Override test');
    $child = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Explicitly parented',
        overrides: ['parentId' => $standaloneId],
    );
    $batch->close($batchId);

    expect((int) $child->parentId)->toBe($standaloneId);
    expect((int) $child->parentId)->not->toBe($batchId);
});

it('close() is idempotent — second close on the same id is a no-op', function () {
    $batch = Audit::$plugin->batch;

    $id = $batch->open('Idempotent close');
    $batch->close($id);

    $parentAfterFirst = AuditRecord::findOne($id);
    $snapshotAfterFirst = $parentAfterFirst->snapshot;
    $eventAfterFirst = $parentAfterFirst->event;

    // Second close should warn-and-return; no side effects, no exception.
    $batch->close($id);

    $parentAfterSecond = AuditRecord::findOne($id);
    expect($parentAfterSecond->event)->toBe($eventAfterFirst);
    expect($parentAfterSecond->snapshot)->toBe($snapshotAfterFirst);
});
