<?php

use App\Actions\Metrics\BuildFacilitatorReadinessPack;

/**
 * A submitted-answer source ref for a respondent's value, mirroring the shape
 * BuildMetricAlignmentBrief attaches to each stakeholder position.
 *
 * @return array<string, mixed>
 */
function packAnswerRef(string $fieldKey, string $defId, string $name, string $value): array
{
    return [
        'id' => "submitted_answer:{$defId}:{$fieldKey}",
        'type' => 'submitted_answer',
        'label' => "{$name} answer",
        'field_key' => $fieldKey,
        'source_id' => $defId,
        'excerpt' => $value,
    ];
}

/**
 * @return array<string, mixed>
 */
function packPosition(
    string $fieldKey,
    string $fieldLabel,
    string $defId,
    string $name,
    string $value,
    ?string $affiliation = null,
): array {
    return [
        'field_key' => $fieldKey,
        'field_label' => $fieldLabel,
        'stakeholder' => ['id' => $defId, 'name' => $name, 'affiliation' => $affiliation],
        'definition_id' => $defId,
        'raw_value' => $value,
        'normalized_value' => $value,
        'display_value' => $value,
        'submitted_at' => '2026-06-01T10:00:00Z',
        'source_refs' => [packAnswerRef($fieldKey, $defId, $name, $value)],
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $positions
 * @return array<string, mixed>
 */
function packAttentionItem(string $category, string $fieldKey, string $fieldLabel, array $positions): array
{
    return [
        'id' => "field:{$fieldKey}:{$category}",
        'category' => $category,
        'field_key' => $fieldKey,
        'field_label' => $fieldLabel,
        'field_role' => 'definition',
        'severity' => $category === 'material_conflict' ? 'medium' : 'low',
        'title' => "Attention on {$fieldLabel}.",
        'rationale' => "Submitted positions for {$fieldLabel} need facilitator review.",
        'stakeholder_positions' => $positions,
        'source_refs' => array_merge([], ...array_map(fn (array $p): array => $p['source_refs'], $positions)),
    ];
}

/**
 * @return array<string, mixed>
 */
function packQuestion(string $fieldKey, string $question): array
{
    return [
        'id' => "question:{$fieldKey}",
        'field_key' => $fieldKey,
        'question' => $question,
        'reason' => 'Stakeholders submitted different positions.',
        'priority' => 'high',
        'source_refs' => [[
            'id' => "field_guidance:{$fieldKey}:prompt",
            'type' => 'field_guidance',
            'label' => 'Workshop prompt',
            'field_key' => $fieldKey,
            'source_id' => null,
            'excerpt' => $question,
            'meta' => ['guidance_kind' => 'workshop_prompt'],
        ]],
    ];
}

/**
 * @param  array<int, array{field_key: string, field_label: string}>  $matrixFields
 * @return array<string, mixed>
 */
function packCueContext(string $fieldKey, string $fieldLabel, array $matrixFields = []): array
{
    return [
        'field_key' => $fieldKey,
        'field_label' => $fieldLabel,
        'field_role' => 'definition',
        'stakeholder_positions' => [],
        'companion_answers' => [],
        'workshop_guidance' => null,
        'matrix_context' => array_map(fn (array $m): array => [
            'field_key' => $m['field_key'],
            'field_label' => $m['field_label'],
            'field_role' => 'definition',
            'stakeholder_positions' => [],
            'source_refs' => [[
                'id' => "field_schema:{$m['field_key']}",
                'type' => 'field_schema',
                'label' => $m['field_label'],
                'field_key' => $m['field_key'],
                'source_id' => null,
                'excerpt' => $m['field_label'],
            ]],
            'citation_ids' => ["field_schema:{$m['field_key']}"],
        ], $matrixFields),
        'citation_ids' => [],
        'alignment_signal' => null,
    ];
}

/**
 * @return array<string, mixed>
 */
function packFieldRole(string $key, string $label, ?string $prompt = null): array
{
    return [
        'key' => $key,
        'label' => $label,
        'role' => 'definition',
        'required' => true,
        'exclude_from_consensus' => false,
        'companion_of' => null,
        'workshop_prompt' => $prompt,
        'workshop_guidance' => null,
        'alignment_signal' => null,
        'values' => [],
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $runOfShow
 * @param  array<int, array<string, mixed>>  $decisionsToLock
 * @return array<string, mixed>
 */
function packFacilitationBrief(array $runOfShow, array $decisionsToLock, string $status = 'draft'): array
{
    return [
        'status' => $status,
        'needs_source_review' => false,
        'document' => [
            'run_of_show' => $runOfShow,
            'decisions_to_lock' => $decisionsToLock,
        ],
    ];
}

/**
 * Dense CAC/CPL-shaped brief: two contested definition fields (overhead handling
 * and cost pool) where overhead handling also carries a companion answer and a
 * matrix link to the cost pool field.
 *
 * @return array<string, mixed>
 */
function denseBrief(): array
{
    $overheadPositions = [
        packPosition('overhead_handling', 'Overhead Handling', 'def-1', 'Alice Finance', 'Head Count Ratio', 'Finance'),
        packPosition('overhead_handling', 'Overhead Handling', 'def-2', 'Bob Sales', 'Revenue Ratio', 'Sales'),
    ];
    $costPoolPositions = [
        packPosition('cost_pool', 'Cost Pool', 'def-1', 'Alice Finance', 'Fully loaded', 'Finance'),
        packPosition('cost_pool', 'Cost Pool', 'def-2', 'Bob Sales', 'Direct only', 'Sales'),
    ];

    return [
        'summary' => ['metric_id' => 'metric-1', 'metric_name' => 'CAC/CPL', 'status' => 'InReview', 'source_counts' => []],
        'stakeholder_positions' => array_merge($overheadPositions, $costPoolPositions),
        'companion_answers' => [[
            'primary_field_key' => 'overhead_handling',
            'companion_field_key' => 'overhead_handling_notes',
            'companion_field_label' => 'Allocation and apportionment rules',
            'companion_field_role' => 'context',
            'answers' => [[
                'definition_id' => 'def-1',
                'stakeholder' => ['id' => 'def-1', 'name' => 'Alice Finance', 'affiliation' => 'Finance'],
                'display_value' => 'We allocate shared RevOps overhead by headcount.',
                'raw_value' => 'We allocate shared RevOps overhead by headcount.',
                'normalized_value' => 'We allocate shared RevOps overhead by headcount.',
                'submitted_at' => '2026-06-01T10:00:00Z',
                'source_refs' => [packAnswerRef('overhead_handling_notes', 'def-1', 'Alice Finance', 'headcount')],
            ]],
        ]],
        'cue_contexts' => [
            packCueContext('overhead_handling', 'Overhead Handling', [
                ['field_key' => 'cost_pool', 'field_label' => 'Cost Pool'],
            ]),
            packCueContext('cost_pool', 'Cost Pool'),
        ],
        'workshop_cues' => [],
        'material_conflicts' => [
            packAttentionItem('material_conflict', 'overhead_handling', 'Overhead Handling', $overheadPositions),
            packAttentionItem('material_conflict', 'cost_pool', 'Cost Pool', $costPoolPositions),
        ],
        'decision_needed' => [],
        'missing_evidence' => [],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [
            packQuestion('overhead_handling', 'How should corporate overhead be allocated?'),
            packQuestion('cost_pool', 'Which costs belong in the cost pool?'),
        ],
        'resolution_options' => [],
        'draft_alignment_summary' => null,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function denseFieldRoles(): array
{
    return [
        packFieldRole('overhead_handling', 'Overhead Handling', 'How should corporate overhead be allocated?'),
        packFieldRole('cost_pool', 'Cost Pool', 'Which costs belong in the cost pool?'),
    ];
}

/**
 * @return array<string, mixed>
 */
function denseFacilitationBrief(): array
{
    return packFacilitationBrief(
        runOfShow: [
            ['id' => 'segment:framing', 'position' => 1, 'duration_minutes' => 10, 'title' => 'Frame the metric', 'decision_ids' => [], 'purpose' => ['text' => 'Open']],
            ['id' => 'segment:overhead', 'position' => 2, 'duration_minutes' => 15, 'title' => 'Lock overhead handling', 'decision_ids' => ['decision:overhead'], 'purpose' => ['text' => 'Decide']],
            ['id' => 'segment:cost_pool', 'position' => 3, 'duration_minutes' => 10, 'title' => 'Lock cost pool', 'decision_ids' => ['decision:cost_pool'], 'purpose' => ['text' => 'Decide']],
        ],
        decisionsToLock: [
            ['id' => 'decision:overhead', 'title' => 'Overhead', 'field_keys' => ['overhead_handling']],
            ['id' => 'decision:cost_pool', 'title' => 'Cost pool', 'field_keys' => ['cost_pool']],
        ],
    );
}

function buildPack(
    array $brief,
    ?array $facilitationBrief = null,
    array $workshopReadiness = ['gaps' => []],
    ?array $fieldRoles = null,
    string $metricId = 'metric-1',
    string $metricName = 'CAC/CPL',
): array {
    return (new BuildFacilitatorReadinessPack)->handle(
        $brief,
        $facilitationBrief,
        $workshopReadiness,
        $fieldRoles ?? denseFieldRoles(),
        $metricId,
        $metricName,
    );
}

it('builds an ordered, source-backed pack for a dense CAC/CPL metric', function () {
    $pack = buildPack(denseBrief(), denseFacilitationBrief());

    expect($pack['schema_version'])->toBe('0.1')
        ->and($pack['provenance'])->toBe('deterministic')
        ->and($pack['metric_id'])->toBe('metric-1')
        ->and($pack['generated_at'])->toBeNull()
        ->and($pack['decisions'])->toHaveCount(2);

    // Ordered by run-of-show position: overhead (segment 2) before cost pool (3).
    $overhead = $pack['decisions'][0];
    expect($overhead['field_key'])->toBe('overhead_handling')
        ->and($overhead['run_of_show_item_id'])->toBe('segment:overhead')
        ->and($overhead['attention_tier'])->toBe('must_resolve')
        ->and($pack['decisions'][1]['field_key'])->toBe('cost_pool');

    // Source positions carry the submitted values and their citations.
    expect($overhead['source_positions'])->toHaveCount(2)
        ->and($overhead['source_positions'][0]['stakeholder_name'])->toBe('Alice Finance')
        ->and($overhead['source_positions'][0]['stakeholder_affiliation'])->toBe('Finance')
        ->and($overhead['source_positions'][0]['value'])->toBe('Head Count Ratio')
        ->and($overhead['source_positions'][0]['citation_ids'])->toContain('submitted_answer:def-1:overhead_handling');

    // Companion context is grouped once under its label with the respondent value.
    expect($overhead['companion_groups'])->toHaveCount(1)
        ->and($overhead['companion_groups'][0]['field_label'])->toBe('Allocation and apportionment rules')
        ->and($overhead['companion_groups'][0]['answers'])->toHaveCount(1)
        ->and($overhead['companion_groups'][0]['answers'][0]['value'])->toBe('We allocate shared RevOps overhead by headcount.');

    // Matrix context summarizes the related field as source-backed text.
    expect($overhead['matrix_context'])->toHaveCount(1)
        ->and($overhead['matrix_context'][0]['text'])->toBe('Cost Pool')
        ->and($overhead['matrix_context'][0]['citation_ids'])->toContain('field_schema:cost_pool');

    // Start move comes from the template workshop prompt.
    expect($overhead['start_with']['text'])->toBe('How should corporate overhead be allocated?')
        ->and($overhead['decision_question']['text'])->toBe('How should corporate overhead be allocated?')
        ->and($overhead['decision_question']['citation_ids'])->toContain('field_guidance:overhead_handling:prompt');

    // Capture requirements include the must-resolve operating-view label.
    $captureLabels = array_column($overhead['capture_requirements'], 'label');
    expect($captureLabels)->toContain('Official wording captured')
        ->toContain('Rationale captured')
        ->toContain('Surviving operating view labeled')
        ->toContain('Owner or approving role captured');

    // At least one process risk for the contested definition.
    expect($overhead['watch_outs'])->not->toBeEmpty()
        ->and(array_column($overhead['watch_outs'], 'label'))
        ->toContain('Do not present one stakeholder source position as the recommendation.');

    // Readiness overview: two decisions, no entry blockers, full timebox.
    expect($pack['readiness']['decision_count'])->toBe(2)
        ->and($pack['readiness']['blocker_count'])->toBe(0)
        ->and($pack['readiness']['planned_minutes'])->toBe(35)
        ->and($pack['readiness']['state'])->toBe('in_progress');

    // The overhead opener derives from the same workshop prompt as its decision
    // question, so the opening plan would only restate decision #1 and is omitted.
    expect($pack['opening_plan'])->toBeNull();
});

it('links related decisions only when the matrix context supports the relationship', function () {
    $pack = buildPack(denseBrief(), denseFacilitationBrief());

    // overhead handling's matrix context names cost pool, so they are linked...
    expect($pack['decisions'][0]['related_decision_ids'])->toBe(['readiness_decision:cost_pool'])
        ->and($pack['decisions'][1]['related_decision_ids'])->toBe(['readiness_decision:overhead_handling']);

    // ...but with the matrix link removed, no relationship is invented.
    $brief = denseBrief();
    $brief['cue_contexts'] = [
        packCueContext('overhead_handling', 'Overhead Handling'),
        packCueContext('cost_pool', 'Cost Pool'),
    ];
    $unlinked = buildPack($brief, denseFacilitationBrief());

    expect($unlinked['decisions'][0]['related_decision_ids'])->toBe([])
        ->and($unlinked['decisions'][1]['related_decision_ids'])->toBe([]);
});

it('produces a sparse but concrete pack for a thin Focus Time metric', function () {
    $positions = [
        packPosition('focus_hours_target', 'Ideal focus hours per workday', 'usr-1', 'Avery Lee', '4', 'Engineering'),
        packPosition('focus_hours_target', 'Ideal focus hours per workday', 'usr-2', 'Jordan Park', '2', 'Product'),
    ];

    $brief = [
        'summary' => [],
        'stakeholder_positions' => $positions,
        'companion_answers' => [],
        'cue_contexts' => [packCueContext('focus_hours_target', 'Ideal focus hours per workday')],
        'workshop_cues' => [],
        'material_conflicts' => [
            packAttentionItem('material_conflict', 'focus_hours_target', 'Ideal focus hours per workday', $positions),
        ],
        'decision_needed' => [],
        'missing_evidence' => [],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [packQuestion('focus_hours_target', 'How many focus hours are realistic per day?')],
        'resolution_options' => [],
        'draft_alignment_summary' => null,
    ];

    $pack = buildPack(
        $brief,
        facilitationBrief: null,
        fieldRoles: [packFieldRole('focus_hours_target', 'Ideal focus hours per workday', 'How many focus hours are realistic per day?')],
        metricId: 'focus-1',
        metricName: 'Focus Time',
    );

    expect($pack['decisions'])->toHaveCount(1);

    $decision = $pack['decisions'][0];
    // Concrete content...
    expect($decision['source_positions'])->toHaveCount(2)
        ->and($decision['start_with']['text'])->toBe('How many focus hours are realistic per day?')
        ->and($decision['capture_requirements'])->not->toBeEmpty();

    // ...without padded optional sections.
    expect($decision['companion_groups'])->toBe([])
        ->and($decision['matrix_context'])->toBe([])
        ->and($decision['related_decision_ids'])->toBe([])
        ->and($decision['fallback_question'])->toBeNull();

    // No facilitation plan and no blockers: preparation is the next step. The
    // opener restates the only decision (shared workshop prompt), so it is omitted.
    expect($pack['workshop_thesis'])->toBeNull()
        ->and($pack['readiness']['planned_minutes'])->toBe(0)
        ->and($pack['readiness']['blocker_count'])->toBe(0)
        ->and($pack['readiness']['state'])->toBe('needs_review')
        ->and($pack['opening_plan'])->toBeNull();
});

it('keeps the opening plan when the opener is a distinct run-of-show title, not a restatement', function () {
    // The field carries no workshop prompt, so the start move comes from the
    // run-of-show segment title — which differs from the decision question and so
    // earns its own "open with" block.
    $positions = [
        packPosition('lead_definition', 'Lead Definition', 'def-1', 'Alice Finance', 'MQL', 'Finance'),
        packPosition('lead_definition', 'Lead Definition', 'def-2', 'Bob Sales', 'SQL', 'Sales'),
    ];

    $brief = [
        'summary' => [],
        'stakeholder_positions' => $positions,
        'companion_answers' => [],
        'cue_contexts' => [packCueContext('lead_definition', 'Lead Definition')],
        'workshop_cues' => [],
        'material_conflicts' => [
            packAttentionItem('material_conflict', 'lead_definition', 'Lead Definition', $positions),
        ],
        'decision_needed' => [],
        'missing_evidence' => [],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [packQuestion('lead_definition', 'Which leads count as qualified?')],
        'resolution_options' => [],
        'draft_alignment_summary' => null,
    ];

    $facilitationBrief = packFacilitationBrief(
        runOfShow: [
            ['id' => 'segment:lead', 'position' => 1, 'duration_minutes' => 20, 'title' => 'Define a qualified lead', 'decision_ids' => ['decision:lead'], 'purpose' => ['text' => 'Decide']],
        ],
        decisionsToLock: [
            ['id' => 'decision:lead', 'title' => 'Lead', 'field_keys' => ['lead_definition']],
        ],
    );

    $pack = buildPack(
        $brief,
        $facilitationBrief,
        fieldRoles: [packFieldRole('lead_definition', 'Lead Definition', prompt: null)],
    );

    $decision = $pack['decisions'][0];
    expect($decision['start_with']['text'])->toBe('Define a qualified lead')
        ->and($decision['decision_question']['text'])->toBe('Which leads count as qualified?');

    expect($pack['opening_plan'])->not->toBeNull();
    expect($pack['opening_plan']['first_decision_id'])->toBe('readiness_decision:lead_definition')
        ->and($pack['opening_plan']['opening_question']['text'])->toBe('Define a qualified lead')
        ->and($pack['opening_plan']['timebox_note'])->toBe('20 min planned for this decision.');
});

it('marks a missing-evidence field as unresolved without inventing stakeholder positions', function () {
    $brief = [
        'summary' => [],
        'stakeholder_positions' => [],
        'companion_answers' => [],
        'cue_contexts' => [],
        'workshop_cues' => [],
        'material_conflicts' => [],
        'decision_needed' => [],
        'missing_evidence' => [
            packAttentionItem('missing_evidence', 'roadmap_alignment', 'Roadmap Alignment', []),
        ],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [packQuestion('roadmap_alignment', 'Who owns roadmap alignment?')],
        'resolution_options' => [],
        'draft_alignment_summary' => null,
    ];

    $pack = buildPack(
        $brief,
        facilitationBrief: null,
        fieldRoles: [packFieldRole('roadmap_alignment', 'Roadmap Alignment', 'Who owns roadmap alignment?')],
    );

    expect($pack['decisions'])->toHaveCount(1);

    $decision = $pack['decisions'][0];
    expect($decision['attention_tier'])->toBe('missing_evidence')
        // No submitted answers exist, so no stakeholder stance is invented.
        ->and($decision['source_positions'])->toBe([])
        ->and($decision['watch_outs'])->toBe([]);

    // The capture checklist records an explicit unresolved-evidence item.
    $captureLabels = array_column($decision['capture_requirements'], 'label');
    expect($captureLabels)->toContain('Unresolved evidence gap marked')
        ->not->toContain('Official wording captured');

    // Missing evidence counts as an entry blocker and surfaces in the summary.
    expect($pack['readiness']['blocker_count'])->toBe(1)
        ->and($pack['readiness']['state'])->toBe('blocked')
        ->and($pack['readiness']['blocker_summary'])->not->toBeEmpty()
        ->and(array_column($pack['facilitation_risks'], 'id'))->toContain('risk:workshop:unresolved_evidence');
});

it('treats a lone readiness gap as a hard entry blocker (state blocked, not needs_review)', function () {
    // FX02: without the gap this pack is `in_progress` (a dense draft plan). A
    // single required-perspective gap must flip it to `blocked`, not soften it to
    // `needs_review` -- a missing required voice demands a pre-workshop action.
    $gap = [
        'id' => 'readiness:required_department:finance',
        'scope' => 'workshop_readiness',
        'kind' => 'missing_stakeholder_perspective',
        'label' => 'Finance perspective',
        'description' => 'Finance is required by this metric template but has no submitted response. Confirm a Finance participant or explicitly accept the missing perspective before the workshop.',
        'source_item_ids' => ['readiness:required_department:finance'],
        'citation_ids' => ['metric_template:cac:required_department:finance'],
    ];

    // Baseline: the same dense inputs with no gap are not blocked.
    expect(buildPack(denseBrief(), denseFacilitationBrief())['readiness']['state'])
        ->toBe('in_progress');

    $pack = buildPack(
        denseBrief(),
        denseFacilitationBrief(),
        workshopReadiness: ['gaps' => [$gap]],
    );

    expect($pack['readiness']['state'])->toBe('blocked')
        ->and($pack['readiness']['blocker_count'])->toBe(1)
        ->and($pack['readiness']['next_action'])->toBe('Resolve 1 blocking item(s) before the workshop.');
});

it('renders a missing-perspective blocker as a full self-explanatory sentence, not a bare label', function () {
    // Mirrors the WorkshopReadinessSnapshot gap shape: a short label fragment plus
    // a description that actually names what is missing and what to do.
    $gap = [
        'id' => 'readiness:required_department:sales',
        'scope' => 'workshop_readiness',
        'kind' => 'missing_stakeholder_perspective',
        'label' => 'Sales perspective',
        'description' => 'Sales is required by this metric template but has no submitted response. Confirm a Sales participant or explicitly accept the missing perspective before the workshop.',
        'source_item_ids' => ['readiness:required_department:sales'],
        'citation_ids' => ['metric_template:cac:required_department:sales'],
    ];

    $pack = buildPack(
        denseBrief(),
        denseFacilitationBrief(),
        workshopReadiness: ['gaps' => [$gap]],
    );

    $summary = $pack['readiness']['blocker_summary'];
    expect($summary)->toHaveCount(1);

    $blockerText = $summary[0]['text'];
    expect($blockerText)->toBe($gap['description'])
        // The bare label fragment must never be the whole blocker line on its own.
        ->and($blockerText)->not->toBe('Sales perspective')
        // The citation id from the gap is preserved on the summary line.
        ->and($summary[0]['citation_ids'])->toContain('metric_template:cac:required_department:sales');
});

it('falls back to the gap label when no description is present', function () {
    $gap = [
        'id' => 'readiness:required_department:sales',
        'scope' => 'workshop_readiness',
        'kind' => 'missing_stakeholder_perspective',
        'label' => 'Sales perspective',
        'description' => '',
        'source_item_ids' => [],
        'citation_ids' => [],
    ];

    $pack = buildPack(
        denseBrief(),
        denseFacilitationBrief(),
        workshopReadiness: ['gaps' => [$gap]],
    );

    expect($pack['readiness']['blocker_summary'][0]['text'])->toBe('Sales perspective');
});

it('does not restate a readiness gap as a capture item once it is already a blocker', function () {
    $gap = [
        'id' => 'readiness:required_department:finance',
        'scope' => 'workshop_readiness',
        'kind' => 'missing_stakeholder_perspective',
        'label' => 'Finance perspective',
        'description' => 'Finance is required by this metric template but has no submitted response. Confirm a Finance participant or explicitly accept the missing perspective before the workshop.',
        'source_item_ids' => ['readiness:required_department:finance'],
        'citation_ids' => ['metric_template:cac:required_department:finance'],
    ];

    $pack = buildPack(
        denseBrief(),
        denseFacilitationBrief(),
        workshopReadiness: ['gaps' => [$gap]],
    );

    // The gap shows once, as an entry blocker carrying its full sentence.
    expect($pack['readiness']['blocker_summary'])->toHaveCount(1)
        ->and($pack['readiness']['blocker_summary'][0]['text'])->toBe($gap['description']);

    // It is not echoed back as a workshop-level capture line. Every remaining
    // capture item is a per-decision requirement carrying a decision id.
    $captureLabels = array_column($pack['capture_checklist'], 'label');
    expect($captureLabels)->not->toContain('Confirm or accept Finance perspective');

    expect($pack['capture_checklist'])->not->toBeEmpty();
    foreach ($pack['capture_checklist'] as $item) {
        expect($item['decision_id'])->not->toBeNull()
            ->and($item['id'])->not->toStartWith('capture:readiness:');
    }
});

it('keeps every evidence-based statement carrying source refs and citation ids', function () {
    $pack = buildPack(denseBrief(), denseFacilitationBrief());

    foreach ($pack['decisions'] as $decision) {
        // Decision questions are workshop-prompt backed.
        expect($decision['decision_question']['citation_ids'])->not->toBeEmpty();

        foreach ($decision['source_positions'] as $position) {
            expect($position['source_refs'])->not->toBeEmpty()
                ->and($position['citation_ids'])->not->toBeEmpty();
        }

        // Top-level decision refs are deduped to a unique id set.
        $ids = array_column($decision['source_refs'], 'id');
        expect($ids)->toBe(array_values(array_unique($ids)));
    }

    // The pack rolls up a deduped citation set across its decisions.
    expect($pack['citation_ids'])->toBe(array_values(array_unique($pack['citation_ids'])))
        ->and($pack['citation_ids'])->toContain('submitted_answer:def-1:overhead_handling');
});

it('reports no preparation needed when there are no decisions or blockers', function () {
    $brief = [
        'summary' => [],
        'stakeholder_positions' => [],
        'companion_answers' => [],
        'cue_contexts' => [],
        'workshop_cues' => [],
        'material_conflicts' => [],
        'decision_needed' => [],
        'missing_evidence' => [],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [],
        'resolution_options' => [],
        'draft_alignment_summary' => null,
    ];

    $pack = buildPack($brief, facilitationBrief: null, fieldRoles: []);

    expect($pack['decisions'])->toBe([])
        ->and($pack['opening_plan'])->toBeNull()
        ->and($pack['capture_checklist'])->toBe([])
        ->and($pack['readiness']['state'])->toBe('no_prep_needed')
        ->and($pack['readiness']['decision_count'])->toBe(0);
});
