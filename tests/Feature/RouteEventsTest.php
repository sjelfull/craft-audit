<?php

use craft\events\RouteEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('logs audit event when route is saved', function () {
    $event = new RouteEvent([
        'uriParts' => ['test-route'],
        'template' => 'test/template',
        'siteUid' => null,
    ]);

    Audit::$plugin->auditService->onSaveRoute($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SavedRoute->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toContain('test/template');
});

it('logs audit event when route is deleted', function () {
    $event = new RouteEvent([
        'uriParts' => ['test-route'],
        'template' => 'test/template',
        'siteUid' => null,
    ]);

    Audit::$plugin->auditService->onDeleteRoute($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::DeletedRoute->value])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);
    expect($model->snapshot)->toHaveKey('template');
    expect($model->snapshot)->toHaveKey('uriParts');
    expect($model->snapshot)->toHaveKey('siteUid');
});

it('captures site UID in route snapshot', function () {
    $siteUid = 'test-site-uid-123';
    $event = new RouteEvent([
        'uriParts' => ['site-specific-route'],
        'template' => 'site/template',
        'siteUid' => $siteUid,
    ]);

    Audit::$plugin->auditService->onSaveRoute($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SavedRoute->value])
        ->one();

    $model = AuditModel::createFromRecord($record);
    expect($model->snapshot['siteUid'])->toBe($siteUid);
});
