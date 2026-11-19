<?php

use App\Actions\Metrics\BuildDeterministicFacilitationBrief;
use App\Actions\Metrics\DeriveMetricFacilitationPlan;
use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\Validator;

/**
 * Builds a generated draft with three decisions in deliberately non-priority
 * order so the run-of-show ordering can be asserted: a medium-priority
 * (missing_evidence) item first, then two high-priority (must_resolve) items.
 */
function facilitationPlanDraft(): array
{
    return [
        'metric_id' => 'metric-1',
        'metric_name' => 'Test Metric',
        'summary' => ['text' => 'Stakeholders disagree.', 'citation_ids' => ['cite-a']],
        'resolution_focus' => [
            [
                'field_key' => 'evidence_gap',
                'kind' => 'missing_evidence',
                'title' => 'Confirm the missing evidence',
                'synthesis' => 'Evidence is incomplete.',
                'source_item_ids' => ['topic:evidence_gap'],
                'citation_ids' => ['cite-a'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
            [
                'field_key' => 'cost_pool',
                'kind' => 'must_resolve',
                'title' => 'Choose the official cost pool',
                'synthesis' => 'Finance and Marketing differ.',
                'source_item_ids' => ['topic:cost_pool'],
                'citation_ids' => ['cite-a'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
            [
                'field_key' => 'lead_denominator',
                'kind' => 'must_resolve',
                'title' => 'Choose the official lead denominator',
                'synthesis' => 'Teams count leads differently.',
                'source_item_ids' => ['topic:lead_denominator'],
                'citation_ids' => ['cite-a'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
        ],
        'supporting_context' => [],
    ];
}

function facilitationAgendaItem(string $id, string $fieldKey, string $kind, string $label): array
{
    return [
        'id' => $id,
        'field_key' => $fieldKey,
        'field_label' => $label,
        'kind' => $kind,
        // Canonical agenda titles are statements, not decision labels.
        'title' => "Stakeholders disagree on {$label}.",
        'rationale' => "Stakeholders submitted different values for {$label}.",
        'citation_ids' => ["submitted_answer:{$fieldKey}"],
    ];
}

/**
 * Defaults to a canonical agenda whose three topics the draft already covers, so
 * no synthetic decisions are added. Pass $agendaItems to exercise omitted topics.
 *
 * @param  list<array<string, mixed>>|null  $agendaItems
 */
function derivedFacilitationPlan(?array $agendaItems = null): array
{
    $agendaItems ??= [
        facilitationAgendaItem('topic:evidence_gap', 'evidence_gap', 'missing_evidence', 'Confirm the missing evidence'),
        facilitationAgendaItem('topic:cost_pool', 'cost_pool', 'must_resolve', 'Choose the official cost pool'),
        facilitationAgendaItem('topic:lead_denominator', 'lead_denominator', 'must_resolve', 'Choose the official lead denominator'),
    ];

    return app(DeriveMetricFacilitationPlan::class)->handle(
        facilitationPlanDraft(),
        'fingerprint-1',
        null,
        $agendaItems,
        [],
    )->toArray();
}

it('sequences the run of show one segment per decision, ordered by priority', function () {
    $runOfShow = derivedFacilitationPlan()['run_of_show'];

    // Framing opener, one segment per decision, readiness closer.
    expect($runOfShow)->toHaveCount(5);

    expect(array_column($runOfShow, 'title'))->toBe([
        'Frame the metric and alignment goal',
        // High-priority must_resolve items lead, in draft order...
        'Lock: Choose the official cost pool',
        'Lock: Choose the official lead denominator',
        // ...then the medium-priority missing_evidence item.
        'Lock: Confirm the missing evidence',
        'Confirm readiness and agree next steps',
    ]);

    // Each decision segment references exactly its own decision.
    expect($runOfShow[1]['decision_ids'])->toBe(['decision:cost_pool'])
        ->and($runOfShow[2]['decision_ids'])->toBe(['decision:lead_denominator'])
        ->and($runOfShow[3]['decision_ids'])->toBe(['decision:evidence_gap']);

    // Timeboxing follows priority, and the bookends carry no decisions.
    expect($runOfShow[1]['duration_minutes'])->toBe(15)
        ->and($runOfShow[3]['duration_minutes'])->toBe(12)
        ->and($runOfShow[0]['decision_ids'])->toBe([])
        ->and($runOfShow[4]['decision_ids'])->toBe([]);
});

it('states the lock method once and keeps per-decision purposes short', function () {
    $runOfShow = derivedFacilitationPlan()['run_of_show'];

    // The shared facilitation method is stated once, in the framing opener...
    expect($runOfShow[0]['purpose']['text'])
        ->toContain('surface the stakeholder positions, weigh the trade-offs, and lock the official choice');

    // ...and is not restated per decision; lock segments carry a short,
    // schema-valid (non-empty) purpose instead of the repeated boilerplate.
    expect($runOfShow[1]['purpose']['text'])->toBe('Lock the official choice the group commits to.')
        ->and($runOfShow[2]['purpose']['text'])->toBe('Lock the official choice the group commits to.')
        ->and($runOfShow[3]['purpose']['text'])->toBe('Lock the official choice the group commits to.');

    foreach ($runOfShow as $segment) {
        expect($segment['purpose']['text'])
            ->not->toContain('surface each stakeholder position, weigh the trade-offs, and agree the official choice');
    }
});

it('trims expected-output descriptions to one non-repetitive line per decision', function () {
    $outputs = derivedFacilitationPlan()['expected_outputs'];

    expect($outputs[0]['description']['text'])
        ->toBe('Choose the official cost pool — documented official rule, ready for reconciliation.');

    // The old auto-generated boilerplate tail is gone from every output.
    foreach ($outputs as $output) {
        expect($output['description']['text'])
            ->not->toContain('the official rule plus any explicitly labeled operating views, ready to carry into the formal reconciliation flow');
    }
});

it('titles field-bound segments from template workshop prompts, falling back to Lock/Confirm', function () {
    // A canonical brief with one conflict field that defines a template workshop
    // prompt (carried as a workshop_prompt field-guidance source ref) and one
    // decision field that does not.
    $brief = [
        'material_conflicts' => [[
            'id' => 'field:costs_included:material_conflict',
            'field_key' => 'costs_included',
            'field_label' => 'CAC cost components',
            'title' => 'Stakeholders disagree on CAC cost components',
            'rationale' => 'Finance and Marketing differ on the cost pool.',
            'source_refs' => [
                ['id' => 'submitted_answer:1'],
                [
                    'id' => 'field_guidance:costs_included:prompt',
                    'type' => 'field_guidance',
                    'excerpt' => 'Agree which acquisition cost pool defines official CAC/CPL',
                    'meta' => ['guidance_kind' => 'workshop_prompt'],
                ],
            ],
        ]],
        'decision_needed' => [[
            'id' => 'field:lead_definition:decision_needed',
            'field_key' => 'lead_definition',
            'field_label' => 'CPL lead definition',
            'title' => 'Confirm the official CPL lead definition',
            'rationale' => 'No template prompt is defined for this field.',
            'source_refs' => [['id' => 'submitted_answer:2']],
        ]],
        'missing_evidence' => [],
        'context_signals' => [],
        'likely_non_issues' => [],
        'recommended_questions' => [],
        'resolution_options' => [],
    ];

    $draft = app(BuildDeterministicFacilitationBrief::class)->build($brief, 'metric-1', 'CAC & CPL');

    $titles = array_column(app(DeriveMetricFacilitationPlan::class)->handle(
        $draft,
        'fingerprint-1',
        null,
        [
            facilitationAgendaItem('field:costs_included:material_conflict', 'costs_included', 'must_resolve', 'CAC cost components'),
            facilitationAgendaItem('field:lead_definition:decision_needed', 'lead_definition', 'needs_confirmation', 'CPL lead definition'),
        ],
        [],
    )->toArray()['run_of_show'], 'title');

    // Prompt-first for the field that defines one; Lock/Confirm + label fallback
    // for the field that does not; the generic bookends stay unchanged.
    expect($titles)->toBe([
        'Frame the metric and alignment goal',
        'Agree which acquisition cost pool defines official CAC/CPL',
        'Confirm: CPL lead definition',
        'Confirm readiness and agree next steps',
    ]);
});

it('does not mirror template executive sections in the run of show', function () {
    $titles = implode("\n", array_column(derivedFacilitationPlan()['run_of_show'], 'title'));

    foreach (['Official Definition', 'Measurement Logic', 'Controls & Caveats', 'Other Decisions'] as $section) {
        expect($titles)->not->toContain($section);
    }
});

it('names each decision in concrete expected outputs instead of generic section completion', function () {
    $outputs = derivedFacilitationPlan()['expected_outputs'];

    $labels = array_column($outputs, 'label');

    expect($labels)->toBe([
        'Locked: Choose the official cost pool',
        'Locked: Choose the official lead denominator',
        'Locked: Confirm the missing evidence',
        'Workshop decision log',
    ]);

    // No "<Section> agreed" generic completion labels remain.
    foreach ($labels as $label) {
        expect($label)->not->toEndWith(' agreed');
    }

    // The per-decision outputs link back to their decision; the log does not.
    expect($outputs[0]['decision_ids'])->toBe(['decision:cost_pool'])
        ->and($outputs[3]['decision_ids'])->toBe([]);
});

it('keeps run-of-show and expected-output references pointing at defined decisions', function () {
    $document = derivedFacilitationPlan();

    $decisionIds = array_column($document['decisions_to_lock'], 'id');

    $referenced = collect($document['run_of_show'])
        ->concat($document['expected_outputs'])
        ->pluck('decision_ids')
        ->flatten()
        ->unique()
        ->all();

    expect(array_diff($referenced, $decisionIds))->toBe([]);
});

it('derives a schema-valid document for a multi-decision draft', function () {
    $schemaPath = base_path('specs/034-optymize-prompt/contracts/consultant-facilitation-brief.schema.json');
    $schema = json_decode(file_get_contents($schemaPath), flags: JSON_THROW_ON_ERROR);
    $document = json_decode(json_encode(derivedFacilitationPlan(), JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

    expect((new Validator)->validate($document, $schema)->isValid())->toBeTrue();
});

it('covers canonical conflicts the capped draft omitted with deterministic decisions', function () {
    // The draft (a capped top-5 scan brief) surfaced 3 of these topics; two
    // canonical conflicts beyond its cap are not in the draft at all.
    $agendaItems = [
        facilitationAgendaItem('topic:cost_pool', 'cost_pool', 'must_resolve', 'Choose the official cost pool'),
        facilitationAgendaItem('topic:lead_denominator', 'lead_denominator', 'must_resolve', 'Choose the official lead denominator'),
        facilitationAgendaItem('topic:evidence_gap', 'evidence_gap', 'missing_evidence', 'Confirm the missing evidence'),
        facilitationAgendaItem('topic:discount_rule', 'discount_rule', 'must_resolve', 'Agree the discount treatment'),
        facilitationAgendaItem('topic:timing_rule', 'timing_rule', 'needs_confirmation', 'Timing rule'),
    ];

    $document = derivedFacilitationPlan($agendaItems);

    // Every canonical topic becomes a decision; nothing is dropped to the cap.
    $decisionFieldKeys = collect($document['decisions_to_lock'])
        ->pluck('field_keys')
        ->flatten()
        ->all();
    expect($document['decisions_to_lock'])->toHaveCount(5)
        ->and($decisionFieldKeys)->toContain('discount_rule')
        ->and($decisionFieldKeys)->toContain('timing_rule')
        ->and($document['coverage']['omitted_topics'])->toBe([]);

    // The omitted conflicts flow into the run of show and the outputs too, using
    // the clean field label rather than the canonical "Stakeholders disagree on"
    // statement.
    $titles = array_column($document['run_of_show'], 'title');
    expect($titles)->toContain('Lock: Agree the discount treatment')
        ->and($titles)->toContain('Confirm: Timing rule')
        ->and($titles)->not->toContain(
            'Lock: Stakeholders disagree on Agree the discount treatment.',
        )
        ->and($titles)->not->toContain('Lock: Timing rule');
    expect(array_column($document['expected_outputs'], 'label'))
        ->toContain('Locked: Agree the discount treatment')
        ->and(array_column($document['expected_outputs'], 'label'))
        ->toContain('Confirmed: Timing rule');

    // A canonical-sourced decision keeps source-backed provenance and cites the
    // canonical agenda topic.
    $discount = collect($document['decisions_to_lock'])
        ->firstWhere('id', 'decision:discount_rule');
    expect($discount['decision_prompt']['provenance'])->toBe('source_backed')
        ->and($discount['decision_prompt']['source_item_ids'])->toBe(['topic:discount_rule'])
        ->and($discount['priority'])->toBe('high');
});

it('derives a full deterministic plan from the canonical agenda when there is no draft', function () {
    $emptyDraft = [
        'metric_id' => 'metric-1',
        'metric_name' => 'Monthly Recurring Revenue',
        'resolution_focus' => [],
        'summary' => ['text' => '', 'citation_ids' => []],
        'supporting_context' => [],
    ];

    $agendaItems = [
        facilitationAgendaItem('topic:measurement', 'measurement', 'must_resolve', 'MRR measurement approach'),
        facilitationAgendaItem('topic:annual', 'annual', 'must_resolve', 'Annual contract treatment'),
        facilitationAgendaItem('topic:discount', 'discount', 'needs_confirmation', 'Discount treatment'),
    ];

    $document = app(DeriveMetricFacilitationPlan::class)->handle(
        $emptyDraft,
        'fingerprint-1',
        null,
        $agendaItems,
        [],
    )->toArray();

    // Every canonical conflict becomes a decision even with no draft.
    $fieldKeys = collect($document['decisions_to_lock'])
        ->pluck('field_keys')
        ->flatten()
        ->all();
    expect($document['decisions_to_lock'])->toHaveCount(3)
        ->and($fieldKeys)->toContain('measurement')
        ->and($fieldKeys)->toContain('annual')
        ->and($fieldKeys)->toContain('discount')
        ->and($document['coverage']['omitted_topics'])->toBe([]);

    // The thesis is synthesized deterministically from the agenda, distinguishing
    // the two conflicts to reconcile from the decision to confirm, and is still
    // source-backed because the agenda items carry citations.
    expect($document['workshop_thesis']['text'])
        ->toContain('Monthly Recurring Revenue')
        ->toContain('reconcile 2 contested aspects')
        ->toContain('confirm 1 decision');
    expect($document['workshop_thesis']['provenance'])->toBe('source_backed');

    // Decision-oriented run of show + a null generated-draft snapshot.
    expect(array_column($document['run_of_show'], 'title'))
        ->toContain('Lock: MRR measurement approach');
    expect($document['source_snapshot']['generated_draft'])->toBeNull();
});

// The controller now always feeds a deterministic brief projection (whose focus
// items carry real source ids and never duplicate or fall off the agenda), so
// these edge cases can only be reached by calling the action directly.

it('derives a fallback-linked, system_suggested decision when a focus item has no source ids', function () {
    $draft = [
        'metric_id' => 'metric-1',
        'metric_name' => 'Test Metric',
        'summary' => ['text' => 'Stakeholders disagree.', 'citation_ids' => []],
        'resolution_focus' => [[
            'field_key' => 'value',
            'kind' => 'missing_evidence',
            'title' => 'Missing answers for the official value',
            'synthesis' => 'Only one stakeholder has answered.',
            'source_item_ids' => [],
            'citation_ids' => ['field:value:answer:1'],
            'facilitator_questions' => [],
            'candidate_resolution_paths' => [],
        ]],
        'supporting_context' => [],
    ];

    $document = app(DeriveMetricFacilitationPlan::class)->handle(
        $draft,
        'fingerprint-1',
        null,
        [facilitationAgendaItem('field:value:missing_evidence', 'value', 'missing_evidence', 'Official value')],
        [],
    )->toArray();

    $decision = $document['decisions_to_lock'][0];

    // No source ids → system_suggested prompt, medium priority, linked to the
    // kind-derived fallback agenda topic.
    expect($decision['decision_prompt']['provenance'])->toBe('system_suggested')
        ->and($decision['decision_prompt']['source_item_ids'])->toBe([])
        ->and($decision['agenda_topic_ids'])->toBe(['field:value:missing_evidence'])
        ->and($decision['priority'])->toBe('medium');
});

it('merges duplicate resolution focus items instead of dropping their evidence', function () {
    $draft = [
        'metric_id' => 'metric-1',
        'metric_name' => 'Test Metric',
        'summary' => ['text' => 'Stakeholders disagree.', 'citation_ids' => []],
        'resolution_focus' => [
            [
                'field_key' => 'value',
                'kind' => 'must_resolve',
                'title' => 'Choose the official value',
                'synthesis' => 'Positions differ.',
                'source_item_ids' => ['field:value:material_conflict'],
                'citation_ids' => ['field:value:answer:1'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
            [
                'field_key' => 'value',
                'kind' => 'must_resolve',
                'title' => 'Duplicate entry for the same field',
                'synthesis' => 'Finance and Marketing diverge.',
                'source_item_ids' => ['field:value:material_conflict'],
                'citation_ids' => ['field:value:answer:2'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
        ],
        'supporting_context' => [],
    ];

    $document = app(DeriveMetricFacilitationPlan::class)->handle(
        $draft,
        'fingerprint-1',
        null,
        [facilitationAgendaItem('field:value:material_conflict', 'value', 'must_resolve', 'Official value')],
        [],
    )->toArray();

    $decisions = $document['decisions_to_lock'];

    expect($decisions)->toHaveCount(1)
        ->and($decisions[0]['decision_prompt']['citation_ids'])
        ->toBe(['field:value:answer:1', 'field:value:answer:2'])
        ->and($decisions[0]['decision_prompt']['text'])
        ->toBe('Positions differ. Finance and Marketing diverge.');
});

it('rejects duplicate focus items with conflicting kinds', function () {
    $draft = [
        'metric_id' => 'metric-1',
        'metric_name' => 'Test Metric',
        'summary' => ['text' => 'Stakeholders disagree.', 'citation_ids' => []],
        'resolution_focus' => [
            [
                'field_key' => 'value',
                'kind' => 'missing_evidence',
                'title' => 'Missing answers for the official value',
                'synthesis' => 'Only one stakeholder has answered.',
                'source_item_ids' => ['field:value:missing_evidence'],
                'citation_ids' => [],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
            [
                'field_key' => 'value',
                'kind' => 'must_resolve',
                'title' => 'Choose the official value',
                'synthesis' => 'Positions differ.',
                'source_item_ids' => ['field:value:material_conflict'],
                'citation_ids' => [],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ],
        ],
        'supporting_context' => [],
    ];

    expect(fn () => app(DeriveMetricFacilitationPlan::class)->handle(
        $draft,
        'fingerprint-1',
        null,
        [],
        [],
    ))->toThrow(ValidationException::class);
});

it('rejects fallback linkage when no matching canonical agenda topic exists', function () {
    $draft = [
        'metric_id' => 'metric-1',
        'metric_name' => 'Test Metric',
        'summary' => ['text' => 'Stakeholders disagree.', 'citation_ids' => []],
        'resolution_focus' => [[
            'field_key' => 'context_only',
            'kind' => 'must_resolve',
            'title' => 'Choose the context-only value',
            'synthesis' => 'This field is not part of the canonical agenda.',
            'source_item_ids' => [],
            'citation_ids' => [],
            'facilitator_questions' => [],
            'candidate_resolution_paths' => [],
        ]],
        'supporting_context' => [],
    ];

    expect(fn () => app(DeriveMetricFacilitationPlan::class)->handle(
        $draft,
        'fingerprint-1',
        null,
        [],
        [],
    ))->toThrow(ValidationException::class);
});
