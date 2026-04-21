<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use craft\base\Component;
use craft\events\BackupEvent;
use craft\events\RestoreEvent;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;

/**
 * BackupHandler — handles database backup/restore audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord`, `_getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class BackupHandler extends Component
{
    public function onBackupCreated(BackupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_BACKUP_CREATED;
            $model->title = basename($event->file);
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
                'ignoreTables' => $event->ignoreTables,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onBackupRestored(RestoreEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_BACKUP_RESTORED;
            $model->title = basename($event->file);
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
            ]));

            return $auditService->_saveRecord($model);
        });
    }
}
