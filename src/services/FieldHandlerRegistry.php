<?php

namespace superbig\audit\services;

use craft\base\Component;
use craft\base\FieldInterface;
use superbig\audit\events\RegisterFieldHandlersEvent;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Registry of audit field handlers. Handlers are registered via the
 * EVENT_REGISTER_HANDLERS event, and looked up by Craft field class.
 *
 * @since 4.0.0
 */
class FieldHandlerRegistry extends Component
{
    public const EVENT_REGISTER_HANDLERS = 'registerHandlers';

    /** @var array<string, class-string<FieldHandler>> field class → handler class */
    private array $map = [];

    /** @var bool */
    private bool $registered = false;

    /**
     * Get a handler instance for a given field, or null if none match.
     * Parent-class checks allow handlers to match subclasses of registered fields.
     */
    public function getHandler(FieldInterface $field): ?FieldHandler
    {
        $this->ensureRegistered();
        $fieldClass = get_class($field);

        if (isset($this->map[$fieldClass])) {
            $handlerClass = $this->map[$fieldClass];
            return new $handlerClass();
        }

        // Walk parents (e.g., custom field extends PlainText)
        foreach ($this->map as $targetClass => $handlerClass) {
            if (is_a($fieldClass, $targetClass, true)) {
                return new $handlerClass();
            }
        }

        return null;
    }

    /**
     * Get the type key for a field (or 'generic' if no handler registered).
     */
    public function getTypeKey(FieldInterface $field): string
    {
        $handler = $this->getHandler($field);
        return $handler !== null ? $handler::typeKey() : 'generic';
    }

    /**
     * Clear the registry cache (useful in tests).
     */
    public function reset(): void
    {
        $this->map = [];
        $this->registered = false;
    }

    private function ensureRegistered(): void
    {
        if ($this->registered) {
            return;
        }

        $event = new RegisterFieldHandlersEvent();
        $this->trigger(self::EVENT_REGISTER_HANDLERS, $event);

        foreach ($event->handlers as $handlerClass) {
            if (!is_subclass_of($handlerClass, FieldHandler::class)) {
                \Craft::warning(
                    "Handler {$handlerClass} does not implement FieldHandler, skipping",
                    __METHOD__
                );
                continue;
            }

            foreach ($handlerClass::supportedFields() as $fieldClass) {
                $this->map[$fieldClass] = $handlerClass;
            }
        }

        $this->registered = true;
    }
}
