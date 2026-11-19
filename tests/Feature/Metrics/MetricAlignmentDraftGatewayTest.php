<?php

use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Support\Metrics\Drafting\LaravelAiMetricAlignmentDraftGateway;
use App\Support\Metrics\Drafting\MetricAlignmentDraftFailure;
use App\Support\Metrics\Drafting\MetricAlignmentDraftGateway;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

it('binds the drafting gateway abstraction to the Laravel AI implementation', function () {
    expect(app(MetricAlignmentDraftGateway::class))
        ->toBeInstanceOf(LaravelAiMetricAlignmentDraftGateway::class);
});

it('generates a typed draft response through the drafting gateway', function () {
    MetricAlignmentDraftAgent::fake([[
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => 'metric-cac',
        'metric_name' => 'Customer Acquisition Cost',
        'summary' => [
            'text' => 'Two submitted cost-basis definitions need workshop resolution.',
            'citation_ids' => ['submitted_answer:definition-finance:cost_basis'],
        ],
        'resolution_focus' => [],
        'supporting_context' => [],
    ]]);

    $result = app(MetricAlignmentDraftGateway::class)->generate('Generate a metric alignment draft.');

    expect($result->isOk())->toBeTrue();

    $response = $result->value();

    expect($response->profileVersion)->toBe('1.0.2')
        ->and($response->provider)->toBe('openrouter')
        ->and($response->requestedModel)->toBe('google/gemini-3-flash-preview')
        ->and($response->returnedModel)->toBe('google/gemini-3-flash-preview')
        ->and($response->draft['metric_id'])->toBe('metric-cac')
        ->and($response->draft['summary']['text'])->toBe('Two submitted cost-basis definitions need workshop resolution.')
        ->and($response->usage)->toBeArray()
        ->and($response->elapsedMs)->toBeInt();

    MetricAlignmentDraftAgent::assertPrompted(function ($prompt) {
        return $prompt->prompt === 'Generate a metric alignment draft.'
            && $prompt->model === 'google/gemini-3-flash-preview'
            && $prompt->provider()->name() === 'openrouter'
            && $prompt->timeout === 90;
    });
});

it('normalizes provider arrays to canonical cardinality limits', function () {
    $focusItem = [
        'field_key' => 'cost_basis',
        'kind' => 'must_resolve',
        'title' => 'Choose the cost basis',
        'synthesis' => 'The submitted positions differ.',
        'source_item_ids' => ['field:cost_basis:material_conflict'],
        'citation_ids' => ['submitted_answer:finance:cost_basis'],
        'facilitator_questions' => array_fill(0, 4, [
            'question' => 'Which cost basis should be official?',
            'reason' => 'The submitted positions differ.',
            'source_question_ids' => ['question:cost_basis'],
            'citation_ids' => ['field_guidance:cost_basis:prompt'],
        ]),
        'candidate_resolution_paths' => array_fill(0, 4, [
            'label' => 'Use the Finance position',
            'description' => 'Use the fully loaded cost basis.',
            'source_option_ids' => ['option:cost_basis:finance'],
            'citation_ids' => ['submitted_answer:finance:cost_basis'],
        ]),
    ];
    $contextItem = [
        'field_key' => 'cost_notes',
        'kind' => 'context_signal',
        'note' => 'Finance supplied additional cost notes.',
        'source_item_ids' => ['field:cost_notes:context_signal'],
        'citation_ids' => ['submitted_answer:finance:cost_notes'],
    ];

    MetricAlignmentDraftAgent::fake([[
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => 'metric-cac',
        'metric_name' => 'Customer Acquisition Cost',
        'summary' => [
            'text' => 'The submitted cost-basis definitions differ.',
            'citation_ids' => ['submitted_answer:finance:cost_basis'],
        ],
        'resolution_focus' => array_fill(0, 6, $focusItem),
        'supporting_context' => array_fill(0, 6, $contextItem),
    ]]);

    $result = app(MetricAlignmentDraftGateway::class)->generate('Generate a metric alignment draft.');
    $draft = $result->value()->draft;

    expect($draft['resolution_focus'])->toHaveCount(5)
        ->and($draft['supporting_context'])->toHaveCount(5)
        ->and($draft['resolution_focus'][0]['facilitator_questions'])->toHaveCount(3)
        ->and($draft['resolution_focus'][0]['candidate_resolution_paths'])->toHaveCount(3);
});

it('returns a typed failure when the provider call errors', function () {
    Exceptions::fake();

    MetricAlignmentDraftAgent::fake([
        fn () => throw new RuntimeException('Provider unavailable.'),
    ]);

    $result = app(MetricAlignmentDraftGateway::class)->generate('Generate a metric alignment draft.');

    expect($result->isErr())->toBeTrue();

    $failure = $result->error();

    expect($failure)->toBeInstanceOf(MetricAlignmentDraftFailure::class)
        ->and($failure->code)->toBe('provider_error')
        ->and($failure->message)->toBe('Provider unavailable.')
        ->and($failure->profileVersion)->toBe('1.0.2')
        ->and($failure->provider)->toBe('openrouter')
        ->and($failure->requestedModel)->toBe('google/gemini-3-flash-preview');

    Exceptions::assertReported(
        fn (RuntimeException $exception): bool => $exception->getMessage() === 'Provider unavailable.',
    );
});

it('keeps provider wire schemas free of canonical cardinality constraints', function () {
    $schemaFactory = new JsonSchemaTypeFactory;
    $productionSchema = (new ObjectType(
        (new MetricAlignmentDraftAgent)->schema($schemaFactory),
    ))->toArray();
    $evaluationSchema = (new ObjectType(
        (new MetricAlignmentDraftAgent('metric-alignment-evaluation.profile'))
            ->schema($schemaFactory),
    ))->toArray();

    expect($productionSchema['properties']['resolution_focus'])
        ->not->toHaveKey('maxItems')
        ->and($productionSchema['properties']['supporting_context'])
        ->not->toHaveKey('maxItems')
        ->and(
            $productionSchema['properties']['resolution_focus']['items']['properties']['facilitator_questions'],
        )->not->toHaveKey('maxItems')
        ->and(
            $productionSchema['properties']['resolution_focus']['items']['properties']['candidate_resolution_paths'],
        )->not->toHaveKey('maxItems')
        ->and($evaluationSchema['properties']['resolution_focus'])
        ->not->toHaveKey('maxItems')
        ->and($evaluationSchema['properties']['supporting_context'])
        ->not->toHaveKey('maxItems');
});

it('returns an unstructured failure when the provider does not return structured output', function () {
    Exceptions::fake();

    MetricAlignmentDraftAgent::fake([
        new AgentResponse(
            invocationId: '123',
            text: 'This is an unstructured text response',
            usage: new Usage,
            meta: new Meta('openrouter', 'google/gemini-3-flash-preview')
        ),
    ]);

    $result = app(MetricAlignmentDraftGateway::class)->generate('Generate a metric alignment draft.');

    expect($result->isErr())->toBeTrue();

    $failure = $result->error();

    expect($failure)->toBeInstanceOf(MetricAlignmentDraftFailure::class)
        ->and($failure->code)->toBe('provider_error')
        ->and($failure->message)->toContain('Undefined property');
});
