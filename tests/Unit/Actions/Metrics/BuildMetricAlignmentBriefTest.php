<?php

use App\Actions\Metrics\BuildMetricAlignmentBrief;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Wire a respondent end to end: user + membership/pending-membership (carrying the
 * canonical affiliation) + engagement participant + metric participant + a
 * submitted definition. Returns the definition id as the string used by field-role
 * values, so a test field role can reference this respondent.
 */
function seedBriefRespondent(
    Metric $metric,
    string $name,
    ?string $membershipAffiliation = null,
    ?string $pendingAffiliation = null,
): string {
    $user = User::factory()->create(['name' => $name]);

    $attributes = ['engagement_id' => $metric->engagement_id];

    if ($pendingAffiliation !== null) {
        $pendingMembership = PendingMembership::factory()->create([
            'organization_id' => $metric->organization_id,
            'engagement_id' => $metric->engagement_id,
            'affiliation' => $pendingAffiliation,
        ]);

        $attributes['participant_type'] = EngagementParticipant::TYPE_PENDING;
        $attributes['membership_id'] = null;
        $attributes['pending_membership_id'] = $pendingMembership->id;
    } else {
        $membership = Membership::factory()->member()->create([
            'organization_id' => $metric->organization_id,
            'user_id' => $user->id,
            'affiliation' => $membershipAffiliation,
        ]);

        $attributes['membership_id'] = $membership->id;
    }

    $engagementParticipant = EngagementParticipant::factory()->create($attributes);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $engagementParticipant->id,
        'user_id' => $user->id,
    ]);

    $definition = MetricDefinition::factory()->for($metric)->create([
        'user_id' => $user->id,
        'status' => 'Submitted',
    ]);

    return (string) $definition->id;
}

/**
 * A single divergent definition field referencing two respondents, used to assert
 * how affiliation is resolved onto stakeholder positions.
 *
 * @return array<int, array<string, mixed>>
 */
function affiliationFieldRoles(string $definitionIdA, string $nameA, string $definitionIdB, string $nameB, ?array $teamDepartmentValues = null): array
{
    $fieldRoles = [
        [
            'key' => 'methodology',
            'label' => 'Measurement methodology',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => $definitionIdA, 'raw' => 'Calendar capture', 'normalized' => 'calendar', 'user' => ['id' => $definitionIdA, 'name' => $nameA], 'submitted_at' => null],
                ['definition_id' => $definitionIdB, 'raw' => 'Self report', 'normalized' => 'self-report', 'user' => ['id' => $definitionIdB, 'name' => $nameB], 'submitted_at' => null],
            ],
        ],
    ];

    if ($teamDepartmentValues !== null) {
        $fieldRoles[] = [
            'key' => 'team_department',
            'label' => 'Respondent team or department',
            'role' => 'context',
            'required' => true,
            'exclude_from_consensus' => true,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => $teamDepartmentValues,
        ];
    }

    return $fieldRoles;
}

it('classifies divergent definition field as material_conflicts', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_1',
            'label' => 'Field One',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Value A',
                    'normalized' => 'value-a',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
                [
                    'definition_id' => '2',
                    'raw' => 'Value B',
                    'normalized' => 'value-b',
                    'user' => ['id' => 2, 'name' => 'User B'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['material_conflicts'])->toHaveCount(1);
    $conflict = $result['material_conflicts'][0];
    expect($conflict['field_key'])->toBe('field_1');
    expect($conflict['category'])->toBe('material_conflict');
    expect($conflict['title'])->toBe('Stakeholders disagree on Field One.');
    expect($conflict['rationale'])->toBe('Stakeholders submitted 2 different values for Field One.');
    expect($conflict['source_refs'])->toHaveCount(2); // 2 submitted answers
    expect($conflict['source_refs'][0]['type'])->toBe('submitted_answer');
});

it('classifies required or guided field with one or no answer as missing_evidence', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_req',
            'label' => 'Required Field',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Value A',
                    'normalized' => 'value-a',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
            ],
        ],
        [
            'key' => 'field_guided',
            'label' => 'Guided Field',
            'role' => 'definition',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => 'Question?',
            'workshop_guidance' => 'Guidance details',
            'alignment_signal' => null,
            'values' => [],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['missing_evidence'])->toHaveCount(2);
    expect($result['missing_evidence'][0]['field_key'])->toBe('field_req');
    expect($result['missing_evidence'][1]['field_key'])->toBe('field_guided');
});

