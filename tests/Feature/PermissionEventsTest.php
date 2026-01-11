<?php

use craft\elements\User;
use craft\events\UserGroupEvent;
use craft\events\UserGroupPermissionsEvent;
use craft\events\UserPermissionsEvent;
use craft\models\UserGroup;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('logs audit event when user permissions are saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserPermissionsEvent([
        'userId' => $user->id,
        'permissions' => ['accessCp', 'editEntries'],
    ]);
    Audit::$plugin->auditService->onUserPermissionsSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_PERMISSIONS_SAVED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->elementId)->toBe($user->id);
});

it('logs audit event when group permissions are saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserGroupPermissionsEvent([
        'groupId' => 1,
        'permissions' => ['accessCp', 'editEntries'],
    ]);
    Audit::$plugin->auditService->onGroupPermissionsSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_GROUP_PERMISSIONS_SAVED])
        ->one();

    expect($record)->not->toBeNull();
});

it('logs audit event when user group is saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $group = new UserGroup();
    $group->name = 'Test Group';
    $group->handle = 'testGroup';

    $event = new UserGroupEvent([
        'userGroup' => $group,
        'isNew' => true,
    ]);
    Audit::$plugin->auditService->onUserGroupSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_GROUP_SAVED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Created: Test Group');
});

it('logs audit event when user group is deleted', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $group = new UserGroup();
    $group->name = 'Deleted Group';
    $group->handle = 'deletedGroup';

    $event = new UserGroupEvent([
        'userGroup' => $group,
    ]);
    Audit::$plugin->auditService->onUserGroupDeleted($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_GROUP_DELETED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Group');
});

it('does not log permission events when disabled', function () {
    Audit::$plugin->getSettings()->logPermissionEvents = false;

    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $event = new UserPermissionsEvent([
        'userId' => $user->id,
        'permissions' => ['accessCp'],
    ]);
    $result = Audit::$plugin->auditService->onUserPermissionsSaved($event);

    expect($result)->toBeFalse();

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_PERMISSIONS_SAVED])
        ->one();

    expect($record)->toBeNull();

    Audit::$plugin->getSettings()->logPermissionEvents = true;
});

it('captures permissions in snapshot', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $permissions = ['accessCp', 'editEntries', 'createEntries'];
    $event = new UserPermissionsEvent([
        'userId' => $user->id,
        'permissions' => $permissions,
    ]);
    Audit::$plugin->auditService->onUserPermissionsSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_USER_PERMISSIONS_SAVED])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('permissions');
    expect($model->snapshot['permissions'])->toBe($permissions);
});
