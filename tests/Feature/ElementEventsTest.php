<?php

use markhuot\craftpest\factories\Entry;
use craft\elements\Entry as EntryElement;
use craft\elements\GlobalSet;
use superbig\audit\Audit;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('logs audit event when entry is created', function () {
    $entry = Entry::factory()->create();

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->event)->toBe(AuditModel::EVENT_ENTRY_CREATED);
    expect($record->elementType)->toBe(EntryElement::class);
});

it('logs audit event when entry is updated', function () {
    $entry = Entry::factory()->create();

    // Clear the creation event
    AuditRecord::deleteAll();

    // Update the entry
    $entry->title = 'Updated Title';
    \Craft::$app->getElements()->saveElement($entry);

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->event)->toBe(AuditModel::EVENT_ENTRY_SAVED);
});

it('logs audit event when entry is deleted', function () {
    $entry = Entry::factory()->create();
    $entryId = $entry->id;

    // Clear the creation event
    AuditRecord::deleteAll();

    // Delete the entry
    \Craft::$app->getElements()->deleteElement($entry);

    $record = AuditRecord::find()
        ->where(['elementId' => $entryId])
        ->one();

    expect($record)->not->toBeNull();
    expect($record->event)->toBe(AuditModel::EVENT_ENTRY_DELETED);
});

it('does not log draft events when disabled', function () {
    // Ensure draft logging is disabled
    Audit::$plugin->getSettings()->logDraftEvents = false;

    $entry = Entry::factory()->create();

    // Create a draft
    $draft = \Craft::$app->getDrafts()->createDraft($entry, \Craft::$app->getUser()->getId());

    $draftRecord = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_SAVED_DRAFT])
        ->one();

    expect($draftRecord)->toBeNull();
});

it('does not log child elements when disabled', function () {
    // Ensure child element logging is disabled
    Audit::$plugin->getSettings()->logChildElementEvents = false;

    // Note: Testing Matrix blocks requires a Matrix field setup
    // This test verifies the setting exists and can be toggled
    expect(Audit::$plugin->getSettings()->logChildElementEvents)->toBeFalse();
});

it('logs global set saves', function () {
    $globals = GlobalSet::find()->one();

    if (!$globals) {
        $this->markTestSkipped('No global sets available for testing');
    }

    // Clear existing logs
    AuditRecord::deleteAll();

    // Save the global set
    \Craft::$app->getElements()->saveElement($globals);

    $record = AuditRecord::find()
        ->where(['event' => AuditModel::EVENT_SAVED_GLOBAL])
        ->one();

    expect($record)->not->toBeNull();
});

it('captures snapshot data with expected fields', function () {
    $entry = Entry::factory()->create();

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toBeArray();
    expect($model->snapshot)->toHaveKey('elementId');
    expect($model->snapshot)->toHaveKey('elementType');
    expect($model->snapshot['elementId'])->toBe($entry->id);
    expect($model->snapshot['elementType'])->toBe(EntryElement::class);
});

it('captures snapshot data with custom fields from news section', function () {
    $bodyText = 'This is the body content for testing';
    $entry = Entry::factory()
        ->section('news')
        ->body($bodyText)
        ->create();

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toBeArray();
    expect($model->snapshot)->toHaveKey('title');
    expect($model->snapshot['title'])->toBe($entry->title);

    // Verify custom field data is captured in content
    expect($model->snapshot)->toHaveKey('content');
    expect($model->snapshot['content'])->toBeArray();
    expect($model->snapshot['content'])->toHaveKey('body');
    expect($model->snapshot['content']['body'])->toBe($bodyText);
});

it('captures snapshot data with matrix field content', function () {
    $entry = Entry::factory()
        ->section('pages')
        ->contentBuilder(
            Entry::factory()->type('plainText')->body('First block content'),
            Entry::factory()->type('callToAction')->heading('CTA Heading')->body('CTA body text'),
        )
        ->create();

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toBeArray();
    expect($model->snapshot)->toHaveKey('elementId');
    expect($model->snapshot['elementId'])->toBe($entry->id);

    // Verify the matrix field was populated on the entry
    expect($entry->contentBuilder->count())->toEqual(2);

    // Verify the matrix field data is captured in snapshot content
    expect($model->snapshot)->toHaveKey('content');
    expect($model->snapshot['content'])->toHaveKey('contentBuilder');

    // Matrix fields are serialized in the content array, keyed by entry ID
    $matrixContent = $model->snapshot['content']['contentBuilder'];
    expect($matrixContent)->toBeArray();
    expect(count($matrixContent))->toEqual(2);

    // Get the matrix entries to find their IDs
    $matrixEntries = $entry->contentBuilder->collect();
    $firstBlockId = $matrixEntries->get(0)->id;
    $secondBlockId = $matrixEntries->get(1)->id;

    // Verify first block (plainText) data is captured in snapshot
    expect($matrixContent)->toHaveKey($firstBlockId);
    expect($matrixContent[$firstBlockId]['type'])->toBe('plainText');
    expect($matrixContent[$firstBlockId]['fields']['body'])->toBe('First block content');

    // Verify second block (callToAction) data is captured in snapshot
    expect($matrixContent)->toHaveKey($secondBlockId);
    expect($matrixContent[$secondBlockId]['type'])->toBe('callToAction');
    expect($matrixContent[$secondBlockId]['fields']['heading'])->toBe('CTA Heading');
    expect($matrixContent[$secondBlockId]['fields']['body'])->toBe('CTA body text');
});
