<?php

namespace App\Support\Metrics;

use App\Models\FinalDefinition;
use App\Models\Metric;
use Illuminate\Support\Str;

class FinalDefinitionRequirements
{
    /**
     * @param  array<int, array{key: string, label: string, required?: bool, exclude_from_consensus?: bool}>  $fieldRoles
     * @param  array<int, array{field: string, label: string}>  $matrixRows
     * @param  array<string, array{value?: mixed}>  $definition
     * @return array{required_fields: array<int, array{key: string, label: string}>, missing_keys: array<int, string>, owner_eligible: bool}
     */
    public static function fromPayload(
        array $fieldRoles,
        array $matrixRows,
        array $definition,
        bool $ownerEligible
    ): array {
        $requiredFields = self::requiredFieldsFromRoles($fieldRoles);

        if ($requiredFields === []) {
            $requiredFields = self::requiredFieldsFromMatrixRows($matrixRows);
        }

        return [
            'required_fields' => $requiredFields,
            'missing_keys' => self::missingKeys($requiredFields, $definition),
            'owner_eligible' => $ownerEligible,
        ];
    }

    /**
     * @return array{required_fields: array<int, array{key: string, label: string}>, missing_keys: array<int, string>, owner_eligible: bool}
     */
    public static function forMetric(Metric $metric, ?FinalDefinition $finalDefinition = null): array
    {
        $finalDefinition = $finalDefinition ?? $metric->finalDefinition;
        $definition = is_array($finalDefinition?->definition_json ?? null)
            ? $finalDefinition->definition_json
            : [];

        $fieldRolesPayload = FieldRoleSchema::forMetric($metric);
        $requiredFields = self::requiredFieldsFromRoles($fieldRolesPayload['field_roles'] ?? []);

        if ($requiredFields === []) {
            $requiredFields = self::requiredFieldsFromMatrix($metric);
        }

        return [
            'required_fields' => $requiredFields,
            'missing_keys' => self::missingKeys($requiredFields, $definition),
            'owner_eligible' => self::ownerEligible($finalDefinition),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, required?: bool, exclude_from_consensus?: bool}>  $fieldRoles
     * @return array<int, array{key: string, label: string}>
     */
    private static function requiredFieldsFromRoles(array $fieldRoles): array
    {
        $required = [];

        foreach ($fieldRoles as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;
            $label = $entry['label'] ?? null;
            $isRequired = $entry['required'] ?? null;
            $excludeFromConsensus = (bool) ($entry['exclude_from_consensus'] ?? false);
            $includedInConsensus = ! $excludeFromConsensus;

            if (
                ! is_string($key)
                || $key === ''
                || ! is_string($label)
                || $label === ''
                || ValidationGuidanceFields::contains($key)
                || ! $isRequired
                || ! $includedInConsensus
            ) {
                continue;
            }

            $required[$key] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        return array_values($required);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private static function requiredFieldsFromMatrix(Metric $metric): array
    {
        $rows = self::matrixRowsForMetric($metric);

        return self::requiredFieldsFromMatrixRows($rows);
    }

    /**
     * @param  array<int, array{field: string, label: string}>  $matrixRows
     * @return array<int, array{key: string, label: string}>
     */
    private static function requiredFieldsFromMatrixRows(array $matrixRows): array
    {
        $required = [];

        foreach ($matrixRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $row['field'] ?? null;
            $label = $row['label'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($label) || $label === '') {
                continue;
            }

            if (ValidationGuidanceFields::contains($key)) {
                continue;
            }

            $required[$key] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        return array_values($required);
    }

    /**
     * @return array<int, array{field: string, label: string}>
     */
    private static function matrixRowsForMetric(Metric $metric): array
    {
        $metric->loadMissing('definitions');

        $labels = [];

        foreach ($metric->definitions as $definition) {
            foreach ($definition->normalizedFields() as $fieldKey => $field) {
                if (! is_string($fieldKey) || $fieldKey === '') {
                    continue;
                }

                if (ValidationGuidanceFields::contains($fieldKey)) {
                    continue;
                }

                if (array_key_exists($fieldKey, $labels)) {
                    continue;
                }

                $label = $field['label'] ?? null;
                if (! is_string($label) || $label === '') {
                    $label = Str::headline((string) $fieldKey);
                }

                $labels[$fieldKey] = $label;
            }
        }

        $rows = [];

        foreach ($labels as $field => $label) {
            $rows[] = [
                'field' => (string) $field,
                'label' => $label,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $requiredFields
     * @param  array<string, array{value?: mixed}>  $definition
     * @return array<int, string>
     */
    private static function missingKeys(array $requiredFields, array $definition): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $value = $definition[$key]['value'] ?? null;

            if (! self::isFilledValue($value)) {
                $missing[] = $key;
            }
        }

        return array_values(array_unique($missing));
    }

    private static function isFilledValue(mixed $value): bool
    {
        return DefinitionFieldValue::isFilled($value);
    }

    private static function ownerEligible(?FinalDefinition $finalDefinition): bool
    {
        if (! $finalDefinition || ! $finalDefinition->owner_participant_id) {
            return false;
        }

        $finalDefinition->loadMissing('ownerParticipant.user');

        return (bool) $finalDefinition->ownerParticipant?->user_id;
    }
}
