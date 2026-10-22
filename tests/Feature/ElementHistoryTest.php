<?php

use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use markhuot\craftpest\factories\Entry as EntryFactory;
use markhuot\craftpest\factories\User as UserFactory;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use yii\web\ForbiddenHttpException;

beforeEach(function () {
    \Craft::$app->set('response', \Craft::createObject(App::webResponseConfig()));
    Audit::$plugin->auditRecorder->writeAfterCommit = false;
    $this->admin = User::find()->admin()->one();
});

function auditHistoryRow(int $elementId, string $title, AuditEvent $event = AuditEvent::EntrySaved): void
{
    $record = new AuditRecord();
    $record->setAttributes([
        'event' => $event->value,
        'title' => $title,
        'elementId' => $elementId,
        'elementType' => Entry::class,
        'siteId' => \Craft::$app->getSites()->getPrimarySite()->id,
        'userId' => User::find()->admin()->one()->id,
    ], false);
    $record->save(false);
}

function cpPath(string $url): string
{
    return '/' . ltrim(parse_url($url, PHP_URL_PATH), '/');
}

it('adds History to the entry edit screen', function () {
    $entry = EntryFactory::factory()->create();

    $this->actingAs($this->admin)
        ->get(cpPath($entry->getCpEditUrl()))
        ->assertOk()
        ->assertSee('action-audit-history-')
        ->assertSee('audit/default/history');
});

it('adds History to user edit screens', function () {
    $user = UserFactory::factory()->create();

    $this->actingAs($this->admin)
        ->get(cpPath(UrlHelper::cpUrl('users/' . $user->id)))
        ->assertOk()
        ->assertSee('action-audit-history-');
});

it('leaves History out for people who cannot view the log', function () {
    $entry = EntryFactory::factory()->create();
    $editor = UserFactory::factory()->create();
    \Craft::$app->getUser()->setIdentity($editor);

    expect(collect($entry->getActionMenuItems())->pluck('id')->filter(fn($id) => str_starts_with((string)$id, 'action-audit-history-')))
        ->toBeEmpty();
});

it('lists only the element\'s events, newest first', function () {
    $entry = EntryFactory::factory()->create();
    $other = EntryFactory::factory()->create();
    AuditRecord::deleteAll();
    auditHistoryRow($entry->id, 'First save');
    auditHistoryRow($entry->id, 'Second save');
    auditHistoryRow($other->id, 'Someone else');

    $response = $this->actingAs($this->admin)
        ->get('/admin/audit/history/' . $entry->id)
        ->assertOk()
        ->assertSee('audit-history__item')
        ->assertDontSee('Someone else');

    expect(substr_count($response->content, 'class="audit-history__item"'))->toBe(2);
});

it('keeps the history of a deleted element', function () {
    $entry = EntryFactory::factory()->create();
    \Craft::$app->getElements()->deleteElement($entry);
    AuditRecord::deleteAll();
    auditHistoryRow($entry->id, 'Old landing page', AuditEvent::EntryDeleted);

    $this->actingAs($this->admin)
        ->get('/admin/audit/history/' . $entry->id)
        ->assertOk()
        ->assertSee('History: Old landing page')
        ->assertSee('Deleted');
});

it('returns the history as a slideout screen for script requests', function () {
    $entry = EntryFactory::factory()->create();
    AuditRecord::deleteAll();
    auditHistoryRow($entry->id, 'Spring launch');

    $response = $this->actingAs($this->admin)
        ->http('get', '/admin/audit/history/' . $entry->id)
        ->addHeader('Accept', 'application/json')
        ->send()
        ->assertOk();

    $data = Json::decode($response->content);

    expect($data['content'])->toContain('audit-history__item');
    expect($data['content'])->toContain('data-audit-open');
    expect($data['editUrl'])->toContain('elementId=' . $entry->id);
});

it('requires permission to view an element\'s history', function () {
    $editor = UserFactory::factory()->create();

    expect(fn() => $this->actingAs($editor)->get('/admin/audit/history/1'))
        ->toThrow(ForbiddenHttpException::class);
});
