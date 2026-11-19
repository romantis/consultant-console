<?php

use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Support\Metrics\Drafting\MetricAlignmentDraftingProfile;
use App\Support\Metrics\Drafting\MetricAlignmentDraftPromptBuilder;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationDataset;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationRunner;

function measuredMetricAlignmentDraft(): array
{
    return [
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => 'metric-gross-churn',
        'metric_name' => 'Gross Churn Rate',
        'summary' => [
            'text' => 'The submitted churn-event definitions differ.',
            'citation_ids' => [
                'submitted_answer:definition-finance:churn_event',
                'submitted_answer:definition-customer-success:churn_event',
            ],
        ],
        'resolution_focus' => [[
            'field_key' => 'churn_event',
            'kind' => 'must_resolve',
            'title' => 'Choose the official churn event',
            'synthesis' => 'Finance and Customer Success submitted different events.',
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

it('performs a budget-safe dry run without prompting a model', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['deepseek/deepseek-v4-flash'],
        '--repetitions' => 1,
        '--budget' => 1,
    ])
        ->expectsOutputToContain('Budget guard passed')
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('rejects a run whose maximum estimated cost exceeds the budget', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['anthropic/claude-opus-4.8'],
        '--repetitions' => 3,
        '--budget' => 0.001,
    ])
        ->expectsOutputToContain('exceeds the $0.00 budget')
        ->assertFailed();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('limits a dry run to explicitly selected dataset cases', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['mistralai/mistral-small-3.2-24b-instruct'],
        '--case' => ['material-conflict-with-guidance'],
        '--repetitions' => 1,
        '--budget' => 1,
    ])
        ->expectsTable(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [['dry-run', 1, '1 x 1', '$0.0019', '$1.00']],
        )
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('uses verified pricing for the GPT 5.4 Mini candidate', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['openai/gpt-5.4-mini'],
        '--case' => ['material-conflict-with-guidance'],
        '--repetitions' => 1,
        '--budget' => 0.05,
    ])
        ->expectsTable(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [['dry-run', 1, '1 x 1', '$0.0394', '$0.05']],
        )
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('uses verified pricing for the Gemini 2.5 Flash candidates', function (string $model, string $maximumCost) {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => [$model],
        '--case' => ['material-conflict-with-guidance'],
        '--repetitions' => 1,
        '--budget' => 0.03,
    ])
        ->expectsTable(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [['dry-run', 1, '1 x 1', $maximumCost, '$0.03']],
        )
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
})->with([
    'Gemini 2.5 Flash Lite' => ['google/gemini-2.5-flash-lite', '$0.0036'],
    'Gemini 2.5 Flash' => ['google/gemini-2.5-flash', '$0.0215'],
]);

it('uses verified pricing for the Gemini 3.1 Pro Preview candidate', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['google/gemini-3.1-pro-preview'],
        '--case' => ['material-conflict-with-guidance'],
        '--repetitions' => 1,
        '--budget' => 0.12,
    ])
        ->expectsTable(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [['dry-run', 1, '1 x 1', '$0.1050', '$0.12']],
        )
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('uses verified pricing for the Gemini 3 Flash Preview candidate', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['google/gemini-3-flash-preview'],
        '--case' => ['material-conflict-with-guidance'],
        '--repetitions' => 1,
        '--budget' => 0.03,
    ])
        ->expectsTable(
            ['Mode', 'Models', 'Fixtures x repetitions', 'Maximum estimated cost', 'Budget'],
            [['dry-run', 1, '1 x 1', '$0.0262', '$0.03']],
        )
        ->assertSuccessful();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('rejects an unknown dataset case before prompting a model', function () {
    MetricAlignmentDraftAgent::fake();

    $this->artisan('mh:evaluate-metric-alignment-draft', [
        '--model' => ['mistralai/mistral-small-3.2-24b-instruct'],
        '--case' => ['missing-case'],
        '--repetitions' => 1,
        '--budget' => 1,
    ])
        ->expectsOutputToContain('Unknown evaluation case: missing-case')
        ->assertFailed();

    MetricAlignmentDraftAgent::assertNeverPrompted();
});