it('classifies context/diagnostic field with value as context_signals and does not drop them', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_context',
            'label' => 'Context Field',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Extra context',
                    'normalized' => 'extra context',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['context_signals'])->toHaveCount(1);
    expect($result['context_signals'][0]['field_key'])->toBe('field_context');
});

it('leaves the context_signal rationale empty when the field carries no distinguishing content', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_context',
            'label' => 'Context Field',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Extra context',
                    'normalized' => 'extra context',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['context_signals'][0]['rationale'])->toBe('')
        ->and($result['context_signals'][0]['rationale'])
        ->not->toBe('Contains supporting information to explain definition alignments.');
});

it('uses field-specific content for the context_signal rationale when available', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_context',
            'label' => 'Context Field',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => [
                'id' => 'sig-context-1',
                'description' => 'Finance and Ops track this metric on different fiscal calendars.',
            ],
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Extra context',
                    'normalized' => 'extra context',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['context_signals'][0]['rationale'])
        ->toBe('Finance and Ops track this metric on different fiscal calendars.');
});

it('classifies excluded field with signal as likely_non_issues and not top conflict', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_excluded',
            'label' => 'Excluded Field',
            'role' => 'definition',
            'required' => false,
            'exclude_from_consensus' => true,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => [
                'id' => 'sig_123',
                'severity' => 'medium',
                'description' => 'Some issue',
            ],
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Value A',
                    'normalized' => 'value-a',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
                [
                    'definition_id' => '2',
                    'raw' => 'Value B',
                    'normalized' => 'value-b',
                    'user' => ['id' => 2, 'name' => 'User B'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['material_conflicts'])->toBeEmpty();
    expect($result['likely_non_issues'])->toHaveCount(1);
    expect($result['likely_non_issues'][0]['field_key'])->toBe('field_excluded');
});

it('uses workshop_prompt for recommended question if provided', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_prompt',
            'label' => 'Prompt Field',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'workshop_prompt' => 'Custom workshop question?',
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['recommended_questions'])->toHaveCount(1);
    expect($result['recommended_questions'][0]['question'])->toBe('Custom workshop question?');
});

it('ensures excluded guided fields do not become missing_evidence or decision_needed', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_excluded_guided',
            'label' => 'Excluded Guided Field',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => true,
            'workshop_prompt' => 'Should this be asked?',
            'workshop_guidance' => 'Some guidance',
            'alignment_signal' => null,
            'values' => [],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['missing_evidence'])->toBeEmpty();
    expect($result['decision_needed'])->toBeEmpty();
    expect($result['recommended_questions'])->toBeEmpty();
    expect($result['likely_non_issues'])->toHaveCount(1);
});

it('excludes draft definitions from the submitted_answers count in summary', function () {
    $metric = Metric::factory()->create();

    // Create a submitted definition
    MetricDefinition::factory()->for($metric)->create([
        'status' => 'Submitted',
    ]);

    // Create a draft definition
    MetricDefinition::factory()->for($metric)->create([
        'status' => 'Draft',
    ]);

    $fieldRoles = [
        [
            'key' => 'field_1',
            'label' => 'Field One',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['summary']['source_counts']['submitted_answers'])->toBe(1);
});

it('produces two separate source references for workshop_prompt and workshop_guidance', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_guided',
            'label' => 'Guided Field',
            'role' => 'definition',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => 'Question context?',
            'workshop_guidance' => 'Guidance detail explanation',
            'alignment_signal' => null,
            'values' => [],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    // Should be in missing_evidence since it is definition, not excluded, and has prompt/guidance but 0 values.
    expect($result['missing_evidence'])->toHaveCount(1);
    $item = $result['missing_evidence'][0];

    // Check that we have two separate source refs
    $sourceRefs = $item['source_refs'];
    expect($sourceRefs)->toHaveCount(2);

    $promptRef = collect($sourceRefs)->firstWhere('meta.guidance_kind', 'workshop_prompt');
    $guidanceRef = collect($sourceRefs)->firstWhere('meta.guidance_kind', 'workshop_guidance');

    expect($promptRef)->not->toBeNull();
    expect($promptRef['excerpt'])->toBe('Question context?');
    expect($promptRef['label'])->toBe('Workshop prompt');

    expect($guidanceRef)->not->toBeNull();
    expect($guidanceRef['excerpt'])->toBe('Guidance detail explanation');
    expect($guidanceRef['label'])->toBe('Workshop guidance');
});

