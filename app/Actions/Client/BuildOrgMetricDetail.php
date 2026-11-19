<?php

namespace App\Actions\Client;

use App\Enums\MetricStatus;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Support\Metrics\ExecutiveDefinitionSections;
use App\Support\Metrics\FieldRoleSchema;
use Illuminate\Support\Str;

class BuildOrgMetricDetail
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        OrgMetric $orgMetric,
        ?Metric $engagementMetric = null
    ): array {
        $orgMetric->loadMissing(['organization:id,name', 'canonicalMetric:id,name']);

        $definitions = OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereNotNull('signed_off_at')
            ->with([
                'signedOffBy:id,name',
                'ownerParticipant.user:id,name,email',
                'metric:id,engagement_id,org_metric_id,metric_template_id,name,status,last_submitted_at',
                'metric.engagement:id,title',
                'metric.template:id,form_schema_json',
                'metricTemplate:id,form_schema_json',
            ])
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get();

        $officialDefinition = $definitions->first();
        $history = $definitions;

        $historyPayload = $history
            ->filter(fn (FinalDefinition|OrgFinalDefinition $definition) => ! $officialDefinition
                || $definition->getKey() !== $officialDefinition->getKey())
            ->map(fn (FinalDefinition|OrgFinalDefinition $definition) => $this->buildHistoryEntry($definition))
            ->values()
            ->all();

        $engagementFieldRolePayload = $engagementMetric
            ? FieldRoleSchema::forMetric($engagementMetric)
            : null;
        $engagementPayload = $engagementMetric
            ? $this->buildEngagementMetricPayload($engagementMetric, $engagementFieldRolePayload)
            : null;
        $officialDefinitionPayload = $this->buildDefinitionPayload($officialDefinition);
        $officialDefinitionJson = is_array($officialDefinition?->definition_json ?? null)
            ? $officialDefinition->definition_json
            : [];
        $fieldRoles = $this->resolveFieldRoles(
            $engagementMetric,
            $officialDefinition,
            $engagementFieldRolePayload
        );
        $owner = null;
        if (
            is_array($officialDefinitionPayload)
            && is_array($officialDefinitionPayload['owner'] ?? null)
        ) {
            $owner = $officialDefinitionPayload['owner'];
        }
        $executiveSections = ExecutiveDefinitionSections::build(
            $officialDefinitionJson,
            $fieldRoles,
            $owner
        );

        return [
            'org_metric' => [
                'id' => (string) $orgMetric->getKey(),
                'key' => $orgMetric->key,
                'slug' => $orgMetric->slug,
                'name' => $orgMetric->canonicalMetric?->name ?? $orgMetric->name,
                'alias' => $orgMetric->alias,
                'organization' => $orgMetric->organization?->only(['id', 'name']),
            ],
            'official_definition' => $officialDefinitionPayload,
            'official_definition_history' => $historyPayload,
            'engagement_metric' => $engagementPayload,
            'executive_sections' => $executiveSections,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDefinitionPayload(FinalDefinition|OrgFinalDefinition|null $definition): ?array
    {
        if (! $definition) {
            return null;
        }

        $definition->loadMissing('ownerParticipant.user', 'signedOffBy', 'metric.engagement');

        return [
            'id' => (string) $definition->getKey(),
            'status' => $definition->status,
            'version' => $definition->version,
            'definition_json' => $definition->definition_json ?? [],
            'notes' => $definition->notes,
            'alignment_summary' => $definition->alignment_summary,
            'operational_status' => $definition->operational_status,
            'operational_status_notes' => $definition->operational_status_notes,
            'implementation_gaps' => $definition->implementation_gaps,
            'validation_steps' => $definition->validation_steps,
            'common_mismatch_causes' => $definition->common_mismatch_causes,
            'owner_participant_id' => $definition->owner_participant_id
                ? (string) $definition->owner_participant_id
                : null,
            'owner_participant' => $definition->ownerParticipant ? [
                'id' => (string) $definition->ownerParticipant->getKey(),
                'responsibility' => $definition->ownerParticipant->responsibility,
                'user' => $definition->ownerParticipant->user?->only(['id', 'name', 'email']),
            ] : null,
            'owner' => $this->resolveOwnerField($definition),
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'metric' => $definition->metric ? [
                'id' => (string) $definition->metric->getKey(),
                'name' => $definition->metric->name,
                'engagement' => $definition->metric->engagement?->only(['id', 'title']),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoryEntry(FinalDefinition|OrgFinalDefinition $definition): array
    {
        $definition->loadMissing('signedOffBy', 'metric.engagement');

        return [
            'id' => (string) $definition->getKey(),
            'version' => $definition->version,
            'status' => $definition->status,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'metric' => $definition->metric ? [
                'id' => (string) $definition->metric->getKey(),
                'name' => $definition->metric->name,
                'engagement' => $definition->metric->engagement?->only(['id', 'title']),
            ] : null,
        ];
    }

    /**
     * @return array{key: string, label: string, value: mixed, participant_id?: string, user?: array}|null
     */
    private function resolveOwnerField(FinalDefinition|OrgFinalDefinition $definition): ?array
    {
        if ($definition->owner_participant_id && $definition->ownerParticipant) {
            return [
                'key' => 'owner_participant',
                'label' => 'Metric Owner',
                'value' => $definition->ownerParticipant->user?->name ?? 'Pending invite',
                'participant_id' => (string) $definition->ownerParticipant->getKey(),
                'user' => $definition->ownerParticipant->user?->only(['id', 'name', 'email']),
            ];
        }

        return $this->extractOwnerField($definition->definition_json ?? []);
    }

    /**
     * @param  array{field_roles?: array<int, array<string, mixed>>}|null  $fieldRolePayload
     * @return array{metric: array{id: string, status: string, last_submitted_at: string|null, field_roles: array<int, array<string, mixed>>}}
     */
    private function buildEngagementMetricPayload(Metric $metric, ?array $fieldRolePayload = null): array
    {
        $resolvedFieldRoles = is_array($fieldRolePayload['field_roles'] ?? null)
            ? $fieldRolePayload['field_roles']
            : [];

        return [
            'metric' => [
                'id' => (string) $metric->getKey(),
                'status' => $metric->status instanceof MetricStatus
                    ? $metric->status->value
                    : (string) $metric->status,
                'last_submitted_at' => optional($metric->last_submitted_at)->toIso8601String(),
                'field_roles' => $resolvedFieldRoles,
            ],
        ];
    }

    /**
     * @param  array{field_roles?: array<int, array<string, mixed>>}|null  $engagementFieldRolePayload
     * @return array<int, array<string, mixed>>
     */
    private function resolveFieldRoles(
        ?Metric $engagementMetric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition,
        ?array $engagementFieldRolePayload = null
    ): array {
        $engagementFieldRoles = is_array($engagementFieldRolePayload['field_roles'] ?? null)
            ? $engagementFieldRolePayload['field_roles']
            : null;

        if ($engagementFieldRoles !== null) {
            return $engagementFieldRoles;
        }

        $schema = $this->resolveTemplateSchema($engagementMetric, $officialDefinition);
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
        ?Metric $engagementMetric,
        FinalDefinition|OrgFinalDefinition|null $officialDefinition
    ): ?array {
        if ($engagementMetric) {
            $engagementMetric->loadMissing('template:id,form_schema_json');
            $schema = $engagementMetric->template?->form_schema_json;
            if (is_array($schema)) {
                return $schema;
            }
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
     * @param  array<string, array<string, mixed>>  $definition
     * @return array{key: string, label: string, value: mixed}|null
     */
    private function extractOwnerField(array $definition): ?array
    {
        foreach ($definition as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? Str::headline((string) $key);
            $value = $field['value'] ?? null;
            $normalizedKey = Str::of((string) $key)->lower()->value();
            $normalizedLabel = Str::of($label)->lower()->value();

            if (str_contains($normalizedKey, 'owner') || str_contains($normalizedLabel, 'owner')) {
                return [
                    'key' => (string) $key,
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return null;
    }
}
