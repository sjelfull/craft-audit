<?php

use craft\elements\Entry;
use craft\queue\jobs\ResaveElements;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;

beforeEach(function () {
    AuditRecord::deleteAll();
});

/**
 * Build a fake ResaveElements job we can pass to ElementHandler.
 *
 * We never run() the job — we only use its instance identity and a couple of
 * public properties (elementType, criteria) that ElementHandler reads.
 */
function makeResaveJob(string $elementType = Entry::class, array $criteria = []): ResaveElements
{
    $job = new ResaveElements();
    $job->elementType = $elementType;
    $job->criteria = $criteria;
    return $job;
}

it('onBeforeResave opens a batch and exposes the batch id via currentBatchId()', function () {
    $handler = Audit::$plugin->elementHandler;
    $batch = Audit::$plugin->batch;

    expect($batch->currentBatchId())->toBeNull();

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $handler->onBeforeResave($job);

    $batchId = $batch->currentBatchId();
    expect($batchId)->toBeInt();
    expect($batchId)->toBeGreaterThan(0);

    $parent = AuditRecord::findOne($batchId);
    expect($parent)->not->toBeNull();
    expect($parent->event)->toBe(AuditEvent::BatchStarted->value);

    // Clean up so the next test starts with an empty stack
    $handler->onResaveEnd($job);
});

it('saveRecord() inside an open resave batch auto-attaches the child to the batch', function () {
    $handler = Audit::$plugin->elementHandler;
    $recorder = Audit::$plugin->auditRecorder;
    $batch = Audit::$plugin->batch;

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $handler->onBeforeResave($job);
    $batchId = $batch->currentBatchId();

    // Write a child row via the legacy getStandardModel() + saveRecord() path.
    // This is the API every handler service still uses, and it MUST inherit
    // parentId from the open batch via the auto-attach hook.
    $model = $recorder->getStandardModel();
    $model->event = AuditEvent::EntrySaved->value;
    $model->title = 'Child entry';
    $recorder->saveRecord($model);

    expect($model->id)->not->toBeNull();
    $childRow = AuditRecord::findOne($model->id);
    expect((int) $childRow->parentId)->toBe($batchId);

    $handler->onResaveEnd($job);
});

it('onResaveEnd closes the batch — parent row is marked completed with childCount', function () {
    $handler = Audit::$plugin->elementHandler;
    $recorder = Audit::$plugin->auditRecorder;
    $batch = Audit::$plugin->batch;

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $handler->onBeforeResave($job);
    $batchId = $batch->currentBatchId();

    // Write two children so childCount has something interesting to report.
    foreach (['one', 'two'] as $title) {
        $model = $recorder->getStandardModel();
        $model->event = AuditEvent::EntrySaved->value;
        $model->title = $title;
        $recorder->saveRecord($model);
    }

    $handler->onResaveEnd($job);

    expect($batch->currentBatchId())->toBeNull();

    $parent = AuditRecord::findOne($batchId);
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);

    $snapshot = audit_snapshot($parent);
    expect($snapshot['state'])->toBe('completed');
    expect($snapshot['childCount'])->toBe(2);
});

it('onResaveEnd(failed: true) closes the batch with state=failed', function () {
    $handler = Audit::$plugin->elementHandler;
    $batch = Audit::$plugin->batch;

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $handler->onBeforeResave($job);
    $batchId = $batch->currentBatchId();

    $handler->onResaveEnd($job, failed: true);

    $parent = AuditRecord::findOne($batchId);
    expect($parent->event)->toBe(AuditEvent::BatchFailed->value);

    $snapshot = audit_snapshot($parent);
    expect($snapshot['state'])->toBe('failed');
});

