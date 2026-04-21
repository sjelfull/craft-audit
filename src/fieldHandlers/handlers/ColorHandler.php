<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Color;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Color field. Normalizes to a hex string for swatch rendering.
 */
class ColorHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [Color::class];
    }

    public static function typeKey(): string
    {
        return 'color';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }
        if (is_object($rawValue) && isset($rawValue->hex)) {
            return (string) $rawValue->hex;
        }
        return (string) $rawValue;
    }
}