it('ensures all generated source references contain typed id, type, label, and excerpt properties', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_1',
            'label' => 'Field One',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'workshop_prompt' => 'Question prompt?',
            'workshop_guidance' => 'Guidance here',
            'alignment_signal' => [
                'id' => 'sig_123',
                'severity' => 'medium',
                'description' => 'Some signal description',
            ],
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Value A',
                    'normalized' => 'value-a',
                    'user' => ['id' => 1, 'name' => 'User A'],
                    'submitted_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['missing_evidence'])->toHaveCount(1);
    $item = $result['missing_evidence'][0];

    $sourceRefs = $item['source_refs'];
    expect($sourceRefs)->toBeArray()->not->toBeEmpty();

    foreach ($sourceRefs as $ref) {
        expect($ref)->toHaveKeys(['id', 'type', 'label', 'field_key', 'source_id', 'excerpt']);
        expect(in_array($ref['type'], [
            'submitted_answer',
            'field_guidance',
            'alignment_signal',
            'signed_definition',
            'workshop_decision',
        ], true))->toBeTrue();
    }
});

it('uses facilitator-facing copy for alignment signal evidence', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'field_signal',
            'label' => 'Signal Field',
            'role' => 'definition',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => [
                'id' => 'sig_456',
                'severity' => 'medium',
                'description' => 'Finance and Marketing submitted different positions.',
            ],
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Value A',
                    'normalized' => 'value-a',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
                [
                    'definition_id' => '2',
                    'raw' => 'Value B',
                    'normalized' => 'value-b',
                    'user' => ['id' => 2, 'name' => 'User B'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['material_conflicts'])->toHaveCount(1);

    $conflict = $result['material_conflicts'][0];
    expect($conflict['rationale'])->toBe(
        'Stakeholders submitted 2 different values for Signal Field.',
    );
    expect($conflict['rationale'])->not->toContain('Discrepancy checker');
    // The signal description belongs in the cue evidence, not the standalone card.
    expect($conflict['rationale'])->not->toContain('Finance and Marketing submitted different positions.');

    $signalRef = collect($conflict['source_refs'])->firstWhere('type', 'alignment_signal');

    expect($signalRef)->not->toBeNull();
    expect($signalRef['label'])->toBe('Detector signal');
    expect($signalRef['excerpt'])->toBe('Finance and Marketing submitted different positions.');
});

it('keeps the conflict rationale field-specific when only a discrepancy signal fired', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'signal_only',
            'label' => 'Signal Only Field',
            'role' => 'definition',
            'required' => false,
            'exclude_from_consensus' => false,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => [
                'id' => 'sig_only',
                'severity' => 'medium',
                'description' => 'Reviewer flagged this field for discussion.',
            ],
            'values' => [
                [
                    'definition_id' => '1',
                    'raw' => 'Same',
                    'normalized' => 'same',
                    'user' => ['id' => 1, 'name' => 'User A'],
                ],
                [
                    'definition_id' => '2',
                    'raw' => 'Same',
                    'normalized' => 'same',
                    'user' => ['id' => 2, 'name' => 'User B'],
                ],
            ],
        ],
    ];

    $action = resolve(BuildMetricAlignmentBrief::class);
    $result = $action->handle($metric, $fieldRoles);

    expect($result['material_conflicts'])->toHaveCount(1);
    $conflict = $result['material_conflicts'][0];
    // Identical submitted values: the divergence count is not informative, so the
    // rationale names the field and the signal without restating its description.
    expect($conflict['rationale'])->toBe('A discrepancy signal flags Signal Only Field for facilitator review.');
    expect($conflict['rationale'])->not->toContain('Reviewer flagged this field for discussion.');
});

