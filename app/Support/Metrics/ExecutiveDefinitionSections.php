<?php

namespace App\Support\Metrics;

use Illuminate\Support\Str;

class ExecutiveDefinitionSections
{
    private const OFFICIAL_DEFINITION = 'official_definition';

    private const MEASUREMENT_LOGIC_RULES = 'measurement_logic_rules';

    private const CONTROLS_CAVEATS = 'controls_caveats';

    /**
     * @var array<string, string>
     */
    private const SECTION_TITLES = [
        self::OFFICIAL_DEFINITION => 'Official Definition',
        self::MEASUREMENT_LOGIC_RULES => 'Measurement Logic & Rules',
        self::CONTROLS_CAVEATS => 'Controls & Caveats',
    ];

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @param  array<string, mixed>|null  $owner
     * @param  array<string, array<string, string>>  $optionLabels
     * @return array<int, array{key: string, title: string, items: array<int, array{key: string, label: string, value: mixed, companions: array<int, array{key: string, label: string, value: mixed}>}>}>
     */
    public static function build(
        array $definition,
        array $fieldRoles = [],
        ?array $owner = null,
        array $optionLabels = []
    ): array {
        $sections = self::initializeSections();

        foreach (self::buildDefinitionItems($definition, $fieldRoles, $optionLabels) as $item) {
            $sectionKey = self::normalizeSection($item['section'] ?? null);

            $sections[$sectionKey]['items'][] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'value' => $item['value'],
                'companions' => $item['companions'],
            ];
        }

