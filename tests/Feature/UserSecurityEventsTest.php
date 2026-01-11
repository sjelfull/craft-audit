<?php

use craft\elements\User;
use craft\events\UserEvent;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('logs audit event when user is activated', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserActivated($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_ACTIVATED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
    expect($record->title)->toBe($user->username);
});

it('logs audit event when user is deactivated', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserDeactivated($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_DEACTIVATED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is suspended', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserSuspended($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_SUSPENDED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is unsuspended', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserUnsuspended($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_UNSUSPENDED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is locked', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserLocked($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_LOCKED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when user is unlocked', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserUnlocked($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_UNLOCKED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('does not log user security events when disabled', function () {
    Audit::$plugin->getSettings()->logUserSecurityEvents = false;

    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    $result = Audit::$plugin->auditService->onUserActivated($event);

    expect($result)->toBeFalse();

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_ACTIVATED])
        ->one();

    expect($record)->toBeNull();

    Audit::$plugin->getSettings()->logUserSecurityEvents = true;
});

it('captures user details in snapshot', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserEvent(['user' => $user]);
    Audit::$plugin->auditService->onUserActivated($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_ACTIVATED])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('userId');
    expect($model->snapshot)->toHaveKey('username');
    expect($model->snapshot)->toHaveKey('email');
    expect($model->snapshot['userId'])->toBe($user->id);
});
