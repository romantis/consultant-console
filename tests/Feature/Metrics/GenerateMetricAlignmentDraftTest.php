<?php

use App\Actions\Metrics\GenerateMetricAlignmentDraft;
use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Support\Metrics\Drafting\MetricAlignmentDraftFailure;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationDataset;

function validGeneratedMetricAlignmentDraft(): array
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

function materialConflictMetricAlignmentBrief(): array
{
    return app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'))[0]
        ->input;
}

it('generates and validates a source-bound metric alignment draft', function () {
    MetricAlignmentDraftAgent::fake([
        validGeneratedMetricAlignmentDraft(),
    ])->preventStrayPrompts();

    $result = app(GenerateMetricAlignmentDraft::class)->handle(
        materialConflictMetricAlignmentBrief(),
    );

    expect($result->isOk())->toBeTrue();

    $response = $result->value();

    expect($response->draft)->toBe(validGeneratedMetricAlignmentDraft())
        ->and($response->profileVersion)->toBe('1.0.2')
        ->and($response->provider)->toBe('openrouter')
        ->and($response->requestedModel)->toBe('google/gemini-3-flash-preview');

    MetricAlignmentDraftAgent::assertPrompted(function ($prompt): bool {
        return str_contains($prompt->prompt, 'Prompt contract version: 1.2.1')
            && str_contains($prompt->prompt, '"metric_id":"metric-gross-churn"')
            && str_contains($prompt->prompt, '"field:churn_event:material_conflict"')
            && str_contains($prompt->prompt, '"question:churn_event"')
            && str_contains($prompt->prompt, '"option:churn_event:use-definition-finance"');
    });
});

it('normalizes provider cardinality before canonical output validation', function () {
    $draft = validGeneratedMetricAlignmentDraft();
    $focusItem = $draft['resolution_focus'][0];
    $focusItem['facilitator_questions'] = array_fill(
        0,
        4,
        $focusItem['facilitator_questions'][0],
    );
    $focusItem['candidate_resolution_paths'] = array_fill(
        0,
        4,
        $focusItem['candidate_resolution_paths'][0],
    );
    $draft['resolution_focus'] = array_fill(0, 6, $focusItem);

    MetricAlignmentDraftAgent::fake([$draft])->preventStrayPrompts();

    $result = app(GenerateMetricAlignmentDraft::class)->handle(
        materialConflictMetricAlignmentBrief(),
    );

    expect($result->isOk())->toBeTrue()
        ->and($result->value()->draft['resolution_focus'])->toHaveCount(5)
        ->and($result->value()->draft['resolution_focus'][0]['facilitator_questions'])->toHaveCount(3)
        ->and($result->value()->draft['resolution_focus'][0]['candidate_resolution_paths'])->toHaveCount(3);
});

it('returns the provider failure without attempting output validation', function () {
    MetricAlignmentDraftAgent::fake([
        fn () => throw new RuntimeException('Provider unavailable.'),
    ])->preventStrayPrompts();

    $result = app(GenerateMetricAlignmentDraft::class)->handle(
        materialConflictMetricAlignmentBrief(),
    );

    expect($result->isErr())->toBeTrue();

    $failure = $result->error();

    expect($failure)->toBeInstanceOf(MetricAlignmentDraftFailure::class)
        ->and($failure->code)->toBe('provider_error')
        ->and($failure->message)->toBe('Provider unavailable.')
        ->and($failure->validationErrors)->toBe([]);
});

it('rejects a provider draft that fails source-bound output validation', function () {
    $invalidDraft = validGeneratedMetricAlignmentDraft();
    $invalidDraft['metric_id'] = 'metric-invented';

    MetricAlignmentDraftAgent::fake([
        $invalidDraft,
    ])->preventStrayPrompts();

    $result = app(GenerateMetricAlignmentDraft::class)->handle(
        materialConflictMetricAlignmentBrief(),
    );

    expect($result->isErr())->toBeTrue();

    $failure = $result->error();

    expect($failure)->toBeInstanceOf(MetricAlignmentDraftFailure::class)
        ->and($failure->code)->toBe('invalid_draft_output')
        ->and($failure->message)->toBe(
            'Provider returned a draft that failed source-bound output validation.',
        )
        ->and($failure->validationErrors)->toContain(
            'Metric ID does not match the input.',
        );
});