it('groups companion submitted answers under the primary field they explain', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'costs_included',
            'label' => 'CAC/CPL cost components included',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'workshop_prompt' => 'Agree which acquisition cost pool defines official CAC/CPL.',
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => 'def-1',
                    'raw' => 'fully_loaded',
                    'normalized' => 'fully_loaded',
                    'user' => ['id' => 'usr-1', 'name' => 'Lia Price'],
                    'submitted_at' => '2026-06-01T10:00:00Z',
                ],
                [
                    'definition_id' => 'def-2',
                    'raw' => 'direct_only',
                    'normalized' => 'direct_only',
                    'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'],
                    'submitted_at' => '2026-06-01T10:30:00Z',
                ],
            ],
        ],
        [
            'key' => 'costs_included_notes',
            'label' => 'Included and excluded GL/cost center rules',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => 'costs_included',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                [
                    'definition_id' => 'def-1',
                    'raw' => 'Includes GL 5000-5400; excludes 6000 overhead.',
                    'normalized' => 'Includes GL 5000-5400; excludes 6000 overhead.',
                    'user' => ['id' => 'usr-1', 'name' => 'Lia Price'],
                    'submitted_at' => '2026-06-01T10:00:00Z',
                ],
                [
                    'definition_id' => 'def-2',
                    'raw' => 'Direct campaign cost centers only.',
                    'normalized' => 'Direct campaign cost centers only.',
                    'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'],
                    'submitted_at' => '2026-06-01T10:30:00Z',
                ],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    expect($result)->toHaveKey('companion_answers')
        ->and($result['companion_answers'])->toHaveCount(1);

    $group = $result['companion_answers'][0];

    expect($group['primary_field_key'])->toBe('costs_included')
        ->and($group['companion_field_key'])->toBe('costs_included_notes')
        ->and($group['companion_field_label'])->toBe('Included and excluded GL/cost center rules')
        ->and($group['companion_field_role'])->toBe('context')
        ->and($group['answers'])->toHaveCount(2);

    $first = $group['answers'][0];

    expect($first['definition_id'])->toBe('def-1')
        ->and($first['stakeholder']['name'])->toBe('Lia Price')
        ->and($first['display_value'])->toBe('Includes GL 5000-5400; excludes 6000 overhead.')
        ->and($first['submitted_at'])->toBe('2026-06-01T10:00:00Z')
        ->and($first['source_refs'])->toHaveCount(1)
        ->and($first['source_refs'][0]['id'])->toBe('submitted_answer:def-1:costs_included_notes')
        ->and($first['source_refs'][0]['type'])->toBe('submitted_answer')
        ->and($first['source_refs'][0]['excerpt'])->toBe('Includes GL 5000-5400; excludes 6000 overhead.');

    // Companion answers carry the same respondents/definition ids as the primary
    // positions, so each companion answer can render beneath the matching answer.
    $primaryConflict = collect($result['material_conflicts'])->firstWhere('field_key', 'costs_included');

    expect($primaryConflict)->not->toBeNull();

    $primaryDefinitionIds = collect($primaryConflict['stakeholder_positions'])->pluck('definition_id')->all();
    $companionDefinitionIds = collect($group['answers'])->pluck('definition_id')->all();

    expect($companionDefinitionIds)->toBe($primaryDefinitionIds);
});

