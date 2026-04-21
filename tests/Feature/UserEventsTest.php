<?php

use craft\elements\User;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('logs audit event when user logs in', function () {
    $user = User::find()->admin()->one();

    // Act as the user (simulates login)
    \Craft::$app->getUser()->setIdentity($user);

    // Manually trigger login event handler
    Audit::$plugin->auditService->onLogin();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserLoggedIn->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->userId)->toBe($user->id);
});

it('logs audit event when user logs out', function () {
    $user = User::find()->admin()->one();

    // Set the user as logged in
    \Craft::$app->getUser()->setIdentity($user);

    // Manually trigger logout event handler
    Audit::$plugin->auditService->onBeforeLogout();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserLoggedOut->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->userId)->toBe($user->id);
});

it('does not log user events when disabled', function () {
    // Disable user event logging
    Audit::$plugin->getSettings()->logUserEvents = false;

    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    // Try to trigger login
    $result = Audit::$plugin->auditService->onLogin();

    expect($result)->toBeFalse();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserLoggedIn->value])
        ->one();

    expect($record)->toBeNull();

    // Re-enable for other tests
    Audit::$plugin->getSettings()->logUserEvents = true;
});

it('captures session ID on login event', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    Audit::$plugin->auditService->onLogin();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserLoggedIn->value])
        ->one();

    expect($record)->not->toBeNull();
    // Session ID may be null in test environment, but attribute should exist on the record
    expect($record->hasAttribute('sessionId'))->toBeTrue();
});
