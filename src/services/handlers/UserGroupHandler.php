<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\events\UserAssignGroupEvent;
use craft\events\UserGroupEvent;
use craft\events\UserGroupPermissionsEvent;
use craft\events\UserPermissionsEvent;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;

/**
 * UserGroupHandler — handles user-group assignment, user/group permission save,
 * and user-group save/delete audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditRecorder`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class UserGroupHandler extends Component
{
    public function onUserGroupsAssigned(UserAssignGroupEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $user = $event->user;
            // Craft 5: userGroups is an array of UserGroup objects, not IDs
            $userGroups = $event->userGroups ?? [];
            $groupIds = [];
            $groupNames = [];
            foreach ($userGroups as $group) {
                $groupIds[] = $group->id;
                $groupNames[] = $group->name;
            }

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::UserGroupsAssigned->value;
            $model->title = $user->username . ' → ' . implode(', ', $groupNames);
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'groupIds' => $groupIds,
                'groupNames' => $groupNames,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserPermissionsSaved(UserPermissionsEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $userId = $event->userId;
            $user = Craft::$app->getUsers()->getUserById($userId);
            $permissions = $event->permissions;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::UserPermissionsSaved->value;
            $model->title = $user ? $user->username : "User #{$userId}";
            $model->elementId = $userId;
            $model->elementType = User::class;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $userId,
                'username' => $user?->username,
                'permissions' => $permissions,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onGroupPermissionsSaved(UserGroupPermissionsEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $groupId = $event->groupId;
            $group = Craft::$app->getUserGroups()->getGroupById($groupId);
            $permissions = $event->permissions;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::GroupPermissionsSaved->value;
            $model->title = $group ? $group->name : "Group #{$groupId}";
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $groupId,
                'groupName' => $group?->name,
                'permissions' => $permissions,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserGroupSaved(UserGroupEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $group = $event->userGroup;
            $isNew = $event->isNew;

            $model = $auditRecorder->getStandardModel();
            $model->event = $isNew ? AuditEvent::UserGroupCreated->value : AuditEvent::UserGroupSaved->value;
            $model->title = $group->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onUserGroupDeleted(UserGroupEvent $event): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $group = $event->userGroup;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::UserGroupDeleted->value;
            $model->title = $group->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }
}
