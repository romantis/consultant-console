<?php

namespace App\Http\Controllers;

use App\Actions\Metrics\UpdateMetricTemplate;
use App\Models\CanonicalMetric;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\OrgMetric;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MetricTemplateController extends Controller
{
    private const CLASSIFICATION_ROLES = ['definition', 'context', 'governance', 'diagnostic'];

    private const EXECUTIVE_SECTIONS = [
        'official_definition',
        'measurement_logic_rules',
        'controls_caveats',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MetricTemplate::class);

        $templates = MetricTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MetricTemplate $template) => $this->presentTemplate($template, $request))
            ->values();

        return Inertia::render('MetricTemplates/Index', [
            'templates' => $templates,
            'can' => [
                'create' => $request->user()->can('create', MetricTemplate::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', MetricTemplate::class);

        return Inertia::render('MetricTemplates/Create', [
            'canonicalMetrics' => $this->loadCanonicalMetrics(),
        ]);
    }

    public function edit(Request $request, MetricTemplate $metricTemplate): Response
    {
        $this->authorize('update', $metricTemplate);

        $usageCount = Metric::query()
            ->where('metric_template_id', $metricTemplate->getKey())
            ->distinct('engagement_id')
            ->count('engagement_id');

        return Inertia::render('MetricTemplates/Edit', [
            'template' => $this->presentTemplate($metricTemplate, $request),
            'canonicalMetrics' => $this->loadCanonicalMetrics(),
            'templateUsage' => $usageCount,
        ]);
    }

    public function preview(Request $request, MetricTemplate $metricTemplate): Response
    {
        $this->authorize('view', $metricTemplate);

        return Inertia::render('MetricTemplates/Preview', [
            'template' => $this->presentTemplate($metricTemplate, $request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MetricTemplate::class);

        $data = $this->validateTemplate($request);

        MetricTemplate::create($data);

        return redirect()
            ->route('metric-templates.index')
            ->with('success', 'Metric template created.');
    }

    public function update(
        Request $request,
        MetricTemplate $metricTemplate,
        UpdateMetricTemplate $updateMetricTemplate
    ): RedirectResponse {
        $this->authorize('update', $metricTemplate);

        $data = $this->validateTemplate($request, $metricTemplate);

        if (
            (string) $metricTemplate->canonical_metric_id !== (string) $data['canonical_metric_id']
            && (
                CanonicalMetric::query()->where('default_metric_template_id', $metricTemplate->id)->exists()
                || OrgMetric::query()->where('default_metric_template_id', $metricTemplate->id)->exists()
            )
        ) {
            throw ValidationException::withMessages([
                'canonical_metric_id' => __('Clear default template assignments before moving this template.'),
            ]);
        }

        $updateMetricTemplate->handle($metricTemplate, $data);

        return redirect()
            ->route('metric-templates.index')
            ->with('success', 'Metric template updated.');
    }

    public function destroy(Request $request, MetricTemplate $metricTemplate): RedirectResponse
    {
        $this->authorize('delete', $metricTemplate);

        if (
            CanonicalMetric::query()->where('default_metric_template_id', $metricTemplate->id)->exists()
            || OrgMetric::query()->where('default_metric_template_id', $metricTemplate->id)->exists()
        ) {
            return redirect()
                ->back()
                ->with('error', __('This template is set as a default. Clear the default before deleting.'));
        }

        $metricTemplate->delete();

        return redirect()
            ->route('metric-templates.index')
            ->with('success', 'Metric template deleted.');
    }

    /**
     * @return array{canonical_metric_id: string, key: string, name: string, description: ?string, form_schema_json: array}
     */
    protected function validateTemplate(Request $request, ?MetricTemplate $template = null): array
    {
        $rules = [
            'canonical_metric_id' => [
                'required',
                'string',
                Rule::exists('canonical_metrics', 'id')->where('is_active', true),
            ],
            'key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('metric_templates', 'key')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'form_schema_json' => ['required', 'array'],
            'form_schema_json.fields' => ['required', 'array', 'min:1'],
            'form_schema_json.fields.*.name' => ['required', 'string', 'max:120'],
            'form_schema_json.fields.*.label' => ['required', 'string', 'max:255'],
            'form_schema_json.fields.*.form_label' => ['nullable', 'string', 'max:255'],
            'form_schema_json.fields.*.type' => ['required', 'string', Rule::in(['text', 'number', 'textarea', 'select', 'checkbox_group'])],
            'form_schema_json.fields.*.required' => ['nullable', 'boolean'],
            'form_schema_json.fields.*.meta' => ['nullable', 'boolean'],
            'form_schema_json.fields.*.options' => ['nullable', 'array'],
            'form_schema_json.fields.*.options.*' => [
                function ($attribute, $value, $fail) {
                    if (is_string($value)) {
                        if (mb_strlen($value) > 255) {
                            $fail(__('Option labels must be 255 characters or fewer.'));
                        }

                        return;
                    }

                    if (is_array($value)) {
                        $label = isset($value['label']) && is_string($value['label']) ? trim($value['label']) : '';
                        $canonical = isset($value['value']) && is_string($value['value']) ? trim($value['value']) : '';

                        if ($label === '' || $canonical === '') {
                            $fail(__('Provide both a value and label for each option.'));

                            return;
                        }

                        if (mb_strlen($canonical) > 120) {
                            $fail(__('Option values must be 120 characters or fewer.'));
                        }

                        if (mb_strlen($label) > 255) {
                            $fail(__('Option labels must be 255 characters or fewer.'));
                        }

                        return;
                    }

                    $fail(__('Each option must be a string or a {value, label} array.'));
                },
            ],
            'form_schema_json.fields.*.with_other' => ['nullable', 'boolean'],
            'form_schema_json.fields.*.other_prompt' => ['nullable', 'string', 'max:255'],
            'form_schema_json.fields.*.workshop_prompt' => ['nullable', 'string', 'max:500'],
            'form_schema_json.fields.*.workshop_guidance' => ['nullable', 'string', 'max:1500'],
            'form_schema_json.fields.*.executive_section' => ['nullable', 'string', Rule::in(self::EXECUTIVE_SECTIONS)],
            'form_schema_json.fields.*.classification' => ['required', 'array'],
            'form_schema_json.fields.*.classification.role' => ['required', 'string', Rule::in(self::CLASSIFICATION_ROLES)],
            'form_schema_json.fields.*.classification.exclude_from_consensus' => ['nullable', 'boolean'],
            'form_schema_json.fields.*.classification.companion_of' => ['nullable', 'string', 'max:120'],
            'form_schema_json.fields.*.classification.executive_section' => ['nullable', 'string', Rule::in(self::EXECUTIVE_SECTIONS)],
        ];

        $validated = $request->validate($rules);

        if (
            $template
            && (string) $template->canonical_metric_id !== (string) $validated['canonical_metric_id']
            && Metric::query()->where('metric_template_id', $template->getKey())->exists()
        ) {
            throw ValidationException::withMessages([
                'canonical_metric_id' => __('This template is already used by engagements and cannot be reassigned.'),
            ]);
        }

        $rawFields = $validated['form_schema_json']['fields'];
        $fields = [];

        foreach ($rawFields as $index => $field) {
            $options = [];
            if (isset($field['options']) && is_array($field['options'])) {
                $options = array_values(array_filter(
                    array_map(function ($option) {
                        if (is_string($option)) {
                            $trimmed = trim($option);

                            return $trimmed !== '' ? $trimmed : null;
                        }

                        if (is_array($option)) {
                            $label = isset($option['label']) && is_string($option['label'])
                                ? trim($option['label'])
                                : '';
                            $canonical = isset($option['value']) && is_string($option['value'])
                                ? trim($option['value'])
                                : '';

                            if ($label === '' && $canonical === '') {
                                return null;
                            }

                            if ($canonical === '') {
                                $canonical = $label;
                            }

                            if ($label === '') {
                                return $canonical;
                            }

                            return ['value' => $canonical, 'label' => $label];
                        }

                        return null;
                    }, $field['options']),
                    fn ($option) => $option !== null
                ));
            }

            $type = $field['type'];

            if (in_array($type, ['select', 'checkbox_group'], true) && empty($options)) {
                throw ValidationException::withMessages([
                    "form_schema_json.fields.$index.options" => __('Provide at least one option.'),
                ]);
            }

            $payload = [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
            ];

            $formLabel = is_string($field['form_label'] ?? null)
                ? trim($field['form_label'])
                : '';

            if ($formLabel !== '') {
                $payload['form_label'] = $formLabel;
            }

            $workshopPrompt = is_string($field['workshop_prompt'] ?? null)
                ? trim($field['workshop_prompt'])
                : '';

            if ($workshopPrompt !== '') {
                $payload['workshop_prompt'] = $workshopPrompt;
            }

            $workshopGuidance = is_string($field['workshop_guidance'] ?? null)
                ? trim($field['workshop_guidance'])
                : '';

            if ($workshopGuidance !== '') {
                $payload['workshop_guidance'] = $workshopGuidance;
            }

            if (! empty($field['meta'])) {
                $payload['meta'] = true;
            }

            if (! empty($options)) {
                $payload['options'] = $options;
            }

            $payload['classification'] = $this->normalizeClassification(
                $field['classification'] ?? null,
                (bool) ($field['meta'] ?? false),
                $index
            );
            $executiveSection = $this->normalizeExecutiveSection(
                is_string($field['executive_section'] ?? null) ? $field['executive_section'] : null,
                is_array($field['classification'] ?? null) ? $field['classification'] : null,
                $payload['classification']['role']
            );
            $payload['executive_section'] = $executiveSection;
            $payload['classification']['executive_section'] = $executiveSection;

            if (in_array($type, ['checkbox_group', 'select'], true) && ! empty($field['with_other'])) {
                $prompt = trim((string) ($field['other_prompt'] ?? ''));
                $payload['with_other'] = true;
                $payload['other_prompt'] = $prompt !== '' ? $prompt : 'Please specify';
            }

            $fields[] = $payload;
        }

        $fieldNames = [];
        $fieldClassifications = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $classification = $field['classification'];

            $fieldNames[] = $name;
            $fieldClassifications[$name] = $classification;
        }

        foreach ($fields as $index => $field) {
            $classificationInput = $rawFields[$index]['classification'] ?? null;

            if (! is_array($classificationInput)) {
                continue;
            }

            $companionOf = $this->normalizeCompanionOf(
                $classificationInput,
                $field['name'],
                $field['classification'],
                $fieldNames,
                $fieldClassifications,
                $index
            );

            if ($companionOf !== null) {
                $fields[$index]['classification']['companion_of'] = $companionOf;
            }
        }

        return [
            'canonical_metric_id' => $validated['canonical_metric_id'],
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'form_schema_json' => [
                'fields' => $fields,
            ],
        ];
    }

    /**
     * @return array{id: string, canonical_metric_id: ?string, key: string, name: string, description: ?string, form_schema_json: array, created_at: ?string, updated_at: ?string, can: array{update: bool, delete: bool}}
     */
    protected function presentTemplate(MetricTemplate $template, Request $request): array
    {
        return [
            'id' => (string) $template->id,
            'canonical_metric_id' => $template->canonical_metric_id
                ? (string) $template->canonical_metric_id
                : null,
            'key' => $template->key,
            'name' => $template->name,
            'description' => $template->description,
            'form_schema_json' => $template->form_schema_json ?? ['fields' => []],
            'created_at' => optional($template->created_at)->toIso8601String(),
            'updated_at' => optional($template->updated_at)->toIso8601String(),
            'can' => [
                'update' => $request->user()->can('update', $template),
                'delete' => $request->user()->can('delete', $template),
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, key: string, name: string, description: ?string}>
     */
    protected function loadCanonicalMetrics(): array
    {
        return CanonicalMetric::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'description'])
            ->map(fn (CanonicalMetric $metric) => [
                'id' => (string) $metric->getKey(),
                'key' => $metric->key,
                'name' => $metric->name,
                'description' => $metric->description,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $classification
     * @return array{role: string, exclude_from_consensus: bool}
     */
    protected function normalizeClassification(?array $classification, bool $isMeta, int|string $index): array
    {
        $role = null;
        if (is_array($classification)) {
            $candidate = $classification['role'] ?? null;
            if (is_string($candidate)) {
                $candidate = strtolower($candidate);
            }

            if (is_string($candidate) && in_array($candidate, self::CLASSIFICATION_ROLES, true)) {
                $role = $candidate;
            }
        }

        if ($role === null) {
            $role = $isMeta ? 'context' : 'definition';
        }

        $excludeFromConsensus = (bool) ($classification['exclude_from_consensus'] ?? false);

        return [
            'role' => $role,
            'exclude_from_consensus' => $excludeFromConsensus,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $classification
     */
    protected function normalizeExecutiveSection(?string $fieldLevelSection, ?array $classification, string $role): string
    {
        $candidates = [
            $fieldLevelSection,
            is_array($classification) && is_string($classification['executive_section'] ?? null)
                ? $classification['executive_section']
                : null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = is_string($candidate) ? trim(strtolower($candidate)) : '';
            if (in_array($normalized, self::EXECUTIVE_SECTIONS, true)) {
                return $normalized;
            }
        }

        return $this->defaultExecutiveSectionForRole($role);
    }

    protected function defaultExecutiveSectionForRole(string $role): string
    {
        return match ($role) {
            'definition' => 'official_definition',
            'diagnostic' => 'controls_caveats',
            'governance', 'context' => 'measurement_logic_rules',
            default => 'measurement_logic_rules',
        };
    }

    /**
     * @param  array<string, mixed>  $classification
     * @param  array{role: string, exclude_from_consensus: bool}  $normalizedClassification
     * @param  array<int, string>  $fieldNames
     * @param  array<string, array{role: string, exclude_from_consensus: bool}>  $fieldClassifications
     */
    protected function normalizeCompanionOf(
        array $classification,
        string $fieldName,
        array $normalizedClassification,
        array $fieldNames,
        array $fieldClassifications,
        int|string $index
    ): ?string {
        $candidate = $classification['companion_of'] ?? null;
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        if ($candidate === $fieldName) {
            throw ValidationException::withMessages([
                "form_schema_json.fields.$index.classification.companion_of" => __('Companion field cannot reference itself.'),
            ]);
        }

        if (! in_array($candidate, $fieldNames, true)) {
            throw ValidationException::withMessages([
                "form_schema_json.fields.$index.classification.companion_of" => __('Companion field must reference another field key in this template.'),
            ]);
        }

        $role = $normalizedClassification['role'] ?? 'context';
        $excludedFromConsensus = (bool) ($normalizedClassification['exclude_from_consensus'] ?? false);

        if ($role === 'definition') {
            throw ValidationException::withMessages([
                "form_schema_json.fields.$index.classification.companion_of" => __('Definition fields cannot be marked as companions.'),
            ]);
        }

        if ($excludedFromConsensus) {
            throw ValidationException::withMessages([
                "form_schema_json.fields.$index.classification.companion_of" => __('Companion fields must be included in the consensus surface.'),
            ]);
        }

        $parentClassification = $fieldClassifications[$candidate] ?? null;
        $parentExcluded = is_array($parentClassification)
            ? (bool) ($parentClassification['exclude_from_consensus'] ?? false)
            : null;

        if ($parentExcluded !== false) {
            throw ValidationException::withMessages([
                "form_schema_json.fields.$index.classification.companion_of" => __('Companion fields must point to a field included in the consensus surface.'),
            ]);
        }

        return $candidate;
    }
}
