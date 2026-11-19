<?php

namespace App\Support\Metrics;

use App\Models\Metric;
use Illuminate\Support\Str;

class FieldRoleSchema
{
    private const EXECUTIVE_SECTIONS = [
        'official_definition',
        'measurement_logic_rules',
        'controls_caveats',
    ];

    /**
     * Build the field-role schema payload for a metric.
     *
     * @return array{field_roles: array<int, array{key: string, label: string, role: string, required: bool, exclude_from_consensus: bool, companion_of: ?string, cue_context_scope?: ?string, executive_section: string, value: mixed, unclassified: bool, workshop_prompt: ?string, workshop_guidance: ?string}>, unclassified: array<int, string>}
     */
    public static function forMetric(Metric $metric): array
    {
        [$labels, $latestValues] = self::collectObservedFieldData($metric);

        $schema = $metric->template?->form_schema_json ?? null;

        return self::buildPayload($schema, $labels, $latestValues);
    }

    /**
     * Build a field-role schema payload directly from a template schema.
     *
     * @param  array<string, mixed>|null  $schema
     * @return array{field_roles: array<int, array{key: string, label: string, role: string, required: bool, exclude_from_consensus: bool, companion_of: ?string, cue_context_scope?: ?string, executive_section: string, value: mixed, unclassified: bool, workshop_prompt: ?string, workshop_guidance: ?string}>, unclassified: array<int, string>}
     */
    public static function forTemplateSchema(?array $schema): array
    {
        return self::buildPayload($schema, [], []);
    }

    /**
     * Provide a map of observed field labels on the metric definitions.
     *
     * @return array<string, string>
     */
    public static function observedFieldLabels(Metric $metric): array
    {
        [$labels] = self::collectObservedFieldData($metric);

        return $labels;
    }