it('builds deterministic cue context with affiliations, companion answers, guidance, and matrix-wide citations', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'team_department',
            'label' => 'Respondent team or department',
            'role' => 'context',
            'required' => true,
            'exclude_from_consensus' => true,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Finance', 'normalized' => 'finance', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Marketing', 'normalized' => 'marketing', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'costs_included',
            'label' => 'CAC/CPL cost components included',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => 'Agree which acquisition cost pool defines official CAC/CPL.',
            'workshop_guidance' => 'Name the official cost pool and label narrower channel views separately.',
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'fully_loaded', 'normalized' => 'fully_loaded', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => '2026-06-01T10:00:00Z'],
                ['definition_id' => 'def-2', 'raw' => 'direct_only', 'normalized' => 'direct_only', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => '2026-06-01T10:30:00Z'],
            ],
        ],
        [
            'key' => 'costs_included_notes',
            'label' => 'Included and excluded GL/cost center rules',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => 'costs_included',
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Includes GL 5000-5400; excludes 6000 overhead.', 'normalized' => 'Includes GL 5000-5400; excludes 6000 overhead.', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => '2026-06-01T10:00:00Z'],
                ['definition_id' => 'def-2', 'raw' => 'Direct campaign cost centers only.', 'normalized' => 'Direct campaign cost centers only.', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => '2026-06-01T10:30:00Z'],
            ],
        ],
        [
            'key' => 'cac_example',
            'label' => 'CAC/CPL calculation example',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Board CAC uses fully loaded acquisition cost.', 'normalized' => 'Board CAC uses fully loaded acquisition cost.', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => '2026-06-01T10:00:00Z'],
                ['definition_id' => 'def-2', 'raw' => 'Campaign CAC uses direct spend only.', 'normalized' => 'Campaign CAC uses direct spend only.', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => '2026-06-01T10:30:00Z'],
            ],
        ],
        [
            'key' => 'cac_pain_points',
            'label' => 'CAC/CPL definition divergence points',
            'role' => 'diagnostic',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => 'Identify which CAC/CPL differences must be resolved.',
            'workshop_guidance' => 'Resolve the official reporting baseline separately from operating views.',
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Finance needs the board baseline.', 'normalized' => 'Finance needs the board baseline.', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => '2026-06-01T10:00:00Z'],
                ['definition_id' => 'def-2', 'raw' => 'Marketing accepts a labeled operating view.', 'normalized' => 'Marketing accepts a labeled operating view.', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => '2026-06-01T10:30:00Z'],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $cueContext = collect($result['cue_contexts'])->firstWhere('field_key', 'costs_included');

    expect($cueContext)->not->toBeNull()
        ->and($cueContext['stakeholder_positions'])->toHaveCount(2)
        ->and($cueContext['stakeholder_positions'][0]['stakeholder']['affiliation'])->toBe('Finance')
        ->and($cueContext['companion_answers'])->toHaveCount(1)
        ->and($cueContext['companion_answers'][0]['answers'][0]['source_refs'][0]['id'])->toBe('submitted_answer:def-1:costs_included_notes')
        ->and($cueContext['workshop_guidance']['text'])->toBe('Name the official cost pool and label narrower channel views separately.')
        ->and($cueContext['workshop_guidance']['citation_ids'])->toBe(['field_guidance:costs_included:guidance']);

    $matrixContext = collect($cueContext['matrix_context'])->keyBy('field_key');

    expect($matrixContext)->toHaveKeys(['cac_example', 'cac_pain_points'])
        ->and($matrixContext['cac_example']['citation_ids'])->toBe([
            'submitted_answer:def-1:cac_example',
            'submitted_answer:def-2:cac_example',
        ])
        ->and($matrixContext['cac_pain_points']['stakeholder_positions'][1]['stakeholder']['affiliation'])->toBe('Marketing')
        ->and($cueContext['citation_ids'])->toContain(
            'submitted_answer:def-1:costs_included',
            'submitted_answer:def-1:costs_included_notes',
            'field_guidance:costs_included:guidance',
            'submitted_answer:def-1:cac_example',
            'submitted_answer:def-1:cac_pain_points',
        );
});

it('resolves respondent affiliation from membership when no team_department field exists', function () {
    $metric = Metric::factory()->create();

    $definitionIdA = seedBriefRespondent($metric, 'Avery Lee', membershipAffiliation: 'Engineering');
    $definitionIdB = seedBriefRespondent($metric, 'Jordan Park', membershipAffiliation: 'Product');

    $fieldRoles = affiliationFieldRoles($definitionIdA, 'Avery Lee', $definitionIdB, 'Jordan Park');

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $positions = collect($result['stakeholder_positions'])->keyBy('definition_id');

    expect($positions[$definitionIdA]['stakeholder']['name'])->toBe('Avery Lee')
        ->and($positions[$definitionIdA]['stakeholder']['affiliation'])->toBe('Engineering')
        ->and($positions[$definitionIdB]['stakeholder']['affiliation'])->toBe('Product');
});

