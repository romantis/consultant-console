<?php

use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricTemplate;
use App\Support\Metrics\FieldRoleSchema;
use Database\Seeders\MetricTemplateSeeder;

it('builds classification payload from template definitions', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'core_value',
                    'type' => 'number',
                    'label' => 'Core Value',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'context_note',
                    'type' => 'textarea',
                    'label' => 'Context Note',
                    'meta' => true,
                    'classification' => [
                        'role' => 'context',
                        'companion_of' => 'core_value',
                    ],
                ],
            ],
        ],
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->for($template, 'template')->create();

    MetricDefinition::factory()->for($metric)->create([
        'normalized_json' => [
            'fields' => [
                'core_value' => [
                    'type' => 'number',
                    'label' => 'Core Value',
                    'normalized' => 42,
                ],
            ],
            'meta' => [
                'context_note' => [
                    'type' => 'textarea',
                    'label' => 'Context Note',
                    'raw' => 'Detailed context',
                ],
            ],
        ],
    ]);

    $payload = FieldRoleSchema::forMetric($metric->fresh('template', 'definitions'));
    $roles = collect($payload['field_roles'])->keyBy('key');

    expect($payload['unclassified'])->toBeEmpty()
        ->and($roles)->toHaveKeys(['core_value', 'context_note'])
        ->and($roles['core_value']['role'])->toBe('definition')
        ->and($roles['core_value']['value'])->toBe(42)
        ->and($roles['context_note']['role'])->toBe('context')
        ->and($roles['context_note']['companion_of'])->toBe('core_value')
        ->and($roles['context_note']['value'])->toBe('Detailed context');
});

it('marks legacy fields as unclassified context while keeping values visible', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'legacy_note',
                    'type' => 'textarea',
                    'label' => 'Legacy Note',
                    'meta' => true,
                ],
            ],
        ],
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->for($template, 'template')->create();

    MetricDefinition::factory()->for($metric)->create([
        'normalized_json' => [
            'fields' => [],
            'meta' => [
                'legacy_note' => [
                    'type' => 'textarea',
                    'label' => 'Legacy Note',
                    'raw' => 'Kept for cleanup',
                ],
            ],
        ],
    ]);

    $payload = FieldRoleSchema::forMetric($metric->fresh('template', 'definitions'));
    $roles = collect($payload['field_roles'])->keyBy('key');

    expect($payload['unclassified'])->toContain('legacy_note')
        ->and($roles['legacy_note']['role'])->toBe('context')
        ->and($roles['legacy_note']['unclassified'])->toBeTrue()
        ->and($roles['legacy_note']['value'])->toBe('Kept for cleanup');
});

it('preserves stored values after template classification changes', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'cleanup_target',
                    'type' => 'text',
                    'label' => 'Cleanup Target',
                ],
            ],
        ],
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->for($template, 'template')->create();

    MetricDefinition::factory()->for($metric)->create([
        'normalized_json' => [
            'fields' => [
                'cleanup_target' => [
                    'type' => 'text',
                    'label' => 'Cleanup Target',
                    'normalized' => 'Original answer',
                ],
            ],
            'meta' => [],
        ],
    ]);

    $initial = FieldRoleSchema::forMetric($metric->fresh('template', 'definitions'));
    $initialRoles = collect($initial['field_roles'])->keyBy('key');

    expect($initial['unclassified'])->toContain('cleanup_target')
        ->and($initialRoles['cleanup_target']['value'])->toBe('Original answer');

    $template->update([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'cleanup_target',
                    'type' => 'text',
                    'label' => 'Cleanup Target',
                    'classification' => [
                        'role' => 'diagnostic',
                    ],
                ],
            ],
        ],
    ]);

    $after = FieldRoleSchema::forMetric($metric->fresh('template', 'definitions'));
    $afterRoles = collect($after['field_roles'])->keyBy('key');

    expect($after['unclassified'])->not->toContain('cleanup_target')
        ->and($afterRoles['cleanup_target']['role'])->toBe('diagnostic')
        ->and($afterRoles['cleanup_target']['value'])->toBe('Original answer');
});

