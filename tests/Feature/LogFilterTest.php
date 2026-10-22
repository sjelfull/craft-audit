<?php

use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Db;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\enums\AuditEventKind;
use superbig\audit\models\LogFilter;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

function _row(array $attributes = []): AuditRecord
{
    $record = new AuditRecord();
    $record->setAttributes(array_merge([
        'event' => AuditEvent::EntrySaved->value,
        'title' => 'Row',
        'siteId' => \Craft::$app->getSites()->getPrimarySite()->id,
    ], $attributes), false);
    $record->save(false);

    if (isset($attributes['dateCreated'])) {
        \Craft::$app->getDb()->createCommand()
            ->update(AuditRecord::tableName(), ['dateCreated' => $attributes['dateCreated']], ['id' => $record->id])
            ->execute();
    }

    return $record;
}

function _filter(array $params): LogFilter
{
    \Craft::$app->getRequest()->setQueryParams($params);

    return LogFilter::fromRequest(\Craft::$app->getRequest());
}

function _titles(LogFilter $filter): array
{
    $query = AuditRecord::find()->orderBy(['id' => SORT_ASC]);
    $filter->apply($query);

    return $query->select('title')->column();
}

it('shows only top-level rows until a filter is set', function () {
    $batch = _row(['event' => AuditEvent::BatchCompleted->value, 'title' => 'Batch']);
    _row(['title' => 'Inside batch', 'parentId' => $batch->id]);
    _row(['title' => 'Loose']);

    expect(_titles(_filter([])))->toBe(['Batch', 'Loose']);
    expect(_titles(_filter(['search' => 'inside'])))->toBe(['Inside batch']);
});

it('filters by kind, and lets a specific event take precedence over the kind', function () {
    _row(['title' => 'Saved', 'event' => AuditEvent::EntrySaved->value]);
    _row(['title' => 'Failed login', 'event' => AuditEvent::UserLoginFailed->value]);
    _row(['title' => 'Field', 'event' => AuditEvent::FieldCreated->value]);

    expect(_titles(_filter(['kind' => 'security'])))->toBe(['Failed login']);
    expect(_titles(_filter(['kind' => 'schema'])))->toBe(['Field']);
    expect(_titles(_filter(['kind' => 'schema', 'event' => AuditEvent::EntrySaved->value])))->toBe(['Saved']);
});

it('ignores an unknown kind and non-numeric ids', function () {
    $filter = _filter(['kind' => 'nope', 'userId' => 'abc', 'siteId' => '1 OR 1=1']);

    expect($filter->kind)->toBeNull();
    expect($filter->userId)->toBeNull();
    expect($filter->siteId)->toBeNull();
    expect($filter->isActive())->toBeFalse();
});

it('filters by user, element type, element, site and session', function () {
    $admin = User::find()->admin()->one();
    _row(['title' => 'By admin', 'userId' => $admin->id]);
    _row(['title' => 'Entry row', 'elementType' => Entry::class, 'elementId' => $admin->id]);
    _row(['title' => 'In session', 'sessionId' => 'abc123']);

    expect(_titles(_filter(['userId' => (string)$admin->id])))->toBe(['By admin']);
    expect(_titles(_filter(['elementType' => Entry::class])))->toBe(['Entry row']);
    expect(_titles(_filter(['elementId' => (string)$admin->id])))->toBe(['Entry row']);
    expect(_titles(_filter(['sessionId' => 'abc123'])))->toBe(['In session']);
    expect(_titles(_filter(['siteId' => (string)\Craft::$app->getSites()->getPrimarySite()->id])))->toHaveCount(3);
});

it('includes the whole of the last day in a date range', function () {
    $tz = new DateTimeZone(\Craft::$app->getTimeZone());
    $at = fn(string $local) => Db::prepareDateForDb(new DateTime($local, $tz));

    _row(['title' => 'Before', 'dateCreated' => $at('2026-03-09 23:59:00')]);
    _row(['title' => 'Start of day', 'dateCreated' => $at('2026-03-10 00:00:00')]);
    _row(['title' => 'End of day', 'dateCreated' => $at('2026-03-11 23:58:00')]);
    _row(['title' => 'After', 'dateCreated' => $at('2026-03-12 00:01:00')]);

    expect(_titles(_filter(['from' => '2026-03-10', 'to' => '2026-03-11'])))->toBe(['Start of day', 'End of day']);
    expect(_titles(_filter(['from' => '2026-03-11'])))->toBe(['End of day', 'After']);
});

it('ignores dates it cannot read', function () {
    $filter = _filter(['from' => 'not a date']);

    expect($filter->getFromDate())->toBeNull();
    expect(_titles($filter))->toBe([]);
});

it('searches titles, IP addresses and user names without regard to case', function () {
    $admin = User::find()->admin()->one();
    _row(['title' => 'Quarterly Results']);
    _row(['title' => 'Other', 'ip' => '203.0.113.9']);
    _row(['title' => 'By user', 'userId' => $admin->id]);

    expect(_titles(_filter(['search' => 'quarterly'])))->toBe(['Quarterly Results']);
    expect(_titles(_filter(['search' => '203.0.113'])))->toBe(['Other']);
    expect(_titles(_filter(['search' => strtoupper($admin->username)])))->toBe(['By user']);
});

it('describes active filters with a link that removes each one', function () {
    $filter = _filter(['kind' => 'schema', 'search' => 'news', 'from' => '2026-03-10']);
    $chips = collect($filter->getActiveFilters())->keyBy('param');

    expect($chips->keys()->all())->toBe(['kind', 'from', 'search']);
    expect($chips['kind']['value'])->toBe(AuditEventKind::Schema->label());
    expect($chips['search']['removeUrl'])->not->toContain('search=');
    expect($chips['search']['removeUrl'])->toContain('kind=schema');
});

it('offers only the events, users and element types that are in the log', function () {
    $admin = User::find()->admin()->one();
    _row(['event' => AuditEvent::FieldCreated->value, 'userId' => $admin->id, 'elementType' => Entry::class]);

    $options = Audit::$plugin->auditService->getFilterOptions();
    $events = array_column(array_filter($options['events'], fn($o) => isset($o['value'])), 'value');

    expect($events)->toBe([AuditEvent::FieldCreated->value]);
    expect(array_column($options['kinds'], 'value'))->toBe(['schema']);
    expect(array_column($options['users'], 'value'))->toBe([$admin->id]);
    expect(array_column($options['elementTypes'], 'value'))->toBe([Entry::class]);
});

it('puts every event in a kind and gives it a tone', function () {
    foreach (AuditEvent::cases() as $event) {
        expect($event->kind())->toBeInstanceOf(AuditEventKind::class);
    }

    expect(AuditEvent::EntryCreated->tone())->toBe('positive');
    expect(AuditEvent::EntryDeleted->tone())->toBe('negative');
    expect(AuditEvent::UserLoginFailed->tone())->toBe('warning');
    expect(AuditEvent::UserUnlocked->tone())->toBe('neutral');
    expect(AuditEvent::UserDeactivated->tone())->toBe('negative');
    expect(AuditEvent::EntrySaved->tone())->toBe('neutral');
});
