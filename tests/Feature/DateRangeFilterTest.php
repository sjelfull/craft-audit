<?php

use superbig\audit\records\AuditRecord;

beforeEach(function () {
    // Clear audit logs before each test
    AuditRecord::deleteAll();
});

it('filters logs by start date', function () {
    // Create records with different dates
    $oldRecord = new AuditRecord();
    $oldRecord->event = 'old-event';
    $oldRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $oldRecord->save();
    
    $recentRecord = new AuditRecord();
    $recentRecord->event = 'recent-event';
    $recentRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $recentRecord->save();

    // Set old record to 5 days ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s'),
        ], ['id' => $oldRecord->id])
        ->execute();

    // Set recent record to 1 day ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-1 days')->format('Y-m-d H:i:s'),
        ], ['id' => $recentRecord->id])
        ->execute();

    // Query with start date filter (3 days ago)
    $startDate = (new \DateTime())->modify('-3 days')->format('Y-m-d');
    $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
    $startDateTime->setTime(0, 0, 0);

    $records = AuditRecord::find()
        ->where(['parentId' => null])
        ->andWhere(['>=', 'dateCreated', $startDateTime->format('Y-m-d H:i:s')])
        ->all();

    // Should only return the recent record
    expect(count($records))->toBe(1);
    expect($records[0]->event)->toBe('recent-event');
});

it('filters logs by end date', function () {
    // Create records with different dates
    $oldRecord = new AuditRecord();
    $oldRecord->event = 'old-event';
    $oldRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $oldRecord->save();
    
    $recentRecord = new AuditRecord();
    $recentRecord->event = 'recent-event';
    $recentRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $recentRecord->save();

    // Set old record to 5 days ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s'),
        ], ['id' => $oldRecord->id])
        ->execute();

    // Set recent record to 1 day ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-1 days')->format('Y-m-d H:i:s'),
        ], ['id' => $recentRecord->id])
        ->execute();

    // Query with end date filter (3 days ago)
    $endDate = (new \DateTime())->modify('-3 days')->format('Y-m-d');
    $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
    $endDateTime->setTime(23, 59, 59);

    $records = AuditRecord::find()
        ->where(['parentId' => null])
        ->andWhere(['<=', 'dateCreated', $endDateTime->format('Y-m-d H:i:s')])
        ->all();

    // Should only return the old record
    expect(count($records))->toBe(1);
    expect($records[0]->event)->toBe('old-event');
});

it('filters logs by date range', function () {
    // Create records with different dates
    $veryOldRecord = new AuditRecord();
    $veryOldRecord->event = 'very-old-event';
    $veryOldRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $veryOldRecord->save();

    $midRecord = new AuditRecord();
    $midRecord->event = 'mid-event';
    $midRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $midRecord->save();
    
    $recentRecord = new AuditRecord();
    $recentRecord->event = 'recent-event';
    $recentRecord->siteId = \Craft::$app->getSites()->getPrimarySite()->id;
    $recentRecord->save();

    // Set very old record to 10 days ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-10 days')->format('Y-m-d H:i:s'),
        ], ['id' => $veryOldRecord->id])
        ->execute();

    // Set mid record to 5 days ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s'),
        ], ['id' => $midRecord->id])
        ->execute();

    // Set recent record to 1 day ago
    \Craft::$app->db->createCommand()
        ->update(AuditRecord::tableName(), [
            'dateCreated' => (new \DateTime())->modify('-1 days')->format('Y-m-d H:i:s'),
        ], ['id' => $recentRecord->id])
        ->execute();

    // Query with date range filter (7 days ago to 3 days ago)
    $startDate = (new \DateTime())->modify('-7 days')->format('Y-m-d');
    $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
    $startDateTime->setTime(0, 0, 0);

    $endDate = (new \DateTime())->modify('-3 days')->format('Y-m-d');
    $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
    $endDateTime->setTime(23, 59, 59);

    $records = AuditRecord::find()
        ->where(['parentId' => null])
        ->andWhere(['>=', 'dateCreated', $startDateTime->format('Y-m-d H:i:s')])
        ->andWhere(['<=', 'dateCreated', $endDateTime->format('Y-m-d H:i:s')])
        ->all();

    // Should only return the mid record
    expect(count($records))->toBe(1);
    expect($records[0]->event)->toBe('mid-event');
});
