<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Dropdown;
use craft\fields\RadioButtons;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for single-option fields (Dropdown, RadioButtons).
 * Stores {value, label} so diffs show both machine and human-readable forms.
 */
class OptionHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [
            Dropdown::class,
            RadioButtons::class,
        ];
    }

    public static function typeKey(): string
    {
        return 'option';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return null;
        }
        if (is_object($rawValue)) {
            return [
                'value' => $rawValue->value ?? null,
                'label' => $rawValue->label ?? null,
            ];
        }
        return ['value' => $rawValue, 'label' => $rawValue];
    }
}
