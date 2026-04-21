<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\base\PluginInterface;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\events\BackupEvent;
use craft\events\ConfigEvent;
use craft\events\EntryTypeEvent;
use craft\events\FieldEvent;
use craft\events\RestoreEvent;
use craft\events\RouteEvent;
use craft\events\SectionEvent;
use craft\events\UserAssignGroupEvent;
use craft\events\UserEvent;
use craft\events\UserGroupEvent;
use craft\events\UserGroupPermissionsEvent;
use craft\events\UserPermissionsEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\queue\jobs\ResaveElements;

use DateTime;
use superbig\audit\Audit;
use superbig\audit\events\SnapshotEvent;
use superbig\audit\helpers\Route;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class AuditService extends Component
{
    public const EVENT_TRIGGER = 'eventTrigger';
    public const EVENT_SNAPSHOT = 'snapshot';

    public function init(): void
    {
        parent::init();
    }

    /**
     * @param ElementInterface $element
     *
     * @return array|null
     */
    public function getEventsForElement(ElementInterface $element): ?array
    {
        $elementId = $element->getId();
        $elementType = get_class($element);

        return $this->getEventsByAttributes(['elementId' => $elementId, 'elementType' => $elementType]);
    }

    /**
     * @param int $id
     *
     * @return null|AuditModel
     */
    public function getEventById(int $id): ?AuditModel
    {
        $models = null;
        $record = AuditRecord::findOne($id);

        if (!$record) {
            return null;
        }

        return AuditModel::createFromRecord($record);
    }

    /**
     * @param null $handle
     *
     * @return array|null
     */
    public function getEventsByHandle($handle = null): ?array
    {
        return $this->getEventsByAttributes(['eventHandle' => $handle]);
    }

    public function getEventsBySessionId($id = null): ?array
    {
        if (!$id) {
            return null;
        }

        return $this->getEventsByAttributes(['sessionId' => $id, 'parentId' => null]);
    }

    /**
     * @param array $attributes
     *
     * @return array|null
     */
    public function getEventsByAttributes(array $attributes = []): ?array
    {
        $models = null;
        $records = AuditRecord::findAll($attributes);

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $models;
    }

    public function getEventCountByParentId($parentId = null): bool|int|string|null
    {
        return AuditRecord::find()
                          ->where(['parentId' => $parentId])
                          ->count();
    }

    /**
     * @param ElementInterface $element
     * @param bool $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, bool $isNew = false): bool
    {
        $settings = Audit::$plugin->getSettings();
        $title = null;
        $rootElement = ElementHelper::rootElement($element);
        $hasParent = $rootElement->id !== $element->id;
        $isGlobal = $element instanceof GlobalSet;
        $isDraft = false;
        $isRevision = false;

        // Skip if this event type is disabled
        if (!$settings->logElementEvents) {
            return false;
        }

        // Skip if this is an element that has a parent
        if ($hasParent && !Audit::$plugin->getSettings()->logChildElementEvents) {
            return false;
        }

        // Return early if no fields have changed?
        $hasNoDirtyAttributes = Audit::$craft34 ? empty($element->getDirtyAttributes()) : false;
        $hasNoDirtyFields = Audit::$craft34 ? empty($element->getDirtyFields()) : false;

        // Skip save if all of these is true
        if (!$settings->logDraftEvents && $hasNoDirtyAttributes && $hasNoDirtyFields && !$isGlobal) {
            return false;
        }

        if (Audit::$craft32) {
            // Skip draft events unless enabled
            $rootElement = ElementHelper::rootElement($element);
            $isDraft = $rootElement->getIsDraft();
            $isRevision = $rootElement->getIsRevision();

            if (!$settings->logDraftEvents && $rootElement->getIsDraft()) {
                return false;
            }

            if ($rootElement->getIsRevision()) {
                return false;
            }
        }

        // Skip drafts and propagating elements
        if ($element->propagating || $element->resaving) {
            return false;
        }

        try {
            /** @var Element $element */
            $model = $this->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_CREATED_ELEMENT : AuditModel::EVENT_SAVED_ELEMENT;
            $model->elementId = $element->getId();
            $model->siteId = $element->siteId;
            $model->elementType = get_class($element);
            $snapshot = [
                'elementId' => $element->getId(),
                'elementType' => get_class($element),
                'elementTypeLabel' => $element::displayName(),
            ];

            if (Audit::$craft32 && ($isDraft || $isRevision)) {
                $model->event = AuditModel::EVENT_SAVED_DRAFT;
            }

            if ($element instanceof Entry) {
                /** @var Entry $element */
                $model->event = $isNew ? AuditModel::EVENT_ENTRY_CREATED : AuditModel::EVENT_ENTRY_SAVED;
                $section = $element->getSection();
                if ($section) {
                    $snapshot['sectionId'] = $section->id;
                    $snapshot['sectionName'] = $section->name;
                    $snapshot['sectionHandle'] = $section->handle;
                }
            }

            if ($element->hasTitles()) {
                $title = $element->title;
            }

            if ($isGlobal) {
                /** @var GlobalSet $element */
                $title = $element->name;
                $model->event = AuditModel::EVENT_SAVED_GLOBAL;
            }

            if ($element instanceof User) {
                /** @var User $element */
                $title = $element->username;
            }

            $snapshot['content'] = $element->getSerializedFieldValues();

            if ($title) {
                $model->title = Html::encode($title);
                $snapshot['title'] = Html::encode($title);
            }

            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));
            $parentId = $this->getParentId($model->elementType);

            if (!empty($parentId)) {
                $model->parentId = $parentId;
            }

            return $this->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @param ElementInterface $element
     *
     * @return bool
     */
    public function onDeleteElement(ElementInterface $element): bool
    {
        $rootElement = ElementHelper::rootElement($element);
        $hasParent = $rootElement->id !== $element->id;

        if (!Audit::$plugin->getSettings()->logElementEvents) {
            return false;
        }

        // Skip drafts
        if (ElementHelper::isDraftOrRevision($element)) {
            return false;
        }

        if ($hasParent && !Audit::$plugin->getSettings()->logChildElementEvents) {
            return false;
        }

        try {
            /** @var Element $element */
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_DELETED_ELEMENT;
            $model->elementId = $element->getId();
            $model->elementType = get_class($element);
            $model->siteId = $element->siteId;
            $snapshot = [
                'elementId' => $element->getId(),
                'elementType' => get_class($element),
                'elementTypeLabel' => $element::displayName(),
            ];

            if ($element instanceof Entry) {
                /** @var Entry $element */
                $model->event = AuditModel::EVENT_ENTRY_DELETED;
                $section = $element->getSection();
                if ($section) {
                    $snapshot['sectionId'] = $section->id;
                    $snapshot['sectionName'] = $section->name;
                    $snapshot['sectionHandle'] = $section->handle;
                }
            }

            if ($element->hasTitles()) {
                $model->title = $element->title;
                $snapshot['title'] = $element->title;
            }

            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $this->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @return bool
     */
    public function onLogin(): bool
    {
        if (!Audit::$plugin->getSettings()->logUserEvents) {
            return false;
        }

        try {
            $model = $this->_getStandardModel();
            $model->event = AuditModel::USER_LOGGED_IN;

            return $this->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @return bool
     */
    public function onBeforeLogout(): bool
    {
        if (!Audit::$plugin->getSettings()->logUserEvents) {
            return false;
        }

        try {
            $model = $this->_getStandardModel();
            $model->event = AuditModel::USER_LOGGED_OUT;

            return $this->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @param string          $event
     * @param PluginInterface $plugin
     *
     * @return bool
     */
    public function onPluginEvent(string $event, PluginInterface $plugin): bool
    {
        if (!Audit::$plugin->getSettings()->logPluginEvents) {
            return false;
        }

        /** @var Plugin $plugin */
        try {
            $model = $this->_getStandardModel();
            $model->event = $event;
            $model->title = $plugin->name;
            $snapshot = [
                'title' => $plugin->name,
                'handle' => $plugin->handle,
                'version' => $plugin->version,
            ];
            $model->snapshot = $snapshot;

            return $this->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * @param AuditModel $auditModel
     * @param            $snapshot
     *
     * @return array
     */
    protected function afterSnapshot(AuditModel $auditModel, $snapshot): array
    {
        $event = new SnapshotEvent([
            'audit' => $auditModel,
            'snapshot' => $snapshot,
        ]);

        $this->trigger(self::EVENT_SNAPSHOT, $event);

        return $event->snapshot;
    }

    /**
     * @return AuditModel
     */
    private function _getStandardModel(): AuditModel
    {
        $app = Craft::$app;
        $request = $app->getRequest();
        $model = new AuditModel();
        $model->siteId = $app->getSites()->currentSite->id;

        if (!$request->isConsoleRequest) {
            $session = $app->getSession();
            $model->sessionId = $session->getId();
            $model->ip = $request->getUserIP();
            $model->userAgent = $request->getUserAgent();

            if ($identity = $app->getUser()->getIdentity()) {
                $model->userId = $identity->id;

                $model->snapshot = [
                    'userId' => $model->userId,
                ];
            }
        }

        return $model;
    }

    /**
     * @param AuditModel $model
     * @param bool $unique
     *
     * @return bool
     */
    public function _saveRecord(AuditModel &$model, bool $unique = true): bool
    {
        try {
            if ($model->id) {
                $record = AuditRecord::findOne($model->id);
            } else {
                $record = new AuditRecord();
            }

            $record->event = $model->event;
            $record->title = $model->title;
            $record->parentId = $model->parentId;
            $record->userId = $model->userId;
            $record->elementId = $model->elementId;
            $record->elementType = $model->elementType;
            $record->ip = $model->ip;
            $record->userAgent = $model->userAgent;
            $record->siteId = $model->siteId;
            $record->snapshot = Json::encode($model->snapshot ?: []);
            $record->sessionId = $model->sessionId;

            if (!$record->save()) {
                Craft::error(
                    Craft::t('audit', 'An error occured when saving audit log record: {error}',
                        [
                            'error' => print_r($record->getErrors(), true),
                        ]),
                    'audit');
            }

            $model->id = $record->id;

            return true;
        } catch (Exception $e) {
            Craft::error(
                Craft::t('audit', 'An error occured when saving audit log record: {error}',
                    [
                        'error' => $e->getMessage(),
                    ]),
                'audit');

            return false;
        }
    }

    public function outputObjectAsTable($input, $end = true): string|\Twig\Markup
    {
        $output = '<table class="audit-snapshot-table">';

        foreach ($input as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $sub = $this->outputObjectAsTable($value, false);
                $output .= "<tr><td><strong>$key</strong>:</td><td>$sub</td></tr>";
            } else {
                $output .= "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
            }
        }
        $output .= "</table>";

        if ($end) {
            $output = Template::raw($output);
        }

        return $output;
    }

    /**
     * @return int|string
     */
    public function pruneLogs(): int|string
    {
        $pruneDays = Audit::$plugin->getSettings()->pruneDays ?? 30;
        $date = (new DateTime())->modify('-' . $pruneDays . ' days')->format('Y-m-d H:i:s');
        $query = AuditRecord::find()->where(['<=', 'dateCreated', $date]);
        $count = $query->count();

        // Delete
        AuditRecord::deleteAll(['<=', 'dateCreated', $date]);

        return $count;
    }

    public function onBeforeResave(ResaveElements $job): bool
    {
        try {
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_RESAVED_ELEMENTS;
            $model->elementType = $job->elementType;
            $model->appendSnapshot('resaveCriteria', $job->criteria);

            $this->_saveRecord($model);

            if ($model->id) {
                $parentIdKey = $this->getParentIdKey($job->elementType);

                Craft::$app->getCache()->set($parentIdKey, $model->id);
            }
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );
        }

        return true;
    }

    /**
     * @param string $elementType
     *
     * @return mixed
     */
    public function getParentId(string $elementType = ''): mixed
    {
        $cache = Craft::$app->getCache();
        $parentId = $cache->get($this->getParentIdKey($elementType));

        return $parentId;
    }

    /**
     * @param ResaveElements $job
     *
     * @return mixed
     */
    public function onResaveEnd(ResaveElements $job): mixed
    {
        try {
            $cache = Craft::$app->getCache();
            $parentKey = $this->getParentIdKey($job->elementType);
            $parentId = $cache->get($parentKey);

            if ($parentId) {
                $parentEvent = $this->getEventById((int) $parentId);
                $subEventCount = $this->getEventCountByParentId((int) $parentId);

                if ($parentEvent) {
                    $parentEvent->title = $subEventCount . ' elements was re-saved';

                    $this->_saveRecord($parentEvent);
                }

                $cache->delete($parentKey);
            }
        } catch (\Exception $e) {
            Craft::error('Failed to remove resave id: ' . $e->getMessage(), __METHOD__);
        }

        return true;
    }

    public function getParentIdKey($elementType = ''): string
    {
        return AuditModel::FLASH_RESAVE_ID . ':' . $elementType;
    }

    public function onSaveRoute(RouteEvent $event)
    {
        if (!Audit::$plugin->getSettings()->logRouteEvents) {
            return false;
        }

        $this->catchSaveError(function() use ($event) {
            $uriDisplay = Route::getUriDisplayHtml($event->uriParts);
            $model = $this->_getStandardModel();
            // Craft 5's RouteEvent doesn't expose routeId, so we can't distinguish new vs. existing
            $model->event = AuditModel::EVENT_SAVED_ROUTE;
            $model->title = $uriDisplay . ' -> ' . $event->template;
            $snapshot = [
                'uriParts' => $event->uriParts,
                'template' => $event->template,
                'siteUid' => $event->siteUid,
            ];
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $this->_saveRecord($model);
        });
    }

    public function onDeleteRoute(RouteEvent $event)
    {
        if (!Audit::$plugin->getSettings()->logRouteEvents) {
            return false;
        }

        $this->catchSaveError(function() use ($event) {
            $uriDisplay = Route::getUriDisplayHtml($event->uriParts);
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_DELETED_ROUTE;
            $model->title = $uriDisplay . ' -> ' . $event->template;
            $snapshot = [
                'uriParts' => $event->uriParts,
                'template' => $event->template,
                'siteUid' => $event->siteUid,
            ];
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $this->_saveRecord($model);
        });
    }

    private function catchSaveError(callable $callable)
    {
        try {
            return $callable();
        } catch (\Exception $e) {
            $this->logSaveError($e);

            return false;
        }
    }

    private function logSaveError(\Exception $e): void
    {
        Craft::error(
            Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
            __METHOD__
        );
    }

    // =========================================================================
    // User Security Events
    // =========================================================================

    public function onUserActivated(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_ACTIVATED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserDeactivated(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_DEACTIVATED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserSuspended(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_SUSPENDED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserUnsuspended(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_UNSUSPENDED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserLocked(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_LOCKED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserUnlocked(UserEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_UNLOCKED;
            $model->title = $user->username;
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserGroupsAssigned(UserAssignGroupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logUserSecurityEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $user = $event->user;
            // Craft 5: userGroups is an array of UserGroup objects, not IDs
            $userGroups = $event->userGroups ?? [];
            $groupIds = [];
            $groupNames = [];
            foreach ($userGroups as $group) {
                $groupIds[] = $group->id;
                $groupNames[] = $group->name;
            }

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_GROUPS_ASSIGNED;
            $model->title = $user->username . ' → ' . implode(', ', $groupNames);
            $model->elementId = $user->id;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $user->id,
                'username' => $user->username,
                'groupIds' => $groupIds,
                'groupNames' => $groupNames,
            ]));

            return $this->_saveRecord($model);
        });
    }

    // =========================================================================
    // Permission Events
    // =========================================================================

    public function onUserPermissionsSaved(UserPermissionsEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $userId = $event->userId;
            $user = Craft::$app->getUsers()->getUserById($userId);
            $permissions = $event->permissions;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_PERMISSIONS_SAVED;
            $model->title = $user ? $user->username : "User #{$userId}";
            $model->elementId = $userId;
            $model->elementType = User::class;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'userId' => $userId,
                'username' => $user?->username,
                'permissions' => $permissions,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onGroupPermissionsSaved(UserGroupPermissionsEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $groupId = $event->groupId;
            $group = Craft::$app->getUserGroups()->getGroupById($groupId);
            $permissions = $event->permissions;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_GROUP_PERMISSIONS_SAVED;
            $model->title = $group ? $group->name : "Group #{$groupId}";
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $groupId,
                'groupName' => $group?->name,
                'permissions' => $permissions,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserGroupSaved(UserGroupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $group = $event->userGroup;
            $isNew = $event->isNew;

            $model = $this->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_USER_GROUP_CREATED : AuditModel::EVENT_USER_GROUP_SAVED;
            $model->title = $group->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onUserGroupDeleted(UserGroupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logPermissionEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $group = $event->userGroup;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_USER_GROUP_DELETED;
            $model->title = $group->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'groupId' => $group->id,
                'groupName' => $group->name,
                'groupHandle' => $group->handle,
            ]));

            return $this->_saveRecord($model);
        });
    }

    // =========================================================================
    // Schema Events
    // =========================================================================

    public function onFieldSaved(FieldEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $field = $event->field;
            $isNew = $event->isNew;

            $model = $this->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_FIELD_CREATED : AuditModel::EVENT_FIELD_SAVED;
            $model->title = $field->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onFieldDeleted(FieldEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $field = $event->field;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_FIELD_DELETED;
            $model->title = $field->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onSectionSaved(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $section = $event->section;
            $isNew = $event->isNew;

            $model = $this->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_SECTION_CREATED : AuditModel::EVENT_SECTION_SAVED;
            $model->title = $section->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onSectionDeleted(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $section = $event->section;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_SECTION_DELETED;
            $model->title = $section->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onEntryTypeSaved(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $entryType = $event->entryType;
            $isNew = $event->isNew;

            $model = $this->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_ENTRY_TYPE_CREATED : AuditModel::EVENT_ENTRY_TYPE_SAVED;
            $model->title = $entryType->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onEntryTypeDeleted(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $entryType = $event->entryType;

            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_ENTRY_TYPE_DELETED;
            $model->title = $entryType->name;
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $this->_saveRecord($model);
        });
    }

    // =========================================================================
    // Database Events
    // =========================================================================

    public function onBackupCreated(BackupEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_BACKUP_CREATED;
            $model->title = basename($event->file);
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
                'ignoreTables' => $event->ignoreTables,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onBackupRestored(RestoreEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logDatabaseEvents) {
            return false;
        }

        return $this->catchSaveError(function() use ($event) {
            $model = $this->_getStandardModel();
            $model->event = AuditModel::EVENT_BACKUP_RESTORED;
            $model->title = basename($event->file);
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'file' => $event->file,
            ]));

            return $this->_saveRecord($model);
        });
    }

    public function onSettingsChanged(ConfigEvent $event, string $settingsType): bool
    {
        return $this->catchSaveError(function() use ($event, $settingsType) {
            $model = $this->_getStandardModel();
            $model->event = $settingsType === 'system'
                ? AuditModel::EVENT_SYSTEM_SETTINGS_CHANGED
                : AuditModel::EVENT_EMAIL_SETTINGS_CHANGED;
            $model->title = ucfirst($settingsType) . ' settings';
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, [
                'settingsType' => $settingsType,
                'path' => $event->path,
                'oldValue' => $event->oldValue,
                'newValue' => $event->newValue,
            ]));

            return $this->_saveRecord($model);
        });
    }
}
