<?php

namespace App\Support;

class IndonesianNumber
{
    /**
     * Convert an Indonesian-formatted number such as 100.000 or 100.000,50
     * into a numeric value for validation and persistence.
     */
    public static function parse($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $negative = str_starts_with($text, '-');
        $text = preg_replace('/[^\d,.]/', '', $text);

        if (str_contains($text, ',')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, '.')) {
            $parts = explode('.', $text);
            $lastPart = end($parts);
            $text = strlen($lastPart) <= 2
                ? implode('', array_slice($parts, 0, -1)) . '.' . $lastPart
                : implode('', $parts);
        }

        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        $number = (float) $text;
        return $negative ? -$number : $number;
    }
}
