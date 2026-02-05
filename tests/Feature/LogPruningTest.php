<?php

use superbig\audit\Audit;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('prunes old logs', function () {
    // Create an old record (older than default 30 days)
    $oldRecord = new AuditRecord();
    $oldRecord->event = 'test-event';
    $oldRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $oldRecord->save();

    // Manually set dateCreated to 60 days ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-60 days')->format('Y-m-d H:i:s'),
        ], ['id' => $oldRecord->id])
        ->execute();

    // Run prune
    $count = Audit::$plugin->auditService->pruneLogs();

    expect($count)->toBeGreaterThanOrEqual(1);

    // Verify the old record was deleted
    $record = AuditRecord::findOne($oldRecord->id);
    expect($record)->toBeNull();
});

it('keeps recent logs when pruning', function () {
    // Create a recent record
    $recentRecord = new AuditRecord();
    $recentRecord->event = 'test-event';
    $recentRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $recentRecord->save();

    // Run prune
    Audit::$plugin->auditService->pruneLogs();

    // Verify the recent record still exists
    $record = AuditRecord::findOne($recentRecord->id);
    expect($record)->not->toBeNull();
});

it('respects pruneDays setting', function () {
    // Set a shorter prune period
    Audit::$plugin->getSettings()->pruneDays = 7;

    // Create a record 10 days old
    $oldRecord = new AuditRecord();
    $oldRecord->event = 'test-event';
    $oldRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $oldRecord->save();

    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-10 days')->format('Y-m-d H:i:s'),
        ], ['id' => $oldRecord->id])
        ->execute();

    // Run prune with 7-day setting
    $count = Audit::$plugin->auditService->pruneLogs();

    expect($count)->toBeGreaterThanOrEqual(1);

    // Reset to default
    Audit::$plugin->getSettings()->pruneDays = 30;
});