it('concurrent resave jobs of the same element type do not collide (FRE-140 regression)', function () {
    $handler = Audit::$plugin->elementHandler;
    $recorder = Audit::$plugin->auditRecorder;
    $batch = Audit::$plugin->batch;

    // Two distinct ResaveElements instances targeting the same element type.
    // Under the legacy cache-key pattern, both would share the key
    // 'auditResaveId:craft\elements\Entry' and the second job's open()
    // would overwrite the first job's parent id in cache. Children written
    // after that overwrite would attach to the wrong parent.
    $jobA = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $jobB = makeResaveJob(Entry::class, ['sectionId' => 2]);

    $handler->onBeforeResave($jobA);
    $batchA = $batch->currentBatchId();

    // Interleave: open B while A is still open, write children under each,
    // and close in LIFO order. This exercises both per-instance isolation
    // AND the BatchService stack semantics.
    $handler->onBeforeResave($jobB);
    $batchB = $batch->currentBatchId();

    expect($batchA)->not->toBe($batchB);

    // While B is the innermost open batch, a child written via saveRecord()
    // should attach to B (not A).
    $childB = $recorder->getStandardModel();
    $childB->event = AuditEvent::EntrySaved->value;
    $childB->title = 'belongs-to-B';
    $recorder->saveRecord($childB);

    // Close B — A is now the innermost open batch.
    $handler->onResaveEnd($jobB);
    expect($batch->currentBatchId())->toBe($batchA);

    // A child written now should attach to A.
    $childA = $recorder->getStandardModel();
    $childA->event = AuditEvent::EntrySaved->value;
    $childA->title = 'belongs-to-A';
    $recorder->saveRecord($childA);

    $handler->onResaveEnd($jobA);
    expect($batch->currentBatchId())->toBeNull();

    // Verify the children attached to the correct parents — the heart of
    // FRE-140. Under the bug, both children would have ended up under the
    // same (wrong) parent.
    $childARow = AuditRecord::findOne($childA->id);
    $childBRow = AuditRecord::findOne($childB->id);
    expect((int) $childARow->parentId)->toBe($batchA);
    expect((int) $childBRow->parentId)->toBe($batchB);

    // And both parent rows closed cleanly with childCount=1 each.
    $parentA = AuditRecord::findOne($batchA);
    $parentB = AuditRecord::findOne($batchB);
    expect($parentA->event)->toBe(AuditEvent::BatchCompleted->value);
    expect($parentB->event)->toBe(AuditEvent::BatchCompleted->value);

    // A's children: B's batch parent row (nested) + childA = 2.
    // B's children: just childB = 1.
    // The important thing for FRE-140 is that childA and childB attached to
    // the *correct* parent above; the childCount values are a bonus check on
    // nested-batch accounting.
    expect(audit_snapshot($parentA)['childCount'])->toBe(2);
    expect(audit_snapshot($parentB)['childCount'])->toBe(1);
});

it('onResaveEnd is idempotent — calling it twice is a safe no-op', function () {
    $handler = Audit::$plugin->elementHandler;
    $batch = Audit::$plugin->batch;

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);
    $handler->onBeforeResave($job);
    $batchId = $batch->currentBatchId();

    $handler->onResaveEnd($job);
    $parentAfterFirst = AuditRecord::findOne($batchId);
    $snapshotAfterFirst = $parentAfterFirst->snapshot;
    $eventAfterFirst = $parentAfterFirst->event;

    // Second close — the lookup table no longer has the entry, so this
    // returns true without ever calling BatchService::close(). No exception.
    $result = $handler->onResaveEnd($job);
    expect($result)->toBeTrue();

    $parentAfterSecond = AuditRecord::findOne($batchId);
    expect($parentAfterSecond->event)->toBe($eventAfterFirst);
    expect($parentAfterSecond->snapshot)->toBe($snapshotAfterFirst);
});

it('onResaveEnd without a prior onBeforeResave is a safe no-op', function () {
    $handler = Audit::$plugin->elementHandler;
    $batch = Audit::$plugin->batch;

    $job = makeResaveJob(Entry::class, ['sectionId' => 1]);

    // No onBeforeResave call. Closing should return cleanly without
    // touching the batch stack or raising an exception.
    $result = $handler->onResaveEnd($job);

    expect($result)->toBeTrue();
    expect($batch->currentBatchId())->toBeNull();

    // No audit records should have been written.
    expect((int) AuditRecord::find()->count())->toBe(0);
});
