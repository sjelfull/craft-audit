<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use Craft;
use craft\base\Component;
use craft\base\Plugin;
use craft\base\PluginInterface;
use superbig\audit\Audit;

/**
 * PluginHandler — handles plugin install/uninstall/enable/disable audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord` and `_getStandardModel` via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class PluginHandler extends Component
{
    public function onPluginEvent(string $event, PluginInterface $plugin): bool
    {
        if (!Audit::$plugin->getSettings()->logPluginEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        /** @var Plugin $plugin */
        try {
            $model = $auditService->_getStandardModel();
            $model->event = $event;
            $model->title = $plugin->name;
            $snapshot = [
                'title' => $plugin->name,
                'handle' => $plugin->handle,
                'version' => $plugin->version,
            ];
            $model->snapshot = $snapshot;

            return $auditService->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }
}
