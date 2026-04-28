<?php

namespace superbig\audit\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\StringHelper;

/**
 * Migrates the {{%audit_log}} table to JSON columns for `snapshot` and
 * `location`, adds `changedFields` (JSON) and `request` (string) columns,
 * and creates composite indexes used by the refactored dashboard queries.
 *
 * Strategy:
 *   1. Add new columns (changedFields, request).
 *   2. Add a shadow `snapshot_new` JSON column, convert every row from the
 *      legacy base64(serialize(...)) / serialize(...) format to JSON, then
 *      drop the old column and rename.
 *   3. Same pattern for `location`.
 *   4. Create composite indexes on (dateCreated, event) and
 *      (userId, dateCreated).
 *
 * Rows that fail to decode are preserved with a `_migrationError` marker so
 * they can be investigated without blocking the migration.
 */
class m260421_000000_snapshot_to_json extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%audit_log}}';
        $db = Craft::$app->db;

        // 1. Add new columns FIRST, JSON type
        if (!$db->columnExists($table, 'changedFields')) {
            $this->addColumn($table, 'changedFields', $this->json()->null());
        }
        if (!$db->columnExists($table, 'request')) {
            $this->addColumn($table, 'request', $this->string(10)->null());
        }

        // 2. Convert snapshot column: create new JSON column, migrate data, swap
        if (!$db->columnExists($table, 'snapshot_new')) {
            $this->addColumn($table, 'snapshot_new', $this->json()->null());
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;
        $total = 0;

        // Process in batches of 200
        foreach ((new Query())
            ->select(['id', 'snapshot'])
            ->from($table)
            ->where(['not', ['snapshot' => null]])
            ->batch(200) as $batch) {
            foreach ($batch as $row) {
                $total++;
                $snapshot = $row['snapshot'];
                if ($snapshot === null || $snapshot === '') {
                    $skipped++;
                    continue;
                }

                // Detect already-JSON (after a partial migration or fresh install)
                $trimmed = ltrim($snapshot);
                if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                    $this->update($table, ['snapshot_new' => $snapshot], ['id' => $row['id']]);
                    $skipped++;
                    continue;
                }

                try {
                    // Old format: either base64(serialize(...)) or serialize(...) directly
                    $data = StringHelper::isBase64($snapshot)
                        ? @unserialize(base64_decode($snapshot))
                        : @unserialize($snapshot);

                    if ($data === false) {
                        throw new \RuntimeException('unserialize returned false');
                    }

                    $this->update($table, [
                        'snapshot_new' => Json::encode($data),
                    ], ['id' => $row['id']]);
                    $migrated++;
                } catch (\Throwable $e) {
                    $this->update($table, [
                        'snapshot_new' => Json::encode([
                            '_migrationError' => true,
                            '_message' => $e->getMessage(),
                            '_rawPreview' => substr((string) $snapshot, 0, 200),
                        ]),
                    ], ['id' => $row['id']]);
                    $failed++;
                }
            }
        }

        Craft::info(
            "Audit snapshot migration: total={$total}, migrated={$migrated}, already_json={$skipped}, failed={$failed}",
            __METHOD__
        );

        // 3. Swap columns: drop old snapshot, rename snapshot_new
        $this->dropColumn($table, 'snapshot');
        $this->renameColumn($table, 'snapshot_new', 'snapshot');

        // 4. Convert location column similarly (simpler — it's already JSON-encoded text in most cases)
        if (!$db->columnExists($table, 'location_new')) {
            $this->addColumn($table, 'location_new', $this->json()->null());
        }
        foreach ((new Query())
            ->select(['id', 'location'])
            ->from($table)
            ->where(['not', ['location' => null]])
            ->batch(500) as $batch) {
            foreach ($batch as $row) {
                $loc = $row['location'];
                if ($loc === null || $loc === '') {
                    continue;
                }

                try {
                    $decoded = Json::decodeIfJson($loc);
                    $this->update($table, [
                        'location_new' => Json::encode(is_array($decoded) ? $decoded : ['raw' => $loc]),
                    ], ['id' => $row['id']]);
                } catch (\Throwable) {
                    $this->update($table, [
                        'location_new' => Json::encode(['raw' => (string) $loc]),
                    ], ['id' => $row['id']]);
                }
            }
        }
        $this->dropColumn($table, 'location');
        $this->renameColumn($table, 'location_new', 'location');

        // 5. Add composite indexes
        $this->createIndex(null, $table, ['dateCreated', 'event'], false);
        $this->createIndex(null, $table, ['userId', 'dateCreated'], false);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m260421_000000_snapshot_to_json cannot be reverted (JSON → serialize lossy).\n";
        return false;
    }
}
