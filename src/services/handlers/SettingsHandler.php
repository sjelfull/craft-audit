<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use craft\base\Component;
use craft\events\ConfigEvent;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;

/**
 * SettingsHandler — handles system/email settings change audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord`, `_getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class SettingsHandler extends Component
{
    public function onSettingsChanged(ConfigEvent $event, string $settingsType): bool
    {
        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $settingsType, $auditService) {
            $model = $auditService->_getStandardModel();
            $model->event = $settingsType === 'system'
                ? AuditModel::EVENT_SYSTEM_SETTINGS_CHANGED
                : AuditModel::EVENT_EMAIL_SETTINGS_CHANGED;
            $model->title = ucfirst($settingsType) . ' settings';
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'settingsType' => $settingsType,
                'path' => $event->path,
                'oldValue' => $event->oldValue,
                'newValue' => $event->newValue,
            ]));

            return $auditService->_saveRecord($model);
        });
    }
}
