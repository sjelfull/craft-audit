<?php

use craft\elements\User;
use craft\events\EntryTypeEvent;
use craft\events\FieldEvent;
use craft\events\SectionEvent;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\Section;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('logs audit event when field is saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $field = new PlainText();
    $field->name = 'Test Field';
    $field->handle = 'testField';

    $event = new FieldEvent([
        'field' => $field,
        'isNew' => true,
    ]);
    Audit::$plugin->auditService->onFieldSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_FIELD_SAVED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Created: Test Field');
});

it('logs audit event when field is deleted', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $field = new PlainText();
    $field->name = 'Deleted Field';
    $field->handle = 'deletedField';

    $event = new FieldEvent([
        'field' => $field,
    ]);
    Audit::$plugin->auditService->onFieldDeleted($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_FIELD_DELETED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Field');
});

it('logs audit event when section is saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $section = new Section();
    $section->name = 'Test Section';
    $section->handle = 'testSection';
    $section->type = Section::TYPE_CHANNEL;

    $event = new SectionEvent([
        'section' => $section,
        'isNew' => true,
    ]);
    Audit::$plugin->auditService->onSectionSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_SECTION_SAVED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Created: Test Section');
});

it('logs audit event when section is deleted', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $section = new Section();
    $section->name = 'Deleted Section';
    $section->handle = 'deletedSection';

    $event = new SectionEvent([
        'section' => $section,
    ]);
    Audit::$plugin->auditService->onSectionDeleted($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_SECTION_DELETED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Section');
});

it('logs audit event when entry type is saved', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $entryType = new EntryType();
    $entryType->name = 'Test Entry Type';
    $entryType->handle = 'testEntryType';

    $event = new EntryTypeEvent([
        'entryType' => $entryType,
        'isNew' => true,
    ]);
    Audit::$plugin->auditService->onEntryTypeSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_ENTRY_TYPE_SAVED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Created: Test Entry Type');
});

it('logs audit event when entry type is deleted', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $entryType = new EntryType();
    $entryType->name = 'Deleted Entry Type';
    $entryType->handle = 'deletedEntryType';

    $event = new EntryTypeEvent([
        'entryType' => $entryType,
    ]);
    Audit::$plugin->auditService->onEntryTypeDeleted($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_ENTRY_TYPE_DELETED])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Entry Type');
});

it('does not log schema events when disabled', function () {
    Audit::$plugin->getSettings()->logSchemaEvents = false;

    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $field = new PlainText();
    $field->name = 'Test Field';
    $field->handle = 'testField';

    $event = new FieldEvent([
        'field' => $field,
        'isNew' => true,
    ]);
    $result = Audit::$plugin->auditService->onFieldSaved($event);

    expect($result)->toBeFalse();

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_FIELD_SAVED])
        ->one();

    expect($record)->toBeNull();

    Audit::$plugin->getSettings()->logSchemaEvents = true;
});

it('captures field details in snapshot', function () {
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);

    $field = new PlainText();
    $field->name = 'Snapshot Test Field';
    $field->handle = 'snapshotTestField';

    $event = new FieldEvent([
        'field' => $field,
        'isNew' => true,
    ]);
    Audit::$plugin->auditService->onFieldSaved($event);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_FIELD_SAVED])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('fieldHandle');
    expect($model->snapshot)->toHaveKey('fieldType');
    expect($model->snapshot)->toHaveKey('isNew');
    expect($model->snapshot['fieldHandle'])->toBe('snapshotTestField');
});
