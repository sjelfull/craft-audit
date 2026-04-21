<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Checkboxes;
use craft\fields\MultiSelect;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for multi-option fields (Checkboxes, MultiSelect).
 * Returns an array of {value, label} for options where selected === true.
 */
class MultiOptionHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [
            Checkboxes::class,
            MultiSelect::class,
        ];
    }

    public static function typeKey(): string
    {
        return 'multi-option';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        $selected = [];
        if (!$rawValue) {
            return $selected;
        }

        if (!is_iterable($rawValue)) {
            return $selected;
        }

        foreach ($rawValue as $option) {
            if (is_object($option) && !empty($option->selected ?? false)) {
                $selected[] = [
                    'value' => $option->value ?? null,
                    'label' => $option->label ?? null,
                ];
            }
        }
        return $selected;
    }
}
