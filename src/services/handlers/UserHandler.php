<?php

namespace superbig\audit\services\handlers;

use Craft;
use craft\auth\methods\TOTP;
use craft\base\Component;
use craft\elements\User;
use craft\events\LoginFailureEvent;
use craft\events\UserEvent;
use craft\helpers\Html;
use craft\records\Authenticator as AuthenticatorRecord;
use craft\records\User as UserRecord;
use craft\records\WebAuthn as WebAuthnRecord;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use yii\base\ActionEvent;
use yii\base\Event;
use yii\db\AfterSaveEvent;

/**
 * UserHandler — handles user-login/logout and user-security audit events
 * extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, and `afterSnapshot` via `Audit::$plugin->auditRecorder`.
 */
class UserHandler extends Component
{
    private const LOGIN_NAME_LENGTH = 255;

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

            // Craft's impersonate actions put the impersonator in the session just before signing in.
            if ($model->impersonatorId !== null) {
                $target = Craft::$app->getUser()->getIdentity();
                $impersonator = Craft::$app->getUsers()->getUserById($model->impersonatorId);
                $model->event = AuditEvent::UserImpersonated->value;
                $model->userId = $model->impersonatorId;
                $model->elementId = $target?->id;
                $model->elementType = User::class;
                $model->title = (string)$target?->username;
                $model->snapshot = $auditRecorder->afterSnapshot($model, [
                    'userId' => $target?->id,
                    'username' => $target?->username,
                    'impersonatorId' => $model->impersonatorId,
                    'impersonatorUsername' => $impersonator?->username,
                ]);
            }

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

    /**
     * A failed sign-in. Records what was typed as the username and Craft's reason, never the password.
     */
    public function onLoginFailure(LoginFailureEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $request = Craft::$app->getRequest();
            $loginName = $request->getIsConsoleRequest() ? null : $request->getBodyParam('loginName');
            $loginName = is_string($loginName) ? mb_substr($loginName, 0, self::LOGIN_NAME_LENGTH) : null;
            $user = $event->user;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::UserLoginFailed->value;
            $model->title = mb_substr(Html::encode($loginName ?? $user?->username ?? ''), 0, 255);
            $model->elementId = $user?->id;
            $model->elementType = $user ? User::class : null;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'loginName' => $loginName,
                // With preventUserEnumeration on, the event's reason is the cover story shown to the visitor.
                'reason' => $user?->authError ?? $event->authError,
                'userId' => $user?->id,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    /**
     * Password and email changes, read from the user record so every path that writes them is covered.
     */
    public function onUserRecordUpdated(AfterSaveEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        $record = $event->sender;
        $old = $event->changedAttributes;
        if (!$record instanceof UserRecord || empty($old)) {
            return false;
        }

        $passwordSaved = $emailSaved = false;

        if (array_key_exists('password', $old) && $record->password !== null && $old['password'] !== $record->password) {
            $passwordSaved = $this->recordAccountChange(AuditEvent::UserPasswordChanged, $record);
        }

        if (array_key_exists('email', $old) && (string)$old['email'] !== (string)$record->email) {
            $emailSaved = $this->recordAccountChange(AuditEvent::UserEmailChanged, $record, [
                'email' => ['handler' => 'plain', 'from' => $old['email'], 'to' => $record->email],
            ]);
        }

        return $passwordSaved || $emailSaved;
    }

    /**
     * Craft stores the authenticator secret only once the first code from the app has been verified.
     */
    public function onAuthenticatorAdded(AfterSaveEvent $event): bool
    {
        $record = $event->sender;
        if (!$record instanceof AuthenticatorRecord || empty($record->auth2faSecret)) {
            return false;
        }

        return $this->recordOwnAuthChange(AuditEvent::UserMfaEnabled, (int)$record->userId, ['method' => TOTP::class]);
    }

    /**
     * Removing a two-step method is a bulk delete with no event, so the CP action that does it is the hook.
     */
    public function onAfterAuthAction(ActionEvent $event): bool
    {
        if ($event->action->id !== 'remove-method') {
            return false;
        }

        $method = Craft::$app->getRequest()->getBodyParam('method');
        $userId = Craft::$app->getUser()->getId();
        if (!is_string($method) || $userId === null) {
            return false;
        }

        return $this->recordOwnAuthChange(AuditEvent::UserMfaDisabled, (int)$userId, ['method' => $method]);
    }

    public function onPasskeyAdded(AfterSaveEvent $event): bool
    {
        $record = $event->sender;
        if (!$record instanceof WebAuthnRecord) {
            return false;
        }

        return $this->recordOwnAuthChange(AuditEvent::UserPasskeyAdded, (int)$record->userId, ['name' => $record->credentialName]);
    }

    public function onPasskeyRemoved(Event $event): bool
    {
        $record = $event->sender;
        if (!$record instanceof WebAuthnRecord) {
            return false;
        }

        return $this->recordOwnAuthChange(AuditEvent::UserPasskeyRemoved, (int)$record->userId, ['name' => $record->credentialName]);
    }

    private function recordOwnAuthChange(AuditEvent $event, int $userId, array $data): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        $record = UserRecord::findOne($userId);
        if ($record === null) {
            return false;
        }

        return $this->recordAccountChange($event, $record, [], $data);
    }

    private function recordAccountChange(AuditEvent $event, UserRecord $user, array $changedFields = [], array $data = []): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $user, $changedFields, $data, $auditRecorder) {
            $model = $auditRecorder->getStandardModel();
            $model->event = $event->value;
            $model->title = (string)$user->username;
            $model->elementId = (int)$user->id;
            $model->elementType = User::class;
            $model->changedFields = $changedFields;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => (int)$user->id,
                'username' => $user->username,
            ], $data));

            return $auditRecorder->saveRecord($model);
        });
    }
}
