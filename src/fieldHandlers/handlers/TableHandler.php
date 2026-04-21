<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Table;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Table fields. Returns rows keyed by column heading for readable diffs.
 */
class TableHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [Table::class];
    }

    public static function typeKey(): string
    {
        return 'table';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if (!is_array($rawValue)) {
            return [];
        }
        $columns = $field->columns ?? [];

        return array_values(array_map(function($row) use ($columns) {
            $labeled = [];
            if (!is_array($row)) {
                return $labeled;
            }
            foreach ($columns as $handle => $col) {
                $heading = is_array($col) ? ($col['heading'] ?? $handle) : $handle;
                $labeled[$heading] = $row[$handle] ?? null;
            }
            // If no columns defined, fall through to the row as-is.
            return $columns ? $labeled : $row;
        }, $rawValue));
    }
}