it('records the complete versioned evaluation profile in dry-run reports', function () {
    $agent = new MetricAlignmentDraftAgent('metric-alignment-evaluation.profile');
    $case = app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'))[0];
    $productionPrompt = app(MetricAlignmentDraftPromptBuilder::class)->build($case->input);
    $report = app(MetricAlignmentEvaluationRunner::class)->run(
        models: ['deepseek/deepseek-v4-flash'],
        repetitions: 1,
        budgetUsd: 1,
        execute: false,
        outputDirectory: base_path('tmp/metric-alignment-evaluation/profile-test'),
        caseIds: ['material-conflict-with-guidance'],
    );

    expect($report['evaluation_profile'])->toMatchArray([
        'version' => '2.0.0',
        'provider' => 'openrouter',
        'wire_schema_version' => '1.0-relaxed',
        'temperature' => 0.1,
        'max_tokens' => 8192,
        'timeout_seconds' => 90,
        'streaming' => false,
        'response_healing' => false,
        'provider_options' => [
            'provider' => [
                'require_parameters' => false,
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
            ],
        ],
    ])->and($report['prompt_version'])->toBe(config('metric-alignment-drafting.prompt_version'))
        ->and($report['prompt_sha256_by_case'])->toBe([
            $case->id => hash('sha256', $productionPrompt),
        ])
        ->and($report['evaluation_profile']['prompt_sha256'])->toHaveLength(64)
        ->and($report['evaluation_profile']['wire_schema_sha256'])->toHaveLength(64)
        ->and($report['evaluation_profile']['canonical_output_schema_sha256'])->toHaveLength(64)
        ->and($report['evaluation_profile_sha256'])->toHaveLength(64)
        ->and($agent->maxTokens())->toBe($report['evaluation_profile']['max_tokens'])
        ->and($agent->temperature())->toBe($report['evaluation_profile']['temperature'])
        ->and($agent->providerOptions('openrouter'))->toBe($report['evaluation_profile']['provider_options']);
});

it('records output size metrics for successful provider responses', function () {
    $draft = measuredMetricAlignmentDraft();

    config()->set('ai.providers.openrouter.key', 'test-key');
    MetricAlignmentDraftAgent::fake([$draft])->preventStrayPrompts();

    $report = app(MetricAlignmentEvaluationRunner::class)->run(
        models: ['google/gemini-3-flash-preview'],
        repetitions: 1,
        budgetUsd: 1,
        execute: true,
        outputDirectory: base_path('tmp/metric-alignment-evaluation/output-metrics-test'),
        caseIds: ['material-conflict-with-guidance'],
    );

    $run = $report['runs'][0];
    $expectedProseCharacters = mb_strlen($draft['summary']['text'])
        + mb_strlen($draft['resolution_focus'][0]['synthesis'])
        + mb_strlen($draft['resolution_focus'][0]['facilitator_questions'][0]['reason'])
        + mb_strlen($draft['resolution_focus'][0]['candidate_resolution_paths'][0]['description']);

    expect($run['status'])->toBe('passed')
        ->and($run['output_json_bytes'])->toBe(strlen(json_encode(
            $draft,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )))
        ->and($run['output_prose_characters'])->toBe($expectedProseCharacters);
});

it('records null output size metrics when the provider call fails', function () {
    config()->set('ai.providers.openrouter.key', 'test-key');
    MetricAlignmentDraftAgent::fake([
        fn () => throw new RuntimeException('Provider unavailable.'),
    ])->preventStrayPrompts();

    $report = app(MetricAlignmentEvaluationRunner::class)->run(
        models: ['google/gemini-3-flash-preview'],
        repetitions: 1,
        budgetUsd: 1,
        execute: true,
        outputDirectory: base_path('tmp/metric-alignment-evaluation/output-metrics-failure-test'),
        caseIds: ['material-conflict-with-guidance'],
    );

    expect($report['runs'][0])
        ->status->toBe('provider_error')
        ->output_json_bytes->toBeNull()
        ->output_prose_characters->toBeNull();
});

it('provides a dedicated drafting profile separate from evaluation settings', function () {
    $profile = new MetricAlignmentDraftingProfile;

    expect($profile->toArray())->toMatchArray([
        'version' => '1.0.2',
        'provider' => 'openrouter',
        'model' => 'google/gemini-3-flash-preview',
        'temperature' => 0.1,
        'max_tokens' => 8192,
        'timeout_seconds' => 90,
        'streaming' => false,
        'response_healing' => false,
        'provider_options' => [
            'provider' => [
                'require_parameters' => false,
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
            ],
        ],
    ]);
});
