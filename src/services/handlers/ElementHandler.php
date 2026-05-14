<?php

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
use superbig\audit\enums\AuditEvent;

/**
 * ElementHandler — handles element-related audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, and `afterSnapshot` via `Audit::$plugin->auditRecorder`.
 */
class ElementHandler extends Component
{
    /**
     * Map of spl_object_id($job) → batchId for in-flight resave jobs.
     *
     * Replaces the legacy cache-keyed-by-element-type pattern (FRE-140), which
     * collided when two queue workers ran the same element type concurrently.
     * Per-job-instance keys make collisions impossible.
     *
     * @var array<int, int>
     */
    private array $resaveBatchIds = [];

    /**
     * @param ElementInterface $element
     * @param bool $isNew
     *
     * @return bool
     */
    public function onSaveElement(ElementInterface $element, bool $isNew = false): bool
    {
        $auditRecorder = Audit::$plugin->auditRecorder;
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
            $model = $auditRecorder->getStandardModel();
            $model->event = $isNew ? AuditEvent::CreatedElement->value : AuditEvent::SavedElement->value;
            $model->elementId = $element->getId();
            $model->siteId = $element->siteId;
            $model->elementType = get_class($element);
            $snapshot = [
                'elementId' => $element->getId(),
                'elementType' => get_class($element),
                'elementTypeLabel' => $element::displayName(),
            ];

            if (Audit::$craft32 && ($isDraft || $isRevision)) {
                $model->event = AuditEvent::SavedDraft->value;
            }

            if ($element instanceof Entry) {
                /** @var Entry $element */
                $model->event = $isNew ? AuditEvent::EntryCreated->value : AuditEvent::EntrySaved->value;
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
                $model->event = AuditEvent::SavedGlobal->value;
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

            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $auditRecorder->saveRecord($model);
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
        $auditRecorder = Audit::$plugin->auditRecorder;
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
            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::DeletedElement->value;
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
                $model->event = AuditEvent::EntryDeleted->value;
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

            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, $snapshot));

            return $auditRecorder->saveRecord($model);
        } catch (\Exception $e) {
            Craft::error(
                Craft::t('audit', 'Error when logging: {error}', ['error' => $e->getMessage()]),
                __METHOD__
            );

            return false;
        }
    }

    /**
     * Opens an audit batch for the resave job. Any audit rows written while
     * the queue worker processes elements inherit the batch as their parent
     * via AuditRecorder's auto-attach hook.
     *
     * @internal Wired from \superbig\audit\Audit::setupQueueEvents().
     */
    public function onBeforeResave(ResaveElements $job): bool
    {
        try {
            $batchId = Audit::$plugin->batch->open(
                title: 'Resaving ' . $job->elementType . ' elements',
                metadata: [
                    'elementType' => $job->elementType,
                    'criteria' => $job->criteria,
                ],
            );
            $this->resaveBatchIds[spl_object_id($job)] = $batchId;
        } catch (\Throwable $e) {
            Craft::error(
                'Audit: failed to open resave batch — ' . $e->getMessage(),
                __METHOD__
            );
        }

        return true;
    }

    /**
     * Closes the batch opened by {@see onBeforeResave()}. Idempotent and safe
     * to call without a prior open() (e.g., if open() itself failed).
     *
     * @internal Wired from \superbig\audit\Audit::setupQueueEvents().
     */
    public function onResaveEnd(ResaveElements $job, bool $failed = false): mixed
    {
        $key = spl_object_id($job);
        if (!isset($this->resaveBatchIds[$key])) {
            return true;
        }

        $batchId = $this->resaveBatchIds[$key];
        unset($this->resaveBatchIds[$key]);

        try {
            Audit::$plugin->batch->close($batchId, failed: $failed);
        } catch (\Throwable $e) {
            Craft::error(
                'Audit: failed to close resave batch — ' . $e->getMessage(),
                __METHOD__
            );
        }

        return true;
    }
}
