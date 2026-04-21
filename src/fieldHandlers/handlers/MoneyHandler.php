<?php

namespace superbig\audit\fieldHandlers\handlers;

use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\fields\Money;
use superbig\audit\fieldHandlers\FieldHandler;

/**
 * Handler for Money fields. Stores amount (integer cents), currency, and display string.
 */
class MoneyHandler implements FieldHandler
{
    public static function supportedFields(): array
    {
        return [Money::class];
    }

    public static function typeKey(): string
    {
        return 'money';
    }

    public function normalize(FieldInterface $field, mixed $rawValue, ElementInterface $element): mixed
    {
        if ($rawValue === null) {
            return null;
        }

        $currency = $field->currency ?? 'USD';

        if (is_object($rawValue) && method_exists($rawValue, 'getAmount')) {
            $amount = (int) ($rawValue->getAmount() ?? 0);
        } else {
            $amount = (int) $rawValue;
        }

        return [
            'amount' => $amount,
            'currency' => $currency,
            'display' => number_format($amount / 100, 2) . ' ' . $currency,
        ];
    }
}
