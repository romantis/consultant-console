<?php

namespace App\Actions\Handbook;

use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\ReconciliationWorkshop;
use App\Models\User;
use App\Support\Metrics\DefinitionFieldValue;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Metrics\FieldRoleSchema;
use App\Support\Metrics\ValidationGuidanceFields;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BuildHandbookExportPayload
{
    public function __construct(
        private readonly SelectOfficialMetrics $officialSelector
    ) {}

    /**
     * @return array{payload:array<string, mixed>, source_checksum:string}
     */
    public function build(
        Organization $organization,
        EloquentCollection $metrics,
        ?User $actor = null,
        ?CarbonInterface $generatedAt = null
    ): array {
        $metrics = $this->officialSelector->handle($organization, $metrics);
        $metricsPayload = $this->buildMetricsPayload($metrics);
        $approvals = $this->buildApprovalsPayload($metrics);
        $executiveBrief = $this->buildExecutiveBriefPayload($metrics, $metricsPayload, $approvals);

        $payload = [
            'organization' => [
                'id' => (string) $organization->getKey(),
                'name' => $organization->name,
            ],
            'next_review_on' => $executiveBrief['next_review_on'],
            'review_cadence' => $executiveBrief['review_cadence'],
            'executive_brief' => $executiveBrief,
            'approvals' => $approvals,
            'metrics' => $metricsPayload,
            'generated_at' => $generatedAt ?? now(),
            'generated_by' => $actor ? [
                'id' => (string) $actor->getKey(),
                'name' => $actor->name,
            ] : [
                'id' => null,
                'name' => 'System',
            ],
        ];

        $sourcePayload = $this->buildSourcePayload(
            $payload['organization'],
            $metricsPayload,
            $executiveBrief,
            $approvals
        );

        return [
            'payload' => $payload,
            'source_checksum' => $this->checksum($sourcePayload),
        ];
    }

    public function sourceChecksum(
        Organization $organization,
        EloquentCollection $metrics
    ): string {
        $metrics = $this->officialSelector->handle($organization, $metrics);
        $metricsPayload = $this->buildMetricsPayload($metrics);
        $approvals = $this->buildApprovalsPayload($metrics);
        $executiveBrief = $this->buildExecutiveBriefPayload($metrics, $metricsPayload, $approvals);

        $sourcePayload = $this->buildSourcePayload(
            [
                'id' => (string) $organization->getKey(),
                'name' => $organization->name,
            ],
            $metricsPayload,
            $executiveBrief,
            $approvals
        );

        return $this->checksum($sourcePayload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMetricsPayload(EloquentCollection $metrics): array
    {
        $metrics->loadMissing([
            'engagement:id,title,starts_on,ends_on,review_cadence,next_review_on',
            'engagement.metrics.template:id,form_schema_json',
        ]);
        $this->loadWorkshopSnapshots($metrics);

        return $metrics
            ->sortBy(fn (Metric $metric) => sprintf(
                '%s|%s',
                Str::lower($metric->name ?? ''),
                (string) $metric->getKey()
            ))
            ->values()
            ->map(function (Metric $metric) {
                $officialDefinition = $this->currentOfficialDefinition($metric);
                $currentMetric = $this->sourceMetricForExport($metric, $officialDefinition);
                $optionLabelMap = FieldOptionLabelResolver::forMetric($currentMetric);
                $fieldRoles = $this->resolveFieldRoles($metric, $officialDefinition);
                $fieldRationales = $this->resolveFieldRationales($metric, $officialDefinition);

                $fields = $this->buildFieldsPayload(
                    $officialDefinition?->definition_json ?? [],
                    $optionLabelMap,
                    collect($fieldRoles),
                    $fieldRationales
                );

                if ($officialDefinition?->owner_participant_id && $officialDefinition->ownerParticipant) {
                    array_unshift($fields, [
                        'key' => 'owner_participant',
                        'label' => 'Metric Owner',
                        'value' => $officialDefinition->ownerParticipant->user?->name ?? 'Pending invite',
                        'executive_section' => 'official_definition',
                    ]);
                }

                return [
                    'id' => (string) $currentMetric->getKey(),
                    'name' => $currentMetric->name,
                    'version' => $officialDefinition?->version,
                    'signed_off_at' => optional($officialDefinition?->signed_off_at)->toIso8601String(),
                    'signed_off_by' => $officialDefinition?->signedOffBy?->name,
                    'fields' => $fields,
                    'notes' => $officialDefinition?->notes,
                    'alignment_summary' => $officialDefinition?->alignment_summary,
                    'operational_status' => $officialDefinition?->operational_status,
                    'operational_status_notes' => $officialDefinition?->operational_status_notes,
                    'implementation_gaps' => $officialDefinition?->implementation_gaps,
                    'validation_steps' => $officialDefinition?->validation_steps,
                    'common_mismatch_causes' => $officialDefinition?->common_mismatch_causes,
                    'executive_takeaway' => $officialDefinition?->executive_takeaway,
                    'official_definition_history' => $this->buildHistoryPayload($metric),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveFieldRoles(
        Metric $metric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition
    ): array {
        $schema = $this->resolveTemplateSchema($metric, $officialDefinition);
        if (! $schema) {
            return [];
        }

        $payload = FieldRoleSchema::forTemplateSchema($schema);

        return is_array($payload['field_roles'] ?? null)
            ? $payload['field_roles']
            : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTemplateSchema(
        Metric $engagementMetric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition
    ): ?array {
        $engagementMetric->loadMissing('template:id,form_schema_json');
        $schema = $engagementMetric->template?->form_schema_json;
        if (is_array($schema)) {
            return $schema;
        }

        if ($officialDefinition instanceof OrgFinalDefinition) {
            $officialDefinition->loadMissing('metricTemplate:id,form_schema_json', 'metric.template:id,form_schema_json');

            $templateSchema = $officialDefinition->metricTemplate?->form_schema_json;
            if (is_array($templateSchema)) {
                return $templateSchema;
            }

            $metricSchema = $officialDefinition->metric?->template?->form_schema_json;
            if (is_array($metricSchema)) {
                return $metricSchema;
            }
        }

        if ($officialDefinition instanceof FinalDefinition) {
            $officialDefinition->loadMissing('metric.template:id,form_schema_json');

            $metricSchema = $officialDefinition->metric?->template?->form_schema_json;
            if (is_array($metricSchema)) {
                return $metricSchema;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{metric_name:string,signed_off_at:?string,signed_off_by_name:?string}>
     */
    private function buildApprovalsPayload(EloquentCollection $metrics): array
    {
        return $metrics
            ->map(function (Metric $metric): ?array {
                $officialDefinition = $this->currentOfficialDefinition($metric);

                if (! $officialDefinition?->signed_off_at) {
                    return null;
                }

                $currentMetric = $this->sourceMetricForExport($metric, $officialDefinition);

                return [
                    'metric_name' => $currentMetric->name,
                    'signed_off_at' => optional($officialDefinition->signed_off_at)->toIso8601String(),
                    'signed_off_by_name' => $officialDefinition->signedOffBy?->name,
                ];
            })
            ->filter()
            ->sortBy(fn (array $approval) => Str::lower($approval['metric_name']))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricsPayload
     * @param  array<int, array{metric_name:string,signed_off_at:?string,signed_off_by_name:?string}>  $approvals
     * @return array{
     *   engagement_id:?string,
     *   engagement_title:?string,
     *   starts_on:?string,
     *   ends_on:?string,
     *   total_metric_count:int,
     *   approved_metric_count:int,
     *   operational_ready_count:int,
     *   needs_follow_up_count:int,
     *   implementation_gap_count:int,
     *   next_review_on:?string,
     *   review_cadence:?string
     * }
     */
    private function buildExecutiveBriefPayload(
        EloquentCollection $metrics,
        array $metricsPayload,
        array $approvals
    ): array {
        $engagement = $this->resolveSingleEngagementContext($metrics);
        $payload = collect($metricsPayload);

        return [
            'engagement_id' => $engagement ? (string) $engagement->getKey() : null,
            'engagement_title' => $engagement?->title,
            'starts_on' => optional($engagement?->starts_on)->toDateString(),
            'ends_on' => optional($engagement?->ends_on)->toDateString(),
            'total_metric_count' => $payload->count(),
            'approved_metric_count' => count($approvals),
            'operational_ready_count' => $payload
                ->where('operational_status', 'operationally_ready')
                ->count(),
            'needs_follow_up_count' => $payload
                ->where('operational_status', 'needs_follow_up')
                ->count(),
            'implementation_gap_count' => $payload
                ->filter(fn (array $metric): bool => $this->hasImplementationGap($metric['implementation_gaps'] ?? null))
                ->count(),
            'next_review_on' => optional($engagement?->next_review_on)->toDateString(),
            'review_cadence' => $engagement?->review_cadence,
        ];
    }

    /**
     * @return array<int, array{
     *   id:string,
     *   version:int|null,
     *   status:string|null,
     *   signed_off_at:?string,
     *   signed_off_by:?array{id:mixed,name:mixed},
     *   metric:?array{id:string,name:?string,engagement:?array{id:mixed,title:mixed}}
     * }>
     */
    private function buildHistoryPayload(Metric $metric): array
    {
        $history = $metric->getRelationValue('officialDefinitionHistory');

        if (! $history instanceof EloquentCollection || $history->isEmpty()) {
            return [];
        }

        return $history
            ->map(fn (FinalDefinition|OrgFinalDefinition $definition) => $this->buildHistoryEntry($definition))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   id:string,
     *   version:int|null,
     *   status:string|null,
     *   signed_off_at:?string,
     *   signed_off_by:?array{id:mixed,name:mixed},
     *   metric:?array{id:string,name:?string,engagement:?array{id:mixed,title:mixed}}
     * }
     */
    private function buildHistoryEntry(FinalDefinition|OrgFinalDefinition $definition): array
    {
        $metric = $definition instanceof OrgFinalDefinition
            ? $definition->metric
            : $definition->metric;
        $engagement = $metric?->engagement;

        return [
            'id' => (string) $definition->getKey(),
            'version' => $definition->version,
            'status' => $definition->status,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'metric' => $metric ? [
                'id' => (string) $metric->getKey(),
                'name' => $metric->name,
                'engagement' => $engagement?->only(['id', 'title']),
            ] : null,
        ];
    }

    private function sourceEngagementForExport(Metric $metric): ?Engagement
    {
        $currentMetric = $this->sourceMetricForExport($metric, $this->currentOfficialDefinition($metric));

        if (! $currentMetric->relationLoaded('engagement')) {
            $currentMetric->loadMissing('engagement:id,title,starts_on,ends_on,review_cadence,next_review_on');
        }

        return $currentMetric->engagement;
    }

    private function resolveSingleEngagementContext(EloquentCollection $metrics): ?Engagement
    {
        $engagements = $metrics
            ->map(fn (Metric $metric) => $this->sourceEngagementForExport($metric))
            ->filter()
            ->values();

        if ($engagements->isEmpty() || $engagements->count() !== $metrics->count()) {
            return null;
        }

        $uniqueIds = $engagements
            ->map(fn (Engagement $engagement) => (string) $engagement->getKey())
            ->unique()
            ->values();

        if ($uniqueIds->count() !== 1) {
            return null;
        }

        /** @var Engagement $engagement */
        $engagement = $engagements->first();

        return $engagement;
    }

    private function sourceMetricForExport(
        Metric $metric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition
    ): Metric {
        if ($officialDefinition instanceof OrgFinalDefinition && $officialDefinition->metric) {
            return $officialDefinition->metric;
        }

        return $metric;
    }

    private function currentOfficialDefinition(Metric $metric): FinalDefinition|OrgFinalDefinition|null
    {
        $officialDefinition = $metric->getRelationValue('officialDefinition');

        if ($officialDefinition instanceof FinalDefinition || $officialDefinition instanceof OrgFinalDefinition) {
            return $officialDefinition;
        }

        $fallback = $metric->finalDefinition;

        return $fallback instanceof FinalDefinition ? $fallback : null;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array<string, string>>  $optionLabelMap
     * @param  Collection<int, mixed>|null  $fieldRoles
     * @param  array<string, string>  $fieldRationales
     * @return array<int, array{key:string,label:string,value:mixed,display_value:string,executive_section:?string,companion_of?:string,rationale?:string}>
     */
    private function buildFieldsPayload(
        array $definition,
        array $optionLabelMap = [],
        $fieldRoles = null,
        array $fieldRationales = []
    ): array {
        $fieldRoles = $fieldRoles ?? collect();

        return collect($definition)
            ->reject(fn (mixed $_field, string|int $key): bool => ValidationGuidanceFields::contains((string) $key))
            ->sortKeys()
            ->map(function ($field, $key) use ($optionLabelMap, $fieldRoles, $fieldRationales) {
                $value = is_array($field) ? ($field['value'] ?? null) : $field;
                $label = is_array($field) ? ($field['label'] ?? null) : null;
                $companionOf = is_array($field) ? ($field['companion_of'] ?? null) : null;
                $companionOf = is_string($companionOf) ? trim($companionOf) : '';

                $roleEntry = $fieldRoles->firstWhere('key', (string) $key);

                $entry = [
                    'key' => (string) $key,
                    'label' => $label ?? Str::headline(str_replace('_', ' ', (string) $key)),
                    'value' => $value,
                    'display_value' => $this->formatFieldValue(
                        $value,
                        $optionLabelMap[(string) $key] ?? []
                    ),
                    'executive_section' => $roleEntry['executive_section'] ?? null,
                ];

                if ($companionOf !== '') {
                    $entry['companion_of'] = $companionOf;
                }

                if (array_key_exists((string) $key, $fieldRationales)) {
                    $entry['rationale'] = $fieldRationales[(string) $key];
                }

                return $entry;
            })
            ->filter(fn (array $entry): bool => DefinitionFieldValue::isFilled($entry['value'] ?? null))
            ->values()
            ->all();
    }

    private function loadWorkshopSnapshots(EloquentCollection $metrics): void
    {
        $sourceMetrics = new EloquentCollection(
            $metrics
                ->map(function (Metric $metric): Metric {
                    return $this->sourceMetricForExport($metric, $this->currentOfficialDefinition($metric));
                })
                ->filter(fn (mixed $metric): bool => $metric instanceof Metric && $metric->exists)
                ->unique(fn (Metric $metric): string => (string) $metric->getKey())
                ->values()
                ->all()
        );

        if ($sourceMetrics->isEmpty()) {
            return;
        }

        $sourceMetrics->loadMissing([
            'workshops:id,metric_id,engagement_id,decision_snapshot,scheduled_at,created_at',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function resolveFieldRationales(
        Metric $metric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition
    ): array {
        $sourceMetric = $this->sourceMetricForExport($metric, $officialDefinition);

        if (! $sourceMetric->relationLoaded('workshops')) {
            return [];
        }

        /** @var ReconciliationWorkshop|null $latestWorkshop */
        $latestWorkshop = $sourceMetric->workshops
            ->filter(function (ReconciliationWorkshop $workshop) use ($sourceMetric): bool {
                if ((string) $workshop->metric_id !== (string) $sourceMetric->getKey()) {
                    return false;
                }

                if ($sourceMetric->engagement_id === null) {
                    return $workshop->engagement_id === null;
                }

                return (string) $workshop->engagement_id === (string) $sourceMetric->engagement_id;
            })
            ->sortByDesc(function (ReconciliationWorkshop $workshop): string {
                return sprintf(
                    '%012d%012d',
                    $workshop->scheduled_at?->getTimestamp() ?? 0,
                    $workshop->created_at?->getTimestamp() ?? 0
                );
            })
            ->first();

        if (! $latestWorkshop) {
            return [];
        }

        $fieldDecisions = $latestWorkshop->decision_snapshot['field_decisions'] ?? null;

        if (! is_array($fieldDecisions)) {
            return [];
        }

        $rationales = [];

        foreach ($fieldDecisions as $fieldKey => $decision) {
            if (! is_array($decision)) {
                continue;
            }

            $rationale = $this->normalizeDecisionText($decision['rationale'] ?? null);

            if ($rationale === null) {
                continue;
            }

            $rationales[(string) $fieldKey] = $rationale;
        }

        return $rationales;
    }

    private function normalizeDecisionText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, string>  $optionLabels
     */
    private function formatFieldValue(mixed $value, array $optionLabels = []): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $entry): string => $this->formatFieldValue($entry, $optionLabels))
                ->filter(fn (string $entry): bool => trim($entry) !== '')
                ->implode(', ');
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '') {
                return '';
            }

            return $optionLabels[$normalized] ?? $normalized;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $organization
     * @param  array<int, array<string, mixed>>  $metrics
     * @param  array{
     *   engagement_id:?string,
     *   engagement_title:?string,
     *   starts_on:?string,
     *   ends_on:?string,
     *   total_metric_count:int,
     *   approved_metric_count:int,
     *   operational_ready_count:int,
     *   needs_follow_up_count:int,
     *   implementation_gap_count:int,
     *   next_review_on:?string,
     *   review_cadence:?string
     * }  $executiveBrief
     * @return array<string, mixed>
     */
    private function buildSourcePayload(
        array $organization,
        array $metrics,
        array $executiveBrief,
        array $approvals
    ): array {
        return [
            'organization' => $organization,
            'executive_brief' => $executiveBrief,
            'next_review_on' => $executiveBrief['next_review_on'],
            'review_cadence' => $executiveBrief['review_cadence'],
            'approvals' => $approvals,
            'metrics' => $metrics,
        ];
    }

    private function hasImplementationGap(mixed $implementationGaps): bool
    {
        if (! is_array($implementationGaps)) {
            return false;
        }

        return collect($implementationGaps)
            ->contains(fn (mixed $value): bool => DefinitionFieldValue::isFilled($value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checksum(array $payload): string
    {
        $normalized = $this->normalizeForChecksum($payload);

        return hash(
            'sha256',
            json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function normalizeForChecksum(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isAssoc = $value !== [] && array_keys($value) !== range(0, count($value) - 1);

        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $key => $entry) {
            $value[$key] = $this->normalizeForChecksum($entry);
        }

        return $value;
    }
}
