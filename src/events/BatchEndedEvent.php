<?php

namespace superbig\audit\events;

use yii\base\Event;

/**
 * Event triggered when a batch is closed via {@see \superbig\audit\services\BatchService::close()}.
 *
 * Fires for both successful and failed batches — distinguish via the `state`
 * property ('completed' or 'failed').
 *
 * This event is observational. Use it to react to batch completion, e.g.,
 * emit a notification or roll up child counts into a dashboard.
 */
class BatchEndedEvent extends Event
{
    public int $batchId;
    public string $title;
    /** @var string 'completed' or 'failed' */
    public string $state;
    public ?int $durationMs = null;
    public int $childCount = 0;
    public array $summary = [];
}
