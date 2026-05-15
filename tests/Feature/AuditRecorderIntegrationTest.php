<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('records an event end-to-end with the new recorder API', function () {
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Integration Test Entry',
        snapshot: ['foo' => 'bar'],
    );

    expect($model)->not->toBeNull();
    expect($model->event)->toBe('entry-saved');
    expect($model->title)->toBe('Integration Test Entry');
    expect($model->snapshot)->toBe(['foo' => 'bar']);

    $record = AuditRecord::findOne($model->id);
    expect($record)->not->toBeNull();
    expect($record->event)->toBe('entry-saved');
});

it('stores snapshot as JSON on disk', function () {
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'JSON Test',
        snapshot: ['nested' => ['array' => [1, 2, 3]]],
    );

    $record = AuditRecord::findOne($model->id);
    $decoded = audit_snapshot($record);
    expect($decoded)->toBe(['nested' => ['array' => [1, 2, 3]]]);
});

it('stores changedFields separately when provided', function () {
    $diff = [
        'title' => ['handler' => 'plain', 'from' => 'Old', 'to' => 'New'],
    ];
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Diff Test',
        snapshot: [],
        changedFields: $diff,
    );

    $record = AuditRecord::findOne($model->id);
    expect($record->changedFields)->not->toBeNull();
    $decoded = audit_decode_json_column($record->changedFields);
    // toEqual not toBe — Postgres JSONB may reorder keys on retrieval.
    expect($decoded)->toEqual($diff);
});

it('detects request source as one of the known values', function () {
    $source = Audit::$plugin->auditRecorder->detectRequestSource();
    // In test environment, this will typically be 'console' but web contexts
    // can produce 'cp' or 'site', and project-config apply produces 'yaml'.
    expect($source)->toBeIn(['cp', 'site', 'console', 'yaml']);
});

it('works with both enum and string event types', function () {
    $m1 = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Enum',
    );
    $m2 = Audit::$plugin->auditRecorder->record(
        event: 'entry-saved',
        title: 'String',
    );

    expect($m1->event)->toBe($m2->event);
    expect($m1->eventEnum)->toBe(AuditEvent::EntrySaved);
    expect($m2->eventEnum)->toBe(AuditEvent::EntrySaved);
});

it('applies overrides to the model', function () {
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Override Test',
        overrides: ['ip' => '10.0.0.1', 'userAgent' => 'PestBot/1.0'],
    );

    expect($model->ip)->toBe('10.0.0.1');
    expect($model->userAgent)->toBe('PestBot/1.0');

    $record = AuditRecord::findOne($model->id);
    expect($record->ip)->toBe('10.0.0.1');
    expect($record->userAgent)->toBe('PestBot/1.0');
});
