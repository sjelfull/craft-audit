<?php

use craft\elements\User;
use craft\events\UserEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
    $adminId = User::find()->admin()->one()->id;
    \Craft::$app->getUser()->setIdentity(User::find()->id($adminId)->one());
});

/**
 * User security events go through \Craft::$app->users->activateUser(),
 * suspendUser(), unsuspendUser(), unlockUser(), etc. Audit::initLogEvents()
 * binds these to UserHandler. These tests build a fresh non-admin user per
 * case and exercise the real service so each pass validates the wiring.
 *
 * Note: there is no public Users::lockUser() method in Craft — locking
 * happens internally inside handleInvalidLogin(). The "lock" test stays
 * handler-direct.
 */
function _makeTestUser(string $tag): User
{
    $suffix = bin2hex(random_bytes(4));
    $user = new User();
    $user->username = $tag . '_' . $suffix;
    $user->email = $tag . '_' . $suffix . '@example.test';
    $user->firstName = 'Test';
    $user->lastName = ucfirst($tag);
    $user->newPassword = 'Test1234567!';
    if (!\Craft::$app->elements->saveElement($user)) {
        throw new \RuntimeException('Failed to save test user: ' . print_r($user->getErrors(), true));
    }
    return $user;
}

it('logs audit event when user is activated', function () {
    $user = _makeTestUser('activate');
    AuditRecord::deleteAll();

    \Craft::$app->users->activateUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserActivated->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is deactivated', function () {
    $user = _makeTestUser('deactivate');
    \Craft::$app->users->activateUser($user);
    AuditRecord::deleteAll();

    \Craft::$app->users->deactivateUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserDeactivated->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is suspended', function () {
    $user = _makeTestUser('suspend');
    AuditRecord::deleteAll();

    \Craft::$app->users->suspendUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserSuspended->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is unsuspended', function () {
    $user = _makeTestUser('unsuspend');
    \Craft::$app->users->suspendUser($user);
    AuditRecord::deleteAll();

    \Craft::$app->users->unsuspendUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserUnsuspended->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

/**
 * Lock has no public Craft API — locking flows through handleInvalidLogin
 * with maxInvalidLogins thresholds and per-user state. We invoke the
 * handler directly here because reproducing that side-channel reliably in
 * a unit test would require manipulating internal user attributes and
 * the global config; the wiring is verified by the runtime path
 * (Users::EVENT_AFTER_LOCK_USER → UserHandler::onUserLocked).
 */
it('logs audit event when user is locked', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->userHandler->onUserLocked($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserLocked->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is unlocked', function () {
    $user = _makeTestUser('unlock');
    AuditRecord::deleteAll();

    \Craft::$app->users->unlockUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserUnlocked->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('does not log user security events when disabled', function () {
    Audit::$plugin->getSettings()->logUserSecurityEvents = false;

    try {
        $user = _makeTestUser('disabled');
        \Craft::$app->users->activateUser($user);

        $record = AuditRecord::find()
            ->where(['event' => AuditEvent::UserActivated->value])
            ->one();

        expect($record)->toBeNull();
    } finally {
        Audit::$plugin->getSettings()->logUserSecurityEvents = true;
    }
});

it('captures user details in snapshot', function () {
    $user = _makeTestUser('snapshot');
    AuditRecord::deleteAll();

    \Craft::$app->users->activateUser($user);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserActivated->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('userId');
    expect($model->snapshot)->toHaveKey('username');
    expect($model->snapshot)->toHaveKey('email');
    expect($model->snapshot['userId'])->toBe($user->id);
});
