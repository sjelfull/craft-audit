<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Neo fields (benf/craft-neo). Plugin may not be installed —
 * supportedFields() returns [] in that case so the handler registers safely.
 *
 * Neo blocks support nested levels via $block->level.
 */
class NeoHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return class_exists('benf\\neo\\Field') ? ['benf\\neo\\Field'] : [];
    }

    public static function typeKey(): string
    {
        return 'neo';
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
        $blocks = $query->all();

        return array_values(array_map(function($block) {
            return [
                'id' => $block->id ?? null,
                'level' => $block->level ?? 1,
                'type' => $block->type->handle ?? null,
                'typeName' => $block->type->name ?? null,
                'enabled' => (bool) ($block->enabled ?? false),
                'fields' => method_exists($block, 'getSerializedFieldValues')
                    ? $block->getSerializedFieldValues()
                    : [],
            ];
        }, array_filter($blocks, fn($b) => is_object($b))));
    }
}
