<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Matrix;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Matrix fields. In Craft 5, Matrix entries are nested elements.
 */
class MatrixHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [Matrix::class];
    }

    public static function typeKey(): string
    {
        return 'matrix';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if (!is_object($rawValue) || !method_exists($rawValue, 'all')) {
            if (is_iterable($rawValue)) {
                $entries = is_array($rawValue) ? $rawValue : iterator_to_array($rawValue);
            } else {
                return [];
            }
        } else {
            $query = clone $rawValue;
            if (method_exists($query, 'status')) {
                $query->status(null);
            }
            $entries = $query->all();
        }

        return array_values(array_map(function($entry) {
            return [
                'id' => $entry->id ?? null,
                'type' => $entry->type->handle ?? null,
                'typeName' => $entry->type->name ?? null,
                'title' => $entry->title ?? null,
                'enabled' => (bool) ($entry->enabled ?? false),
                'fields' => method_exists($entry, 'getSerializedFieldValues')
                    ? $entry->getSerializedFieldValues()
                    : [],
            ];
        }, array_filter($entries, fn($e) => is_object($e))));
    }
}