it('only exposes consensus-included fields to discrepancy payloads', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'primary_definition',
                    'type' => 'number',
                    'label' => 'Primary Definition',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'deep_context',
                    'type' => 'textarea',
                    'label' => 'Deep Context',
                    'meta' => true,
                    'classification' => [
                        'role' => 'context',
                    ],
                ],
                [
                    'name' => 'governance_flag',
                    'type' => 'text',
                    'label' => 'Governance Flag',
                    'classification' => [
                        'role' => 'governance',
                        'exclude_from_consensus' => true,
                    ],
                ],
            ],
        ],
    ]);

    /** @var Metric $metric */
    $metric = Metric::factory()->for($template, 'template')->create();

    MetricDefinition::factory()->for($metric)->create([
        'normalized_json' => [
            'fields' => [
                'primary_definition' => [
                    'type' => 'number',
                    'label' => 'Primary Definition',
                    'normalized' => 74,
                ],
                'governance_flag' => [
                    'type' => 'text',
                    'label' => 'Governance Flag',
                    'normalized' => 'Gov',
                ],
            ],
            'meta' => [
                'deep_context' => [
                    'type' => 'textarea',
                    'label' => 'Deep Context',
                    'raw' => 'Resolver context',
                ],
            ],
        ],
    ]);

    $resolutionFields = FieldRoleSchema::resolutionFieldsForMetric($metric->fresh('template', 'definitions'));

    expect($resolutionFields)->toHaveKeys(['primary_definition', 'deep_context'])
        ->and($resolutionFields)->not->toHaveKey('governance_flag')
        ->and($resolutionFields['primary_definition']['role'])->toBe('definition')
        ->and($resolutionFields['primary_definition']['value'])->toBe(74)
        ->and($resolutionFields['deep_context']['role'])->toBe('context')
        ->and($resolutionFields['deep_context']['value'])->toBe('Resolver context');
});

it('exposes workshop_prompt and workshop_guidance in field roles for cac_cpl template', function () {
    $this->seed(MetricTemplateSeeder::class);

    $template = MetricTemplate::where('key', 'cac_cpl')->firstOrFail();
    $payload = FieldRoleSchema::forTemplateSchema($template->form_schema_json);
    $roles = collect($payload['field_roles'])->keyBy('key');

    // Key decision-bearing fields have prompts and guidance
    expect($roles)->toHaveKeys([
        'costs_included',
        'lead_definition',
        'customer_definition',
        'cost_timing',
        'deduplication_method',
        'overhead_handling',
        'cac_pain_points',
    ]);

    expect($roles['costs_included']['workshop_prompt'])->toBe('Agree which acquisition cost pool defines official CAC/CPL.')
        ->and($roles['costs_included']['workshop_guidance'])->toContain('A fully loaded pool includes compensation and overhead')
        ->and($roles['lead_definition']['workshop_prompt'])->toContain('official CPL denominator')
        ->and($roles['lead_definition']['workshop_guidance'])->toContain('Earlier, broader stages produce a larger denominator')
        ->and($roles['lead_definition']['workshop_guidance'])->toContain('different acquisition milestone')
        ->and($roles['customer_definition']['workshop_prompt'])->toBe('Align on the exact qualification event that marks a customer as officially acquired.')
        ->and($roles['customer_definition']['workshop_guidance'])->toContain('Contract signature, first payment, go-live, and subscription activation')
        ->and($roles['cost_timing']['workshop_prompt'])->toBe('Decide how to align the timing of acquisition spend with the resulting leads or customers.')
        ->and($roles['cost_timing']['workshop_guidance'])->toContain('Same-period matching emphasizes current activity')
        ->and($roles['deduplication_method']['workshop_prompt'])->toBe('Decide how prospects with multiple campaign touches are counted.')
        ->and($roles['deduplication_method']['workshop_guidance'])->toContain('Counting touches measures interactions')
        ->and($roles['overhead_handling']['workshop_prompt'])->toBe('Agree how overhead and indirect acquisition costs are allocated in CAC.')
        ->and($roles['overhead_handling']['workshop_guidance'])->toContain('partial allocation includes a defined share of indirect costs')
        ->and($roles['cac_pain_points']['workshop_prompt'])->toBe('Identify which CAC/CPL differences must be resolved or preserved as labeled operating views.')
        ->and($roles['cac_pain_points']['workshop_guidance'])->toContain('Resolve the official reporting baseline separately');

    // Fields without guidance have null values but keys exist
    expect($roles['team_department'])->toHaveKey('workshop_prompt')
        ->and($roles['team_department'])->toHaveKey('workshop_guidance')
        ->and($roles['team_department']['workshop_prompt'])->toBeNull()
        ->and($roles['team_department']['workshop_guidance'])->toBeNull();

    // Existing classification attributes remain unchanged
    expect($roles['lead_definition']['role'])->toBe('definition')
        ->and($roles['lead_definition']['executive_section'])->toBe('official_definition')
        ->and($roles['lead_definition']['exclude_from_consensus'])->toBeFalse()
        ->and($roles['lead_definition']['companion_of'])->toBeNull();
});

