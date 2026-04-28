<?php

use craft\elements\User;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
    $user = User::find()->admin()->one();
    \Craft::$app->getUser()->setIdentity($user);
});

/**
 * These tests exercise the real Craft API surface
 * (\Craft::$app->fields->saveField(), Entries::saveSection() etc.) so that
 * each assertion validates the full chain: Craft service → event fired →
 * Audit::initLogEvents() listener → handler service → audit record persisted.
 *
 * The Pest bootstrap (tests/Pest.php) calls Audit::initLogEvents() reflectively
 * because in the test environment Craft is a console request, which the
 * production code path skips.
 *
 * Handles are randomized per test because Craft persists field/section/entry
 * type rows in project config and the test DB is not reset between cases
 * within a single suite run.
 */
function _schemaTestSuffix(): string
{
    return substr(bin2hex(random_bytes(4)), 0, 8);
}

function _buildTestSection(string $nameSuffix, string $handle): Section
{
    $primarySite = \Craft::$app->sites->getPrimarySite();

    $entryTypeHandle = 'et' . substr(bin2hex(random_bytes(4)), 0, 8);
    $entryType = new EntryType();
    $entryType->name = 'ET ' . $nameSuffix;
    $entryType->handle = $entryTypeHandle;

    if (!\Craft::$app->entries->saveEntryType($entryType)) {
        throw new \RuntimeException('Failed to save entry type for test section: ' . print_r($entryType->getErrors(), true));
    }

    $section = new Section();
    $section->name = $nameSuffix;
    $section->handle = $handle;
    $section->type = Section::TYPE_CHANNEL;
    $section->setSiteSettings([
        $primarySite->id => new Section_SiteSettings([
            'siteId' => $primarySite->id,
            'enabledByDefault' => true,
            'hasUrls' => false,
        ]),
    ]);
    $section->setEntryTypes([$entryType]);

    return $section;
}

it('logs audit event when field is saved', function () {
    $suffix = _schemaTestSuffix();
    $field = new PlainText();
    $field->name = 'Test Field ' . $suffix;
    $field->handle = 'testField' . $suffix;

    expect(\Craft::$app->fields->saveField($field))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::FieldCreated->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Test Field ' . $suffix);
});

it('logs audit event when field is deleted', function () {
    $suffix = _schemaTestSuffix();
    $field = new PlainText();
    $field->name = 'Deleted Field ' . $suffix;
    $field->handle = 'deletedField' . $suffix;

    expect(\Craft::$app->fields->saveField($field))->toBeTrue();

    AuditRecord::deleteAll();

    \Craft::$app->fields->deleteField($field);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::FieldDeleted->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Field ' . $suffix);
});

it('logs audit event when section is saved', function () {
    $suffix = _schemaTestSuffix();
    $section = _buildTestSection('Test Section ' . $suffix, 'testSection' . $suffix);

    expect(\Craft::$app->entries->saveSection($section))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SectionCreated->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Test Section ' . $suffix);
});

it('logs audit event when section is deleted', function () {
    $suffix = _schemaTestSuffix();
    $section = _buildTestSection('Deleted Section ' . $suffix, 'deletedSection' . $suffix);

    expect(\Craft::$app->entries->saveSection($section))->toBeTrue();

    AuditRecord::deleteAll();

    \Craft::$app->entries->deleteSectionById($section->id);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::SectionDeleted->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Section ' . $suffix);
});

it('logs audit event when entry type is saved', function () {
    $suffix = _schemaTestSuffix();
    $entryType = new EntryType();
    $entryType->name = 'Test Entry Type ' . $suffix;
    $entryType->handle = 'testEntryType' . $suffix;

    expect(\Craft::$app->entries->saveEntryType($entryType))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::EntryTypeCreated->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Test Entry Type ' . $suffix);
});

it('logs audit event when entry type is deleted', function () {
    $suffix = _schemaTestSuffix();
    $entryType = new EntryType();
    $entryType->name = 'Deleted Entry Type ' . $suffix;
    $entryType->handle = 'deletedEntryType' . $suffix;

    expect(\Craft::$app->entries->saveEntryType($entryType))->toBeTrue();

    AuditRecord::deleteAll();

    \Craft::$app->entries->deleteEntryTypeById($entryType->id);

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::EntryTypeDeleted->value])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->title)->toBe('Deleted Entry Type ' . $suffix);
});

it('does not log schema events when disabled', function () {
    Audit::$plugin->getSettings()->logSchemaEvents = false;

    try {
        $suffix = _schemaTestSuffix();
        $field = new PlainText();
        $field->name = 'Disabled Field ' . $suffix;
        $field->handle = 'disabledField' . $suffix;

        expect(\Craft::$app->fields->saveField($field))->toBeTrue();

        $record = AuditRecord::find()
            ->where(['event' => AuditEvent::FieldCreated->value])
            ->one();

        expect($record)->toBeNull();
    } finally {
        Audit::$plugin->getSettings()->logSchemaEvents = true;
    }
});

it('captures field details in snapshot', function () {
    $suffix = _schemaTestSuffix();
    $field = new PlainText();
    $field->name = 'Snapshot Test Field ' . $suffix;
    $field->handle = 'snapshotTestField' . $suffix;

    expect(\Craft::$app->fields->saveField($field))->toBeTrue();

    $record = AuditRecord::find()
        ->where(['event' => AuditEvent::FieldCreated->value])
        ->one();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('fieldHandle');
    expect($model->snapshot)->toHaveKey('fieldType');
    expect($model->snapshot['fieldHandle'])->toBe('snapshotTestField' . $suffix);
});
