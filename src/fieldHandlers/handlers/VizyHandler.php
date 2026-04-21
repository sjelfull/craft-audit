<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Vizy fields (verbb/vizy). Vizy stores mixed text nodes and block nodes,
 * so we normalize to a list of nodes preserving type, marks, and nested block fields.
 */
class VizyHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return class_exists('verbb\\vizy\\fields\\VizyField')
            ? ['verbb\\vizy\\fields\\VizyField']
            : [];
    }

    public static function typeKey(): string
    {
        return 'vizy';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return [];
        }

        // VizyNodeCollection / NodeCollection — iterate and serialize each node.
        $nodes = [];
        if (is_iterable($rawValue)) {
            foreach ($rawValue as $node) {
                $nodes[] = $this->serializeNode($node);
            }
        } elseif (is_object($rawValue) && method_exists($rawValue, 'getNodes')) {
            foreach ($rawValue->getNodes() as $node) {
                $nodes[] = $this->serializeNode($node);
            }
        }

        return $nodes;
    }

    private function serializeNode(mixed $node): array
    {
        if (!is_object($node)) {
            return ['type' => 'unknown', 'value' => $node];
        }

        // Try serialize() first — Vizy nodes usually provide it.
        if (method_exists($node, 'serializeValue')) {
            $serialized = $node->serializeValue();
            if (is_array($serialized)) {
                return $serialized;
            }
        }
        if (method_exists($node, 'getRawNode')) {
            $raw = $node->getRawNode();
            if (is_array($raw)) {
                return $raw;
            }
        }

        // Fallback: introspect common properties.
        $out = [
            'type' => $node->type ?? (method_exists($node, 'getType') ? $node->getType() : 'unknown'),
        ];
        if (isset($node->content)) {
            $out['content'] = $node->content;
        }
        if (isset($node->marks)) {
            $out['marks'] = $node->marks;
        }
        if (isset($node->attrs)) {
            $out['attrs'] = $node->attrs;
        }
        if (method_exists($node, 'getSerializedFieldValues')) {
            $out['fields'] = $node->getSerializedFieldValues();
        }
        return $out;
    }
}
