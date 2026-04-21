<?php

use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

it('creates model from record with all fields populated', function () {
    $record = new AuditRecord();
    $record->id = 1;
    $record->event = AuditModel::EVENT_SAVED_ELEMENT;
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
    expect($model->event)->toBe(AuditModel::EVENT_SAVED_ELEMENT);
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

it('has correct event constant values', function () {
    expect(AuditModel::EVENT_SAVED_ELEMENT)->toBe('saved-element');
    expect(AuditModel::EVENT_CREATED_ELEMENT)->toBe('created-element');
    expect(AuditModel::EVENT_DELETED_ELEMENT)->toBe('deleted-element');
    expect(AuditModel::EVENT_SAVED_GLOBAL)->toBe('saved-global');
    expect(AuditModel::USER_LOGGED_IN)->toBe('user-logged-in');
    expect(AuditModel::USER_LOGGED_OUT)->toBe('user-logged-out');
    expect(AuditModel::EVENT_PLUGIN_ENABLED)->toBe('enabled-plugin');
    expect(AuditModel::EVENT_PLUGIN_DISABLED)->toBe('disabled-plugin');
    expect(AuditModel::EVENT_CREATED_ROUTE)->toBe('created-route');
    expect(AuditModel::EVENT_SAVED_ROUTE)->toBe('saved-route');
    expect(AuditModel::EVENT_DELETED_ROUTE)->toBe('deleted-route');
});

it('returns translated event label', function () {
    $model = new AuditModel();
    $model->event = AuditModel::EVENT_SAVED_ELEMENT;

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