    /**
     * Build a keyed map of fields that should appear in consensus contexts.
     *
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @return array<string, array{key: string, label: string, role: string, executive_section?: string, value: mixed}>
     */
    public static function resolutionFieldMap(array $fieldRoles): array
    {
        $map = [];

        foreach ($fieldRoles as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;
            $label = $entry['label'] ?? null;
            $role = $entry['role'] ?? null;
            $excludeFromConsensus = $entry['exclude_from_consensus'] ?? false;

            if (
                ! is_string($key)
                || $key === ''
                || ! is_string($label)
                || $label === ''
                || ! is_string($role)
                || $role === ''
                || ValidationGuidanceFields::contains($key)
                || $excludeFromConsensus === true
            ) {
                continue;
            }

            $map[$key] = [
                'key' => $key,
                'label' => $label,
                'role' => $role,
                'executive_section' => is_string($entry['executive_section'] ?? null)
                    ? $entry['executive_section']
                    : null,
                'value' => $entry['value'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * Convenience wrapper to return resolution fields for a specific metric.
     *
     * @return array<string, array{key: string, label: string, role: string, executive_section?: string, value: mixed}>
     */
    public static function resolutionFieldsForMetric(Metric $metric): array
    {
        $payload = self::forMetric($metric);

        return self::resolutionFieldMap($payload['field_roles']);
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $latestValues
     * @return array{field_roles: array<int, array{key: string, label: string, role: string, required: bool, exclude_from_consensus: bool, companion_of: ?string, cue_context_scope?: ?string, executive_section: string, value: mixed, unclassified: bool, workshop_prompt: ?string, workshop_guidance: ?string}>, unclassified: array<int, string>}
     */
    protected static function buildPayload(?array $schema, array $labels, array $latestValues): array
    {
        $entries = [];
        $unclassified = [];

        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $field['name'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (ValidationGuidanceFields::contains($key)) {
                continue;
            }

            $label = is_string($field['label'] ?? null) && $field['label'] !== ''
                ? $field['label']
                : ($labels[$key] ?? Str::headline($key));

            $classification = self::normalizeClassification($field['classification'] ?? null);

            if ($classification === null) {
                $classification = self::fallbackClassification();
            }

            $workshopPrompt = isset($field['workshop_prompt']) && is_string($field['workshop_prompt']) && trim($field['workshop_prompt']) !== ''
                ? trim($field['workshop_prompt'])
                : null;
            $workshopGuidance = isset($field['workshop_guidance']) && is_string($field['workshop_guidance']) && trim($field['workshop_guidance']) !== ''
                ? trim($field['workshop_guidance'])
                : null;

            $entry = array_merge(
                [
                    'key' => $key,
                    'label' => $label,
                    'required' => (bool) ($field['required'] ?? false),
                    'value' => $latestValues[$key] ?? null,
                    'workshop_prompt' => $workshopPrompt,
                    'workshop_guidance' => $workshopGuidance,
                ],
                $classification
            );

            $entries[$key] = $entry;

            if (! empty($entry['unclassified'])) {
                $unclassified[] = $key;
            }
        }

        foreach ($labels as $key => $label) {
            if (! is_string($key) || $key === '' || array_key_exists($key, $entries)) {
                continue;
            }

            if (ValidationGuidanceFields::contains($key)) {
                continue;
            }

            $entry = [
                'key' => $key,
                'label' => $label,
                'required' => false,
                'value' => $latestValues[$key] ?? null,
                'workshop_prompt' => null,
                'workshop_guidance' => null,
                ...self::fallbackClassification(),
            ];

            $entries[$key] = $entry;
            $unclassified[] = $key;
        }

        $unclassified = array_values(array_unique($unclassified));

        return [
            'field_roles' => array_values($entries),
            'unclassified' => $unclassified,
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    protected static function collectObservedFieldData(Metric $metric): array
    {
        $metric->loadMissing('definitions');

        $definitions = $metric->definitions;

        if ($definitions->isEmpty()) {
            return [[], []];
        }

        $sorted = $definitions->sortByDesc(function ($definition) {
            return optional($definition->submitted_at ?? $definition->updated_at ?? $definition->created_at)?->timestamp ?? 0;
        });

        $labels = [];
        $latestValues = [];

        foreach ($sorted as $definition) {
            self::extractFieldData($definition->normalizedFields(), $labels, $latestValues);
            self::extractFieldData($definition->metaFields(), $labels, $latestValues);
        }

        return [$labels, $latestValues];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $latestValues
     */
    protected static function extractFieldData(array $fields, array &$labels, array &$latestValues): void
    {
        foreach ($fields as $key => $field) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (ValidationGuidanceFields::contains($key)) {
                continue;
            }

            if (! array_key_exists($key, $labels)) {
                $label = $field['label'] ?? null;
                $labels[$key] = is_string($label) && $label !== '' ? $label : Str::headline($key);
            }

            if (! array_key_exists($key, $latestValues)) {
                $latestValues[$key] = $field['normalized'] ?? $field['raw'] ?? null;
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $classification
     */
    protected static function normalizeClassification(?array $classification): ?array
    {
        if (! is_array($classification)) {
            return null;
        }

        $role = $classification['role'] ?? null;
        if (! is_string($role) || $role === '') {
            return null;
        }

        $role = strtolower($role);
        if (! in_array($role, ['definition', 'context', 'governance', 'diagnostic'], true)) {
            return null;
        }

        $companionOf = $classification['companion_of'] ?? null;
        $companionOf = is_string($companionOf) ? trim($companionOf) : '';
        $companionOf = $companionOf !== '' ? $companionOf : null;
        $cueContextScope = $classification['cue_context_scope'] ?? null;
        $cueContextScope = is_string($cueContextScope) ? trim($cueContextScope) : '';
        $cueContextScope = $cueContextScope !== '' ? $cueContextScope : null;
        $excludeFromConsensus = (bool) ($classification['exclude_from_consensus'] ?? false);
        $executiveSection = self::normalizeExecutiveSection(
            is_string($classification['executive_section'] ?? null)
                ? $classification['executive_section']
                : null,
            $role
        );

        return [
            'role' => $role,
            'exclude_from_consensus' => $excludeFromConsensus,
            'companion_of' => $companionOf,
            'cue_context_scope' => $cueContextScope,
            'executive_section' => $executiveSection,
            'unclassified' => false,
        ];
    }

    /**
     * @return array{role: string, exclude_from_consensus: bool, companion_of: ?string, executive_section: string, unclassified: bool}
     */
    protected static function fallbackClassification(): array
    {
        return [
            'role' => 'context',
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'executive_section' => self::defaultExecutiveSectionForRole('context'),
            'unclassified' => true,
        ];
    }

    private static function normalizeExecutiveSection(?string $section, string $role): string
    {
        $candidate = is_string($section) ? trim(strtolower($section)) : '';

        if (in_array($candidate, self::EXECUTIVE_SECTIONS, true)) {
            return $candidate;
        }

        return self::defaultExecutiveSectionForRole($role);
    }

    private static function defaultExecutiveSectionForRole(string $role): string
    {
        return match ($role) {
            'definition' => 'official_definition',
            'diagnostic' => 'controls_caveats',
            'governance', 'context' => 'measurement_logic_rules',
            default => 'measurement_logic_rules',
        };
    }
}
