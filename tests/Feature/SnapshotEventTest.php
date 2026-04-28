<?php

use markhuot\craftpest\factories\Entry;
use superbig\audit\Audit;
use superbig\audit\events\SnapshotEvent;
use superbig\audit\models\AuditModel;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\AuditRecorder;

beforeEach(function () {
    AuditRecord::deleteAll();
});

it('allows snapshot modification via EVENT_SNAPSHOT', function () {
    // Register an event handler to modify the snapshot
    $customData = 'custom-test-value-' . uniqid();

    \yii\base\Event::on(
        AuditRecorder::class,
        AuditRecorder::EVENT_SNAPSHOT,
        function (SnapshotEvent $event) use ($customData) {
            $event->snapshot['customField'] = $customData;
        }
    );

    // Create an entry to trigger the snapshot event
    $entry = Entry::factory()->create();

    $record = AuditRecord::find()
        ->where(['elementId' => $entry->id])
        ->one();

    expect($record)->not->toBeNull();

    $model = AuditModel::createFromRecord($record);

    expect($model->snapshot)->toHaveKey('customField');
    expect($model->snapshot['customField'])->toBe($customData);

    // Clean up the event handler
    \yii\base\Event::off(
        AuditRecorder::class,
        AuditRecorder::EVENT_SNAPSHOT
    );
});
