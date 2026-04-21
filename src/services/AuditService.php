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
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\base\PluginInterface;
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
     * @deprecated Use Audit::$plugin->elementHandler->onSaveElement() instead.
     */
    public function onSaveElement(ElementInterface $element, bool $isNew = false): bool
    {
        return Audit::$plugin->elementHandler->onSaveElement($element, $isNew);
    }

    /**
     * @param ElementInterface $element
     *
     * @return bool
     * @deprecated Use Audit::$plugin->elementHandler->onDeleteElement() instead.
     */
    public function onDeleteElement(ElementInterface $element): bool
    {
        return Audit::$plugin->elementHandler->onDeleteElement($element);
    }

    /**
     * @return bool
     * @deprecated Use Audit::$plugin->userHandler->onLogin() instead.
     */
    public function onLogin(): bool
    {
        return Audit::$plugin->userHandler->onLogin();
    }

    /**
     * @return bool
     * @deprecated Use Audit::$plugin->userHandler->onBeforeLogout() instead.
     */
    public function onBeforeLogout(): bool
    {
        return Audit::$plugin->userHandler->onBeforeLogout();
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
     * @internal Public for use by handler services in services/handlers/.
     */
    public function afterSnapshot(AuditModel $auditModel, $snapshot): array
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
     * @internal Public for use by handler services in services/handlers/. Do not call from outside the plugin.
     */
    public function _getStandardModel(): AuditModel
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

    /**
     * @deprecated Use Audit::$plugin->elementHandler->onBeforeResave() instead.
     */
    public function onBeforeResave(ResaveElements $job): bool
    {
        return Audit::$plugin->elementHandler->onBeforeResave($job);
    }

    /**
     * @param string $elementType
     *
     * @return mixed
     * @deprecated Use Audit::$plugin->elementHandler->getParentId() instead.
     */
    public function getParentId(string $elementType = ''): mixed
    {
        return Audit::$plugin->elementHandler->getParentId($elementType);
    }

    /**
     * @param ResaveElements $job
     *
     * @return mixed
     * @deprecated Use Audit::$plugin->elementHandler->onResaveEnd() instead.
     */
    public function onResaveEnd(ResaveElements $job): mixed
    {
        return Audit::$plugin->elementHandler->onResaveEnd($job);
    }

    /**
     * @deprecated Use Audit::$plugin->elementHandler->getParentIdKey() instead.
     */
    public function getParentIdKey($elementType = ''): string
    {
        return Audit::$plugin->elementHandler->getParentIdKey($elementType);
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

    /**
     * @internal Public for use by handler services in services/handlers/.
     */
    public function catchSaveError(callable $callable)
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

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserActivated() instead.
     */
    public function onUserActivated(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserActivated($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserDeactivated() instead.
     */
    public function onUserDeactivated(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserDeactivated($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserSuspended() instead.
     */
    public function onUserSuspended(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserSuspended($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserUnsuspended() instead.
     */
    public function onUserUnsuspended(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserUnsuspended($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserLocked() instead.
     */
    public function onUserLocked(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserLocked($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userHandler->onUserUnlocked() instead.
     */
    public function onUserUnlocked(UserEvent $event): bool
    {
        return Audit::$plugin->userHandler->onUserUnlocked($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userGroupHandler->onUserGroupsAssigned() instead.
     */
    public function onUserGroupsAssigned(UserAssignGroupEvent $event): bool
    {
        return Audit::$plugin->userGroupHandler->onUserGroupsAssigned($event);
    }

    // =========================================================================
    // Permission Events
    // =========================================================================

    /**
     * @deprecated Use Audit::$plugin->userGroupHandler->onUserPermissionsSaved() instead.
     */
    public function onUserPermissionsSaved(UserPermissionsEvent $event): bool
    {
        return Audit::$plugin->userGroupHandler->onUserPermissionsSaved($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userGroupHandler->onGroupPermissionsSaved() instead.
     */
    public function onGroupPermissionsSaved(UserGroupPermissionsEvent $event): bool
    {
        return Audit::$plugin->userGroupHandler->onGroupPermissionsSaved($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userGroupHandler->onUserGroupSaved() instead.
     */
    public function onUserGroupSaved(UserGroupEvent $event): bool
    {
        return Audit::$plugin->userGroupHandler->onUserGroupSaved($event);
    }

    /**
     * @deprecated Use Audit::$plugin->userGroupHandler->onUserGroupDeleted() instead.
     */
    public function onUserGroupDeleted(UserGroupEvent $event): bool
    {
        return Audit::$plugin->userGroupHandler->onUserGroupDeleted($event);
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
