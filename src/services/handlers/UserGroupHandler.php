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
use superbig\audit\models\AuditModel;

/**
 * UserGroupHandler — handles user-group assignment, user/group permission save,
 * and user-group save/delete audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord`, `_getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class UserGroupHandler extends Component
{
    public function onUserGroupsAssigned(UserAssignGroupEvent $event): bool
    {
        $auditService = Audit::$plugin->auditService;

        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $user = $event->user;
            // Craft 5: userGroups is an array of UserGroup objects, not IDs
            $userGroups = $event->userGroups ?? [];
            $groupIds = [];
            $groupNames = [];
            foreach ($userGroups as $group) {
                $groupIds[] = $group->id;
                $groupNames[] = $group->name;
            }

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_GROUPS_ASSIGNED;
            $model->title = $user->username . ' → ' . implode(', ', $groupNames);
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'groupIds' => $groupIds,
                'groupNames' => $groupNames,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onUserPermissionsSaved(UserPermissionsEvent $event): bool
    {
        $auditService = Audit::$plugin->auditService;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $userId = $event->userId;
            $user = Craft::$app->getUsers()->getUserById($userId);
            $permissions = $event->permissions;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_PERMISSIONS_SAVED;
            $model->title = $user ? $user->username : "User #{$userId}";
            $model->elementId = $userId;
            $model->elementType = User::class;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $userId,
                'username' => $user?->username,
                'permissions' => $permissions,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onGroupPermissionsSaved(UserGroupPermissionsEvent $event): bool
    {
        $auditService = Audit::$plugin->auditService;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $groupId = $event->groupId;
            $group = Craft::$app->getUserGroups()->getGroupById($groupId);
            $permissions = $event->permissions;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_GROUP_PERMISSIONS_SAVED;
            $model->title = $group ? $group->name : "Group #{$groupId}";
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $groupId,
                'groupName' => $group?->name,
                'permissions' => $permissions,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onUserGroupSaved(UserGroupEvent $event): bool
    {
        $auditService = Audit::$plugin->auditService;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $group = $event->userGroup;
            $isNew = $event->isNew;

            $model = $auditService->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_USER_GROUP_CREATED : AuditModel::EVENT_USER_GROUP_SAVED;
            $model->title = $group->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onUserGroupDeleted(UserGroupEvent $event): bool
    {
        $auditService = Audit::$plugin->auditService;

        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $group = $event->userGroup;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_GROUP_DELETED;
            $model->title = $group->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $auditService->_saveRecord($model);
        });
    }
}