it('exposes companion_of relationships for cac_cpl companion fields', function () {
    $this->seed(MetricTemplateSeeder::class);

    $template = MetricTemplate::where('key', 'cac_cpl')->firstOrFail();
    $payload = FieldRoleSchema::forTemplateSchema($template->form_schema_json);
    $roles = collect($payload['field_roles'])->keyBy('key');

    $companionMap = [
        'lead_definition_notes' => 'lead_definition',
        'customer_definition_notes' => 'customer_definition',
        'deduplication_notes' => 'deduplication_method',
        'costs_included_notes' => 'costs_included',
        'cost_timing_notes' => 'cost_timing',
        'overhead_handling_notes' => 'overhead_handling',
    ];

    foreach ($companionMap as $companionKey => $primaryKey) {
        expect($roles)->toHaveKey($companionKey);
        expect($roles[$companionKey]['companion_of'])->toBe(
            $primaryKey,
            "{$companionKey} must declare companion_of => {$primaryKey}."
        );
    }

    // The primary definition/governance fields they explain stay non-companions.
    foreach (array_values($companionMap) as $primaryKey) {
        expect($roles[$primaryKey]['companion_of'])->toBeNull(
            "{$primaryKey} is a primary field and must not be a companion."
        );
    }

    // Companion notes remain part of the consensus/source surface.
    $resolutionFields = FieldRoleSchema::resolutionFieldMap($payload['field_roles']);
    expect($resolutionFields)->toHaveKeys(array_keys($companionMap));
});

it('exposes explicit matrix-wide cue context fields for the cac_cpl template', function () {
    $this->seed(MetricTemplateSeeder::class);

    $template = MetricTemplate::where('key', 'cac_cpl')->firstOrFail();
    $payload = FieldRoleSchema::forTemplateSchema($template->form_schema_json);
    $roles = collect($payload['field_roles'])->keyBy('key');

    expect($roles['cac_example']['cue_context_scope'])->toBe('matrix_wide')
        ->and($roles['cac_pain_points']['cue_context_scope'])->toBe('matrix_wide')
        ->and($roles['additional_notes']['cue_context_scope'])->toBe('matrix_wide')
        ->and($roles['costs_included']['cue_context_scope'] ?? null)->toBeNull()
        ->and($roles['costs_included_notes']['cue_context_scope'] ?? null)->toBeNull();

    // T048: cac_example is an illustrative example, so it must be a context role
    // (never its own "Lock: …" decision segment) while staying pinned to the
    // official_definition executive section. cac_pain_points stays diagnostic, so
    // its guidance still drives a decision segment. Asserting against the real
    // seeded template guards the seeder from drifting back to the definition role.
    expect($roles['cac_example']['role'])->toBe('context')
        ->and($roles['cac_example']['executive_section'])->toBe('official_definition')
        ->and($roles['cac_pain_points']['role'])->toBe('diagnostic');
});

