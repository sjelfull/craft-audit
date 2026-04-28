<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

/**
 * Routes go through \Craft::$app->routes->saveRoute()/deleteRouteByUid()
 * which fire EVENT_AFTER_SAVE_ROUTE / EVENT_BEFORE_DELETE_ROUTE. The plugin's
 * RouteHandler is wired to those in Audit::initLogEvents(). These tests
 * exercise the full path: real service call → real event → audit record.
 */

it('logs audit event when route is saved', function () {
    $suffix = bin2hex(random_bytes(4));
    \Craft::$app->routes->saveRoute(
        ['test-route-' . $suffix],
        'test/template/' . $suffix
    );

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SavedRoute->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toContain('test/template/' . $suffix);
});

it('logs audit event when route is deleted', function () {
    $suffix = bin2hex(random_bytes(4));
    $routeUid = \Craft::$app->routes->saveRoute(
        ['delete-route-' . $suffix],
        'delete/template/' . $suffix
    );

    AuditRecord::deleteAll();

    \Craft::$app->routes->deleteRouteByUid($routeUid);

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
    $suffix = bin2hex(random_bytes(4));
    $siteUid = \Craft::$app->sites->getPrimarySite()->uid;
    \Craft::$app->routes->saveRoute(
        ['site-route-' . $suffix],
        'site/template/' . $suffix,
        $siteUid
    );

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SavedRoute->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    $model = AuditModel::createFromRecord($record);
    expect($model->snapshot['siteUid'])->toBe($siteUid);
});
