<?php

use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Json;
use markhuot\craftpest\factories\User as UserFactory;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

beforeEach(function () {
    // craft-pest reuses one response object, and behaviors left by earlier requests stop CP screens from rendering.
    \Craft::$app->set('response', \Craft::createObject(App::webResponseConfig()));
    AuditRecord::deleteAll();
    $this->admin = User::find()->admin()->one();

    foreach (['Spring launch' => AuditEvent::EntrySaved, 'Body field' => AuditEvent::FieldCreated] as $title => $event) {
        $record = new AuditRecord();
        $record->setAttributes([
            'event' => $event->value,
            'title' => $title,
            'siteId' => \Craft::$app->getSites()->getPrimarySite()->id,
            'userId' => $this->admin->id,
            'changedFields' => $event === AuditEvent::EntrySaved
                ? ['_title' => ['handler' => 'plain', 'from' => 'Spring', 'to' => 'Spring launch']]
                : null,
        ], false);
        $record->save(false);
    }
});

it('renders the log with its filters', function () {
    $this->actingAs($this->admin)
        ->get('/admin/audit')
        ->assertOk()
        ->assertSee('audit-filters')
        ->assertSee('Spring launch')
        ->assertSee('Body field');
});

it('renders filtered results without the page around them for script requests', function () {
    $response = $this->actingAs($this->admin)
        ->http('get', '/admin/audit?kind=schema')
        ->addHeader('Accept', 'application/json')
        ->send()
        ->assertOk();

    $data = Json::decode($response->content);

    expect($data['html'])->toContain('Body field');
    expect($data['html'])->not->toContain('Spring launch');
    expect($data['html'])->not->toContain('audit-filters');
    expect($data['url'])->toContain('kind=schema');
});

it('says when nothing matches', function () {
    $this->actingAs($this->admin)
        ->get('/admin/audit?search=nothing-like-this')
        ->assertOk()
        ->assertSee('No events match these filters.');
});

it('shows a log entry with its changes', function () {
    $this->actingAs($this->admin)
        ->get('/admin/audit/log/' . AuditRecord::find()->select('id')->where(['title' => 'Spring launch'])->scalar())
        ->assertOk()
        ->assertSee('audit-changed-field')
        ->assertSee('Spring launch');
});

it('escapes titles once, whether or not they were stored encoded', function () {
    AuditRecord::updateAll(['title' => 'Q&amp;A <b>'], ['title' => 'Spring launch']);
    $id = AuditRecord::find()->select('id')->where(['title' => 'Q&amp;A <b>'])->scalar();

    $this->actingAs($this->admin)
        ->get('/admin/audit/log/' . $id)
        ->assertOk()
        ->assertSee('Q&amp;A &lt;b&gt;')
        ->assertDontSee('Q&amp;amp;A')
        ->assertDontSee('Q&amp;A <b>');
});

it('returns 404 for a missing log entry', function () {
    expect(fn() => $this->actingAs($this->admin)->get('/admin/audit/log/999999'))
        ->toThrow(NotFoundHttpException::class);
});

it('requires permission to view the log', function () {
    $editor = UserFactory::factory()->create();

    expect(fn() => $this->actingAs($editor)->get('/admin/audit'))
        ->toThrow(ForbiddenHttpException::class);
});

it('returns the log entry as a slideout screen for script requests', function () {
    $id = AuditRecord::find()->select('id')->where(['title' => 'Spring launch'])->scalar();

    $response = $this->actingAs($this->admin)
        ->http('get', '/admin/audit/log/' . $id)
        ->addHeader('Accept', 'application/json')
        ->send()
        ->assertOk();

    $data = Json::decode($response->content);

    expect($data['content'])->toContain('audit-changed-field');
    expect($data['content'])->toContain('audit-detail__header');
    expect($data['sidebar'])->toContain('audit-meta');
    expect($data['editUrl'])->toContain('audit/log/' . $id);
});
