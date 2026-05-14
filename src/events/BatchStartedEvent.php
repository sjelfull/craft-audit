<?php

namespace superbig\audit\events;

use yii\base\Event;

/**
 * Event triggered when a batch is opened via {@see \superbig\audit\services\BatchService::open()}.
 *
 * Listeners receive the batch's audit row ID, the human-readable title,
 * and the metadata array supplied to open().
 *
 * This event is observational and cannot cancel the batch.
 */
class BatchStartedEvent extends Event
{
    public int $batchId;
    public string $title;
    public array $metadata = [];
}
