<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Super Table fields (verbb/super-table).
 */
class SuperTableHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return class_exists('verbb\\supertable\\fields\\SuperTableField')
            ? ['verbb\\supertable\\fields\\SuperTableField']
            : [];
    }

    public static function typeKey(): string
    {
        return 'supertable';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if (!is_object($rawValue) || !method_exists($rawValue, 'all')) {
            return [];
        }

        $query = clone $rawValue;
        if (method_exists($query, 'status')) {
            $query->status(null);
        }
        $rows = $query->all();

        return array_values(array_map(function($row) {
            return [
                'id' => $row->id ?? null,
                'enabled' => (bool) ($row->enabled ?? false),
                'fields' => method_exists($row, 'getSerializedFieldValues')
                    ? $row->getSerializedFieldValues()
                    : [],
            ];
        }, array_filter($rows, fn($r) => is_object($r))));
    }
}
