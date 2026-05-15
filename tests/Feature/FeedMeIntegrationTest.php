<?php

use craft\feedme\events\FeedProcessEvent;
use craft\feedme\models\FeedModel;
use craft\feedme\Plugin as FeedMePlugin;
use craft\feedme\services\Process;
use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use yii\base\Event;

/**
 * Integration tests for Feed Me + Audit batch wiring.
 *
 * Unlike the unit suite in {@see \Tests\Unit\FeedMeListenerTest}, these
 * tests load Feed Me's real classes and dispatch events through Yii the
 * way Feed Me actually does at runtime. They prove three contracts the
 * unit tests can't:
 *
 *  1. `Plugins::EVENT_AFTER_LOAD_PLUGINS` fires in time for our
 *     `class_exists()` probe to find Feed Me.
 *  2. Yii's class-string event dispatch
 *     (`Event::on('craft\\feedme\\services\\Process', ...)`) reaches our
 *     handler when Feed Me's Process service triggers
 *     `EVENT_BEFORE_PROCESS_FEED` / `EVENT_AFTER_PROCESS_FEED`.
 *  3. Audit rows written between Before and After inherit the batch's
 *     `parentId` via the AuditRecorder auto-attach hook.
 *  4. A real CSV import (the final test below) drives Feed Me's
 *     `FeedImport` queue job end-to-end: parses the file, creates
 *     entries, fires real Process events, and verifies our listener
 *     produces correctly linked batch + child audit rows.
 */

beforeEach(function () {
    if (!class_exists(FeedMePlugin::class)) {
        $this->markTestSkipped('Feed Me not installed — integration tests require craftcms/feed-me in require-dev.');
    }

    AuditRecord::deleteAll();

    // Same filemtime warning suppression as the unit tests — Yii FileCache
    // and PHPUnit 10 disagree about @-operator behavior.
    set_error_handler(function (int $errno, string $errstr, string $errfile): bool {
        if (
            $errno === E_WARNING
            && str_contains($errstr, 'filemtime')
            && str_contains($errfile, 'FileCache.php')
        ) {
            return true;
        }
        return false;
    }, E_WARNING);
});

afterEach(function () {
    restore_error_handler();
});

/**
 * Build a real Feed Me FeedModel — minimal but well-formed. Persisting it
 * via the Feeds service isn't required for the event-dispatch tests below
 * since we trigger events directly; the model just needs valid id+name+
 * elementType so our listener's defensive reads succeed.
 */
function makeRealFeedModel(int $id, string $name = 'Audit Integration Test Feed'): FeedModel
{
    $feed = new FeedModel();
    $feed->id = $id;
    $feed->name = $name;
    $feed->elementType = \craft\elements\Entry::class;
    $feed->siteId = 1;
    $feed->feedType = 'CSV';
    $feed->duplicateHandle = ['add'];
    $feed->passkey = 'integration-test-passkey';
    $feed->fieldMapping = [];
    $feed->fieldUnique = [];

    return $feed;
}

it('loads Feed Me classes after Plugins::EVENT_AFTER_LOAD_PLUGINS fires', function () {
    // Contract 1: deferred-registration probe. The listener's
    // feedMeAvailable() check runs after Plugins::EVENT_AFTER_LOAD_PLUGINS
    // by design; by the time any test runs, plugin loading has completed
    // (InstallsCraft forces a plugins->loadPlugins() during install).
    expect(class_exists(Process::class))->toBeTrue();
    expect(class_exists(FeedProcessEvent::class))->toBeTrue();
    expect(Audit::$plugin->feedMeListener->feedMeAvailable())->toBeTrue();
});

it('verifies Process exposes the event constants the listener targets', function () {
    // Sanity check against Feed Me drift: if either constant's string
    // value ever changes upstream, the listener's hardcoded
    // 'onBeforeProcessFeed' / 'onAfterProcessFeed' strings stop matching
    // and this test catches it before users notice.
    expect(Process::EVENT_BEFORE_PROCESS_FEED)->toBe('onBeforeProcessFeed');
    expect(Process::EVENT_AFTER_PROCESS_FEED)->toBe('onAfterProcessFeed');
});

