<?php

use superbig\audit\Audit;
use superbig\audit\enums\AuditEvent;
use superbig\audit\records\AuditRecord;
use superbig\audit\services\FeedMeListener;
use yii\base\Event;

/**
 * Unit tests for the FeedMeListener.
 *
 * These exercise the listener's public API directly with synthetic events,
 * verifying the open/close/cache logic in isolation from Feed Me itself.
 * The companion {@see \Tests\Feature\FeedMeIntegrationTest} drives real
 * Feed Me classes end-to-end to prove the Yii class-string event dispatch
 * actually reaches our handler.
 *
 * Stub for Feed Me's FeedProcessEvent — yii\base\Event uses BaseObject which
 * rejects unknown property assignment, so we declare $feed explicitly. The
 * listener accepts any yii\base\Event subclass and reads $event->feed
 * defensively.
 */
class FeedProcessEventStub extends Event
{
    public ?object $feed = null;
    public array $feedData = [];
}

beforeEach(function () {
    AuditRecord::deleteAll();

    // PHPUnit 10+ converts E_WARNING into test warnings even when the source
    // suppresses with the @-operator. Yii's FileCache::getValue() calls
    // @filemtime() against cache files that may have been removed by GC, and
    // those produce stat-failed warnings that surface as test warnings even
    // though FileCache treats them as cache misses (which is the correct
    // behavior). Filter just those out — anything else still propagates.
    set_error_handler(function (int $errno, string $errstr, string $errfile): bool {
        if (
            $errno === E_WARNING
            && str_contains($errstr, 'filemtime')
            && str_contains($errfile, 'FileCache.php')
        ) {
            return true; // suppress
        }
        return false; // let PHPUnit handle anything else
    }, E_WARNING);
});

afterEach(function () {
    restore_error_handler();
});

/**
 * Build a synthetic event whose $feed property mimics Feed Me's FeedModel.
 *
 * Feed Me's FeedProcessEvent extends yii\base\Event and adds public $feed
 * (a FeedModel instance). Our listener only reads $event->feed defensively
 * via $event->feed ?? null, so any object with the right shape works.
 */
function makeFeedEvent(?int $feedId, string $name = 'Test Feed', string $elementType = 'craft\elements\Entry'): Event
{
    $feed = new \stdClass();
    if ($feedId !== null) {
        $feed->id = $feedId;
    }
    $feed->name = $name;
    $feed->elementType = $elementType;
    $feed->siteId = 1;

    $event = new FeedProcessEventStub();
    $event->feed = $feed;
    return $event;
}

it('feedMeAvailable() reflects whether the Feed Me Process class is loadable', function () {
    $listener = new FeedMeListener();
    // In require-dev Feed Me is installed, so this is true. The negative
    // path — Feed Me absent — is impossible to assert without uninstalling
    // composer deps mid-suite, so it lives in static analysis and the
    // class_exists() probe in the listener source itself.
    expect($listener->feedMeAvailable())->toBe(class_exists('craft\\feedme\\services\\Process'));
});

it('init() does not throw regardless of Feed Me availability', function () {
    $listener = new FeedMeListener();
    $listener->init();
    expect(true)->toBeTrue(); // reached without exception
});

it('opens a batch when onBeforeProcessFeed is called', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 42, name: 'Products');

    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    $listener->onBeforeProcessFeed($event);
    expect(Audit::$plugin->batch->currentBatchId())->not->toBeNull();

    $batchId = Audit::$plugin->batch->currentBatchId();
    $parent = AuditRecord::findOne($batchId);
    expect($parent)->not->toBeNull();
    expect($parent->title)->toBe('Feed Me: Products');
    expect($parent->event)->toBe(AuditEvent::BatchStarted->value);

    $snapshot = json_decode($parent->snapshot, true);
    expect($snapshot['metadata']['source'])->toBe('feed-me');
    expect($snapshot['metadata']['feedId'])->toBe(42);
    expect($snapshot['metadata']['elementType'])->toBe('craft\elements\Entry');

    // Clean up.
    Audit::$plugin->batch->close($batchId);
});

it('writes the batch ID to the cache under audit:feedme:<feedId>', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 99);
    $listener->onBeforeProcessFeed($event);

    $cached = Craft::$app->getCache()->get('audit:feedme:99');
    expect($cached)->not->toBeFalse();
    expect((int) $cached)->toBe(Audit::$plugin->batch->currentBatchId());

    Audit::$plugin->batch->close((int) $cached);
});

it('closes the batch when onAfterProcessFeed is called', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 7);

    $listener->onBeforeProcessFeed($event);
    $batchId = Audit::$plugin->batch->currentBatchId();
    expect($batchId)->not->toBeNull();

    $listener->onAfterProcessFeed($event);

    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    expect(Craft::$app->getCache()->get('audit:feedme:7'))->toBeFalse();

    $parent = AuditRecord::findOne($batchId);
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);
});

it('audit rows written between Before and After inherit the batch parentId', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 11);

    $listener->onBeforeProcessFeed($event);
    $batchId = Audit::$plugin->batch->currentBatchId();

    $child = Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Imported entry',
    );

    $listener->onAfterProcessFeed($event);

    expect((int) $child->parentId)->toBe($batchId);
});

it('onAfterProcessFeed without a paired Before is a silent no-op', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 404);

    // No Before — directly call After. Should not throw, no batch closed.
    $listener->onAfterProcessFeed($event);

    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    expect((int) AuditRecord::find()->count())->toBe(0);
});

it('onBeforeProcessFeed handles a missing feed.id gracefully', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: null);  // no id property

    $listener->onBeforeProcessFeed($event);

    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    expect((int) AuditRecord::find()->count())->toBe(0);
});

it('onBeforeProcessFeed handles a missing feed property gracefully', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = new FeedProcessEventStub();  // $feed is null

    $listener->onBeforeProcessFeed($event);

    expect(Audit::$plugin->batch->currentBatchId())->toBeNull();
    expect((int) AuditRecord::find()->count())->toBe(0);
});

it('survives a feed close mid-batch via the cache (pagination case)', function () {
    $listener = Audit::$plugin->feedMeListener;
    $event = makeFeedEvent(feedId: 55);

    $listener->onBeforeProcessFeed($event);
    $batchId = Audit::$plugin->batch->currentBatchId();

    // Write a child while the batch is open.
    Audit::$plugin->auditRecorder->record(
        event: AuditEvent::EntrySaved,
        title: 'Imported during pagination page 1',
    );

    // Cache should carry the batch ID, ready for a separate-process After.
    $cachedBatchId = Craft::$app->getCache()->get('audit:feedme:55');
    expect((int) $cachedBatchId)->toBe($batchId);

    $listener->onAfterProcessFeed($event);

    expect(Craft::$app->getCache()->get('audit:feedme:55'))->toBeFalse();

    $parent = AuditRecord::findOne($batchId);
    expect($parent->event)->toBe(AuditEvent::BatchCompleted->value);

    $snapshot = json_decode($parent->snapshot, true);
    expect($snapshot['childCount'])->toBe(1);
});