it('guides every required consensus field in seeded business metric templates', function () {
    $this->seed(MetricTemplateSeeder::class);

    $templateKeys = [
        'active_users',
        'churn',
        'conversion_rate',
        'cac_cpl',
        'lead_quality',
        'ltv',
        'mrr',
        'nps',
    ];

    $templates = MetricTemplate::query()
        ->whereIn('key', $templateKeys)
        ->get()
        ->keyBy('key');

    expect($templates)->toHaveCount(count($templateKeys));

    foreach ($templateKeys as $templateKey) {
        $template = $templates->get($templateKey);
        $roles = collect(FieldRoleSchema::forTemplateSchema($template->form_schema_json)['field_roles']);

        $requiredConsensusFields = $roles->filter(
            fn (array $field): bool => $field['required'] && ! $field['exclude_from_consensus']
        );

        expect($requiredConsensusFields)->not->toBeEmpty();

        foreach ($requiredConsensusFields as $field) {
            $fieldPath = "{$templateKey}.{$field['key']}";

            $this->assertIsString(
                $field['workshop_prompt'],
                "{$fieldPath} must define a workshop prompt."
            );
            $this->assertNotSame(
                '',
                trim($field['workshop_prompt']),
                "{$fieldPath} workshop prompt must not be blank."
            );
            $this->assertIsString(
                $field['workshop_guidance'],
                "{$fieldPath} must define workshop guidance."
            );
            $this->assertNotSame(
                '',
                trim($field['workshop_guidance']),
                "{$fieldPath} workshop guidance must not be blank."
            );
        }

        $supportingFields = collect($template->form_schema_json['fields'] ?? [])
            ->filter(function (array $field): bool {
                $name = $field['name'] ?? null;

                return is_string($name)
                    && (
                        str_ends_with($name, '_notes')
                        || str_ends_with($name, '_example')
                    );
            });

        foreach ($supportingFields as $field) {
            expect($field)
                ->not->toHaveKey('workshop_prompt')
                ->not->toHaveKey('workshop_guidance');
        }
    }
});

it('keeps seeded workshop guidance grounded in each field\'s choices', function () {
    $this->seed(MetricTemplateSeeder::class);

    $npsRoles = collect(FieldRoleSchema::forTemplateSchema(
        MetricTemplate::query()->where('key', 'nps')->firstOrFail()->form_schema_json
    )['field_roles'])->keyBy('key');

    expect($npsRoles['passive_handling']['workshop_prompt'])
        ->toContain('follow-up')
        ->and($npsRoles['passive_handling']['workshop_guidance'])
        ->toContain('does not change the standard NPS formula')
        ->and($npsRoles['response_volume_band']['workshop_guidance'])
        ->toContain('not as proof of representativeness')
        ->and($npsRoles['sample_representativeness']['workshop_guidance'])
        ->toContain('who responded, not only how many responded');

    $mrrRoles = collect(FieldRoleSchema::forTemplateSchema(
        MetricTemplate::query()->where('key', 'mrr')->firstOrFail()->form_schema_json
    )['field_roles'])->keyBy('key');

    expect($mrrRoles['annual_deal_handling']['workshop_guidance'])
        ->toContain('monthly run rate')
        ->and($mrrRoles['included_revenue_streams']['workshop_guidance'])
        ->toContain('variable usage belongs in a separately defined usage view')
        ->and($mrrRoles['churn_timing']['workshop_guidance'])
        ->toContain('keep pipeline bookings separate from active MRR');
});

it('separates official MRR policies from billing, bookings, forecast, and pipeline views', function () {
    $this->seed(MetricTemplateSeeder::class);

    $templates = MetricTemplate::query()
        ->whereIn('key', ['conversion_rate', 'mrr'])
        ->get()
        ->keyBy('key');

    $mrrFields = collect($templates->get('mrr')->form_schema_json['fields'] ?? [])
        ->keyBy('name');

    $optionMap = fn (string $field): array => collect($mrrFields[$field]['options'] ?? [])
        ->mapWithKeys(fn (array $option): array => [$option['value'] => $option['label']])
        ->all();

    expect($optionMap('mrr_snapshot_timing'))
        ->toHaveKey('contract_close_bookings_view', 'Contract-close bookings view (not MRR)')
        ->not->toHaveKey('contract_close')
        ->and($optionMap('annual_deal_handling'))
        ->toHaveKey('full_invoice_in_invoice_month', 'Full invoice amount in the invoice month (billing view, not MRR)')
        ->toHaveKey('normalized_contract_value')
        ->not->toHaveKeys(['recognize_when_invoiced', 'even_proration'])
        ->and($optionMap('discount_handling'))
        ->toHaveKey('list_price_view', 'Undiscounted list-price view (separate pricing view)')
        ->and($optionMap('churn_timing'))
        ->toHaveKey('retained_pipeline_history', 'Cancelled deals retained in pipeline history (not active MRR)')
        ->and($optionMap('trial_handling'))
        ->toHaveKey('payment_method_forecast_view', 'Included with payment method on file (forecast view)')
        ->and($optionMap('included_revenue_streams'))
        ->toHaveKey('recurring_subscription_charges')
        ->toHaveKey('variable_usage_charges', 'Variable usage charges (separate usage view)')
        ->toHaveKey('one_time_setup_fees', 'One-time setup fees (not MRR)')
        ->not->toHaveKeys(['new_subscriptions', 'expansion_subscriptions', 'downgrades_contraction']);

    $conversionFields = collect($templates->get('conversion_rate')->form_schema_json['fields'] ?? [])
        ->keyBy('name');
    $attribution = $conversionFields['attribution_model'];

    expect($attribution)
        ->not->toHaveKey('meta')
        ->and($attribution['classification']['role'])->toBe('governance')
        ->and($attribution['classification']['exclude_from_consensus'])->toBeFalse();
});

