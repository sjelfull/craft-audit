<?php

namespace superbig\audit\fieldHandlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;

/**
 * Contract for audit field handlers.
 *
 * A handler knows how to extract a normalized, JSON-serializable value from a Craft field,
 * and declares a short type key used for rendering lookup.
 */
interface FieldHandler
{
    /**
     * Which Craft field classes this handler supports.
     *
     * @return string[] Fully-qualified class names
     */
    public static function supportedFields(): array;

    /**
     * Short type key stored in the diff data for template lookup.
     * Must be unique across handlers. Examples: 'plain', 'richtext', 'relation'.
     */
    public static function typeKey(): string;

    /**
     * Extract a normalized, JSON-serializable value from a field's raw value.
     * Must return scalars, arrays, nulls — never objects or resources.
     */
    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed;
}
