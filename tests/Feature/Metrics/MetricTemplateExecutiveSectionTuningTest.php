<?php

use App\Models\MetricTemplate;
use Database\Seeders\MetricTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies explicit executive section tuning for seeded metric templates', function () {
    $this->seed(MetricTemplateSeeder::class);

    $template = MetricTemplate::query()->where('key', 'active_users')->firstOrFail();
    expect($template->form_schema_json)->not->toHaveKey('executive_section_overrides');

    $fields = collect($template->form_schema_json['fields'] ?? [])->keyBy('name');

    expect($fields)->toHaveKeys([
        'active_metrics_notes',
        'reporting_frequency_notes',
        'active_pain_points',
    ]);

    expect($fields['active_metrics_notes']['executive_section'] ?? null)
        ->toBe('official_definition')
        ->and($fields['active_metrics_notes']['classification']['executive_section'] ?? null)
        ->toBe('official_definition')
        ->and($fields['reporting_frequency_notes']['executive_section'] ?? null)
        ->toBe('measurement_logic_rules')
        ->and($fields['reporting_frequency_notes']['classification']['executive_section'] ?? null)
        ->toBe('measurement_logic_rules')
        ->and($fields['active_pain_points']['executive_section'] ?? null)
        ->toBe('controls_caveats')
        ->and($fields['active_pain_points']['classification']['executive_section'] ?? null)
        ->toBe('controls_caveats');
});

it('falls back to role-based executive sections when a template has no per-metric tuning map', function () {
    $this->seed(MetricTemplateSeeder::class);

    $template = MetricTemplate::query()->where('key', 'field_classification_demo')->firstOrFail();
    $fields = collect($template->form_schema_json['fields'] ?? [])->keyBy('name');

    expect($fields)->toHaveKeys(['focus_window_preference']);

    expect($fields['focus_window_preference']['classification']['role'] ?? null)
        ->toBe('context')
        ->and($fields['focus_window_preference']['executive_section'] ?? null)
        ->toBe('measurement_logic_rules')
        ->and($fields['focus_window_preference']['classification']['executive_section'] ?? null)
        ->toBe('measurement_logic_rules');
});

it('does not add validation guidance fields to seeded metric templates', function () {
    $this->seed(MetricTemplateSeeder::class);

    $templates = MetricTemplate::query()->orderBy('key')->get();

    expect($templates)->not->toBeEmpty();

    foreach ($templates as $template) {
        $fieldNames = collect($template->form_schema_json['fields'] ?? [])
            ->pluck('name')
            ->values();

        expect($fieldNames)
            ->not->toContain('validation_steps')
            ->not->toContain('common_mismatch_causes');
    }
});