it(
    'opens an Audit batch when Feed Me Process triggers EVENT_BEFORE_PROCESS_FEED via class-string dispatch',
    function () {
        // Contract 2: this is the integration test that the unit suite
        // can't run. We use Yii's class-string Event::trigger() exactly
        // the way Feed Me's FeedImport job does at runtime
        // (vendor/craftcms/feed-me/src/queue/jobs/FeedImport.php:168).
        // If our listener's Event::on(Process::class, ...) wiring is
        // wrong, the handler never fires and the batch never opens.

        $feed = makeRealFeedModel(id: 1001, name: 'Integration Products Feed');
        $event = new FeedProcessEvent([
            'feed' => $feed,
            'feedData' => [],
        ]);

        expect(Audit::$plugin->batch->currentBatchId())->toBeNull();

        // Yii class-level event dispatch matches on the class hierarchy
        // of the triggering instance, so any Process instance works.
        // In production Feed Me's queue job uses
        // `Plugin::$plugin->process` (a singleton component), but the
        // wiring path is identical — what's being tested here is whether
        // `Event::on(Process::class, ...)` in our listener catches
        // `Process::trigger(EVENT_BEFORE_PROCESS_FEED, ...)` regardless
        // of which instance fires it.
        $processService = new Process();
        $processService->trigger(Process::EVENT_BEFORE_PROCESS_FEED, $event);

        // If our listener received the event, a batch is now open.
        $batchId = Audit::$plugin->batch->currentBatchId();
        expect($batchId)->not->toBeNull();

        $parent = AuditRecord::findOne($batchId);
        expect($parent)->not->toBeNull();
        expect($parent->title)->toBe('Feed Me: Integration Products Feed');
        expect($parent->event)->toBe(AuditEvent::BatchStarted->value);

        // Snapshot carries the metadata our handler put there.
        $snapshot = audit_snapshot($parent);
        expect($snapshot['metadata']['source'])->toBe('feed-me');
        expect($snapshot['metadata']['feedId'])->toBe(1001);

        // Clean up so afterEach doesn't see leaked state.
        Audit::$plugin->batch->close($batchId);
    }
);

it('closes the Audit batch and auto-attaches children when Feed Me triggers EVENT_AFTER_PROCESS_FEED', function () {
    // Contract 3: end-to-end auto-attach + close. Trigger BEFORE, write
    // a child the way an entry save would, then trigger AFTER and prove
    // both the close fires and the child's parentId points at the batch.

    $feed = makeRealFeedModel(id: 1002);
    $beforeEvent = new FeedProcessEvent(['feed' => $feed, 'feedData' => []]);
    $afterEvent = new FeedProcessEvent(['feed' => $feed]);

    $processService = new Process();

    // BEFORE — opens batch
    $processService->trigger(Process::EVENT_BEFORE_PROCESS_FEED, $beforeEvent);
    $batchId = Audit::$plugin->batch->currentBatchId();
    expect($batchId)->not->toBeNull();

    // Simulate Feed Me saving an element while the batch is open.
    // In real life this happens via Element::saveElement, which fires
    // EVENT_AFTER_SAVE and audit records it. We exercise the recorder
    // directly because Feed Me's full processFeed() call requires
    // sections/entry types we don't provision in the test fixture.
    $child = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Imported via Feed Me integration test',
    );
    expect((int) $child->parentId)->toBe($batchId);

    // AFTER — closes batch
    $processService->trigger(Process::EVENT_AFTER_PROCESS_FEED, $afterEvent);
    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();

    // Parent row reflects the close and child count.
    $parent = AuditRecord::findOne($batchId);
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);
    $snapshot = audit_snapshot($parent);
    expect($snapshot['childCount'])->toBe(1);
});

it('handles two sequential feed imports independently without batch bleed', function () {
    // Pagination/concurrency wrinkle: when two feeds run back-to-back the
    // listener stores their batch IDs under distinct cache keys
    // (audit:feedme:<feedId>). Verify that finishing feed A doesn't close
    // feed B's batch.

    $processService = new Process();
    $feedA = makeRealFeedModel(id: 2001, name: 'Feed A');
    $feedB = makeRealFeedModel(id: 2002, name: 'Feed B');

    $processService->trigger(Process::EVENT_BEFORE_PROCESS_FEED, new FeedProcessEvent(['feed' => $feedA]));
    $batchA = Audit::$plugin->batch->currentBatchId();

    $processService->trigger(Process::EVENT_BEFORE_PROCESS_FEED, new FeedProcessEvent(['feed' => $feedB]));
    $batchB = Audit::$plugin->batch->currentBatchId();

    expect($batchA)->not->toBe($batchB);

    // Close A — B should still be open with its own batch ID accessible
    // via the cache.
    $processService->trigger(Process::EVENT_AFTER_PROCESS_FEED, new FeedProcessEvent(['feed' => $feedA]));
    expect(Craft::$app->getCache()->get('audit:feedme:2001'))->toBeFalse();
    expect((int) Craft::$app->getCache()->get('audit:feedme:2002'))->toBe($batchB);

    // Clean up B.
    $processService->trigger(Process::EVENT_AFTER_PROCESS_FEED, new FeedProcessEvent(['feed' => $feedB]));
});

