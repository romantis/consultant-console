<?php

use App\Actions\Metrics\BuildDeterministicFacilitationBrief;

function deterministicBriefFixture(): array
{
    return [
        'material_conflicts' => [[
            'id' => 'field:cost:material_conflict',
            'field_key' => 'cost',
            'field_label' => 'CAC cost components',
            'title' => 'Stakeholders disagree on CAC cost components',
            'rationale' => 'Finance and Marketing differ on the cost pool.',
            'source_refs' => [['id' => 'submitted_answer:1'], ['id' => 'submitted_answer:2']],
        ]],
        'decision_needed' => [],
        'missing_evidence' => [[
            'id' => 'field:lead:missing_evidence',
            'field_key' => 'lead',
            'field_label' => 'CPL lead definition',
            'title' => 'Missing answers for CPL lead definition',
            'rationale' => 'No submitted answer is available.',
            'source_refs' => [],
        ]],
        'context_signals' => [[
            'id' => 'field:notes:context_signal',
            'field_key' => 'notes',
            'title' => 'Reporting context',
            'rationale' => 'Marketing tracks a separate operating view.',
            'source_refs' => [['id' => 'field_guidance:notes']],
        ]],
        'likely_non_issues' => [],
        'recommended_questions' => [[
            'id' => 'question:cost',
            'field_key' => 'cost',
            'question' => 'Agree the official cost pool.',
            'reason' => 'Material conflict.',
            'priority' => 'high',
            'source_refs' => [['id' => 'submitted_answer:1']],
        ]],
        'resolution_options' => [[
            'id' => 'option:cost:use-1',
            'field_key' => 'cost',
            'label' => 'Use Finance position',
            'description' => 'Adopt the fully-loaded view.',
            'source_refs' => [['id' => 'submitted_answer:1']],
        ]],
    ];
}

it('projects the canonical brief into a deterministic draft-shaped brief', function () {
    $draft = app(BuildDeterministicFacilitationBrief::class)
        ->build(deterministicBriefFixture(), 'metric-1', 'CAC & CPL');

    expect($draft['schema_version'])->toBe('1.0')
        ->and($draft['status'])->toBe('draft')
        ->and($draft['metric_id'])->toBe('metric-1')
        ->and($draft['metric_name'])->toBe('CAC & CPL');

    // Thesis distinguishes the conflict to reconcile from the evidence gap to
    // close, so the missing-evidence field is never called "contested".
    expect($draft['summary']['text'])
        ->toContain('reconcile 1 contested aspect')
        ->toContain('close 1 evidence gap')
        ->toContain('of CAC & CPL')
        ->toContain('CAC cost components')
        ->toContain('CPL lead definition');
    expect($draft['summary']['citation_ids'])->toContain('submitted_answer:1');
});

it('maps each canonical agenda item to a resolution-focus decision', function () {
    $focus = app(BuildDeterministicFacilitationBrief::class)
        ->build(deterministicBriefFixture(), 'metric-1', 'CAC & CPL')['resolution_focus'];

    expect($focus)->toHaveCount(2);

    // Material conflict → must_resolve, with options + questions attached.
    $cost = collect($focus)->firstWhere('field_key', 'cost');
    expect($cost['kind'])->toBe('must_resolve')
        ->and($cost['title'])->toBe('CAC cost components')
        ->and($cost['synthesis'])->toBe('Finance and Marketing differ on the cost pool.')
        ->and($cost['source_item_ids'])->toBe(['field:cost:material_conflict'])
        ->and($cost['citation_ids'])->toBe(['submitted_answer:1', 'submitted_answer:2'])
        ->and($cost['facilitator_questions'][0]['question'])->toBe('Agree the official cost pool.')
        ->and($cost['candidate_resolution_paths'][0]['label'])->toBe('Use Finance position');

    // Missing evidence → no candidate paths (nothing submitted to restate).
    $lead = collect($focus)->firstWhere('field_key', 'lead');
    expect($lead['kind'])->toBe('missing_evidence')
        ->and($lead['candidate_resolution_paths'])->toBe([]);
});

it('carries the template workshop prompt onto the field that defines one', function () {
    $brief = deterministicBriefFixture();
    // The cost field's canonical attention item gains a workshop_prompt
    // field-guidance source ref, which the brief builder only emits when the
    // template actually defines a prompt for the field.
    $brief['material_conflicts'][0]['source_refs'][] = [
        'id' => 'field_guidance:cost:prompt',
        'type' => 'field_guidance',
        'excerpt' => 'Agree which acquisition cost pool defines official CAC/CPL',
        'meta' => ['guidance_kind' => 'workshop_prompt'],
    ];

    $focus = app(BuildDeterministicFacilitationBrief::class)
        ->build($brief, 'metric-1', 'CAC & CPL')['resolution_focus'];

    $cost = collect($focus)->firstWhere('field_key', 'cost');
    $lead = collect($focus)->firstWhere('field_key', 'lead');

    // The verbatim prompt rides along on the field that defines it...
    expect($cost['workshop_prompt'])->toBe('Agree which acquisition cost pool defines official CAC/CPL')
        // ...and stays empty for a field with no template prompt.
        ->and($lead['workshop_prompt'])->toBe('');
});

it('projects supporting context from canonical signals', function () {
    $context = app(BuildDeterministicFacilitationBrief::class)
        ->build(deterministicBriefFixture(), 'metric-1', 'CAC & CPL')['supporting_context'];

    expect($context)->toHaveCount(1)
        ->and($context[0]['kind'])->toBe('context_signal')
        ->and($context[0]['field_key'])->toBe('notes')
        ->and($context[0]['note'])->toBe('Marketing tracks a separate operating view.');
});
