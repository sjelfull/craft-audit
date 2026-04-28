<?php

namespace superbig\audit\events;

use superbig\audit\models\AuditModel;
use yii\base\Event;

class SnapshotEvent extends Event
{
    /**
     * @var AuditModel The Audit model
     */
    public $audit;

    /**
     * @var array Snapshot
     */
    public $snapshot;
}
