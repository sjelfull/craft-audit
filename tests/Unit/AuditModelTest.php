<?php

use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

it('creates model from record with all fields populated', function () {
    $record = new AuditRecord();
    $record->id = 1;
    $record->event = AuditEvent::SavedElement->value;
    $record->title = 'Test Entry';
    $record->userId = 1;
    $record->elementId = 100;
    $record->elementType = 'craft\\elements\\Entry';
    $record->ip = '127.0.0.1';
    $record->userAgent = 'Mozilla/5.0';
    $record->siteId = 1;
    $record->sessionId = 'test-session';
    $record->snapshot = '{"key":"value"}';
    $record->dateCreated = new \DateTime();

    $model = AuditModel::createFromRecord($record);

    expect($model->id)->toBe(1);
    expect($model->event)->toBe(AuditEvent::SavedElement->value);
    expect($model->title)->toBe('Test Entry');
    expect($model->userId)->toBe(1);
    expect($model->elementId)->toBe(100);
    expect($model->elementType)->toBe('craft\\elements\\Entry');
    expect($model->ip)->toBe('127.0.0.1');
    expect($model->userAgent)->toBe('Mozilla/5.0');
    expect($model->siteId)->toBe(1);
    expect($model->sessionId)->toBe('test-session');
    expect($model->snapshot)->toBe(['key' => 'value']);
});

it('returns translated event label', function () {
    $model = new AuditModel();
    $model->event = AuditEvent::SavedElement->value;

    $label = $model->getEventLabel();

    expect($label)->toBeString();
    // Label should contain the event name (may be translated)
    expect($label)->not->toBeEmpty();
});

it('appends data to snapshot correctly', function () {
    $model = new AuditModel();
    $model->snapshot = ['existing' => 'data'];

    $model->appendSnapshot('newKey', 'newValue');

    expect($model->snapshot)->toHaveKey('existing');
    expect($model->snapshot)->toHaveKey('newKey');
    expect($model->snapshot['newKey'])->toBe('newValue');
});

it('returns valid JSON from getSnapshotJson', function () {
    $model = new AuditModel();
    $model->snapshot = [
        'title' => 'Test Entry',
        'elementId' => 123,
        'nested' => ['key' => 'value'],
    ];

    $json = $model->getSnapshotJson();

    expect($json)->toBeString();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray();
    expect($decoded['title'])->toBe('Test Entry');
    expect($decoded['elementId'])->toBe(123);
    expect($decoded['nested']['key'])->toBe('value');
});
