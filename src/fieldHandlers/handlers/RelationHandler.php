<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Addresses;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Entries;
use craft\fields\Tags;
use craft\fields\Users;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for relation fields — Entries, Assets, Categories, Tags, Users, Addresses.
 * Normalizes to a list of {id, title, type, status}.
 */
class RelationHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [
            Entries::class,
            Assets::class,
            Categories::class,
            Tags::class,
            Users::class,
            Addresses::class,
        ];
    }

    public static function typeKey(): string
    {
        return 'relation';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if (!$rawValue) {
            return [];
        }

        if (is_object($rawValue) && method_exists($rawValue, 'all')) {
            // ElementQuery — clone so we don't mutate shared state.
            $query = clone $rawValue;
            if (method_exists($query, 'status')) {
                $query->status(null);
            }
            $elements = $query->all();
        } elseif (is_iterable($rawValue)) {
            $elements = is_array($rawValue) ? $rawValue : iterator_to_array($rawValue);
        } else {
            $elements = [$rawValue];
        }

        $elements = array_values(array_filter($elements, fn($el) => is_object($el)));

        return array_map(function($el) {
            $title = null;
            if (isset($el->title)) {
                $title = $el->title;
            } elseif (isset($el->name)) {
                $title = $el->name;
            } else {
                $title = (string) $el;
            }

            return [
                'id' => $el->id ?? null,
                'title' => $title,
                'type' => method_exists($el, 'displayName') ? $el::displayName() : get_class($el),
                'status' => method_exists($el, 'getStatus') ? $el->getStatus() : null,
            ];
        }, $elements);
    }
}
