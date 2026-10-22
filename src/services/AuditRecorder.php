<?php

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\db\Connection;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Session as SessionHelper;
use craft\helpers\StringHelper;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\events\SnapshotEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

/**
 * Single entry point for recording audit events.
 *
 * Unlike the legacy AuditService which has ~30 event-specific methods,
 * AuditRecorder has one `record()` method that handlers call directly.
 */
class AuditRecorder extends Component
{
    public const EVENT_BEFORE_RECORD = 'beforeRecord';
    public const EVENT_SNAPSHOT = 'snapshot';

    /**
     * The connection rows are written to and whose transactions they follow. Null means Craft's own.
     */
    public ?Connection $db = null;

    /**
     * Hold rows recorded inside a transaction until it commits, so a rolled-back save leaves no row.
     */
    public bool $writeAfterCommit = true;

    /**
     * @var AuditRecord[]
     */
    private array $pending = [];

    /**
     * @var array<int, true> Connections whose transaction events are being followed.
     */
    private array $listening = [];

    /**
     * Record an audit event.
     *
     * @param AuditEvent|string $event         The event type (enum or string backing value)
     * @param string|null       $title         Human-readable title (e.g., the entry title)
     * @param array             $snapshot      Full state snapshot (JSON-serializable)
     * @param array             $changedFields Diff data from FieldDiffService (optional)
     * @param array             $overrides     Optional: override any AuditModel property
     * @param bool              $writeNow      Write inside any open transaction instead of waiting for it to commit,
     *                                         so the row has an id straight away (batch parents need one)
     */
    public function record(
        AuditEvent|string $event,
        ?string $title = null,
        array $snapshot = [],
        array $changedFields = [],
        array $overrides = [],
        bool $writeNow = false,
    ): ?AuditModel {
        try {
            return $this->recordModel($event, $title, $snapshot, $changedFields, $overrides, $writeNow);
        } catch (\Throwable $e) {
            $this->fail('Could not record an event', $e);
            return null;
        }
    }

    private function recordModel(
        AuditEvent|string $event,
        ?string $title,
        array $snapshot,
        array $changedFields,
        array $overrides,
        bool $writeNow,
    ): ?AuditModel {
        // Build model
        $model = new AuditModel();
        $model->event = $event instanceof AuditEvent ? $event->value : $event;
        $model->eventEnum = $event instanceof AuditEvent ? $event : AuditEvent::tryFromString($event);
        $model->title = $title ?? '';
        $model->snapshot = $snapshot;
        $model->changedFields = $changedFields;

        // Context (user/session/site/ip)
        $this->hydrateContext($model);

        // Detect YAML-apply
        $model->request = $this->detectRequestSource();

        // Apply overrides last (callers can override any property)
        foreach ($overrides as $key => $value) {
            if (property_exists($model, $key)) {
                $model->$key = $value;
            }
        }

        // Auto-attach to the currently-open batch if no parentId override was provided.
        // Note: when BatchService::open() calls record() to create the batch row itself,
        // it hasn't pushed onto the stack yet — so currentBatchId() returns null (or the
        // outer batch's id for nested batches, in which case it's already supplied via
        // overrides above). This prevents a batch row from auto-attaching to itself.
        if ($model->parentId === null) {
            $model->parentId = Audit::$plugin->batch->currentBatchId();
        }

        // Fire BeforeRecord event — allows mutation / cancellation
        $beforeEvent = new BeforeRecordEvent(['model' => $model]);
        $this->trigger(self::EVENT_BEFORE_RECORD, $beforeEvent);
        if (!$beforeEvent->isValid) {
            return null;
        }

        $record = $this->toRecord($model);
        $record->location = $model->location ?: null;
        $record->request = $model->request;

        if (!$this->write($record, $writeNow)) {
            return null;
        }

        $model->id = $record->id;
        $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated) ?: null;

