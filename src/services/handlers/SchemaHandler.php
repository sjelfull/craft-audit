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
use superbig\audit\enums\AuditEvent;

/**
 * SchemaHandler — handles field / section / entry-type audit events extracted from AuditService.
 *
 * Behavior is preserved verbatim: methods delegate back to AuditService for
 * `saveRecord`, `getStandardModel`, `afterSnapshot`, and `catchSaveError`
 * via `Audit::$plugin->auditRecorder`.
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

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $field = $event->field;
            $isNew = $event->isNew;

            $model = $auditRecorder->getStandardModel();
            $model->event = $isNew ? AuditEvent::FieldCreated->value : AuditEvent::FieldSaved->value;
            $model->title = $field->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onFieldDeleted(FieldEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $field = $event->field;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::FieldDeleted->value;
            $model->title = $field->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'fieldId' => $field->id,
                'fieldName' => $field->name,
                'fieldHandle' => $field->handle,
                'fieldType' => get_class($field),
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onSectionSaved(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $section = $event->section;
            $isNew = $event->isNew;

            $model = $auditRecorder->getStandardModel();
            $model->event = $isNew ? AuditEvent::SectionCreated->value : AuditEvent::SectionSaved->value;
            $model->title = $section->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onSectionDeleted(SectionEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $section = $event->section;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::SectionDeleted->value;
            $model->title = $section->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'sectionHandle' => $section->handle,
                'sectionType' => $section->type,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onEntryTypeSaved(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $entryType = $event->entryType;
            $isNew = $event->isNew;

            $model = $auditRecorder->getStandardModel();
            $model->event = $isNew ? AuditEvent::EntryTypeCreated->value : AuditEvent::EntryTypeSaved->value;
            $model->title = $entryType->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }

    public function onEntryTypeDeleted(EntryTypeEvent $event): bool
    {
        if (!Audit::$plugin->getSettings()->logSchemaEvents) {
            return false;
        }

        $auditRecorder = Audit::$plugin->auditRecorder;

        return $auditRecorder->catchSaveError(function() use ($event, $auditRecorder) {
            $entryType = $event->entryType;

            $model = $auditRecorder->getStandardModel();
            $model->event = AuditEvent::EntryTypeDeleted->value;
            $model->title = $entryType->name;
            $model->snapshot = $auditRecorder->afterSnapshot($model, array_merge($model->snapshot, [
                'entryTypeId' => $entryType->id,
                'entryTypeName' => $entryType->name,
                'entryTypeHandle' => $entryType->handle,
            ]));

            return $auditRecorder->saveRecord($model);
        });
    }
}
