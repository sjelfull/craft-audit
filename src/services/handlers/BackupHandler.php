<?php

namespace superbig\audit\services\handlers;

use craft\base\Component;
use craft\events\BackupEvent;
use craft\events\RestoreEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;

/**
 * BackupHandler — handles database backup/restore audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditRecorder`.
 */
class BackupHandler extends Component
{
    public function onBackupCreated(BackupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::BackupCreated->value;
            $model->title = basename($event->file);
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
                'ignoreTables' => $event->ignoreTables,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onBackupRestored(RestoreEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::BackupRestored->value;
            $model->title = basename($event->file);
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }
}
