<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Date;
use craft\fields\Time;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Date and Time fields.
 */
class DateHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [
            Date::class,
            Time::class,
        ];
    }

    public static function typeKey(): string
    {
        return 'date';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if (!$rawValue instanceof \DateTime) {
            return null;
        }
        if ($field instanceof Time) {
            return $rawValue->format('H:i:s');
        }
        $showTime = $field->showTime ?? false;
        return $showTime ? $rawValue->format('Y-m-d H:i:s') : $rawValue->format('Y-m-d');
    }
}
