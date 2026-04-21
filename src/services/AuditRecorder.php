<?php

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

/**
 * Single entry point for recording audit events.
 *
 * Unlike the legacy AuditService which has ~30 event-specific methods,
 * AuditRecorder has one `record()` method that handlers call directly.
 *
 * @since 4.0.0
 */
class AuditRecorder extends Component
{
    public const EVENT_BEFORE_RECORD = 'beforeRecord';

    /**
     * Record an audit event.
     *
     * @param AuditEvent|string $event         The event type (enum or string backing value)
     * @param string|null       $title         Human-readable title (e.g., the entry title)
     * @param array             $snapshot      Full state snapshot (JSON-serializable)
     * @param array             $changedFields Diff data from FieldDiffService (optional)
     * @param array             $overrides     Optional: override any AuditModel property
     */
    public function record(
        AuditEvent|string $event,
        ?string $title = null,
        array $snapshot = [],
        array $changedFields = [],
        array $overrides = [],
    ): ?AuditModel {
        $settings = Audit::$plugin->getSettings();

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

        // Fire BeforeRecord event — allows mutation / cancellation
        $beforeEvent = new BeforeRecordEvent(['model' => $model]);
        $this->trigger(self::EVENT_BEFORE_RECORD, $beforeEvent);
        if (!$beforeEvent->isValid) {
            return null;
        }

        // Persist
        $record = new AuditRecord();
        $record->siteId = $model->siteId;
        $record->sessionId = $model->sessionId;
        $record->userId = $model->userId;
        $record->elementId = $model->elementId;
        $record->elementType = $model->elementType;
        $record->parentId = $model->parentId;
        $record->event = $model->event;
        $record->title = $model->title;
        $record->ip = $model->ip;
        $record->userAgent = $model->userAgent;
        $record->location = $model->location ? Json::encode($model->location) : null;
        $record->snapshot = Json::encode($model->snapshot ?: []);
        $record->changedFields = !empty($model->changedFields) ? Json::encode($model->changedFields) : null;
        $record->request = $model->request;

        if (!$record->save()) {
            Craft::warning(
                'Audit: failed to save record: ' . Json::encode($record->getErrors()),
                __METHOD__
            );
            return null;
        }

        $model->id = $record->id;
        $model->dateCreated = $record->dateCreated;

        return $model;
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
}
