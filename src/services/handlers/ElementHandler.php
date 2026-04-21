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
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\elements\User;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\queue\jobs\ResaveElements;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;

/**
 * ElementHandler — handles element-related audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord`, `_getStandardModel`, and `afterSnapshot` via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class ElementHandler extends Component
{
    /**
     * @param ElementInterface $element
     * @param bool $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, bool $isNew = false): bool
    {
        $auditService = Audit::$plugin->auditService;
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
            $model = $auditService->_getStandardModel();
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

            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, $snapshot));
            $parentId = $this->getParentId($model->elementType);

            if (!empty($parentId)) {
                $model->parentId = $parentId;
            }

            return $auditService->_saveRecord($model);
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
        $auditService = Audit::$plugin->auditService;
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
            $model = $auditService->_getStandardModel();
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

            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $auditService->_saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    public function onBeforeResave(ResaveElements $job): bool
    {
        $auditService = Audit::$plugin->auditService;

        try {
            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_RESAVED_ELEMENTS;
            $model->elementType = $job->elementType;
            $model->appendSnapshot('resaveCriteria', $job->criteria);

            $auditService->_saveRecord($model);

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
        $auditService = Audit::$plugin->auditService;

        try {
            $cache = Craft::$app->getCache();
            $parentKey = $this->getParentIdKey($job->elementType);
            $parentId = $cache->get($parentKey);

            if ($parentId) {
                $parentEvent = $auditService->getEventById((int) $parentId);
                $subEventCount = $auditService->getEventCountByParentId((int) $parentId);

                if ($parentEvent) {
                    $parentEvent->title = $subEventCount . ' elements was re-saved';

                    $auditService->_saveRecord($parentEvent);
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
}
