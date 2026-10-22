<?php

use craft\elements\User;
use craft\helpers\Json;
use markhuot\craftpest\factories\Entry as EntryFactory;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
    Audit::$plugin->getSettings()->logDraftEvents = false;
    \Craft::$app->getUser()->setIdentity(User::find()->admin()->one());
});

function _changedFields(AuditRecord $row): array
{
    $value = $row->changedFields;

    return is_string($value) ? Json::decode($value) : (array) $value;
}

it('records only the fields a save changed, with before and after values', function () {
    $entry = EntryFactory::factory()->section('news')->title('Before')->create();
    AuditRecord::deleteAll();

    $entry->title = 'After';
    expect(\Craft::$app->getElements()->saveElement($entry))->toBeTrue();

    $row = AuditRecord::find()->where(['elementId' => $entry->id, 'event' => AuditEvent::EntrySaved->value])->one();
    $changes = _changedFields($row);

    expect($changes)->toHaveKey('_title');
    expect($changes['_title']['from'])->toBe('Before');
    expect($changes['_title']['to'])->toBe('After');
    expect($changes)->not->toHaveKey('_slug');
});

it('records custom field changes', function () {
    $entry = EntryFactory::factory()->section('news')->create();
    $entry->setFieldValue('body', 'Old words');
    \Craft::$app->getElements()->saveElement($entry);
    AuditRecord::deleteAll();

    $entry->setFieldValue('body', 'New words');
    \Craft::$app->getElements()->saveElement($entry);

    $row = AuditRecord::find()->where(['elementId' => $entry->id])->one();
    $changes = _changedFields($row);

    expect($changes)->toHaveKey('body');
    expect($changes['body']['to'])->toContain('New words');
});

it('records the starting values of a new element', function () {
    $entry = EntryFactory::factory()->section('news')->title('Brand new')->create();

    $row = AuditRecord::find()->where(['elementId' => $entry->id, 'event' => AuditEvent::EntryCreated->value])->one();
    $changes = _changedFields($row);

    expect($changes['_title']['from'])->toBeNull();
    expect($changes['_title']['to'])->toBe('Brand new');
    expect($changes)->not->toHaveKey('_expiryDate');
});

it('records what applying a draft changed on the canonical entry', function () {
    $entry = EntryFactory::factory()->section('news')->title('Live')->create();
    AuditRecord::deleteAll();

    $draft = \Craft::$app->getDrafts()->createDraft($entry, \Craft::$app->getUser()->getId());
    $draft->title = 'Live, edited';
    \Craft::$app->getElements()->saveElement($draft);
    \Craft::$app->getDrafts()->applyDraft($draft);

    $row = AuditRecord::find()->where(['elementId' => $entry->id])->one();
    $changes = _changedFields($row);

    expect($changes['_title']['from'])->toBe('Live');
    expect($changes['_title']['to'])->toBe('Live, edited');
});
