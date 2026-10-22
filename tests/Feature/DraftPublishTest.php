<?php

use craft\elements\Entry;
use craft\elements\User;
use markhuot\craftpest\factories\Entry as EntryFactory;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
    Audit::$plugin->getSettings()->logDraftEvents = false;
    \Craft::$app->getUser()->setIdentity(User::find()->admin()->one());
});

function _newsEntry(string $title): Entry
{
    $section = \Craft::$app->getEntries()->getSectionByHandle('news');

    return new Entry([
        'sectionId' => $section->id,
        'typeId' => $section->getEntryTypes()[0]->id,
        'title' => $title,
    ]);
}

it('logs one entry-created row when an unpublished draft is applied', function () {
    $userId = \Craft::$app->getUser()->getId();
    $entry = _newsEntry('Drafted in the CP');
    $entry->setFieldValue('body', 'First words');

    expect(\Craft::$app->getDrafts()->saveElementAsDraft($entry, $userId, null, null, false))->toBeTrue();

    // The CP autosaves the draft as the editor types, so it is clean by the time it's applied.
    $entry->setFieldValue('body', 'More words');
    expect(\Craft::$app->getElements()->saveElement($entry))->toBeTrue();
    expect(AuditRecord::find()->where(['elementId' => $entry->id])->count())->toEqual(0);

    \Craft::$app->getDrafts()->applyDraft($entry);

    $rows = AuditRecord::find()->where(['elementId' => $entry->id])->all();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->event)->toBe(AuditEvent::EntryCreated->value);
    expect($rows[0]->title)->toBe('Drafted in the CP');
});

it('logs entry-saved, not entry-created, when a draft of an existing entry is applied', function () {
    $entry = EntryFactory::factory()->section('news')->title('Live')->create();
    AuditRecord::deleteAll();

    $draft = \Craft::$app->getDrafts()->createDraft($entry, \Craft::$app->getUser()->getId());
    $draft->title = 'Live, edited';
    \Craft::$app->getElements()->saveElement($draft);

    \Craft::$app->getDrafts()->applyDraft($draft);

    $rows = AuditRecord::find()->where(['elementId' => $entry->id])->all();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->event)->toBe(AuditEvent::EntrySaved->value);
});
