<?php

namespace superbig\audit\services;

use Craft;
use craft\services\Plugins;
use superbig\audit\Audit;
use Throwable;
use yii\base\Component;
use yii\base\Event;

/**
 * Wraps each Feed Me feed import in an Audit batch.
 *
 * When Feed Me is installed, the BEFORE/AFTER feed-process events open and
 * close a {@see BatchService} batch, so all audit rows produced by Feed Me's
 * element saves inherit the batch's parentId via the AuditRecorder auto-attach
 * hook.
 *
 * When Feed Me is NOT installed, this listener registers nothing — no errors,
 * no warnings, no perf impact.
 *
 * Pagination wrinkle: Feed Me's BEFORE and AFTER events may fire in different
 * queue jobs / different PHP processes. We therefore persist the batch ID in
 * Craft's cache (keyed by feed ID) rather than an in-memory property.
 */
class FeedMeListener extends Component
{
    /**
     * Feed Me's Process service FQCN. String literal so PHPStan won't try to
     * resolve the class when Feed Me isn't installed.
     */
    private const FEED_ME_PROCESS_CLASS = 'craft\\feedme\\services\\Process';

    /**
     * Feed Me's FeedProcessEvent FQCN — only used as a string-existence probe.
     */
    private const FEED_ME_EVENT_CLASS = 'craft\\feedme\\events\\FeedProcessEvent';

    /** Matches Process::EVENT_BEFORE_PROCESS_FEED */
    private const EVENT_BEFORE_PROCESS_FEED = 'onBeforeProcessFeed';

    /** Matches Process::EVENT_AFTER_PROCESS_FEED */
    private const EVENT_AFTER_PROCESS_FEED = 'onAfterProcessFeed';

    /** 24-hour cache TTL — far longer than any sane feed import. */
    private const CACHE_TTL = 86400;

    /**
     * Has THIS PROCESS already attached the EVENT_AFTER_LOAD_PLUGINS deferred
     * registration? Static (not per-instance) because Yii's `Event::on` writes
     * to a global static handler registry — registering once per instance is
     * not the same as registering once per process. pest's `InstallsCraft`
     * resets and recreates the plugin instance between tests; without this
     * static guard, each new instance would stack another deferred-registration
     * callback on the Plugins class (and another pair of Process listeners
     * once Feed Me loads). After N tests, a single feed import would open N
     * Audit batches.
     */
    private static bool $bootstrapped = false;

    /**
     * Has THIS PROCESS already registered Process listeners? Same reasoning
     * as $bootstrapped — static, not per-instance, because Yii's handler
     * registry is global.
     */
    private static bool $registered = false;

    /**
     * Defers listener registration until {@see Plugins::EVENT_AFTER_LOAD_PLUGINS}
     * so Feed Me's classes are guaranteed to be available before we probe for them.
     *
     * Idempotent per-process — safe to construct multiple instances; only the
     * first one (per PHP process) attaches the deferred-registration callback.
     */
    public function init(): void
    {
        parent::init();

        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function() {
                if (!$this->feedMeAvailable()) {
                    return;
                }
                $this->register();
            }
        );
    }

    /**
     * Whether Feed Me's Process service and event class are both available.
     */
    public function feedMeAvailable(): bool
    {
        return class_exists(self::FEED_ME_PROCESS_CLASS)
            && class_exists(self::FEED_ME_EVENT_CLASS);
    }

    /**
     * Register the BEFORE/AFTER listeners on Feed Me's Process service class.
     *
     * Idempotent per-process.
     */
    protected function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        Event::on(
            self::FEED_ME_PROCESS_CLASS,
            self::EVENT_BEFORE_PROCESS_FEED,
            function(Event $event) {
                $this->onBeforeProcessFeed($event);
            }
        );

        Event::on(
            self::FEED_ME_PROCESS_CLASS,
            self::EVENT_AFTER_PROCESS_FEED,
            function(Event $event) {
                $this->onAfterProcessFeed($event);
            }
        );
    }

    /**
     * Open a batch when Feed Me starts processing a feed.
     *
     * Reads $event->feed defensively — the listener accepts any event object
     * with a `feed` property exposing `id`, `name`, `elementType`. Errors
     * are caught and logged so we never throw back into Feed Me.
     */
    public function onBeforeProcessFeed(Event $event): void
    {
        try {
            $feed = $event->feed ?? null;
            if ($feed === null || !isset($feed->id)) {
                return;
            }
            $feedId = (int) $feed->id;
            $feedName = (string) ($feed->name ?? 'feed');
            $elementType = (string) ($feed->elementType ?? '');

            $batchId = Audit::$plugin->batch->open(
                title: 'Feed Me: ' . $feedName,
                metadata: [
                    'source' => 'feed-me',
                    'feedId' => $feedId,
                    'feedName' => $feedName,
                    'elementType' => $elementType,
                ],
            );

            // Persist for the AFTER listener — may run in a different PHP
            // process if the feed paginates.
            Craft::$app->getCache()->set($this->cacheKey($feedId), $batchId, self::CACHE_TTL);
        } catch (Throwable $e) {
            Craft::error('Audit: failed to open Feed Me batch — ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Close the batch when Feed Me finishes processing a feed.
     *
     * If no cached batch ID is found, the BEFORE event never fired (or the
     * cache was cleared) — silently no-op.
     */
    public function onAfterProcessFeed(Event $event): void
    {
        try {
            $feed = $event->feed ?? null;
            if ($feed === null || !isset($feed->id)) {
                return;
            }
            $feedId = (int) $feed->id;
            $cacheKey = $this->cacheKey($feedId);
            $batchId = Craft::$app->getCache()->get($cacheKey);
            if (!$batchId) {
                Craft::info(
                    "Audit: onAfterProcessFeed for feed {$feedId} found no cached batch — skipping close",
                    __METHOD__
                );
                return;
            }
            Audit::$plugin->batch->close((int) $batchId);
            Craft::$app->getCache()->delete($cacheKey);
        } catch (Throwable $e) {
            Craft::error('Audit: failed to close Feed Me batch — ' . $e->getMessage(), __METHOD__);
        }
    }

    protected function cacheKey(int $feedId): string
    {
        return 'audit:feedme:' . $feedId;
    }
}
