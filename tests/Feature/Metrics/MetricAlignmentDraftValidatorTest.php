<?php

use App\Support\Metrics\Drafting\MetricAlignmentDraftOutputValidator;
use App\Support\Metrics\Evaluation\MetricAlignmentDraftValidator;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationCase;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationDataset;

function materialConflictCase(): MetricAlignmentEvaluationCase
{
    return app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'))[0];
}

function validMaterialConflictDraft(): array
{
    return [
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => 'metric-gross-churn',
        'metric_name' => 'Gross Churn Rate',
        'summary' => [
            'text' => 'Finance and Customer Success use different churn events.',
            'citation_ids' => [
                'submitted_answer:definition-finance:churn_event',
                'submitted_answer:definition-customer-success:churn_event',
            ],
        ],
        'resolution_focus' => [[
            'field_key' => 'churn_event',
            'kind' => 'must_resolve',
            'title' => 'Choose the official churn event',
            'synthesis' => 'The submitted positions differ.',
            'source_item_ids' => ['field:churn_event:material_conflict'],
            'citation_ids' => [
                'submitted_answer:definition-finance:churn_event',
                'submitted_answer:definition-customer-success:churn_event',
            ],
            'facilitator_questions' => [[
                'question' => 'Which event should officially mark churn?',
                'reason' => 'The submitted positions differ.',
                'source_question_ids' => ['question:churn_event'],
                'citation_ids' => ['field_guidance:churn_event:prompt'],
            ]],
            'candidate_resolution_paths' => [[
                'label' => 'Use the Finance position',
                'description' => 'Use contract termination.',
                'source_option_ids' => ['option:churn_event:use-definition-finance'],
                'citation_ids' => ['submitted_answer:definition-finance:churn_event'],
            ]],
        ]],
        'supporting_context' => [],
    ];
}

it('passes a source-bound draft', function () {
    $result = app(MetricAlignmentDraftValidator::class)->validate(
        materialConflictCase(),
        validMaterialConflictDraft(),
    );

    expect($result->passed())->toBeTrue()
        ->and($result->errors)->toBe([]);
});

it('passes production output validation without evaluation-only expectations', function () {
    $case = materialConflictCase();
    $result = app(MetricAlignmentDraftOutputValidator::class)->validate(
        $case->input,
        validMaterialConflictDraft(),
    );

    expect($result->passed())->toBeTrue()
        ->and($result->errors)->toBe([]);
});

it('fails unknown citations and resolution options', function () {
    $draft = validMaterialConflictDraft();
    $draft['summary']['citation_ids'][] = 'invented:citation';
    $draft['resolution_focus'][0]['candidate_resolution_paths'][0]['source_option_ids'] = ['invented:option'];

    $result = app(MetricAlignmentDraftValidator::class)->validate(materialConflictCase(), $draft);

    expect($result->passed())->toBeFalse()
        ->and($result->errors)->toContain(
            'Unknown citation_ids value: invented:citation.',
            'Unknown source_option_ids value: invented:option.',
        );
});

it('rejects source items bound to a different field', function () {
    $case = materialConflictCase();
    $brief = $case->input;
    $brief['decision_needed'][] = [
        'id' => 'field:billing_boundary:decision_needed',
        'category' => 'decision_needed',
        'field_key' => 'billing_boundary',
        'field_label' => 'Billing boundary',
        'field_role' => 'definition',
        'title' => 'Confirm the billing boundary.',
        'rationale' => 'The submitted definition requires confirmation.',
        'source_refs' => [[
            'id' => 'field_guidance:billing_boundary:prompt',
            'type' => 'field_guidance',
            'label' => 'Workshop prompt',
            'field_key' => 'billing_boundary',
        ]],
    ];

    $draft = validMaterialConflictDraft();
    $draft['resolution_focus'][0]['source_item_ids'] = [
        'field:billing_boundary:decision_needed',
    ];

    $result = app(MetricAlignmentDraftOutputValidator::class)->validate($brief, $draft);

    expect($result->passed())->toBeFalse()
        ->and($result->errors)->toContain(
            'Source item field:billing_boundary:decision_needed does not belong to field churn_event.',
            'Source item field:billing_boundary:decision_needed does not support resolution kind must_resolve.',
        );
});

it('rejects duplicate bound identifiers even when the provider grammar omits uniqueItems', function () {
    $draft = validMaterialConflictDraft();
    $draft['summary']['citation_ids'][] = 'submitted_answer:definition-finance:churn_event';

    $result = app(MetricAlignmentDraftValidator::class)->validate(materialConflictCase(), $draft);

    expect($result->errors)->toContain(
        'citation_ids values must be unique within each list.',
    );
});

it('rejects text that exceeds the canonical output schema limits', function () {
    $draft = validMaterialConflictDraft();
    $draft['summary']['text'] = str_repeat('a', 601);

    $result = app(MetricAlignmentDraftValidator::class)->validate(materialConflictCase(), $draft);

    expect($result->passed())->toBeFalse()
        ->and($result->errors)->toContain(
            'Canonical output schema violation at /summary/text: Maximum string length is 600, found 601',
        );
});

it('rejects incomplete structures that the domain checks do not cover', function () {
    $draft = validMaterialConflictDraft();
    unset($draft['resolution_focus'][0]['facilitator_questions']);

    $result = app(MetricAlignmentDraftValidator::class)->validate(materialConflictCase(), $draft);

    expect($result->passed())->toBeFalse()
        ->and($result->errors)->toContain(
            'Canonical output schema violation at /resolution_focus/0: The required properties (facilitator_questions) are missing',
        );
});

it('rejects candidate paths for missing evidence', function () {
    $case = app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'))[1];
    $draft = validMaterialConflictDraft();
    $draft['metric_id'] = $case->input['summary']['metric_id'];
    $draft['metric_name'] = $case->input['summary']['metric_name'];
    $draft['resolution_focus'][0]['kind'] = 'missing_evidence';
    $draft['resolution_focus'][0]['field_key'] = 'historical_window';

    $result = app(MetricAlignmentDraftValidator::class)->validate($case, $draft);

    expect($result->errors)->toContain(
        'Missing-evidence focus must not contain candidate resolution paths.',
    );
});
