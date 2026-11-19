<?php

namespace App\Support\Discrepancies;

use App\Models\Discrepancy;

class DiscrepancyPresenter
{
    /**
     * @param  array<string, array{key: string, label: string, role: string, value: mixed}>  $resolutionFields
     */
    public static function present(Discrepancy $discrepancy, array $resolutionFields = []): array
    {
        return [
            'id' => (string) $discrepancy->getKey(),
            'metric_id' => (string) $discrepancy->metric_id,
            'field_key' => $discrepancy->field_key,
            'rule_type' => $discrepancy->rule_type,
            'severity' => $discrepancy->severity,
            'status' => $discrepancy->status,
            'description' => $discrepancy->description,
            'detected_at' => optional($discrepancy->detected_at)->toIso8601String(),
            'resolved_at' => optional($discrepancy->resolved_at)->toIso8601String(),
            'values_snapshot' => $discrepancy->values_snapshot ?? [],
            'supporting_fields' => self::formatSupportingFields($discrepancy->field_key, $resolutionFields),
        ];
    }

    /**
     * @param  array<string, array{key: string, label: string, role: string, value: mixed}>  $resolutionFields
     */
    protected static function formatSupportingFields(?string $fieldKey, array $resolutionFields): array
    {
        if (empty($resolutionFields)) {
            return [];
        }

        $ordered = [];

        if (is_string($fieldKey) && $fieldKey !== '' && isset($resolutionFields[$fieldKey])) {
            $ordered[] = $resolutionFields[$fieldKey];
        }

        foreach ($resolutionFields as $key => $field) {
            if ($key === $fieldKey) {
                continue;
            }

            $ordered[] = $field;
        }

        return array_map(function ($field) {
            return [
                'key' => $field['key'],
                'label' => $field['label'],
                'role' => $field['role'],
                'value' => $field['value'] ?? null,
            ];
        }, $ordered);
    }
}
