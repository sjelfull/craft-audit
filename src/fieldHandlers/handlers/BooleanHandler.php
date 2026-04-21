<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Lightswitch;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Lightswitch (boolean) fields.
 */
class BooleanHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [Lightswitch::class];
    }

    public static function typeKey(): string
    {
        return 'boolean';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        return (bool) $rawValue;
    }
}
