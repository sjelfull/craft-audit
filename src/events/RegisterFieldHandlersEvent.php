<?php

namespace superbig\audit\events;

use yii\base\Event;

/**
 * Event for registering field handlers with the FieldHandlerRegistry.
 *
 * @since 4.0.0
 */
class RegisterFieldHandlersEvent extends Event
{
    /** @var string[] Array of fully-qualified handler class names */
    public array $handlers = [];
}
