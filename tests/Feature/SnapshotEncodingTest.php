<?php

use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use superbig\audit\Audit;

/**
 * Regression test for FRE-239: snapshot column must be stored as
 * single-encoded native JSON, not as a doubly-encoded string.
 *
 * Pre-fix: writes wrapped values in Json::encode() before assigning to AR,
 * and Yii AR encoded the resulting JSON string a second time. The column
 * stored escaped doubly-encoded bytes like `"\"key\":\"value\""` instead
 * of native JSON `{"key":"value"}`.
 *
 * Post-fix: services assign arrays directly; AR auto-decodes on read.
 *
 * These tests guard against the double-encode being silently reintroduced,
 * AND verify legacy doubly-encoded rows still decode for backward compat.
 */

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('stores snapshot as native JSON, not as a doubly-encoded string', function () {
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'FRE-239 regression',
        snapshot: ['key' => 'value', 'nested' => ['a' => 1]],
    );
    expect($model)->not->toBeNull();

    // Portable raw-bytes inspection: select with a driver-appropriate cast
    // so Yii returns the on-disk JSON form as a string. MySQL stores native
    // JSON; Postgres uses JSONB which we cast to text.
    $db = Craft::$app->getDb();
    $driver = $db->driverName;
    $sql = $driver === 'pgsql'
        ? 'SELECT snapshot::text AS s FROM ' . $db->quoteTableName('{{%audit_log}}') . ' WHERE id = :id'
        : 'SELECT snapshot AS s FROM ' . $db->quoteTableName('{{%audit_log}}') . ' WHERE id = :id';

    $raw = $db->createCommand($sql)->bindValue(':id', $model->id)->queryScalar();

    expect($raw)->toBeString();
    // First non-whitespace character must be `{` (a JSON object), not `"`
    // (the pre-fix doubly-encoded string form).
    expect(ltrim((string) $raw)[0] ?? '')->toBe('{');
});

it('returns snapshot as an array via AR (no manual decode needed)', function () {
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'FRE-239 read regression',
        snapshot: ['source' => 'test', 'count' => 42],
    );

    $record = AuditRecord::findOne($model->id);
    expect($record->snapshot)->toBeArray();
    // Use toEqual rather than toBe — Postgres JSON storage may reorder
    // keys on retrieval. We care about value equality, not insertion order.
    expect($record->snapshot)->toEqual(['source' => 'test', 'count' => 42]);
});

it('returns changedFields as an array via AR (no manual decode needed)', function () {
    $diff = ['title' => ['handler' => 'plain', 'from' => 'Old', 'to' => 'New']];
    $model = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'FRE-239 changedFields regression',
        changedFields: $diff,
    );

    $record = AuditRecord::findOne($model->id);
    expect($record->changedFields)->toBeArray();
    // Key order may differ on Postgres JSONB — use toEqual not toBe.
    expect($record->changedFields)->toEqual($diff);
});

it('decodes legacy doubly-encoded snapshot rows defensively', function () {
    // Simulate a row written by the old buggy code (Json::encode of an
    // already-JSON string) by inserting directly via raw SQL.
    $legacyDoublyEncoded = '"{\\"feedId\\":123,\\"source\\":\\"legacy\\"}"';

    $db = Craft::$app->getDb();
    $db->createCommand()->insert('{{%audit_log}}', [
        'siteId' => 1,
        'event' => 'legacy-row',
        'ip' => '0.0.0.0',
        'userAgent' => 'test',
        'snapshot' => $legacyDoublyEncoded,
        'dateCreated' => date('Y-m-d H:i:s'),
        'dateUpdated' => date('Y-m-d H:i:s'),
        'uid' => \craft\helpers\StringHelper::UUID(),
    ])->execute();

    $record = AuditRecord::find()->where(['event' => 'legacy-row'])->one();
    expect($record)->not->toBeNull();

    $model = \superbig\audit\models\AuditModel::createFromRecord($record);
    expect($model->snapshot)->toBeArray();
    expect($model->snapshot['feedId'])->toBe(123);
    expect($model->snapshot['source'])->toBe('legacy');
});
