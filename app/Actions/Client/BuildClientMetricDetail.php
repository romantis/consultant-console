<?php

namespace App\Actions\Client;

use App\Enums\MetricStatus;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Metrics\FieldRoleSchema;
use App\Support\Metrics\FinalDefinitionRequirements;
use App\Support\Metrics\ValidationGuidanceFields;
use Illuminate\Support\Str;

class BuildClientMetricDetail
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Metric $metric): array
    {
        $metric->loadMissing([
            'definitions.user',
            'discrepancies',
            'engagement:id,title,status',
            'template',
        ]);

        $finalDefinitions = FinalDefinition::query()
            ->where('metric_id', $metric->getKey())
            ->with(['signedOffBy:id,name', 'ownerParticipant.user:id,name,email'])
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->get();

        $currentFinal = $finalDefinitions->first(fn (FinalDefinition $definition) => $definition->signed_off_at);
        $currentDraft = $finalDefinitions->first(fn (FinalDefinition $definition) => ! $definition->signed_off_at);

        $history = $finalDefinitions
            ->filter(fn (FinalDefinition $definition) => $definition->signed_off_at)
            ->filter(fn (FinalDefinition $definition) => ! $currentFinal || $definition->getKey() !== $currentFinal->getKey())
            ->map(fn (FinalDefinition $definition) => $this->buildHistoryEntry($definition))
            ->values()
            ->all();

        $matrixPayload = $this->buildDiscrepancyMatrix($metric);
        $fieldRolePayload = FieldRoleSchema::forMetric($metric);

        $latestDefinition = $finalDefinitions->first();
        $requirements = FinalDefinitionRequirements::forMetric($metric, $latestDefinition);

        return [
            'metric' => [
                'id' => (string) $metric->getKey(),
                'name' => $metric->name,
                'status' => $metric->status instanceof MetricStatus
                    ? $metric->status->value
                    : (string) $metric->status,
                'engagement' => $metric->engagement?->only(['id', 'title', 'status']),
                'last_submitted_at' => optional($metric->last_submitted_at)->toIso8601String(),
                'matrix' => $matrixPayload['matrix'],
                'context_fields' => $matrixPayload['context_fields'],
                'field_roles' => $fieldRolePayload['field_roles'] ?? [],
                'definitions' => $matrixPayload['definitions'],
            ],
            'draft_definition' => $this->buildDefinitionPayload($currentDraft),
            'final_definition' => $this->buildDefinitionPayload($currentFinal),
            'final_definition_history' => $history,
            'final_definition_requirements' => $requirements,
        ];
    }

    /**
     * @return array{matrix: array<int, array<string, mixed>>, context_fields: array<int, array<string, mixed>>, definitions: array<int, array<string, mixed>>}
     */
    private function buildDiscrepancyMatrix(Metric $metric): array
    {
        $discrepanciesByField = $metric->discrepancies->keyBy('field_key');
        $matrix = [];
        $metaMatrix = [];
        $optionLabelMap = FieldOptionLabelResolver::forMetric($metric);

        foreach ($metric->definitions as $definition) {
            foreach ($definition->normalizedFields() as $fieldKey => $field) {
                if (! is_string($fieldKey) || ValidationGuidanceFields::contains($fieldKey)) {
                    continue;
                }

                $matrix[$fieldKey]['label'] = $field['label'] ?? Str::headline($fieldKey);
                $matrix[$fieldKey]['type'] = $field['type'] ?? 'text';
                $matrix[$fieldKey]['options'] = $optionLabelMap[$fieldKey] ?? null;
                $matrix[$fieldKey]['values'][] = [
                    'definition_id' => (string) $definition->getKey(),
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'other_text' => $field['other_text'] ?? null,
                    'other_text_normalized' => $field['other_text_normalized'] ?? null,
                    'user' => $definition->user?->only(['id', 'name']),
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                ];
                $matrix[$fieldKey]['discrepancy'] = optional($discrepanciesByField->get($fieldKey))->only([
                    'severity',
                    'description',
                    'status',
                ]);
            }

            foreach ($definition->metaFields() as $fieldKey => $field) {
                if (! is_string($fieldKey) || ValidationGuidanceFields::contains($fieldKey)) {
                    continue;
                }

                $metaMatrix[$fieldKey]['label'] = $field['label'] ?? Str::headline($fieldKey);
                $metaMatrix[$fieldKey]['type'] = $field['type'] ?? 'text';
                $metaMatrix[$fieldKey]['options'] = $optionLabelMap[$fieldKey] ?? null;
                $metaMatrix[$fieldKey]['values'][] = [
                    'definition_id' => (string) $definition->getKey(),
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'other_text' => $field['other_text'] ?? null,
                    'other_text_normalized' => $field['other_text_normalized'] ?? null,
                    'user' => $definition->user?->only(['id', 'name']),
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                ];
            }
        }

        return [
            'matrix' => collect($matrix)->map(fn ($row, $fieldKey) => [
                'field' => (string) $fieldKey,
                'label' => $row['label'],
                'type' => $row['type'],
                'values' => $row['values'] ?? [],
                'options' => $row['options'] ?? null,
                'discrepancy' => $row['discrepancy'] ?? null,
            ])->values()->all(),
            'context_fields' => collect($metaMatrix)->map(fn ($row, $fieldKey) => [
                'field' => (string) $fieldKey,
                'label' => $row['label'],
                'type' => $row['type'],
                'values' => $row['values'] ?? [],
                'options' => $row['options'] ?? null,
            ])->values()->all(),
            'definitions' => $metric->definitions->map(fn ($definition) => [
                'id' => (string) $definition->getKey(),
                'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                'user' => $definition->user?->only(['id', 'name']),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDefinitionPayload(?FinalDefinition $definition): ?array
    {
        if (! $definition) {
            return null;
        }

        $definition->loadMissing('ownerParticipant.user', 'signedOffBy');

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoryEntry(FinalDefinition $definition): array
    {
        return [
            'id' => (string) $definition->getKey(),
            'version' => $definition->version,
            'status' => $definition->status,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
        ];
    }

    /**
     * @return array{key: string, label: string, value: mixed, participant_id?: string, user?: array}|null
     */
    private function resolveOwnerField(FinalDefinition $definition): ?array
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
