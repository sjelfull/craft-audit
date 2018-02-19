<?php
/**
 * Audit plugin for Craft CMS 3.x
 *
 * Log adding/updating/deleting of elements
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2017 Superbig
 */

namespace superbig\audit\events;

use yii\base\Event;

/**
 * @author    Superbig
 * @package   Audit
 * @since     1.0.0
 */
class SnapshotEvent extends Event
{
    /**
     * @var array Snapshot
     */
    public $snapshot;
}
