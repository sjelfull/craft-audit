<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for rich-text fields from CKEditor, Redactor, and TinyMCE plugins.
 * Fields are gated behind class_exists() so the handler registers safely even
 * when none of the plugins are installed.
 */
class RichTextHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return array_values(array_filter([
            'craft\\ckeditor\\Field',
            'craft\\redactor\\Field',
            'craft\\tinymce\\Field',
        ], 'class_exists'));
    }

    public static function typeKey(): string
    {
        return 'richtext';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return null;
        }
        return (string) $rawValue;
    }
}