it('falls back to pending membership affiliation when no membership exists', function () {
    $metric = Metric::factory()->create();

    $definitionIdA = seedBriefRespondent($metric, 'Avery Lee', pendingAffiliation: 'Engineering');
    $definitionIdB = seedBriefRespondent($metric, 'Jordan Park', membershipAffiliation: 'Product');

    $fieldRoles = affiliationFieldRoles($definitionIdA, 'Avery Lee', $definitionIdB, 'Jordan Park');

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $positions = collect($result['stakeholder_positions'])->keyBy('definition_id');

    expect($positions[$definitionIdA]['stakeholder']['affiliation'])->toBe('Engineering')
        ->and($positions[$definitionIdB]['stakeholder']['affiliation'])->toBe('Product');
});

it('prefers membership affiliation and uses a team_department field only as a fallback', function () {
    $metric = Metric::factory()->create();

    // A has a canonical membership affiliation AND a divergent team_department answer.
    $definitionIdA = seedBriefRespondent($metric, 'Avery Lee', membershipAffiliation: 'Engineering');
    // B has no membership affiliation, only a submitted team_department answer.
    $definitionIdB = seedBriefRespondent($metric, 'Jordan Park');

    $fieldRoles = affiliationFieldRoles(
        $definitionIdA,
        'Avery Lee',
        $definitionIdB,
        'Jordan Park',
        teamDepartmentValues: [
            ['definition_id' => $definitionIdA, 'raw' => 'Platform Guild', 'normalized' => 'platform-guild', 'user' => ['id' => $definitionIdA, 'name' => 'Avery Lee'], 'submitted_at' => null],
            ['definition_id' => $definitionIdB, 'raw' => 'Product', 'normalized' => 'product', 'user' => ['id' => $definitionIdB, 'name' => 'Jordan Park'], 'submitted_at' => null],
        ],
    );

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $positions = collect($result['stakeholder_positions'])->keyBy('definition_id');

    expect($positions[$definitionIdA]['stakeholder']['affiliation'])->toBe('Engineering')
        ->and($positions[$definitionIdB]['stakeholder']['affiliation'])->toBe('Product');
});

it('renders the bare respondent name when no affiliation is available anywhere', function () {
    $metric = Metric::factory()->create();

    $definitionIdA = seedBriefRespondent($metric, 'Avery Lee');
    $definitionIdB = seedBriefRespondent($metric, 'Jordan Park');

    $fieldRoles = affiliationFieldRoles($definitionIdA, 'Avery Lee', $definitionIdB, 'Jordan Park');

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $positions = collect($result['stakeholder_positions'])->keyBy('definition_id');

    expect($positions[$definitionIdA]['stakeholder']['name'])->toBe('Avery Lee')
        ->and($positions[$definitionIdA]['stakeholder']['affiliation'])->toBeNull()
        ->and($positions[$definitionIdB]['stakeholder']['affiliation'])->toBeNull();
});

it('omits blank or self-scoped matrix-wide fields from cue context', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'team_department',
            'label' => 'Respondent team or department',
            'role' => 'context',
            'required' => true,
            'exclude_from_consensus' => true,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Finance', 'normalized' => 'finance', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Marketing', 'normalized' => 'marketing', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'cac_pain_points',
            'label' => 'CAC/CPL definition divergence points',
            'role' => 'diagnostic',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => 'Identify which CAC/CPL differences must be resolved.',
            'workshop_guidance' => 'Resolve the official reporting baseline separately from operating views.',
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Finance needs the board baseline.', 'normalized' => 'Finance needs the board baseline.', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Marketing accepts a labeled operating view.', 'normalized' => 'Marketing accepts a labeled operating view.', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'cac_example',
            'label' => 'CAC/CPL calculation example',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Board CAC uses fully loaded acquisition cost.', 'normalized' => 'Board CAC uses fully loaded acquisition cost.', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Campaign CAC uses direct spend only.', 'normalized' => 'Campaign CAC uses direct spend only.', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'additional_notes',
            'label' => 'Additional context',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => '', 'normalized' => '', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => null, 'normalized' => null, 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    $cueContext = collect($result['cue_contexts'])->firstWhere('field_key', 'cac_pain_points');
    $matrixContextKeys = collect($cueContext['matrix_context'])->pluck('field_key')->all();

    expect($matrixContextKeys)->toBe(['cac_example'])
        ->and($matrixContextKeys)->not->toContain('cac_pain_points')
        ->and($matrixContextKeys)->not->toContain('additional_notes');
});

