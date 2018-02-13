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
use craft\models\EntryDraft;
use superbig\audit\Audit;

use Craft;
use craft\base\Component;
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

    const EVENT_TRIGGER = 'eventTrigger';

    // Public Methods
    // =========================================================================

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

    public function getEventsByHandle($handle = null)
    {
        return $this->getEventsByAttributes(['eventHandle' => $handle]);
    }

    public function getEventsBySessionId($id = null)
    {
        if (!$id) {
            return null;
        }
        
        return $this->getEventsByAttributes(['sessionId' => $id]);
    }

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

    /**
     * @param ElementInterface $element
     * @param bool             $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, $isNew = false)
    {
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
            $model->title      = $element->title;
            $snapshot['title'] = $element->title;
        }

        if ($element->hasContent()) {
            $snapshot['content'] = $element->getSerializedFieldValues();
        }

        $model->snapshot = array_merge($model->snapshot, $snapshot);

        return $this->_saveRecord($model);
    }

    /**
     * @param ElementInterface $element
     *
     * @return bool
     */
    public function onDeleteElement(ElementInterface $element)
    {
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

        $model->snapshot = array_merge($model->snapshot, $snapshot);

        return $this->_saveRecord($model);
    }


    public function onLogin ()
    {
        $model        = $this->_getStandardModel();
        $model->event = AuditModel::USER_LOGGED_IN;


        return $this->_saveRecord($model);
    }

    public function onBeforeLogout()
    {
        Craft::$app->getSession()->set('audit.userId', Craft::$app->getUser()->id);
    }

    public function onLogout()
    {
        $model        = $this->_getStandardModel();
        $model->event = AuditModel::USER_LOGGED_OUT;

        return $this->_saveRecord($model);
    }


    public function onPluginEvent(string $event, PluginInterface $plugin): bool
    {
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
    }

    /**
     * @return AuditModel
     */
    private function _getStandardModel()
    {
        $app              = Craft::$app;
        $request          = $app->getRequest();
        $model            = new AuditModel();
        $model->ip        = $request->getUserIP();
        $model->userAgent = $request->getUserAgent();
        $model->siteId    = $app->getSites()->currentSite->id;
        $model->sessionId = $app->getSession()->getId();

        if ($userId = Craft::$app->getSession()->get('audit.userId', null)) {
            $model->userId = $userId;
        }
        else {
            $model->userId = $app->getUser()->id;
        }

        $model->snapshot = [
            'userId' => $model->userId,
        ];

        return $model;
    }

    /**
     * @param AuditModel $model
     * @param bool       $unique
     *
     * @return bool
     */
    public function _saveRecord(AuditModel $model, $unique = true)
    {
        try {
            /*if ( $model->id ) {
                $record = AuditRecord::findOne($model->id);
            }
            else {
            } */
            $record              = new AuditRecord();
            $record->event       = $model->event;
            $record->title       = $model->title;
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
}
