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
use craft\elements\User;
use craft\events\UserEvent;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;

/**
 * UserHandler — handles user-login/logout and user-security audit events
 * extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, and `afterSnapshot` via `Audit::$plugin->auditRecorder`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class UserHandler extends Component
{
    /**
     * @return bool
     */
    public function onLogin(): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserEvents) {
            return false;
        }

        try {
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::USER_LOGGED_IN;

            return $auditRecorder->saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @return bool
     */
    public function onBeforeLogout(): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserEvents) {
            return false;
        }

        try {
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::USER_LOGGED_OUT;

            return $auditRecorder->saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    public function onUserActivated(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_ACTIVATED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserDeactivated(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_DEACTIVATED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserSuspended(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_SUSPENDED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserUnsuspended(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_UNSUSPENDED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserLocked(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_LOCKED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserUnlocked(UserEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditModel::EVENT_USER_UNLOCKED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }
}
