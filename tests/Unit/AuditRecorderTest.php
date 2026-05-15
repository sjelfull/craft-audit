<?php

use craft\elements\User;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\events\BeforeRecordEvent;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\AuditRecorder;

/**
 * Helper: toggle ProjectConfig::isApplyingExternalChanges via reflection
 * since the public setter is blocked (read-only Yii property).
 */
function setApplyingExternalChanges(bool $value): void
{
    $pc = \Craft::$app->projectConfig;
    $ref = new \ReflectionClass($pc);
    // Try common property names across Craft versions
    foreach (['_applyingExternalChanges', 'applyingExternalChanges'] as $propName) {
        if ($ref->hasProperty($propName)) {
            $prop = $ref->getProperty($propName);
            $prop->setAccessible(true);
            $prop->setValue($pc, $value);
            return;
        }
    }
    // Fallback: write to the getter backing if none found (no-op if it doesn't exist)
}

beforeEach(function () {
    AuditRecord::deleteAll();
    // Scrub any BeforeRecord listeners from prior tests BEFORE running
    \yii\base\Event::off(AuditRecorder::class, AuditRecorder::EVENT_BEFORE_RECORD);
    $admin = User::find()->admin()->one();
    if ($admin) {
        \Craft::$app->getUser()->setIdentity($admin);
    }
});

afterEach(function () {
    // Reset isApplyingExternalChanges first (reflection-safe) before anything else
    setApplyingExternalChanges(false);
    // Targeted cleanup — only AuditRecorder event listeners
    \yii\base\Event::off(AuditRecorder::class, AuditRecorder::EVENT_BEFORE_RECORD);
});

it('records an event with a title', function () {
    $recorder = Audit::$plugin->auditRecorder;

    $model = $recorder->record(
        AuditEvent::UserLoggedIn,
        'Test login',
        ['foo' => 'bar'],
    );

    expect($model)->not->toBeNull();
    expect($model->id)->not->toBeNull();
    expect($model->event)->toBe(AuditEvent::UserLoggedIn->value);
    expect($model->title)->toBe('Test login');
    expect($model->snapshot)->toBe(['foo' => 'bar']);

    $record = AuditRecord::findOne($model->id);
    expect($record)->not->toBeNull();
    expect($record->event)->toBe(AuditEvent::UserLoggedIn->value);
    expect($record->title)->toBe('Test login');
});

it('accepts either an enum or a string for the event', function () {
    $recorder = Audit::$plugin->auditRecorder;

    $m1 = $recorder->record(AuditEvent::UserLoggedIn, 'a');
    $m2 = $recorder->record('user-logged-in', 'b');

    expect($m1->event)->toBe('user-logged-in');
    expect($m1->eventEnum)->toBe(AuditEvent::UserLoggedIn);
    expect($m2->event)->toBe('user-logged-in');
    expect($m2->eventEnum)->toBe(AuditEvent::UserLoggedIn);

    // Unknown string — eventEnum should be null but string still stored
    $m3 = $recorder->record('wholly-unknown-event', 'c');
    expect($m3->event)->toBe('wholly-unknown-event');
    expect($m3->eventEnum)->toBeNull();
});

it('fires BeforeRecord event', function () {
    $fired = false;
    $captured = null;
    \yii\base\Event::on(
        AuditRecorder::class,
        AuditRecorder::EVENT_BEFORE_RECORD,
        function (BeforeRecordEvent $event) use (&$fired, &$captured) {
            $fired = true;
            $captured = $event->model->event;
        }
    );

    Audit::$plugin->auditRecorder->record(AuditEvent::UserLoggedIn, 'Trigger');

    expect($fired)->toBeTrue();
    expect($captured)->toBe(AuditEvent::UserLoggedIn->value);
});

it('allows BeforeRecord handlers to mutate the model', function () {
    \yii\base\Event::on(
        AuditRecorder::class,
        AuditRecorder::EVENT_BEFORE_RECORD,
        function (BeforeRecordEvent $event) {
            $event->model->title = 'Mutated';
            $event->model->snapshot['injected'] = true;
        }
    );

    $model = Audit::$plugin->auditRecorder->record(
        AuditEvent::UserLoggedIn,
        'Original',
    );

    expect($model->title)->toBe('Mutated');
    expect($model->snapshot)->toHaveKey('injected');

    $record = AuditRecord::findOne($model->id);
    expect($record->title)->toBe('Mutated');
});

it('allows BeforeRecord handlers to veto the record', function () {
    \yii\base\Event::on(
        AuditRecorder::class,
        AuditRecorder::EVENT_BEFORE_RECORD,
        function (BeforeRecordEvent $event) {
            $event->isValid = false;
        }
    );

    $before = AuditRecord::find()->count();
    $model = Audit::$plugin->auditRecorder->record(AuditEvent::UserLoggedIn, 'Veto');
    $after = AuditRecord::find()->count();

    expect($model)->toBeNull();
    expect($after)->toBe($before);
});

it('detects YAML-apply via isApplyingExternalChanges', function () {
    $recorder = Audit::$plugin->auditRecorder;

    expect($recorder->detectRequestSource())->not->toBe('yaml');

    setApplyingExternalChanges(true);
    expect($recorder->detectRequestSource())->toBe('yaml');

    setApplyingExternalChanges(false);
    expect($recorder->detectRequestSource())->not->toBe('yaml');
});

it('persists request source and changedFields', function () {
    $changed = [
        '_title' => ['handler' => 'plain', 'from' => 'Old', 'to' => 'New'],
    ];

    $model = Audit::$plugin->auditRecorder->record(
        AuditEvent::EntrySaved,
        'An Entry',
        ['some' => 'snap'],
        $changed,
    );

    expect($model)->not->toBeNull();
    expect($model->request)->not->toBeNull();
    expect($model->changedFields)->toEqual($changed);

    $record = AuditRecord::findOne($model->id);
    expect($record->request)->not->toBeNull();
    expect($record->changedFields)->not->toBeNull();
    $decoded = audit_decode_json_column($record->changedFields);
    // toEqual not toBe — Postgres JSONB may reorder keys on retrieval.
    expect($decoded)->toEqual($changed);
});
