<?php

use craft\elements\User;
use markhuot\craftpest\factories\User as UserFactory;
use superbig\audit\Audit;
use superbig\audit\console\controllers\DefaultController as ConsoleController;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\Settings;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\Privacy;

beforeEach(function () {
    AuditRecord::deleteAll();
    Audit::$plugin->auditRecorder->writeAfterCommit = false;
    $this->settings = Audit::$plugin->getSettings();
    $this->settings->redact = [];
    $this->settings->anonymiseDeletedUsers = true;
});

function privacyRow(array $attributes): AuditRecord
{
    $record = new AuditRecord();
    $record->setAttributes($attributes + [
        'event' => AuditEvent::EntrySaved->value,
        'siteId' => \Craft::$app->getSites()->getPrimarySite()->id,
    ], false);
    $record->save(false);

    return $record;
}

it('reads redacted handles from a list or from text', function () {
    expect((new Settings(['redact' => ['phone', ' email ', 'phone', '']]))->getRedactedHandles())->toBe(['phone', 'email']);
    expect((new Settings(['redact' => "phone\nemail, secret"]))->getRedactedHandles())->toBe(['phone', 'email', 'secret']);

    $settings = new Settings(['redact' => "phone\nemail"]);
    $settings->validate();
    expect($settings->redact)->toBe(['phone', 'email']);
});

it('blanks redacted values in snapshots and diffs before saving', function () {
    $this->settings->redact = ['phone', 'email'];

    $model = Audit::$plugin->auditRecorder->record(AuditEvent::EntrySaved, 'Contact page', [
        'title' => 'Contact page',
        'email' => 'someone@example.com',
        'content' => ['phone' => '+47 123 45 678', 'body' => 'Call us', 'blocks' => [['fields' => ['phone' => '555']]]],
        'emptyPhone' => ['phone' => null],
    ], [
        'phone' => ['handler' => 'plain', 'from' => '+47 000', 'to' => '+47 123 45 678'],
        'body' => ['handler' => 'plain', 'from' => 'Hi', 'to' => 'Call us'],
    ]);

    $record = AuditRecord::findOne($model->id);
    $snapshot = audit_snapshot($record);
    $changes = audit_decode_json_column($record->changedFields);

    expect($snapshot['email'])->toBe(Privacy::MASK);
    expect($snapshot['content']['phone'])->toBe(Privacy::MASK);
    expect($snapshot['content']['blocks'][0]['fields']['phone'])->toBe(Privacy::MASK);
    expect($snapshot['content']['body'])->toBe('Call us');
    expect($snapshot['emptyPhone']['phone'])->toBeNull();
    expect($changes['phone'])->toBe(['handler' => 'redacted']);
    expect($changes['body']['to'])->toBe('Call us');
    expect(json_encode([$snapshot, $changes]))->not->toContain('123 45');
});

it('redacts rows already in the log on demand', function () {
    $row = privacyRow([
        'title' => 'Contact page',
        'snapshot' => ['content' => ['phone' => '+47 123 45 678']],
        'changedFields' => ['phone' => ['handler' => 'plain', 'from' => 'a', 'to' => 'b']],
    ]);
    $untouched = privacyRow(['title' => 'Other', 'snapshot' => ['content' => ['body' => 'x']]]);
    $this->settings->redact = ['phone'];

    $this->console(ConsoleController::class, 'redact')
        ->assertSuccesful()
        ->assertSee('1 row');

    $row->refresh();
    expect(audit_snapshot($row)['content']['phone'])->toBe(Privacy::MASK);
    expect(audit_decode_json_column($row->changedFields)['phone'])->toBe(['handler' => 'redacted']);
    $untouched->refresh();
    expect(audit_snapshot($untouched)['content']['body'])->toBe('x');
});

