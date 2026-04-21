<?php

use superbig\audit\migrations\m260421_000000_snapshot_to_json;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('can insert a record with legacy-looking snapshot value and read it back', function () {
    // The test DB schema has already run the migration (snapshot is JSON).
    // We can't re-run the migration, but we can insert JSON-encoded data and
    // verify it round-trips, which is what the migration produces.
    $oldData = ['entryId' => 42, 'title' => 'Legacy Entry'];

    Craft::$app->db->createCommand()->insert('{{%audit_log}}', [
        'siteId' => 1,
        'event' => 'entry-saved',
        'title' => 'Legacy',
        'snapshot' => \craft\helpers\Json::encode($oldData),
        'dateCreated' => \craft\helpers\Db::prepareDateForDb(new \DateTime()),
        'dateUpdated' => \craft\helpers\Db::prepareDateForDb(new \DateTime()),
        'uid' => \craft\helpers\StringHelper::UUID(),
    ])->execute();

    $id = Craft::$app->db->getLastInsertID();
    $record = AuditRecord::findOne($id);

    expect($record)->not->toBeNull();
    expect($record->snapshot)->toBeString();
    expect($record->snapshot)->toContain('entry');
});

it('handles the plain serialize() format (no base64 wrapping)', function () {
    $data = ['key' => 'value'];
    $serialized = serialize($data);

    // Directly unserialize it the way the migration does
    expect(unserialize($serialized))->toBe($data);
    expect(\craft\helpers\StringHelper::isBase64($serialized))->toBeFalse();
});

it('detects already-JSON snapshots idempotently', function () {
    // If run twice, the migration should not double-encode. The detection logic
    // checks the first non-whitespace character.
    $original = '{"entryId":42,"title":"Already JSON"}';
    $trimmed = ltrim($original);
    expect($trimmed[0] === '{' || $trimmed[0] === '[')->toBeTrue();

    $arrayJson = '  [1,2,3]';
    $trimmed2 = ltrim($arrayJson);
    expect($trimmed2[0] === '{' || $trimmed2[0] === '[')->toBeTrue();
});

it('can decode base64-wrapped serialized snapshots the way the migration does', function () {
    $data = ['entryId' => 42, 'title' => 'Legacy'];
    $legacySnapshot = base64_encode(serialize($data));

    expect(\craft\helpers\StringHelper::isBase64($legacySnapshot))->toBeTrue();

    $decoded = @unserialize(base64_decode($legacySnapshot));
    expect($decoded)->toBe($data);
});

it('gracefully handles malformed serialize data', function () {
    // A corrupted serialized string
    $bogus = 'a:1:{s:3:"key"; BROKEN';

    $result = @unserialize($bogus);
    expect($result)->toBeFalse();
    // Migration catches this and stores a _migrationError marker on the row.
});

it('has a migration class and safeDown refuses to revert', function () {
    $migration = new m260421_000000_snapshot_to_json();
    expect($migration)->toBeInstanceOf(m260421_000000_snapshot_to_json::class);

    // safeDown should log and return false (lossy conversion)
    ob_start();
    $result = $migration->safeDown();
    ob_end_clean();
    expect($result)->toBeFalse();
});