it('completes a real CSV import end-to-end: entries created, batch opened, children attached, batch closed', function () {
    // This is the full end-to-end integration test. We:
    //   1. Write a 3-row CSV to disk
    //   2. Configure a real FeedModel pointing at it
    //   3. Persist the feed via Feeds::saveFeed()
    //   4. Drive Feed Me's FeedImport queue job (the same code path
    //      production uses) end-to-end
    //   5. Assert: 3 entries created, 1 batch parent row, 3 child audit
    //      rows attached to it, batch state = completed

    // --- 1. Resolve the news section + entry type (from project.yaml stubs) ---
    $section = Craft::$app->getEntries()->getSectionByHandle('news');
    expect($section)->not->toBeNull();

    $entryType = $section->getEntryTypes()[0] ?? null;
    expect($entryType)->not->toBeNull();

    // --- 2. Write the CSV fixture ---
    $csvPath = sys_get_temp_dir() . '/audit-feedme-e2e-' . uniqid() . '.csv';
    file_put_contents($csvPath, "title,slug\nFeed Me Test Entry A,feed-me-test-a\nFeed Me Test Entry B,feed-me-test-b\nFeed Me Test Entry C,feed-me-test-c\n");

    // --- 3. Build + save the feed ---
    $feed = new FeedModel();
    $feed->name = 'Audit E2E Integration Feed';
    // Use plain filesystem path: Feed Me's getRawData calls realpath() which
    // returns false for file:// URLs even when file_exists() returns true.
    $feed->feedUrl = $csvPath;
    $feed->feedType = 'csv'; // DataTypes service registers handles via strtolower(displayName())
    $feed->elementType = \craft\elements\Entry::class;
    $feed->siteId = 1;
    $feed->duplicateHandle = ['add'];
    $feed->passkey = 'audit-e2e-test';
    $feed->elementGroup = [
        \craft\elements\Entry::class => [
            'section' => $section->id,
            'entryType' => $entryType->id,
        ],
    ];
    $feed->fieldMapping = [
        'title' => ['node' => 'title', 'attribute' => true],
        'slug' => ['node' => 'slug', 'attribute' => true],
    ];
    $feed->fieldUnique = ['title' => 1];
    $feed->backup = false;
    $feed->setEmptyValues = false;
    $feed->updateSearchIndexes = false;

    // Install Feed Me if not already — installPlugin is a no-op if installed.
    if (FeedMePlugin::getInstance() === null) {
        Craft::$app->getPlugins()->installPlugin('feed-me');
    }

    $saved = FeedMePlugin::getInstance()->feeds->saveFeed($feed);
    expect($saved)->toBeTrue();
    expect($feed->id)->not->toBeNull();

    // Reload from DB so fieldMapping / fieldUnique are decoded back to arrays.
    // saveFeed() leaves the in-memory object with the JSON-encoded strings it
    // wrote to the row, which breaks Process::getFeedSettings()'s hash filters.
    $feed = FeedMePlugin::getInstance()->feeds->getFeedById($feed->id);

    // --- 4. Drive the FeedImport queue job synchronously ---
    // continueOnError = false so any per-item failure surfaces as an
    // exception instead of being swallowed by Feed Me's logging path.
    $job = new \craft\feedme\queue\jobs\FeedImport([
        'feed' => $feed,
        'continueOnError' => false,
    ]);
    $queue = Craft::$app->getQueue();

    // Use status(null) — Feed Me creates entries with status 'pending' (no
    // postDate yet) and the default Entry query excludes anything other
    // than 'live'.
    $entriesBefore = (int) \craft\elements\Entry::find()->sectionId($section->id)->status(null)->count();
    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();

    $job->execute($queue);

    // --- 5. Assertions ---
    $entriesAfter = (int) \craft\elements\Entry::find()->sectionId($section->id)->status(null)->count();
    expect($entriesAfter - $entriesBefore)->toBe(3);

    // Batch lifecycle should have completed
    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();

    // Find the batch parent row. Now that snapshot is stored as native JSON,
    // we can iterate and match in PHP via the helper — clean and portable.
    $batchParent = null;
    $snapshot = null;
    foreach (AuditRecord::find()->where(['event' => AuditEvent::BatchCompleted->value])->all() as $candidate) {
        $decoded = audit_snapshot($candidate);
        if (is_array($decoded) && ($decoded['metadata']['feedId'] ?? null) === $feed->id) {
            $batchParent = $candidate;
            $snapshot = $decoded;
            break;
        }
    }
    expect($batchParent)->not->toBeNull();
    expect($snapshot['metadata']['source'])->toBe('feed-me');
    expect($snapshot['metadata']['feedId'])->toBe($feed->id);
    expect($snapshot['childCount'])->toBeGreaterThanOrEqual(3);

    // The 3 entry-created rows are attached to the batch parent
    $children = AuditRecord::find()
        ->where(['parentId' => $batchParent->id])
        ->all();
    expect(count($children))->toBeGreaterThanOrEqual(3);

    // Cache should be cleared after onAfterProcessFeed
    expect(Craft::$app->getCache()->get('audit:feedme:' . $feed->id))->toBeFalse();

    // --- Cleanup ---
    @unlink($csvPath);
    foreach ([
        \craft\elements\Entry::find()->sectionId($section->id)->status(null)->slug('feed-me-test-a')->one(),
        \craft\elements\Entry::find()->sectionId($section->id)->status(null)->slug('feed-me-test-b')->one(),
        \craft\elements\Entry::find()->sectionId($section->id)->status(null)->slug('feed-me-test-c')->one(),
    ] as $e) {
        if ($e) {
            Craft::$app->getElements()->deleteElement($e, true);
        }
    }
    FeedMePlugin::getInstance()->feeds->deleteFeedById($feed->id);
});
