<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Email;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\Url;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for plain scalar-like fields: PlainText, Number, Email, Url.
 */
class PlainHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [
            PlainText::class,
            Number::class,
            Email::class,
            Url::class,
        ];
    }

    public static function typeKey(): string
    {
        return 'plain';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return null;
        }
        return is_scalar($rawValue) ? $rawValue : (string) $rawValue;
    }
}