it('anonymises a deleted user\'s rows instead of only unlinking them', function () {
    $user = UserFactory::factory()->create();
    $admin = User::find()->admin()->one();

    $byUser = privacyRow(['title' => 'Spring launch', 'userId' => $user->id, 'ip' => '203.0.113.9', 'userAgent' => 'Firefox', 'sessionId' => 'abc', 'location' => ['city' => 'Bergen']]);
    $aboutUser = privacyRow(['title' => $user->email, 'elementId' => $user->id, 'elementType' => User::class, 'userId' => $admin->id, 'ip' => '198.51.100.1', 'snapshot' => ['email' => $user->email], 'changedFields' => ['email' => ['handler' => 'plain', 'from' => 'a', 'to' => 'b']]]);
    $failedLogin = privacyRow(['event' => AuditEvent::UserLoginFailed->value, 'title' => $user->username, 'elementId' => $user->id, 'elementType' => User::class, 'ip' => '203.0.113.9']);
    $impersonated = privacyRow(['title' => 'Body field', 'userId' => $admin->id, 'impersonatorId' => $user->id, 'ip' => '198.51.100.1']);
    $other = privacyRow(['title' => 'Unrelated', 'userId' => $admin->id, 'ip' => '198.51.100.1']);

    \Craft::$app->getElements()->deleteElement($user);

    $byUser->refresh();
    expect($byUser->userId)->toBeNull();
    expect($byUser->ip)->toBeNull();
    expect($byUser->userAgent)->toBeNull();
    expect($byUser->sessionId)->toBeNull();
    expect($byUser->location)->toBeNull();
    expect($byUser->title)->toBe('Spring launch');

    $aboutUser->refresh();
    expect($aboutUser->title)->toBe(Privacy::ANONYMISED_TITLE);
    expect($aboutUser->snapshot)->toBeNull();
    expect($aboutUser->changedFields)->toBeNull();
    expect((int)$aboutUser->userId)->toBe((int)$admin->id);
    expect($aboutUser->ip)->toBe('198.51.100.1');

    $failedLogin->refresh();
    expect($failedLogin->title)->toBe(Privacy::ANONYMISED_TITLE);
    expect($failedLogin->ip)->toBeNull();

    expect(AuditRecord::findOne($impersonated->id)->impersonatorId)->toBeNull();
    expect(AuditRecord::findOne($other->id)->ip)->toBe('198.51.100.1');

    $leaks = AuditRecord::find()->where(['or', ['like', 'title', $user->username], ['like', 'title', $user->email]])->count();
    expect((int)$leaks)->toBe(0);
});

it('anonymises a hard-deleted user even though the links are already gone', function () {
    $user = UserFactory::factory()->create();
    $byUser = privacyRow(['title' => 'Spring launch', 'userId' => $user->id, 'ip' => '203.0.113.9']);
    $aboutUser = privacyRow(['title' => $user->email, 'elementId' => $user->id, 'elementType' => User::class]);

    \Craft::$app->getElements()->deleteElement($user, true);

    expect(AuditRecord::findOne($byUser->id)->ip)->toBeNull();
    expect(AuditRecord::findOne($aboutUser->id)->title)->toBe(Privacy::ANONYMISED_TITLE);
});

it('keeps deleted users\' rows as they are when anonymising is turned off', function () {
    $this->settings->anonymiseDeletedUsers = false;
    $user = UserFactory::factory()->create();
    $aboutUser = privacyRow(['title' => $user->email, 'elementId' => $user->id, 'elementType' => User::class, 'ip' => '203.0.113.9']);

    \Craft::$app->getElements()->deleteElement($user);

    expect(AuditRecord::findOne($aboutUser->id)->title)->toBe($user->email);
});

it('anonymises a user on demand from the console', function () {
    $user = UserFactory::factory()->create();
    $aboutUser = privacyRow(['title' => $user->email, 'elementId' => $user->id, 'elementType' => User::class]);

    $this->console(ConsoleController::class, 'anonymiseUser', [$user->username])
        ->assertSuccesful()
        ->assertSee('Anonymised');

    expect(AuditRecord::findOne($aboutUser->id)->title)->toBe(Privacy::ANONYMISED_TITLE);

    $this->console(ConsoleController::class, 'anonymiseUser', ['nobody-by-this-name'])
        ->assertFailed();
});
