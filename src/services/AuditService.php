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

use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Plugin;
use craft\base\PluginInterface;
use craft\elements\User;
use craft\events\RouteEvent;
use craft\helpers\Template;
use craft\queue\jobs\ResaveElements;
use DateTime;
use superbig\audit\Audit;

use Craft;
use craft\base\Component;
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
    // Public Methods
    // =========================================================================

    const EVENT_TRIGGER  = 'eventTrigger';
    const EVENT_SNAPSHOT = 'snapshot';

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
    }

    /**
     * @param ElementInterface $element
     *
     * @return array|null
     */
    public function getEventsForElement(ElementInterface $element)
    {
        $elementId   = $element->getId();
        $elementType = get_class($element);

        return $this->getEventsByAttributes(['elementId' => $elementId, 'elementType' => $elementType]);
    }

    /**
     * @param null $id
     *
     * @return null|AuditModel
     */
    public function getEventById($id = null)
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
    public function getEventsByHandle($handle = null)
    {
        return $this->getEventsByAttributes(['eventHandle' => $handle]);
    }

    /**
     * @param null $id
     *
     * @return array|null
     */
    public function getEventsBySessionId($id = null)
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
    public function getEventsByAttributes($attributes = [])
    {
        $models  = null;
        $records = AuditRecord::findAll($attributes);

        if ($records) {
            foreach ($records as $record) {
                $models[] = AuditModel::createFromRecord($record);
            }
        }

        return $models;
    }

    public function getEventCountByParentId($parentId = null)
    {
        return AuditRecord::find()
                          ->where(['parentId' => $parentId])
                          ->count();
    }

    /**
     * @param Element $element
     * @param bool    $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, $isNew = false)
    {
        $title    = null;
        $isParent = false;

        // Skip drafts
        if (ElementHelper::isDraftOrRevision($element) || $element->propagating || $element->resaving) {
            return false;
        }

        try {
            /** @var Element $element */
            $model              = $this->_getStandardModel();
            $model->event       = $isNew ? AuditModel::EVENT_CREATED_ELEMENT : AuditModel::EVENT_SAVED_ELEMENT;
            $model->elementId   = $element->getId();
            $model->elementType = get_class($element);
            $snapshot           = [
                'elementId'   => $element->getId(),
                'elementType' => get_class($element),
            ];

            if ($element->hasTitles()) {
                $title = $element->title;
            }

            if ($element instanceof User) {
                /** @var User $element */
                $title = $element->username;
            }

            if ($element->hasContent()) {
                $snapshot['content'] = $element->getSerializedFieldValues();
            }

            if ($title) {
                $model->title      = $title;
                $snapshot['title'] = $title;
            }

            $model->snapshot   = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));
            $parentId          = $this->getParentId($model->elementType);

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
    public function onDeleteElement(ElementInterface $element)
    {
        // Skip drafts
        if (ElementHelper::isDraftOrRevision($element)) {
            return false;
        }

        try {
            /** @var Element $element */
            $model              = $this->_getStandardModel();
            $model->event       = AuditModel::EVENT_DELETED_ELEMENT;
            $model->elementType = get_class($element);
            $snapshot           = [
                'elementId'   => $element->getId(),
                'elementType' => get_class($element),
            ];

            if ($element->hasTitles()) {
                $model->title      = $element->title;
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
    public function onLogin()
    {
        try {
            $model        = $this->_getStandardModel();
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
    public function onBeforeLogout()
    {
        try {
            $model        = $this->_getStandardModel();
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
        /** @var Plugin $plugin */
        try {
            $model           = $this->_getStandardModel();
            $model->event    = $event;
            $model->title    = $plugin->name;
            $snapshot        = [
                'title'   => $plugin->name,
                'handle'  => $plugin->handle,
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
    protected function afterSnapshot(AuditModel $auditModel, $snapshot)
    {
        $event = new SnapshotEvent([
            'audit'    => $auditModel,
            'snapshot' => $snapshot,
        ]);

        $this->trigger(self::EVENT_SNAPSHOT, $event);

        return $event->snapshot;
    }

    /**
     * @return AuditModel
     */
    private function _getStandardModel()
    {
        $app           = Craft::$app;
        $request       = $app->getRequest();
        $model         = new AuditModel();
        $model->siteId = $app->getSites()->currentSite->id;

        if (!$request->isConsoleRequest) {
            $session          = $app->getSession();
            $model->sessionId = $session->getId();
            $model->ip        = $request->getUserIP();
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
     * @param bool       $unique
     *
     * @return bool
     */
    public function _saveRecord(AuditModel &$model, $unique = true)
    {
        try {
            if ($model->id) {
                $record = AuditRecord::findOne($model->id);
            }
            else {
                $record = new AuditRecord();
            }

            $record->event       = $model->event;
            $record->title       = $model->title;
            $record->parentId    = $model->parentId;
            $record->userId      = $model->userId;
            $record->elementId   = $model->elementId;
            $record->elementType = $model->elementType;
            $record->ip          = $model->ip;
            $record->userAgent   = $model->userAgent;
            $record->siteId      = $model->siteId;
            $record->snapshot    = serialize($model->snapshot);
            $record->sessionId   = $model->sessionId;

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

    public function outputObjectAsTable($input, $end = true)
    {
        $output = '<table class="audit-snapshot-table">';

        foreach ($input as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                $sub    = $this->outputObjectAsTable($value, false);
                $output .= "<tr><td><strong>$key</strong>:</td><td>$sub</td></tr>";
            }
            else {
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
    public function pruneLogs()
    {
        $pruneDays = Audit::$plugin->getSettings()->pruneDays ?? 30;
        $date      = (new DateTime())->modify('-' . $pruneDays . ' days')->format('Y-m-d H:i:s');
        $query     = AuditRecord::find()->where('dateCreated <= :pruneDate', [':pruneDate' => $date]);
        $count     = $query->count();

        // Delete
        AuditRecord::deleteAll('dateCreated <= :pruneDate', [':pruneDate' => $date]);

        return $count;
    }

    public function onBeforeResave(ResaveElements $job)
    {
        try {
            $model              = $this->_getStandardModel();
            $model->event       = AuditModel::EVENT_RESAVED_ELEMENTS;
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
    public function getParentId($elementType = '')
    {
        $cache    = Craft::$app->getCache();
        $parentId = $cache->get($this->getParentIdKey($elementType));

        return $parentId;
    }

    /**
     * @param ResaveElements $job
     *
     * @return mixed
     */
    public function onResaveEnd(ResaveElements $job)
    {
        try {
            $cache     = Craft::$app->getCache();
            $parentKey = $this->getParentIdKey($job->elementType);
            $parentId  = $cache->get($parentKey);

            if ($parentId) {
                $parentEvent   = $this->getEventById($parentId);
                $subeventCount = $this->getEventCountByParentId($parentId);

                if ($parentEvent) {
                    $parentEvent->title = $subeventCount . ' elements was resaved';

                    $this->_saveRecord($parentEvent);
                }

                $cache->delete($parentKey);
            }
        } catch (\Exception $e) {
            Craft::error('Failed to remove resave id: ' . $e->getMessage(), __METHOD__);
        }

        return true;
    }

    public function getParentIdKey($elementType = '')
    {
        return AuditModel::FLASH_RESAVE_ID . ':' . $elementType;
    }

    public function onSaveRoute(RouteEvent $event)
    {
        $this->catchSaveError(function() use ($event) {
            $uriDisplay      = Route::getUriDisplayHtml($event->uriParts);
            $isNew           = $event->routeId === null;
            $model           = $this->_getStandardModel();
            $model->event    = $isNew ? AuditModel::EVENT_CREATED_ROUTE : AuditModel::EVENT_SAVED_ROUTE;
            $model->title    = $uriDisplay . ' -> ' . $event->template;
            $snapshot        = [
                'uriParts' => $event->uriParts,
                'routeId'  => $event->routeId,
                'template' => $event->template,
            ];
            $model->snapshot = $this->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $this->_saveRecord($model);
        });
    }

    public function onDeleteRoute(RouteEvent $event)
    {
        $this->catchSaveError(function() use ($event) {
            $uriDisplay      = Route::getUriDisplayHtml($event->uriParts);
            $model           = $this->_getStandardModel();
            $model->event    = AuditModel::EVENT_DELETED_ROUTE;
            $model->title    = $uriDisplay . ' -> ' . $event->template;
            $snapshot        = [
                'uriParts' => $event->uriParts,
                'routeId'  => $event->routeId,
                'template' => $event->template,
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

    private function logSaveError(\Exception $e)
    {
        Craft::error(
            Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
            __METHOD__
        );
    }
}
