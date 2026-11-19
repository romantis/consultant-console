<?php

namespace App\Support\Handbook;

use App\Support\Metrics\DefinitionFieldValue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class HandbookPdfDocument
{
    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array<int, array<string, mixed>>  $approvals
     * @param  array<int, array{name:string, history:array<int, array<string, mixed>>}>  $revisionHistory
     * @param  array{title:string,subject:string,author:string,creator:string,generated_at:string}  $metadata
     * @param  array<int, array{metric_name:string,status_label:string,status_tone:string,owner:string,next_step:string,version:string}>  $executiveMetricRows
     */
    public function __construct(
        public readonly string $organizationName,
        public readonly string $generatedAt,
        public readonly string $generatedDate,
        public readonly string $generatedBy,
        public readonly array $metadata,
        public readonly array $executiveBrief,
        public readonly array $executiveMetricRows,
        public readonly array $metrics,
        public readonly array $approvals,
        public readonly array $revisionHistory
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $executiveBrief = is_array($payload['executive_brief'] ?? null)
            ? $payload['executive_brief']
            : [];
        $metrics = is_array($payload['metrics'] ?? null)
            ? array_values(array_filter($payload['metrics'], 'is_array'))
            : [];
        $metricSections = array_map([self::class, 'buildMetricSection'], $metrics);

        return new self(
            organizationName: trim((string) (($payload['organization']['name'] ?? '') ?: '')),
            generatedAt: self::isoString($payload['generated_at'] ?? null),
            generatedDate: self::dateString($payload['generated_at'] ?? null),
            generatedBy: trim((string) (($payload['generated_by']['name'] ?? '') ?: 'System')),
            metadata: self::buildMetadata($payload),
            executiveBrief: self::buildExecutiveBrief($executiveBrief, $metrics),
            executiveMetricRows: self::buildExecutiveMetricRows($metricSections),
            metrics: $metricSections,
            approvals: self::buildApprovals($payload['approvals'] ?? null),
            revisionHistory: self::buildRevisionHistory($metrics),
        );
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private static function buildExecutiveBrief(array $brief, array $metrics): array
    {
        $engagementTitle = self::text($brief['engagement_title'] ?? null);

        return [
            'engagement_title' => $engagementTitle,
            'date_range' => self::humanDateRange($brief['starts_on'] ?? null, $brief['ends_on'] ?? null),
            'total_metric_count' => (int) ($brief['total_metric_count'] ?? count($metrics)),
            'approved_metric_count' => (int) ($brief['approved_metric_count'] ?? 0),
            'operational_ready_count' => (int) ($brief['operational_ready_count'] ?? 0),
            'needs_follow_up_count' => (int) ($brief['needs_follow_up_count'] ?? 0),
            'implementation_gap_count' => (int) ($brief['implementation_gap_count'] ?? 0),
            'next_review_on' => self::humanDateString($brief['next_review_on'] ?? null),
            'review_cadence' => self::text($brief['review_cadence'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title:string,subject:string,author:string,creator:string,generated_at:string}
     */
    private static function buildMetadata(array $payload): array
    {
        $organizationName = trim((string) (($payload['organization']['name'] ?? '') ?: 'Organization'));
        $generatedBy = trim((string) (($payload['generated_by']['name'] ?? '') ?: 'System'));
        $engagementTitle = self::text($payload['executive_brief']['engagement_title'] ?? null);
        $subject = $engagementTitle === null
            ? 'Metrics handbook export'
            : 'Metrics handbook export for '.$engagementTitle;

        return [
            'title' => 'Metrics Handbook - '.$organizationName,
            'subject' => $subject,
            'author' => $generatedBy,
            'creator' => 'Metric Harmony Console',
            'generated_at' => self::isoString($payload['generated_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<string, mixed>
     */
    private static function buildMetricSection(array $metric): array
    {
        $fields = self::normalizeFields($metric['fields'] ?? null);
        $metricName = trim((string) ($metric['name'] ?? ''));
        $groupedFields = self::groupFields($fields);
        $operationalStatusKey = self::text($metric['operational_status'] ?? null);
        $implementationGaps = is_array($metric['implementation_gaps'] ?? null)
            ? $metric['implementation_gaps']
            : [];

        $sections = self::buildExecutiveSections($groupedFields, $metric);

        return [
            'name' => $metricName,
            'executive_takeaway' => self::text($metric['executive_takeaway'] ?? null),
            'version' => self::text($metric['version'] ?? null),
            'signed_off_at' => self::text($metric['signed_off_at'] ?? null) ?? 'Pending',
            'signed_off_by' => self::text($metric['signed_off_by'] ?? null) ?? '—',
            'alignment_summary' => self::text($metric['alignment_summary'] ?? null),
            'approval_version_rows' => self::buildApprovalVersionRows($metric, $groupedFields),
            'approval_strip_segments' => self::buildApprovalStripSegments($metric),
            'operational_status_label' => match ($operationalStatusKey) {
                'operationally_ready' => 'Ready for Use',
                'needs_follow_up' => 'Implementation Follow-up Needed',
                default => null,
            },
            'operational_status_notes' => self::text($metric['operational_status_notes'] ?? null),
            'implementation_gap_rows' => self::buildImplementationGapRows($implementationGaps),
            'validation_guidance_rows' => self::buildValidationGuidanceRows($metric),
            'notes' => self::text($metric['notes'] ?? null) ?? '—',
            'executive_sections' => $sections,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groupedFields
     * @param  array<string, mixed>  $metric
     * @return array<int, array{title:string,items:array<int, array<string, mixed>>}>
     */
    private static function buildExecutiveSections(array $groupedFields, array $metric): array
    {
        $definitionFields = array_values(array_filter(
            $groupedFields,
            static fn (array $field): bool => ! self::isOwnerField($field)
        ));

        $sections = [
            'official_definition' => [
                'title' => 'Official Definition',
                'items' => [],
            ],
            'measurement_logic_rules' => [
                'title' => 'Measurement Logic & Rules',
                'items' => [],
            ],
            'controls_caveats' => [
                'title' => 'Controls & Caveats',
                'items' => [],
            ],
        ];

        foreach ($definitionFields as $field) {
            $sectionKey = $field['executive_section'] ?? 'official_definition';
            if (! isset($sections[$sectionKey])) {
                $sectionKey = 'official_definition';
            }
            $sections[$sectionKey]['items'][] = $field;
        }

        return array_values(array_filter(
            $sections,
            fn (array $section) => count($section['items']) > 0
        ));
    }

    /**
     * @param  array<string, mixed>  $metric
     * @param  array<int, array<string, mixed>>  $groupedFields
     * @return array<int, array{label:string,value:string}>
     */
    private static function buildApprovalVersionRows(array $metric, array $groupedFields): array
    {
        $ownerField = self::resolveOwnerField($groupedFields);

        return array_values(array_filter([
            self::row('Metric Owner', $ownerField['value'] ?? null),
            self::row('Current Version', self::versionLabel($metric['version'] ?? null)),
            self::row('Signed Off On', self::text($metric['signed_off_at'] ?? null)),
            self::row('Approved By', self::text($metric['signed_off_by'] ?? null)),
        ]));
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<int, string>
     */
    private static function buildApprovalStripSegments(array $metric): array
    {
        return array_values(array_filter([
            ($approvedOn = self::humanDateString($metric['signed_off_at'] ?? null)) !== null ? 'Approved '.$approvedOn : null,
            self::versionLabel($metric['version'] ?? null),
            self::text($metric['signed_off_by'] ?? null),
        ]));
    }

    /**
     * @return array<int, array{metric_name:string,signed_off_at:string,signed_off_by_name:string}>
     */
    private static function buildApprovals(mixed $approvals): array
    {
        if (! is_array($approvals)) {
            return [];
        }

        $entries = [];

        foreach ($approvals as $approval) {
            if (! is_array($approval)) {
                continue;
            }

            $entries[] = [
                'metric_name' => self::text($approval['metric_name'] ?? null) ?? '—',
                'signed_off_at' => self::text($approval['signed_off_at'] ?? null) ?? '—',
                'signed_off_by_name' => self::text($approval['signed_off_by_name'] ?? null) ?? '—',
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricSections
     * @return array<int, array{metric_name:string,status_label:string,status_tone:string,owner:string,next_step:string,version:string}>
     */
    private static function buildExecutiveMetricRows(array $metricSections): array
    {
        return array_values(array_map(
            static function (array $metric): array {
                $statusLabel = self::text($metric['operational_status_label'] ?? null) ?? '—';

                return [
                    'metric_name' => self::text($metric['name'] ?? null) ?? '—',
                    'status_label' => $statusLabel,
                    'status_tone' => match ($statusLabel) {
                        'Ready for Use' => 'ready',
                        'Implementation Follow-up Needed' => 'follow-up',
                        default => 'neutral',
                    },
                    'owner' => self::findLabeledRowValue($metric['approval_version_rows'] ?? null, 'Metric Owner') ?? '—',
                    'next_step' => self::findLabeledRowValue($metric['implementation_gap_rows'] ?? null, 'Suggested next step') ?? '—',
                    'version' => self::findLabeledRowValue($metric['approval_version_rows'] ?? null, 'Current Version') ?? '—',
                ];
            },
            $metricSections
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array<int, array{name:string, history:array<int, array<string, mixed>>}>
     */
    private static function buildRevisionHistory(array $metrics): array
    {
        $sections = [];

        foreach ($metrics as $metric) {
            $history = is_array($metric['official_definition_history'] ?? null)
                ? array_values(array_filter($metric['official_definition_history'], 'is_array'))
                : [];

            $currentVersion = self::buildCurrentRevisionHistoryEntry($metric);

            if ($currentVersion !== null) {
                array_unshift($history, $currentVersion);
            }

            if ($history === []) {
                continue;
            }

            $sections[] = [
                'name' => self::text($metric['name'] ?? null) ?? '—',
                'history' => array_map(
                    static fn (array $entry): array => [
                        'version' => $entry['version'] ?? '—',
                        'approved_on' => self::text($entry['signed_off_at'] ?? $entry['approved_on'] ?? null) ?? '—',
                        'approved_by' => self::text($entry['signed_off_by']['name'] ?? $entry['approved_by'] ?? null) ?? '—',
                    ],
                    $history
                ),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<string, mixed>|null
     */
    private static function buildCurrentRevisionHistoryEntry(array $metric): ?array
    {
        $approvedOn = self::text($metric['signed_off_at'] ?? null);

        if ($approvedOn === null) {
            return null;
        }

        return [
            'version' => $metric['version'] ?? '—',
            'approved_on' => $approvedOn,
            'approved_by' => self::text($metric['signed_off_by'] ?? null) ?? '—',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, 'is_array'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function groupFields(array $fields): array
    {
        $fieldsByKey = [];
        $fieldKeys = [];

        foreach ($fields as $field) {
            $fieldKey = self::text($field['key'] ?? null);

            if ($fieldKey === null) {
                continue;
            }

            $fieldsByKey[$fieldKey] = $field;
            $fieldKeys[] = $fieldKey;
        }

        $companionsByBase = [];
        $attachedCompanionKeys = [];

        foreach ($fields as $field) {
            $fieldKey = self::text($field['key'] ?? null);

            if ($fieldKey === null) {
                continue;
            }

            $baseKey = self::resolveCompanionBaseKey($field, $fieldKey, $fieldsByKey, $fieldKeys);

            if ($baseKey === null) {
                continue;
            }

            $companionsByBase[$baseKey] ??= [];
            $companionsByBase[$baseKey][] = $field;
            $attachedCompanionKeys[$fieldKey] = true;
        }

        $grouped = [];

        foreach ($fields as $field) {
            $fieldKey = self::text($field['key'] ?? null);

            if ($fieldKey === null || isset($attachedCompanionKeys[$fieldKey])) {
                continue;
            }

            $value = $field['value'] ?? null;

            if (! DefinitionFieldValue::isFilled($value)) {
                continue;
            }

            $grouped[] = [
                'key' => $fieldKey,
                'label' => self::displayFieldLabel($field, $fieldKey),
                'value' => self::displayValue($field),
                'executive_section' => $field['executive_section'] ?? null,
                'companions' => array_values(array_filter(
                    array_map(
                        static fn (array $companion): ?array => DefinitionFieldValue::isFilled($companion['value'] ?? null)
                            ? [
                                'key' => self::text($companion['key'] ?? null) ?? 'notes',
                                'label' => self::displayFieldLabel($companion, 'Notes'),
                                'value' => self::displayValue($companion),
                            ]
                            : null,
                        $companionsByBase[$fieldKey] ?? []
                    )
                )),
            ];

            $rationale = self::text($field['rationale'] ?? null);

            if ($rationale !== null) {
                $grouped[array_key_last($grouped)]['rationale'] = $rationale;
            }
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, array<string, mixed>>  $fieldsByKey
     * @param  array<int, string>  $fieldKeys
     */
    private static function resolveCompanionBaseKey(
        array $field,
        string $fieldKey,
        array $fieldsByKey,
        array $fieldKeys
    ): ?string {
        $companionOf = self::text($field['companion_of'] ?? null);

        if (
            $companionOf !== null
            && $companionOf !== $fieldKey
            && isset($fieldsByKey[$companionOf])
        ) {
            return $companionOf;
        }

        if (! str_ends_with($fieldKey, '_notes')) {
            return null;
        }

        $prefix = substr($fieldKey, 0, -strlen('_notes'));

        if (isset($fieldsByKey[$prefix])) {
            return $prefix;
        }

        foreach ($fieldKeys as $candidateKey) {
            if (
                $candidateKey !== $fieldKey
                && ! str_ends_with($candidateKey, '_notes')
                && str_starts_with($candidateKey, $prefix.'_')
            ) {
                return $candidateKey;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $implementationGaps
     * @return array<int, array{label:string,value:string}>
     */
    private static function buildImplementationGapRows(array $implementationGaps): array
    {
        $nextStep = self::text($implementationGaps['suggested_next_step'] ?? null)
            ?? self::text($implementationGaps['next_step'] ?? null);

        return array_values(array_filter([
            self::row('Current state', self::text($implementationGaps['current_state'] ?? null)),
            self::row('Gap', self::text($implementationGaps['gap'] ?? null)),
            self::row('Responsible Team', self::text($implementationGaps['owner'] ?? null)),
            self::row('Suggested next step', $nextStep),
        ]));
    }

    /**
     * @param  array<string, mixed>  $metric
     * @return array<int, array{label:string,value:string}>
     */
    private static function buildValidationGuidanceRows(array $metric): array
    {
        return array_values(array_filter([
            self::row('Validation steps', self::text($metric['validation_steps'] ?? null)),
            self::row('Common mismatch causes', self::text($metric['common_mismatch_causes'] ?? null)),
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $groupedFields
     * @return array{key:string,label:string,value:string}|null
     */
    private static function resolveOwnerField(array $groupedFields): ?array
    {
        foreach ($groupedFields as $field) {
            if (self::isOwnerField($field)) {
                $key = self::text($field['key'] ?? null);
                $label = self::text($field['label'] ?? null);
                $value = self::text($field['value'] ?? null);

                if ($value === null) {
                    continue;
                }

                return [
                    'key' => $key ?? 'owner',
                    'label' => $label ?? 'Metric Owner',
                    'value' => $value,
                ];
            }
        }

        return null;
    }

    private static function findLabeledRowValue(mixed $rows, string $label): ?string
    {
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (($row['label'] ?? null) !== $label) {
                continue;
            }

            return self::text($row['value'] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function displayFieldLabel(array $field, string $fallback): string
    {
        $label = self::text($field['label'] ?? null) ?? $fallback;
        $normalizedLabel = strtolower((string) preg_replace('/\s+/', ' ', $label));

        if (str_contains($normalizedLabel, 'pain points')) {
            return 'Known Pain Points';
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function isOwnerField(array $field): bool
    {
        $key = strtolower(self::text($field['key'] ?? null) ?? '');
        $label = strtolower(self::text($field['label'] ?? null) ?? '');

        return str_contains($key, 'owner') || str_contains($label, 'owner');
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function displayValue(array $field): string
    {
        $displayValue = self::text($field['display_value'] ?? null);

        if ($displayValue !== null) {
            return $displayValue;
        }

        $value = $field['value'] ?? null;

        if (is_array($value)) {
            return implode(', ', array_map(
                static fn (mixed $entry): string => is_scalar($entry) ? (string) $entry : '',
                $value
            ));
        }

        return is_scalar($value) ? (string) $value : '—';
    }

    /**
     * @return array{label:string,value:string}|null
     */
    private static function row(string $label, ?string $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    private static function versionLabel(mixed $value): ?string
    {
        $version = self::text($value);

        return $version === null ? null : 'Version '.$version;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function isoString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return self::text($value) ?? '';
    }

    private static function dateString(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        $text = self::text($value);

        if ($text === null) {
            return '';
        }

        try {
            return CarbonImmutable::parse($text)->toDateString();
        } catch (\Throwable) {
            return $text;
        }
    }

    private static function humanDateString(mixed $value): ?string
    {
        $date = self::parseDate($value);

        if ($date === null) {
            return self::text($value);
        }

        return $date->format('F j, Y');
    }

    private static function humanDateRange(mixed $startsOn, mixed $endsOn): ?string
    {
        $startDate = self::parseDate($startsOn);
        $endDate = self::parseDate($endsOn);

        if ($startDate !== null && $endDate !== null) {
            if ($startDate->year === $endDate->year) {
                if ($startDate->month === $endDate->month) {
                    return sprintf(
                        '%s %d-%d, %d',
                        $startDate->format('F'),
                        $startDate->day,
                        $endDate->day,
                        $startDate->year
                    );
                }

                return sprintf(
                    '%s %d - %s %d, %d',
                    $startDate->format('F'),
                    $startDate->day,
                    $endDate->format('F'),
                    $endDate->day,
                    $startDate->year
                );
            }

            return sprintf(
                '%s - %s',
                $startDate->format('F j, Y'),
                $endDate->format('F j, Y')
            );
        }

        return match (true) {
            $startDate !== null => self::humanDateString($startsOn),
            $endDate !== null => self::humanDateString($endsOn),
            self::text($startsOn) !== null && self::text($endsOn) !== null => self::text($startsOn).' to '.self::text($endsOn),
            self::text($startsOn) !== null => self::text($startsOn),
            self::text($endsOn) !== null => self::text($endsOn),
            default => null,
        };
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }
}
