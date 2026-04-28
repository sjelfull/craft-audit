<?php

namespace superbig\audit\services\handlers;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\events\UserEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;

/**
 * UserHandler — handles user-login/logout and user-security audit events
 * extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, and `afterSnapshot` via `Audit::$plugin->auditRecorder`.
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
            $model->event = AuditEvent::UserLoggedIn->value;

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
            $model->event = AuditEvent::UserLoggedOut->value;

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
            $model->event = AuditEvent::UserActivated->value;
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
            $model->event = AuditEvent::UserDeactivated->value;
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
            $model->event = AuditEvent::UserSuspended->value;
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
            $model->event = AuditEvent::UserUnsuspended->value;
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
            $model->event = AuditEvent::UserLocked->value;
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
            $model->event = AuditEvent::UserUnlocked->value;
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
