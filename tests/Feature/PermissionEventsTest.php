<?php

use craft\elements\User;
use craft\models\UserGroup;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);
});

/**
 * Permission and user-group events go through Craft's UserPermissions and
 * UserGroups services. These tests invoke the real services so each pass
 * validates that:
 *   - the underlying Craft service still fires the event
 *   - Audit::initLogEvents() wires the right event handle to the right handler
 *   - the handler writes a record with the expected shape.
 */

it('logs audit event when user permissions are saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->userPermissions->saveUserPermissions($user->id, ['accessCp']);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserPermissionsSaved->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when group permissions are saved', function () {
    $suffix = bin2hex(random_bytes(4));
    $group = new UserGroup();
    $group->name = 'Perm Test Group ' . $suffix;
    $group->handle = 'permTestGroup' . $suffix;
    expect(\Craft::$app->userGroups->saveGroup($group))->toBeTrue();

    AuditRecord::deleteAll();

    \Craft::$app->userPermissions->saveGroupPermissions($group->id, ['accessCp']);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::GroupPermissionsSaved->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
});

it('logs audit event when user group is saved', function () {
    $suffix = bin2hex(random_bytes(4));
    $group = new UserGroup();
    $group->name = 'Test Group ' . $suffix;
    $group->handle = 'testGroup' . $suffix;

    expect(\Craft::$app->userGroups->saveGroup($group))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserGroupCreated->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Test Group ' . $suffix);
});

it('logs audit event when user group is deleted', function () {
    $suffix = bin2hex(random_bytes(4));
    $group = new UserGroup();
    $group->name = 'Deleted Group ' . $suffix;
    $group->handle = 'deletedGroup' . $suffix;
    expect(\Craft::$app->userGroups->saveGroup($group))->toBeTrue();

    AuditRecord::deleteAll();

    expect(\Craft::$app->userGroups->deleteGroupById($group->id))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserGroupDeleted->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Group ' . $suffix);
});

it('does not log permission events when disabled', function () {
    Audit::$plugin->getSettings()->logPermissionEvents = false;

    try {
        $user = User::find()->admin()->one();
        \Craft::$app->userPermissions->saveUserPermissions($user->id, ['accessCp']);

        $record = AuditRecord::find()
            ->where(['event' => AuditEvent::UserPermissionsSaved->value])
            ->one();

        expect($record)->toBeNull();
    } finally {
        Audit::$plugin->getSettings()->logPermissionEvents = true;
    }
});

it('captures permissions in snapshot', function () {
    $user = User::find()->admin()->one();
    $permissions = ['accessCp', 'accessSiteWhenSystemIsOff'];
    \Craft::$app->userPermissions->saveUserPermissions($user->id, $permissions);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::UserPermissionsSaved->value])
        ->orderBy(['id' => SORT_DESC])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('permissions');
    // Craft normalizes permissions to lowercase before persisting, so the
    // snapshot reflects the normalized form. Handler-direct tests bypassed
    // this normalization and asserted on the raw input — converting to a
    // real-API call surfaced the actual stored shape.
    expect($model->snapshot['permissions'])->toContain('accesscp');
});
