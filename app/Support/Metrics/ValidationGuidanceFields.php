<?php

namespace App\Support\Metrics;

final class ValidationGuidanceFields
{
    private const KEYS = [
        'validation_steps',
        'common_mismatch_causes',
    ];

    public static function contains(string $fieldKey): bool
    {
        return in_array($fieldKey, self::KEYS, true);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return self::KEYS;
    }
}
