<?php

namespace superbig\audit\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use superbig\audit\Audit;

/**
 * Field diff service — captures element state and computes diffs.
 */
class FieldDiffService extends Component
{
    /** Truncation threshold per field in serialized bytes */
    public int $maxFieldBytes = 1_000_000;

    /**
     * Capture an element's current state as a map of
     * handle → {handler: typeKey, value: normalized}.
     */
    public function captureState(ElementInterface $element): array
    {
        $registry = Audit::$plugin->fieldHandlerRegistry;
        $state = [];

        // Native attributes
        $state['_title'] = ['handler' => 'plain', 'value' => $element->title ?? null];
        $state['_slug'] = ['handler' => 'plain', 'value' => $element->slug ?? null];
        $state['_status'] = ['handler' => 'plain', 'value' => $element->getStatus()];
        $state['_enabled'] = ['handler' => 'boolean', 'value' => (bool) $element->enabled];

        if ($element instanceof Entry) {
            $state['_postDate'] = ['handler' => 'date', 'value' => $element->postDate?->format('Y-m-d H:i:s')];
            $state['_expiryDate'] = ['handler' => 'date', 'value' => $element->expiryDate?->format('Y-m-d H:i:s')];
        }

        // Custom fields
        $fieldLayout = $element->getFieldLayout();
        if ($fieldLayout !== null) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                try {
                    $rawValue = $element->getFieldValue($field->handle);
                } catch (\Throwable $e) {
                    $state[$field->handle] = [
                        'handler' => 'error',
                        'value' => ['_error' => true, '_message' => $e->getMessage()],
                    ];
                    continue;
                }

                $handler = $registry->getHandler($field);
                $typeKey = $registry->getTypeKey($field);

                try {
                    if ($handler !== null) {
                        $normalized = $handler->normalize($field, $rawValue, $element);
                    } else {
                        // Generic fallback: Craft's own serialization
                        $normalized = $field->serializeValue($rawValue, $element);
                    }

                    // Guard against oversized values
                    $encoded = json_encode($normalized);
                    if ($encoded !== false && strlen($encoded) > $this->maxFieldBytes) {
                        $normalized = [
                            '_truncated' => true,
                            '_originalSize' => strlen($encoded),
                            '_preview' => is_string($normalized)
                                ? mb_substr($normalized, 0, 1000)
                                : null,
                        ];
                    }
                } catch (\Throwable $e) {
                    Craft::warning(
                        "Audit: failed to normalize field {$field->handle}: {$e->getMessage()}",
                        __METHOD__
                    );
                    $normalized = [
                        '_error' => true,
                        '_message' => $e->getMessage(),
                    ];
                }

                $state[$field->handle] = [
                    'handler' => $typeKey,
                    'value' => $normalized,
                ];
            }
        }

        return $state;
    }

    /**
     * Diff two states. Returns only changed entries.
     * Shape: { handle => { handler: typeKey, from: oldValue, to: newValue } }
     */
    public function diff(array $oldState, array $newState): array
    {
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldState), array_keys($newState)));

        foreach ($allKeys as $key) {
            $old = $oldState[$key] ?? null;
            $new = $newState[$key] ?? null;

            $oldValue = $old['value'] ?? null;
            $newValue = $new['value'] ?? null;

            if ($this->valuesEqual($oldValue, $newValue)) {
                continue;
            }

            $changes[$key] = [
                'handler' => $new['handler'] ?? $old['handler'] ?? 'generic',
                'from' => $oldValue,
                'to' => $newValue,
            ];
        }

        return $changes;
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (is_array($a) && is_array($b)) {
            return json_encode($a) === json_encode($b);
        }
        return false;
    }
}
