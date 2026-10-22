<?php

use craft\elements\User;
use craft\helpers\Json;
use markhuot\craftpest\factories\User as UserFactory;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use yii\web\ForbiddenHttpException;

beforeEach(function () {
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

it('requires permission to view the log', function () {
    $editor = UserFactory::factory()->create();

    expect(fn() => $this->actingAs($editor)->get('/admin/audit'))
        ->toThrow(ForbiddenHttpException::class);
});
