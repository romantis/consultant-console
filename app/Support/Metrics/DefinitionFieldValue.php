<?php

namespace App\Support\Metrics;

class DefinitionFieldValue
{
    public static function isFilled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (self::isFilled($entry)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null;
    }
}
