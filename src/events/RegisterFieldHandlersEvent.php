<?php

namespace superbig\audit\events;

use yii\base\Event;

/**
 * Event for registering field handlers with the FieldHandlerRegistry.
 */
class RegisterFieldHandlersEvent extends Event
{
    /** @var string[] Array of fully-qualified handler class names */
    public array $handlers = [];
}
