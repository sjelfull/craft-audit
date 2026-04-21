<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for SEO fields (ether/craft-seo). Normalizes title, description,
 * keywords, social, and robots for readable diffs.
 */
class SeoHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return class_exists('ether\\seo\\fields\\SeoField')
            ? ['ether\\seo\\fields\\SeoField']
            : [];
    }

    public static function typeKey(): string
    {
        return 'seo';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return null;
        }

        $get = static function($src, string $key, $default = null) {
            if (is_array($src)) {
                return $src[$key] ?? $default;
            }
            if (is_object($src)) {
                return $src->{$key} ?? $default;
            }
            return $default;
        };

        $title = $get($rawValue, 'title');
        $description = $get($rawValue, 'description');
        $keywords = $get($rawValue, 'keywords', []);
        $social = $get($rawValue, 'social', []);
        $advanced = $get($rawValue, 'advanced', []);
        $robots = $get($rawValue, 'robots', $get($advanced, 'robots', []));

        // Keywords may be array of {keyword, rating}
        if (is_object($keywords)) {
            $keywords = (array) $keywords;
        }

        return [
            'title' => is_scalar($title) ? (string) $title : $title,
            'description' => is_scalar($description) ? (string) $description : $description,
            'keywords' => $keywords,
            'social' => is_object($social) ? (array) $social : $social,
            'robots' => is_object($robots) ? (array) $robots : $robots,
        ];
    }
}
