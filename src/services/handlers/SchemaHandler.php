<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\services\handlers;

use craft\base\Component;
use craft\events\EntryTypeEvent;
use craft\events\FieldEvent;
use craft\events\SectionEvent;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;

/**
 * SchemaHandler — handles field / section / entry-type audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `_saveRecord`, `_getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditService`.
 *
 * @author    Superbig
 * @package   Audit
 * @since     3.x
 */
class SchemaHandler extends Component
{
    public function onFieldSaved(FieldEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $field = $event->field;
            $isNew = $event->isNew;

            $model = $auditService->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_FIELD_CREATED : AuditModel::EVENT_FIELD_SAVED;
            $model->title = $field->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onFieldDeleted(FieldEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $field = $event->field;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_FIELD_DELETED;
            $model->title = $field->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onSectionSaved(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $section = $event->section;
            $isNew = $event->isNew;

            $model = $auditService->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_SECTION_CREATED : AuditModel::EVENT_SECTION_SAVED;
            $model->title = $section->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onSectionDeleted(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $section = $event->section;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_SECTION_DELETED;
            $model->title = $section->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onEntryTypeSaved(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $entryType = $event->entryType;
            $isNew = $event->isNew;

            $model = $auditService->_getStandardModel();
            $model->event = $isNew ? AuditModel::EVENT_ENTRY_TYPE_CREATED : AuditModel::EVENT_ENTRY_TYPE_SAVED;
            $model->title = $entryType->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $auditService->_saveRecord($model);
        });
    }

    public function onEntryTypeDeleted(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditService = Audit::$plugin->auditService;

        return $auditService->catchSaveError(function() use ($event, $auditService) {
            $entryType = $event->entryType;

            $model = $auditService->_getStandardModel();
            $model->event = AuditModel::EVENT_ENTRY_TYPE_DELETED;
            $model->title = $entryType->name;
            $model->snapshot = $auditService->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $auditService->_saveRecord($model);
        });
    }
}
