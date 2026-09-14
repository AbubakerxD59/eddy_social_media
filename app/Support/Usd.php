<?php

namespace App\Support;

class Usd
{
    public static function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $normalized = preg_replace('/\b(USD|EUR|GBP|AED|PKR|INR|CAD|AUD)\b\.?/i', '', $text) ?? $text;
        $normalized = str_replace(['€', '£', '¥', '₹', '₨', 'Rs.', 'Rs'], '$', $normalized);
        $normalized = preg_replace('/\$\s+/', '$', $normalized) ?? $normalized;
        $normalized = preg_replace('/\$+/', '$', $normalized) ?? $normalized;
        $normalized = trim(preg_replace('/\s{2,}/', ' ', $normalized) ?? $normalized);

        if ($normalized === '' || $normalized === '$') {
            return null;
        }

        if (! str_contains($normalized, '$') && preg_match('/\d/u', $normalized) === 1) {
            return '$'.$normalized;
        }

        return $normalized;
    }

    public static function fromCents(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }
}