        return array_values(array_filter(
            $sections,
            fn (array $section): bool => $section['items'] !== []
        ));
    }

    /**
     * @return array<string, array{key: string, title: string, items: array<int, array{key: string, label: string, value: mixed, companions: array<int, array{key: string, label: string, value: mixed}>}>}>
     */
    private static function initializeSections(): array
    {
        $sections = [];

        foreach (self::SECTION_TITLES as $key => $title) {
            $sections[$key] = [
                'key' => $key,
                'title' => $title,
                'items' => [],
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @param  array<string, array<string, string>>  $optionLabels
     * @return array<int, array{key: string, label: string, value: mixed, companions: array<int, array{key: string, label: string, value: mixed}>, section: string}>
     */
    private static function buildDefinitionItems(array $definition, array $fieldRoles, array $optionLabels): array
    {
        if ($definition === []) {
            return [];
        }

        $entries = [];

        foreach ($definition as $key => $field) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            if (ValidationGuidanceFields::contains($key)) {
                continue;
            }

            $value = is_array($field) ? ($field['value'] ?? null) : $field;

            if (! DefinitionFieldValue::isFilled($value)) {
                continue;
            }

            $displayValue = self::formatFieldValue($value, $optionLabels[$key] ?? []);

            if (is_array($field)) {
                $entries[] = [
                    'key' => $key,
                    'label' => self::resolveLabel($field['label'] ?? null, $key),
                    'value' => $displayValue,
                    'companion_of' => self::normalizeCompanionOf($field['companion_of'] ?? null),
                    'role' => null,
                    'section' => null,
                    'companions' => [],
                ];

                continue;
            }

            $entries[] = [
                'key' => $key,
                'label' => self::resolveLabel(null, $key),
                'value' => $displayValue,
                'companion_of' => null,
                'role' => null,
                'section' => null,
                'companions' => [],
            ];
        }

        $orderedEntries = self::orderEntries($entries, $fieldRoles);
        $orderedKeys = array_map(fn (array $entry): string => $entry['key'], $orderedEntries);
        $keySet = array_fill_keys($orderedKeys, true);

        $companionsByBase = [];

        foreach ($orderedEntries as $entry) {
            $baseKey = self::companionBaseKey($entry, $orderedKeys);
            if ($baseKey === null || ! isset($keySet[$baseKey])) {
                continue;
            }

            if (! isset($companionsByBase[$baseKey])) {
                $companionsByBase[$baseKey] = [];
            }

            $companionsByBase[$baseKey][] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'value' => $entry['value'],
            ];
        }

        $result = [];

        foreach ($orderedEntries as $entry) {
            $baseKey = self::companionBaseKey($entry, $orderedKeys);
            if ($baseKey !== null && isset($keySet[$baseKey])) {
                continue;
            }

            $result[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'value' => $entry['value'],
                'companions' => $companionsByBase[$entry['key']] ?? [],
                'section' => self::normalizeSection($entry['section'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{key: string, label: string, value: mixed, companion_of: ?string, role: ?string, section: ?string, companions: array<int, array{key: string, label: string, value: mixed}>}>  $entries
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @return array<int, array{key: string, label: string, value: mixed, companion_of: ?string, role: ?string, section: ?string, companions: array<int, array{key: string, label: string, value: mixed}>}>
     */
    private static function orderEntries(array $entries, array $fieldRoles): array
    {
        if ($entries === []) {
            return [];
        }

        if ($fieldRoles === []) {
            return array_map(
                fn (array $entry): array => self::assignFallbackSection($entry),
                $entries
            );
        }

        $entryByKey = [];
        foreach ($entries as $entry) {
            $entryByKey[$entry['key']] = $entry;
        }

        $ordered = [];
        $seen = [];

        foreach ($fieldRoles as $roleEntry) {
            if (! is_array($roleEntry)) {
                continue;
            }

            $key = $roleEntry['key'] ?? null;
            if (! is_string($key) || $key === '' || isset($seen[$key]) || ! isset($entryByKey[$key])) {
                continue;
            }

            if (($roleEntry['exclude_from_consensus'] ?? false) === true) {
                continue;
            }

            $entry = $entryByKey[$key];
            $entry['role'] = is_string($roleEntry['role'] ?? null) ? strtolower((string) $roleEntry['role']) : null;
            $entry['section'] = self::normalizeSection($roleEntry['executive_section'] ?? null, $entry['role']);

            if ($entry['companion_of'] === null) {
                $entry['companion_of'] = self::normalizeCompanionOf($roleEntry['companion_of'] ?? null);
            }

            $ordered[] = $entry;
            $seen[$key] = true;
        }

        foreach ($entries as $entry) {
            if (isset($seen[$entry['key']])) {
                continue;
            }

            $ordered[] = self::assignFallbackSection($entry);
            $seen[$entry['key']] = true;
        }

        return $ordered;
    }

    /**
     * @param  array{key: string, label: string, value: mixed, companion_of: ?string, role: ?string, section: ?string, companions: array<int, array{key: string, label: string, value: mixed}>}  $entry
     * @return array{key: string, label: string, value: mixed, companion_of: ?string, role: ?string, section: ?string, companions: array<int, array{key: string, label: string, value: mixed}>}
     */
    private static function assignFallbackSection(array $entry): array
    {
        $entry['section'] = self::normalizeSection($entry['section'] ?? null, $entry['role'], $entry['key']);

        return $entry;
    }

    /**
     * @param  array{key: string, companion_of: ?string}  $entry
     * @param  array<int, string>  $orderedKeys
     */
    private static function companionBaseKey(array $entry, array $orderedKeys): ?string
    {
        $explicit = $entry['companion_of'] ?? null;

        if (
            is_string($explicit)
            && $explicit !== ''
            && $explicit !== $entry['key']
            && in_array($explicit, $orderedKeys, true)
        ) {
            return $explicit;
        }

        if (! str_ends_with($entry['key'], '_notes')) {
            return null;
        }

        $prefix = substr($entry['key'], 0, -strlen('_notes'));
        if (in_array($prefix, $orderedKeys, true)) {
            return $prefix;
        }

        foreach ($orderedKeys as $candidate) {
            if (
                $candidate !== $entry['key']
                && ! str_ends_with($candidate, '_notes')
                && str_starts_with($candidate, $prefix.'_')
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private static function resolveLabel(mixed $label, string $key): string
    {
        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        return Str::headline(str_replace('_', ' ', $key));
    }

    private static function normalizeCompanionOf(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);

        return $candidate !== '' ? $candidate : null;
    }

    private static function normalizeSection(
        mixed $section,
        ?string $role = null,
        ?string $fieldKey = null
    ): string {
        $candidate = is_string($section) ? trim(strtolower($section)) : '';

        if (array_key_exists($candidate, self::SECTION_TITLES)) {
            return $candidate;
        }

        $normalizedRole = is_string($role) ? trim(strtolower($role)) : '';

        if ($normalizedRole === 'definition') {
            return self::OFFICIAL_DEFINITION;
        }

        if ($normalizedRole === 'diagnostic') {
            return self::CONTROLS_CAVEATS;
        }

        if ($normalizedRole === 'governance' || $normalizedRole === 'context') {
            return self::MEASUREMENT_LOGIC_RULES;
        }

        $normalizedKey = is_string($fieldKey) ? strtolower($fieldKey) : '';

        if ($normalizedKey !== '' && self::containsAny($normalizedKey, ['pain', 'risk', 'divergence', 'conflict', 'blocker'])) {
            return self::CONTROLS_CAVEATS;
        }

        if ($normalizedKey !== '' && self::containsAny($normalizedKey, ['owner', 'review', 'decision', 'summary'])) {
            return self::OFFICIAL_DEFINITION;
        }

        if (
            $normalizedKey !== ''
            && self::containsAny(
                $normalizedKey,
                ['policy', 'timing', 'timezone', 'cutoff', 'cadence', 'dedup', 'attribution', 'source']
            )
        ) {
            return self::MEASUREMENT_LOGIC_RULES;
        }

        return self::OFFICIAL_DEFINITION;
    }

    private static function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    /**
     * Format field value using option labels.
     *
     * @param  array<string, string>  $optionLabels
     */
    private static function formatFieldValue(mixed $value, array $optionLabels): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $entry) => self::formatFieldValue($entry, $optionLabels))
                ->filter(fn (mixed $entry) => is_string($entry) ? trim($entry) !== '' : $entry !== null)
                ->implode(', ');
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '') {
                return '';
            }

            return $optionLabels[$normalized] ?? $normalized;
        }

        return $value;
    }
}
