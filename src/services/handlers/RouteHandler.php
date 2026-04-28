<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use craft\base\Component;
use craft\events\RouteEvent;
use superbig\audit\Audit;
use superbig\audit\helpers\Route;
use superbig\audit\models\AuditModel;

/**
 * RouteHandler — handles route save/delete audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditRecorder`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class RouteHandler extends Component
{
    public function onSaveRoute(RouteEvent $event)
    {
        if (!Audit::$plugin->getSettings()->logRouteEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $uriDisplay = Route::getUriDisplayHtml($event->uriParts);
            $model = $auditRecorder->getStandardModel();
            // Craft 5's RouteEvent doesn't expose routeId, so we can't distinguish new vs. existing
            $model->event = AuditModel::EVENT_SAVED_ROUTE;
            $model->title = $uriDisplay . ' -> ' . $event->template;
            $snapshot = [
                'uriParts' => $event->uriParts,
                'template' => $event->template,
                'siteUid' => $event->siteUid,
            ];
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onDeleteRoute(RouteEvent $event)
    {
        if (!Audit::$plugin->getSettings()->logRouteEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $uriDisplay = Route::getUriDisplayHtml($event->uriParts);
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_DELETED_ROUTE;
            $model->title = $uriDisplay . ' -> ' . $event->template;
            $snapshot = [
                'uriParts' => $event->uriParts,
                'template' => $event->template,
                'siteUid' => $event->siteUid,
            ];
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $auditRecorder->saveRecord($model);
        });
    }
}
