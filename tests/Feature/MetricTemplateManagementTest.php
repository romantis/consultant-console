<?php

use App\Enums\MembershipRole;
use App\Models\CanonicalMetric;
use App\Models\Membership;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStaffUser(): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

function makeClientUser(): User
{
    $user = User::factory()->create();
    $org = Organization::factory()->create(['is_internal' => false]);

    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

it('requires authentication to view templates', function () {
    $this->get(route('metric-templates.index'))->assertRedirect(route('login'));
});

it('prevents non-staff users from managing templates', function () {
    $user = makeClientUser();
    $template = MetricTemplate::factory()->create();
    $canonical = CanonicalMetric::factory()->create();

    $this->actingAs($user)->get(route('metric-templates.index'))->assertForbidden();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'retention',
        'name' => 'Retention',
        'description' => 'Retention metric',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)->post(route('metric-templates.store'), $payload)->assertForbidden();
    $this->actingAs($user)->put(route('metric-templates.update', $template), $payload)->assertForbidden();
    $this->actingAs($user)->delete(route('metric-templates.destroy', $template))->assertForbidden();
    $this->actingAs($user)->get(route('metric-templates.preview', $template))->assertForbidden();
});

it('allows staff to view templates', function () {
    $user = makeStaffUser();
    MetricTemplate::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('metric-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('MetricTemplates/Index'));
});

it('allows staff to create, update, and delete templates', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create([
        'key' => 'retention_rate',
        'name' => 'Retention Rate',
    ]);

    $createPayload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'retention_rate',
        'name' => 'Retention Rate',
        'description' => 'Monthly retention rate',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Retention %',
                    'form_label' => 'What retention percentage did you observe?',
                    'type' => 'number',
                    'required' => true,
                    'meta' => true,
                    'executive_section' => 'official_definition',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $createPayload)
        ->assertRedirect(route('metric-templates.index'));

    $template = MetricTemplate::where('key', 'retention_rate')->first();
    expect($template)->not->toBeNull();
    expect($template->name)->toBe('Retention Rate');
    expect($template->form_schema_json['fields'][0]['required'] ?? false)->toBeTrue();
    expect($template->form_schema_json['fields'][0]['meta'] ?? false)->toBeTrue();
    expect($template->form_schema_json['fields'][0]['form_label'] ?? null)->toBe('What retention percentage did you observe?');
    expect($template->form_schema_json['fields'][0]['executive_section'] ?? null)->toBe('official_definition');
    expect($template->form_schema_json['fields'][0]['classification']['role'])->toBe('definition');
    expect($template->form_schema_json['fields'][0]['classification']['executive_section'] ?? null)->toBe('official_definition');

    $updatePayload = [
        'canonical_metric_id' => (string) $template->canonical_metric_id,
        'key' => 'retention_rate',
        'name' => 'Retention Rate Updated',
        'description' => 'Updated description',
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Retention %',
                    'type' => 'number',
                    'required' => false,
                    'executive_section' => 'official_definition',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'notes',
                    'label' => 'Notes',
                    'type' => 'textarea',
                    'required' => false,
                    'meta' => true,
                    'executive_section' => 'controls_caveats',
                    'classification' => [
                        'role' => 'context',
                        'companion_of' => 'value',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('metric-templates.update', $template), $updatePayload)
        ->assertRedirect(route('metric-templates.index'));

    $template->refresh();
    expect($template->name)->toBe('Retention Rate Updated');
    expect($template->form_schema_json['fields'])->toHaveCount(2);
    expect($template->form_schema_json['fields'][0]['required'])->toBeFalse();
    expect($template->form_schema_json['fields'][1]['meta'] ?? false)->toBeTrue();
    expect($template->form_schema_json['fields'][1]['executive_section'] ?? null)->toBe('controls_caveats');
    expect($template->form_schema_json['fields'][1]['classification']['role'])->toBe('context');
    expect($template->form_schema_json['fields'][1]['classification']['companion_of'] ?? null)->toBe('value');
    expect($template->form_schema_json['fields'][1]['classification']['executive_section'] ?? null)->toBe('controls_caveats');

    $this->actingAs($user)
        ->delete(route('metric-templates.destroy', $template))
        ->assertRedirect(route('metric-templates.index'));

    $this->assertDatabaseMissing('metric_templates', ['id' => $template->id]);

    $template = MetricTemplate::factory()->create();

    $this->actingAs($user)
        ->get(route('metric-templates.preview', $template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('MetricTemplates/Preview'));
});

it('requires classification role selection', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'definition_miss',
        'name' => 'Missing Classification',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => null,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.0.classification.role',
        ]);
});

it('validates optional form label length', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'form_label_length',
        'name' => 'Form Label Length',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'form_label' => str_repeat('a', 256),
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.0.form_label',
        ]);
});

it('validates executive section selection', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'invalid_exec_section',
        'name' => 'Invalid Executive Section',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'executive_section' => 'not_a_real_section',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.0.executive_section',
        ]);
});