it('keeps seeded facilitation copy neutral and terminology-specific', function () {
    $this->seed(MetricTemplateSeeder::class);

    $templateKeys = [
        'active_users',
        'churn',
        'conversion_rate',
        'cac_cpl',
        'lead_quality',
        'ltv',
        'mrr',
        'nps',
    ];

    $templates = MetricTemplate::query()
        ->whereIn('key', $templateKeys)
        ->get()
        ->keyBy('key');

    $loadedPhrases = [
        'true unit economics',
        'more rigorous',
        'real recurring revenue',
        'real humans',
        'artificially lower',
    ];

    foreach ($templateKeys as $templateKey) {
        $roles = collect(FieldRoleSchema::forTemplateSchema(
            $templates->get($templateKey)->form_schema_json
        )['field_roles']);

        foreach ($roles as $field) {
            foreach (['workshop_prompt', 'workshop_guidance'] as $copyKey) {
                $copy = $field[$copyKey];

                if ($copy === null) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/\bboard\b/i',
                    $copy,
                    "{$templateKey}.{$field['key']}.{$copyKey} assumes a board-only audience."
                );

                foreach ($loadedPhrases as $loadedPhrase) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $loadedPhrase,
                        $copy,
                        "{$templateKey}.{$field['key']}.{$copyKey} contains loaded wording."
                    );
                }
            }
        }
    }

    $mrrRoles = collect(FieldRoleSchema::forTemplateSchema(
        $templates->get('mrr')->form_schema_json
    )['field_roles'])->keyBy('key');
    $ltvRoles = collect(FieldRoleSchema::forTemplateSchema(
        $templates->get('ltv')->form_schema_json
    )['field_roles'])->keyBy('key');
    $conversionRoles = collect(FieldRoleSchema::forTemplateSchema(
        $templates->get('conversion_rate')->form_schema_json
    )['field_roles'])->keyBy('key');
    $churnRoles = collect(FieldRoleSchema::forTemplateSchema(
        $templates->get('churn')->form_schema_json
    )['field_roles'])->keyBy('key');

    expect($mrrRoles['mrr_description']['workshop_guidance'])
        ->toContain('recurring monthly run rate')
        ->and($mrrRoles['mrr_description']['workshop_guidance'])
        ->toContain('billing or bookings views separately')
        ->and($mrrRoles['discount_handling']['workshop_guidance'])
        ->toContain('without treating billing as revenue recognition')
        ->and($ltvRoles['discount_method']['workshop_guidance'])
        ->toContain('undiscounted nominal value')
        ->and($ltvRoles['discount_method']['workshop_guidance'])
        ->toContain('present value')
        ->and($conversionRoles['attribution_model']['workshop_guidance'])
        ->toContain('do not change the total conversion count')
        ->and($churnRoles['downgrade_handling']['workshop_guidance'])
        ->toContain('Downgrades do not change logo count');
});

it('exposes stored workshop guidance from template fields', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'type' => 'select',
                    'label' => 'Lead definition',
                    'workshop_prompt' => 'Agree the official lead stage.',
                    'workshop_guidance' => 'Different lead stages change CPL.',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ]);

    $payload = FieldRoleSchema::forTemplateSchema($template->form_schema_json);
    $roles = collect($payload['field_roles'])->keyBy('key');

    expect($roles['lead_definition']['workshop_prompt'])->toBe('Agree the official lead stage.')
        ->and($roles['lead_definition']['workshop_guidance'])->toBe('Different lead stages change CPL.');
});
