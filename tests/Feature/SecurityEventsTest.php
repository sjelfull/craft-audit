<?php

use craft\auth\methods\TOTP;
use craft\controllers\AuthController;
use craft\elements\User;
use craft\helpers\Session as SessionHelper;
use craft\records\Authenticator as AuthenticatorRecord;
use craft\records\WebAuthn as WebAuthnRecord;
use markhuot\craftpest\factories\Entry as EntryFactory;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use yii\base\ActionEvent;
use yii\base\Controller;
use yii\base\InlineAction;

beforeEach(function () {
    AuditRecord::deleteAll();
});

function _securityUser(string $tag, bool $admin = false): User
{
    $suffix = bin2hex(random_bytes(4));
    $user = new User();
    $user->username = "{$tag}_{$suffix}";
    $user->email = "{$tag}_{$suffix}@example.test";
    $user->newPassword = 'Correct-horse-1';
    $user->admin = $admin;
    if (!\Craft::$app->getElements()->saveElement($user)) {
        throw new RuntimeException('Failed to save test user: ' . print_r($user->getErrors(), true));
    }
    \Craft::$app->getUsers()->activateUser($user);

    return $user;
}

function _securityRows(AuditEvent $event): array
{
    return AuditRecord::find()->where(['event' => $event->value])->orderBy(['id' => SORT_ASC])->all();
}

it('logs a failed sign-in for an unknown username without the password', function () {
    $this->action('users/login', ['loginName' => 'nobody@example.test', 'password' => 'hunter2-secret']);

    $rows = _securityRows(AuditEvent::UserLoginFailed);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->title)->toBe('nobody@example.test');
    expect($rows[0]->elementId)->toBeNull();

    $snapshot = audit_snapshot($rows[0]);
    expect($snapshot['loginName'])->toBe('nobody@example.test');
    expect($snapshot['reason'])->toBe(User::AUTH_INVALID_CREDENTIALS);
    expect(json_encode($snapshot))->not->toContain('hunter2-secret');
});

it('logs a failed sign-in against a real account with the account as subject', function () {
    $user = _securityUser('wrongpass');
    AuditRecord::deleteAll();

    $this->action('users/login', ['loginName' => $user->username, 'password' => 'not-the-password']);

    $rows = _securityRows(AuditEvent::UserLoginFailed);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->elementId)->toBe($user->id);
    expect($rows[0]->elementType)->toBe(User::class);
    expect(audit_snapshot($rows[0])['reason'])->toBe(User::AUTH_INVALID_CREDENTIALS);
});

it('encodes the attempted username in the title', function () {
    $this->action('users/login', ['loginName' => '<script>alert(1)</script>', 'password' => 'x']);

    $rows = _securityRows(AuditEvent::UserLoginFailed);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->title)->not->toContain('<script>');
});

it('logs an impersonation and ties later rows to both users', function () {
    $admin = User::find()->admin()->one();
    $target = _securityUser('target');
    \Craft::$app->getUser()->setIdentity($admin);
    AuditRecord::deleteAll();

    SessionHelper::set(\Craft::$app->getUser()->impersonatorIdParam, $admin->id);
    \Craft::$app->getUser()->login($target);

    $rows = _securityRows(AuditEvent::UserImpersonated);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->userId)->toBe($admin->id);
    expect($rows[0]->impersonatorId)->toBe($admin->id);
    expect($rows[0]->elementId)->toBe($target->id);
    expect(_securityRows(AuditEvent::UserLoggedIn))->toHaveCount(0);

    $entry = EntryFactory::factory()->section('news')->create();
    $saved = AuditRecord::find()->where(['elementId' => $entry->id])->one();
    expect($saved->userId)->toBe($target->id);
    expect($saved->impersonatorId)->toBe($admin->id);

    SessionHelper::remove(\Craft::$app->getUser()->impersonatorIdParam);
    \Craft::$app->getUser()->setIdentity($admin);
});