it('allows excluding a field from consensus surfaces', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'consensus_exclusion',
        'name' => 'Consensus Exclusion',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => 'context',
                        'exclude_from_consensus' => true,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertRedirect(route('metric-templates.index'));

    $template = MetricTemplate::query()->where('key', 'consensus_exclusion')->first();

    expect($template)->not->toBeNull()
        ->and(
            $template->form_schema_json['fields'][0]['classification']['exclude_from_consensus'] ?? null
        )->toBeTrue();
});

it('rejects companion_of references to unknown field keys', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'companion_miss',
        'name' => 'Companion Missing',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'value_notes',
                    'label' => 'Value notes',
                    'type' => 'textarea',
                    'required' => false,
                    'classification' => [
                        'role' => 'context',
                        'companion_of' => 'missing_key',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.1.classification.companion_of',
        ]);
});

it('requires classification role selection on update', function () {
    $user = makeStaffUser();
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ]);

    $payload = [
        'canonical_metric_id' => (string) $template->canonical_metric_id,
        'key' => $template->key,
        'name' => 'Role Missing',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'classification' => [
                        'role' => null,
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('metric-templates.update', $template), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.0.classification.role',
        ]);
});

it('rejects companion_of when field is excluded from consensus on update', function () {
    $user = makeStaffUser();
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'classification' => [
                        'role' => 'context',
                    ],
                ],
            ],
        ],
    ]);

    $payload = [
        'canonical_metric_id' => (string) $template->canonical_metric_id,
        'key' => $template->key,
        'name' => 'Companion Excluded',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'label' => 'Value',
                    'type' => 'number',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'value_notes',
                    'label' => 'Value notes',
                    'type' => 'textarea',
                    'classification' => [
                        'role' => 'context',
                        'exclude_from_consensus' => true,
                        'companion_of' => 'value',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('metric-templates.update', $template), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.1.classification.companion_of',
        ]);
});

it('persists workshop prompt and guidance on template fields', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'cac_alignment',
        'name' => 'CAC Alignment',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'label' => 'Lead definition',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['mql', 'sql'],
                    'workshop_prompt' => ' Agree which lead stage is official. ',
                    'workshop_guidance' => ' MQL and SQL can produce materially different CPL. ',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertRedirect(route('metric-templates.index'));

    $template = MetricTemplate::where('key', 'cac_alignment')->firstOrFail();
    $field = $template->form_schema_json['fields'][0];

    expect($field['workshop_prompt'])->toBe('Agree which lead stage is official.')
        ->and($field['workshop_guidance'])->toBe('MQL and SQL can produce materially different CPL.');
});

it('validates workshop guidance length', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();

    $payload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => 'cac_alignment_long',
        'name' => 'CAC Alignment Long',
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'label' => 'Lead definition',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['mql', 'sql'],
                    'workshop_prompt' => str_repeat('a', 501),
                    'workshop_guidance' => str_repeat('a', 1501),
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->post(route('metric-templates.store'), $payload)
        ->assertSessionHasErrors([
            'form_schema_json.fields.0.workshop_prompt',
            'form_schema_json.fields.0.workshop_guidance',
        ]);
});

it('persists changes to workshop prompt and guidance on update path', function () {
    $user = makeStaffUser();
    $canonical = CanonicalMetric::factory()->create();
    $template = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonical->id,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'label' => 'Lead definition',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['mql', 'sql'],
                    'workshop_prompt' => 'Agree lead stage.',
                    'workshop_guidance' => 'Lead stages differ.',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ]);

    // 1. Test update with trimmed values
    $updatePayload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => $template->key,
        'name' => $template->name,
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'label' => 'Lead definition',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['mql', 'sql'],
                    'workshop_prompt' => '  Agree lead stage updated.  ',
                    'workshop_guidance' => '  Lead stages differ updated.  ',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('metric-templates.update', $template), $updatePayload)
        ->assertRedirect(route('metric-templates.index'));

    $template->refresh();
    $field = $template->form_schema_json['fields'][0];
    expect($field['workshop_prompt'])->toBe('Agree lead stage updated.')
        ->and($field['workshop_guidance'])->toBe('Lead stages differ updated.');

    // 2. Test update with empty strings (should omit guidance keys)
    $emptyPayload = [
        'canonical_metric_id' => (string) $canonical->getKey(),
        'key' => $template->key,
        'name' => $template->name,
        'description' => null,
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'lead_definition',
                    'label' => 'Lead definition',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['mql', 'sql'],
                    'workshop_prompt' => '   ',
                    'workshop_guidance' => '',
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(route('metric-templates.update', $template), $emptyPayload)
        ->assertRedirect(route('metric-templates.index'));

    $template->refresh();
    $field = $template->form_schema_json['fields'][0];
    expect($field)->not->toHaveKey('workshop_prompt')
        ->and($field)->not->toHaveKey('workshop_guidance');
});