it('omits companion groups for blank answers and fields without companion metadata', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'lead_definition',
            'label' => 'CPL lead definition',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'workshop_prompt' => 'Agree which lead stage defines the official CPL denominator.',
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'mql', 'normalized' => 'mql', 'user' => ['id' => 'u1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'sql', 'normalized' => 'sql', 'user' => ['id' => 'u2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'lead_definition_notes',
            'label' => 'Lead definition qualifiers and exceptions',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => 'lead_definition',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => '', 'normalized' => '', 'user' => ['id' => 'u1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => null, 'normalized' => null, 'user' => ['id' => 'u2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    // Every companion answer is blank, so no companion group is emitted.
    expect($result['companion_answers'])->toBe([]);

    // The primary field's raw submitted answers remain unchanged.
    $primaryConflict = collect($result['material_conflicts'])->firstWhere('field_key', 'lead_definition');

    expect($primaryConflict)->not->toBeNull();

    $excerpts = collect($primaryConflict['source_refs'])
        ->where('type', 'submitted_answer')
        ->pluck('excerpt')
        ->all();

    expect($excerpts)->toBe(['mql', 'sql']);
});

it('displays raw text for free-text fields and uses normalized for option mapping with other_text included', function () {
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'costs_included',
                    'type' => 'checkbox_group',
                    'label' => 'Costs included',
                    'options' => [
                        ['value' => 'ad_spend', 'label' => 'Ad Spend'],
                        ['value' => 'marketing_comp', 'label' => 'Marketing Comp'],
                        ['value' => 'other', 'label' => 'Other'],
                    ],
                ],
                [
                    'name' => 'costs_included_notes',
                    'type' => 'textarea',
                    'label' => 'Included notes',
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()->create([
        'metric_template_id' => $template->id,
    ]);

    $fieldRoles = [
        [
            'key' => 'costs_included',
            'label' => 'Costs included',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'values' => [
                [
                    'definition_id' => 'def-1',
                    'raw' => ['ad_spend', 'other'],
                    'normalized' => ['ad_spend', 'other'],
                    'other_text' => 'Partner commissions',
                    'user' => ['id' => 1, 'name' => 'Lia Price'],
                ],
            ],
        ],
        [
            'key' => 'costs_included_notes',
            'label' => 'Included notes',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => 'costs_included',
            'values' => [
                [
                    'definition_id' => 'def-1',
                    'raw' => 'We include Ad Spend and other marketing costs.',
                    'normalized' => 'we include ad spend and other marketing costs.',
                    'user' => ['id' => 1, 'name' => 'Lia Price'],
                ],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    // 1. Verify option-label mapping uses normalized/raw and includes other_text
    $primaryPos = collect($result['stakeholder_positions'])->firstWhere('field_key', 'costs_included');
    expect($primaryPos)->not->toBeNull();
    expect($primaryPos['display_value'])->toBe('Ad Spend, Other (Partner commissions)');

    // 2. Verify free-text field displays raw text (original casing)
    $companionAnswers = $result['companion_answers'][0];
    expect($companionAnswers['companion_field_key'])->toBe('costs_included_notes');
    $companionPos = $companionAnswers['answers'][0];
    expect($companionPos['display_value'])->toBe('We include Ad Spend and other marketing costs.');
});

it('includes empty workshop_cues array by default in the handle result', function () {
    $metric = Metric::factory()->create();
    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, []);

    expect($result)->toHaveKey('workshop_cues')
        ->and($result['workshop_cues'])->toBeArray()
        ->and($result['workshop_cues'])->toBeEmpty();
});

it('ensures a matrix-wide context field is classified as context_signal and does not become a decision segment', function () {
    $metric = Metric::factory()->create();

    $fieldRoles = [
        [
            'key' => 'cac_example',
            'label' => 'CAC/CPL calculation example',
            'role' => 'context',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => 'matrix_wide',
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'Board CAC', 'normalized' => 'Board CAC', 'user' => ['id' => 'usr-1', 'name' => 'Lia Price'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Campaign CAC', 'normalized' => 'Campaign CAC', 'user' => ['id' => 'usr-2', 'name' => 'Lila Martin'], 'submitted_at' => null],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    // Should not be in material_conflicts, decision_needed, or missing_evidence
    expect(collect($result['material_conflicts'])->firstWhere('field_key', 'cac_example'))->toBeNull()
        ->and(collect($result['decision_needed'])->firstWhere('field_key', 'cac_example'))->toBeNull()
        ->and(collect($result['missing_evidence'])->firstWhere('field_key', 'cac_example'))->toBeNull()
        // Should be in context_signals
        ->and(collect($result['context_signals'])->firstWhere('field_key', 'cac_example'))->not->toBeNull()
        // Recommended questions should not contain it
        ->and(collect($result['recommended_questions'])->firstWhere('field_key', 'cac_example'))->toBeNull()
        // Resolution options should not contain it
        ->and(collect($result['resolution_options'])->firstWhere('field_key', 'cac_example'))->toBeNull();
});

it('ensures Focus Time alignment brief has empty matrix_context and preserves raw casing', function () {
    $metric = Metric::factory()->create(['key' => 'focus-time-alignment', 'name' => 'Focus Time Alignment']);

    $fieldRoles = [
        [
            'key' => 'focus_hours_target',
            'label' => 'Ideal focus hours per workday',
            'role' => 'definition',
            'required' => true,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => '4', 'normalized' => '4', 'user' => ['id' => 'usr-1', 'name' => 'Avery Lee'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => '2', 'normalized' => '2', 'user' => ['id' => 'usr-2', 'name' => 'Jordan Park'], 'submitted_at' => null],
            ],
        ],
        [
            'key' => 'meeting_rules',
            'label' => 'What are your personal meeting rules during focus time?',
            'role' => 'governance',
            'required' => false,
            'exclude_from_consensus' => false,
            'companion_of' => null,
            'cue_context_scope' => null,
            'workshop_prompt' => null,
            'workshop_guidance' => null,
            'alignment_signal' => null,
            'values' => [
                ['definition_id' => 'def-1', 'raw' => 'No meetings 9–11am; 1:1s after 3pm.', 'normalized' => 'no meetings 9–11am; 1:1s after 3pm.', 'user' => ['id' => 'usr-1', 'name' => 'Avery Lee'], 'submitted_at' => null],
                ['definition_id' => 'def-2', 'raw' => 'Limit meetings to 3 hours per day and avoid back-to-back afternoons.', 'normalized' => 'limit meetings to 3 hours per day and avoid back-to-back afternoons.', 'user' => ['id' => 'usr-2', 'name' => 'Jordan Park'], 'submitted_at' => null],
            ],
        ],
    ];

    $result = resolve(BuildMetricAlignmentBrief::class)->handle($metric, $fieldRoles);

    // 1. Assert that cue_contexts have empty matrix_context
    expect($result['cue_contexts'])->not->toBeEmpty();
    foreach ($result['cue_contexts'] as $cueContext) {
        expect($cueContext['matrix_context'])->toBeEmpty();
    }

    // 2. Assert raw submitted-answer casing is preserved in the values returned
    $meetingRulesCue = collect($result['cue_contexts'])->firstWhere('field_key', 'meeting_rules');
    expect($meetingRulesCue)->not->toBeNull();

    $averyPos = collect($meetingRulesCue['stakeholder_positions'])->firstWhere('stakeholder.name', 'Avery Lee');
    expect($averyPos)->not->toBeNull();
    expect($averyPos['raw_value'])->toBe('No meetings 9–11am; 1:1s after 3pm.');
});
