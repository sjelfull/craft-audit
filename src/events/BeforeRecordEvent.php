<?php

namespace superbig\audit\events;

use craft\events\CancelableEvent;
use superbig\audit\models\AuditModel;

/**
 * Event triggered before an audit log record is persisted.
 *
 * Handlers may:
 *  - Mutate `$model` (e.g., add data to the snapshot, change the title)
 *  - Set `$isValid = false` to prevent the record from being written
 *
 * @since 4.0.0
 */
class BeforeRecordEvent extends CancelableEvent
{
    public AuditModel $model;
}