it('logs a password change without the password', function () {
    $user = _securityUser('pwchange');
    AuditRecord::deleteAll();

    $user->newPassword = 'Another-horse-2';
    expect(\Craft::$app->getElements()->saveElement($user))->toBeTrue();

    $rows = _securityRows(AuditEvent::UserPasswordChanged);
    expect($rows)->toHaveCount(1);
    expect($rows[0]->elementId)->toBe($user->id);
    expect(json_encode(audit_snapshot($rows[0])))->not->toContain('Another-horse-2');
});

it('logs an email change with the old and new address', function () {
    $user = _securityUser('emailchange');
    $old = $user->email;
    AuditRecord::deleteAll();

    $user->email = 'changed_' . $old;
    expect(\Craft::$app->getElements()->saveElement($user))->toBeTrue();

    $rows = _securityRows(AuditEvent::UserEmailChanged);
    expect($rows)->toHaveCount(1);
    $changed = audit_decode_json_column($rows[0]->changedFields);
    expect($changed['email']['from'])->toBe($old);
    expect($changed['email']['to'])->toBe('changed_' . $old);
});

it('does not log a password or email change for an unrelated save', function () {
    $user = _securityUser('unrelated');
    AuditRecord::deleteAll();

    $user->firstName = 'Renamed';
    \Craft::$app->getElements()->saveElement($user);

    expect(_securityRows(AuditEvent::UserPasswordChanged))->toHaveCount(0);
    expect(_securityRows(AuditEvent::UserEmailChanged))->toHaveCount(0);
});

it('logs two-step verification being set up and removed', function () {
    $user = _securityUser('mfa');
    \Craft::$app->getUser()->setIdentity($user);
    AuditRecord::deleteAll();

    // What TOTP::verify() stores once the first code checks out.
    $record = new AuthenticatorRecord(['userId' => $user->id, 'auth2faSecret' => 'JBSWY3DPEHPK3PXP', 'oldTimestamp' => time()]);
    expect($record->save())->toBeTrue();

    $enabled = _securityRows(AuditEvent::UserMfaEnabled);
    expect($enabled)->toHaveCount(1);
    expect($enabled[0]->elementId)->toBe($user->id);
    expect(audit_snapshot($enabled[0])['method'])->toBe(TOTP::class);

    \Craft::$app->getRequest()->setBodyParams(['method' => TOTP::class]);
    \Craft::$app->getAuth()->getMethod(TOTP::class, $user)->remove();
    $controller = new AuthController('auth', \Craft::$app);
    $controller->trigger(Controller::EVENT_AFTER_ACTION, new ActionEvent(new InlineAction('remove-method', $controller, 'actionRemoveMethod')));
    \Craft::$app->getRequest()->setBodyParams([]);

    $removed = _securityRows(AuditEvent::UserMfaDisabled);
    expect($removed)->toHaveCount(1);
    expect($removed[0]->elementId)->toBe($user->id);

    \Craft::$app->getUser()->setIdentity(User::find()->admin()->one());
});

it('logs a passkey being added and removed', function () {
    $user = _securityUser('passkey');
    \Craft::$app->getUser()->setIdentity($user);
    AuditRecord::deleteAll();

    $record = new WebAuthnRecord([
        'userId' => $user->id,
        'credentialId' => 'credential-' . $user->id,
        'credentialName' => 'Laptop',
        'credential' => '{}',
    ]);
    expect($record->save())->toBeTrue();
    \Craft::$app->getAuth()->deletePasskey($user, $record->uid);

    $added = _securityRows(AuditEvent::UserPasskeyAdded);
    $removed = _securityRows(AuditEvent::UserPasskeyRemoved);
    expect($added)->toHaveCount(1);
    expect($removed)->toHaveCount(1);
    expect(audit_snapshot($added[0])['name'])->toBe('Laptop');
    expect($removed[0]->elementId)->toBe($user->id);

    \Craft::$app->getUser()->setIdentity(User::find()->admin()->one());
});
