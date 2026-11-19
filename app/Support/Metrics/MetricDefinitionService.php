<?php

namespace App\Support\Metrics;

use App\Actions\Metrics\ComputeDiscrepancies;
use App\Models\AuditLog;
use App\Models\FinalDefinition;
use App\Models\FormSubmission;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricTemplate;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MetricDefinitionService
{
    public function recordFromSubmission(FormSubmission $submission): MetricDefinition
    {
        $submission->loadMissing(['form.organization', 'form.engagement', 'form.metric', 'user']);

        $form = $submission->form;
        $organization = $form->organization;
        $template = MetricTemplate::where('key', $form->template_key)->first();

        $metric = $form->metric;

        if ($metric && $metric->organization_id !== $organization->id) {
            $metric = null;
        }

        if ($metric && $metric->engagement_id !== $form->engagement_id) {
            $metric = null;
        }

        $metricKey = null;
        if (! $metric) {
            $metricKey = $template?->key ?? Str::slug($form->title ?: Str::random(8));

            if (! $metricKey) {
                $metricKey = 'metric-'.Str::lower(Str::random(6));
            }
        }

        return DB::transaction(function () use ($metric, $submission, $template, $organization, $form, $metricKey) {
            if (! $metric) {
                $metric = Metric::firstOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'engagement_id' => $form->engagement_id,
                        'key' => $metricKey,
                    ],
                    [
                        'metric_template_id' => $template?->id,
                        'name' => $template?->name ?? $form->title,
                        'slug' => Str::slug(($form->title ?: ($template?->name ?? $metricKey)).'-'.Str::random(4)),
                    ]
                );
            }

            // Lock the parent metric row first
            $lockedMetric = Metric::where('id', $metric->id)->lockForUpdate()->first();

            if (! $lockedMetric->metric_template_id && $template?->id) {
                $lockedMetric->forceFill(['metric_template_id' => $template->id])->save();
            }

            $normalized = $this->normalizeAnswers(
                $form->schema_json ?? ['fields' => []],
                $submission->answers_json ?? []
            );

            $definition = MetricDefinition::updateOrCreate(
                [
                    'metric_id' => $lockedMetric->id,
                    'form_submission_id' => $submission->id,
                ],
                [
                    'user_id' => $submission->user_id,
                    'source_type' => 'form_submission',
                    'status' => $submission->submitted_at ? 'Submitted' : 'Draft',
                    'definition_json' => $submission->answers_json ?? [],
                    'normalized_json' => $normalized,
                    'submitted_at' => $submission->submitted_at,
                ]
            );

            $lockedMetric->forceFill([
                'name' => $lockedMetric->name ?: ($template?->name ?? $form->title ?? 'Metric'),
                'last_submitted_at' => $submission->submitted_at,
                'definitions_changed_at' => now(),
            ])->save();

            $this->ensureFinalDefinitionExists($lockedMetric, $normalized, $submission->user_id);

            ComputeDiscrepancies::dispatchSync(
                (string) $lockedMetric->getKey(),
                $submission->user?->getKey()
            );

            $this->log(
                $lockedMetric,
                'metric.definition.recorded',
                [
                    'definition_id' => $definition->id,
                    'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                    'user_id' => $submission->user_id,
                ],
                $submission->user
            );

            return $definition;
        });
    }

    protected function normalizeAnswers(array $schema, array $answers): array
    {
        $fields = [];
        $metaFields = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $key = $field['name'] ?? null;
            if (! $key) {
                continue;
            }

            $normalizedField = $this->normalizeFieldEntry($field, $answers);

            if ((bool) ($field['meta'] ?? false)) {
                $metaFields[$key] = $normalizedField;

                continue;
            }

            $fields[$key] = $normalizedField;
        }

        // Carry through any answers that are not described in schema for completeness.
        foreach ($answers as $key => $value) {
            if (isset($fields[$key]) || isset($metaFields[$key])) {
                continue;
            }

            $type = is_numeric($value) ? 'number' : 'text';

            $fields[$key] = [
                'type' => $type,
                'label' => Str::headline(str_replace('_', ' ', $key)),
                'raw' => $value,
                'normalized' => $this->normalizeValue($type, $value),
            ];
        }

        return [
            'fields' => $fields,
            'meta' => $metaFields,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function normalizeFieldEntry(array $field, array &$answers): array
    {
        $key = $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $raw = Arr::get($answers, $key);
        $label = $field['label'] ?? Str::headline(str_replace('_', ' ', $key));

        $normalized = [
            'type' => $type,
            'label' => $label,
            'raw' => $raw,
            'normalized' => $this->normalizeValue($type, $raw),
        ];

        if (in_array($type, ['checkbox_group', 'select'], true) && ! empty($field['with_other'])) {
            $otherKey = $key.'__other_text';
            $otherText = Arr::get($answers, $otherKey);

            if (array_key_exists($otherKey, $answers)) {
                unset($answers[$otherKey]);
            }

            if (is_string($otherText) && trim($otherText) !== '') {
                $normalized['other_text'] = $otherText;
                $normalized['other_text_normalized'] = $this->normalizeText($otherText);
            }
        }

        return $normalized;
    }

    protected function normalizeValue(string $type, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'number', 'numeric' => $this->normalizeNumber($value),
            'enum', 'select' => $this->normalizeEnum($value),
            'checkbox_group' => $this->normalizeMultiSelect($value),
            default => $this->normalizeText($value),
        };
    }

    protected function normalizeMultiSelect(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($entry) => $this->normalizeEnum($entry))
                ->filter(fn ($entry) => $entry !== null)
                ->values()
                ->all();
        }

        $normalized = $this->normalizeEnum($value);

        return $normalized === null ? [] : [$normalized];
    }

    protected function normalizeNumber(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = str_replace([',', ' '], '', $value);

            return is_numeric($clean) ? (float) $clean : null;
        }

        return null;
    }

    protected function normalizeEnum(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::of((string) $value)->squish()->lower()->value();
    }

    protected function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Str::of((string) $value)->squish()->lower()->value();
    }

    protected function ensureFinalDefinitionExists(Metric $metric, array $normalizedFields, ?string $userId): void
    {
        if ($metric->finalDefinition()->exists()) {
            return;
        }

        $fields = $this->extractNormalizedFieldBucket($normalizedFields);

        $definition = collect($fields)->mapWithKeys(function ($field, $key) {
            return [$key => ['label' => $field['label'] ?? Str::headline($key), 'value' => null]];
        })->all();

        FinalDefinition::create([
            'metric_id' => $metric->id,
            'user_id' => $userId,
            'status' => 'Draft',
            'definition_json' => $definition,
        ]);
    }

    protected function log(Metric $metric, string $action, array $properties = [], ?User $actor = null): void
    {
        $userId = $actor?->getKey() ?? ($properties['user_id'] ?? null);

        AuditLog::create([
            'organization_id' => $metric->organization_id,
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => 'metric',
            'subject_id' => $metric->id,
            'properties' => Arr::except($properties, ['user_id']),
        ]);
    }

    protected function extractNormalizedFieldBucket(array $normalized): array
    {
        if (isset($normalized['fields']) || isset($normalized['meta'])) {
            return $normalized['fields'] ?? [];
        }

        return $normalized;
    }
}