        return $model;
    }

    /**
     * Rows waiting for the outermost transaction to commit, for a batch's child count.
     */
    public function pendingChildCount(int $parentId): int
    {
        return count(array_filter($this->pending, fn(AuditRecord $record) => (int)$record->parentId === $parentId));
    }

    public function getDb(): Connection
    {
        return $this->db ?? Craft::$app->getDb();
    }

    /**
     * Writes a row now, or holds it until the outermost transaction commits and drops it if that rolls back.
     * Yii reports only the outermost commit and rollback, so a savepoint rolled back inside a committed
     * transaction keeps its rows.
     */
    private function write(AuditRecord $record, bool $writeNow = false): bool
    {
        try {
            $now = Db::prepareDateForDb(DateTimeHelper::now());
            $record->dateCreated ??= $now;
            $record->dateUpdated ??= $now;
            $record->uid ??= StringHelper::UUID();

            $db = $this->getDb();
            if ($writeNow || !$this->writeAfterCommit || $db->getTransaction() === null) {
                return $this->insert($db, $record);
            }

            $this->listen($db);
            $this->pending[] = $record;

            return true;
        } catch (\Throwable $e) {
            $this->fail('Could not write an audit row', $e);
            return false;
        }
    }

    private function insert(Connection $db, AuditRecord $record): bool
    {
        $values = $record->getDirtyAttributes();
        unset($values['id']);
        $keys = $db->getSchema()->insert(AuditRecord::tableName(), $values);
        if ($keys === false) {
            return false;
        }
        $record->id = (int)$keys['id'];

        return true;
    }

    private function listen(Connection $db): void
    {
        $key = spl_object_id($db);
        if (isset($this->listening[$key])) {
            return;
        }
        $this->listening[$key] = true;

        $db->on(Connection::EVENT_COMMIT_TRANSACTION, function() use ($db) {
            $rows = $this->pending;
            $this->pending = [];
            foreach ($rows as $record) {
                try {
                    $this->insert($db, $record);
                } catch (\Throwable $e) {
                    $this->fail('Could not write an audit row', $e);
                }
            }
        });
        $db->on(Connection::EVENT_ROLLBACK_TRANSACTION, function() {
            $this->pending = [];
        });
    }

    private function toRecord(AuditModel $model): AuditRecord
    {
        $record = new AuditRecord();
        $record->siteId = $model->siteId;
        $record->sessionId = $model->sessionId;
        $record->userId = $model->userId;
        $record->impersonatorId = $model->impersonatorId;
        $record->elementId = $model->elementId;
        $record->elementType = $model->elementType;
        $record->parentId = $model->parentId;
        $record->event = $model->event;
        $record->title = $model->title;
        $record->ip = $model->ip;
        $record->userAgent = $model->userAgent;
        $record->snapshot = $model->snapshot ?: [];
        $record->changedFields = !empty($model->changedFields) ? $model->changedFields : null;

        return $record;
    }

    private function fail(string $message, \Throwable $e): void
    {
        try {
            Craft::error(sprintf('%s: %s', $message, $e->getMessage()), 'audit');
        } catch (\Throwable) {
            // Nowhere left to report it, and recording must never throw.
        }
    }

    /**
     * Detect the request source.
     * Returns 'yaml' for project-config apply, 'console' for console, 'cp'/'site' for web.
     */
    public function detectRequestSource(): string
    {
        if (Craft::$app->projectConfig->isApplyingExternalChanges) {
            return 'yaml';
        }

        $request = Craft::$app->getRequest();
        if ($request->isConsoleRequest) {
            return 'console';
        }
        if ($request->isCpRequest ?? false) {
            return 'cp';
        }
        return 'site';
    }

    /**
     * Populate contextual fields on the model (user, session, site, ip, UA).
     */
    protected function hydrateContext(AuditModel $model): void
    {
        $model->siteId = Craft::$app->sites->currentSite->id ?? 1;

        $user = Craft::$app->user->getIdentity();
        if ($user !== null) {
            $model->userId = $user->id;
            $model->impersonatorId = $this->impersonatorId($user->id);
        }

        $request = Craft::$app->getRequest();
        if (!$request->isConsoleRequest) {
            try {
                $model->ip = $request->getUserIP() ?: '';
                $model->userAgent = (string) ($request->getUserAgent() ?? '');
            } catch (\Throwable) {
                // Console or early-boot — leave empty
            }

            try {
                $session = Craft::$app->getSession();
                $model->sessionId = $session->getId() ?: null;
            } catch (\Throwable) {
                $model->sessionId = null;
            }
        }
    }

    /**
     * The real user behind an impersonated session, read from the session so it costs no query.
     */
    public function impersonatorId(?int $identityId): ?int
    {
        try {
            $id = (int)SessionHelper::get(Craft::$app->getUser()->impersonatorIdParam) ?: null;
        } catch (\Throwable) {
            return null;
        }

        return $id !== null && $id !== $identityId ? $id : null;
    }

    /**
     * @internal Public for use by handler services in services/handlers/. Do not call from outside the plugin.
     */
    public function afterSnapshot(AuditModel $auditModel, $snapshot): array
    {
        $event = new SnapshotEvent([
            'audit' => $auditModel,
            'snapshot' => $snapshot,
        ]);

        $this->trigger(self::EVENT_SNAPSHOT, $event);

        return $event->snapshot;
    }

    /**
     * @internal Public for use by handler services in services/handlers/. Do not call from outside the plugin.
     */
    public function getStandardModel(): AuditModel
    {
        $app = Craft::$app;
        $request = $app->getRequest();
        $model = new AuditModel();
        $model->siteId = $app->getSites()->currentSite->id;

        if (!$request->isConsoleRequest) {
            $session = $app->getSession();
            $model->sessionId = $session->getId();
            $model->ip = $request->getUserIP();
            $model->userAgent = $request->getUserAgent();

            if ($identity = $app->getUser()->getIdentity()) {
                $model->userId = $identity->id;
                $model->impersonatorId = $this->impersonatorId($identity->id);

                $model->snapshot = [
                    'userId' => $model->userId,
                ];
            }
        }

        return $model;
    }

    /**
     * @internal Public for use by handler services in services/handlers/. Do not call from outside the plugin.
     */
    public function saveRecord(AuditModel &$model, bool $unique = true): bool
    {
        try {
            // Auto-attach to the currently-open batch if no parentId was set explicitly.
            if ($model->parentId === null) {
                $model->parentId = Audit::$plugin->batch->currentBatchId();
            }

            if ($model->id) {
                $record = AuditRecord::findOne($model->id);
                if ($record === null) {
                    return false;
                }
                foreach ($this->toRecord($model)->getDirtyAttributes() as $name => $value) {
                    $record->setAttribute($name, $value);
                }

                return $record->save(false);
            }

            $record = $this->toRecord($model);
            if (!$this->write($record)) {
                return false;
            }
            $model->id = $record->id;

            return true;
        } catch (\Throwable $e) {
            $this->fail('Could not save an audit row', $e);

            return false;
        }
    }

    /**
     * @internal Public for use by handler services in services/handlers/. Do not call from outside the plugin.
     */
    public function catchSaveError(callable $callable)
    {
        try {
            return $callable();
        } catch (\Throwable $e) {
            $this->fail('Error when logging', $e);

            return false;
        }
    }
}
