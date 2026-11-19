<?php

use App\Actions\Metrics\BuildMetricAlignmentBrief;
use App\Actions\Metrics\GenerateMetricAlignmentDraft;
use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Jobs\GenerateMetricAlignmentDraftJob;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\MetricAlignmentDraft;
use App\Models\Organization;
use App\Support\Metrics\Drafting\BriefFingerprint;
use App\Support\Metrics\Drafting\MetricAlignmentDraftGateway;
use App\Support\Metrics\Drafting\MetricAlignmentDraftOutputValidator;
use App\Support\Metrics\Drafting\MetricAlignmentDraftPromptBuilder;
use Illuminate\Support\Facades\Log;

function validJobGeneratedDraft(Metric $metric): array
{
    return [
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => (string) $metric->id,
        'metric_name' => $metric->name,
        'summary' => [
            'text' => 'The required metric value still needs source evidence.',
            'citation_ids' => ['field_schema:value'],
        ],
        'resolution_focus' => [[
            'field_key' => 'value',
            'kind' => 'missing_evidence',
            'title' => 'Confirm the metric value evidence',
            'synthesis' => 'No submitted value is available for comparison.',
            'source_item_ids' => ['field:value:missing_evidence'],
            'citation_ids' => ['field_schema:value'],
            'facilitator_questions' => [[
                'question' => 'What source should establish the metric value?',
                'reason' => 'The required field has no submitted evidence.',
                'source_question_ids' => ['question:value'],
                'citation_ids' => ['field_schema:value'],
            ]],
            'candidate_resolution_paths' => [],
        ]],
        'supporting_context' => [],
    ];
}

it('saves the generated draft and sets status to idle on success', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $metric->refresh();

    $briefBuilder = app(BuildMetricAlignmentBrief::class);
    $brief = $briefBuilder->build($metric);
    $fingerprint = BriefFingerprint::calculate($brief);
    $revision = $metric->brief_source_revision;

    $draftData = validJobGeneratedDraft($metric);
    MetricAlignmentDraftAgent::fake([$draftData])->preventStrayPrompts();

    expect(MetricAlignmentDraft::count())->toBe(0);

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        $brief,
        $fingerprint,
        $revision
    );

    $job->handle(
        app(GenerateMetricAlignmentDraft::class),
        $briefBuilder
    );

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('idle')
        ->and($metric->draft_generation_error)->toBeNull();

    expect(MetricAlignmentDraft::count())->toBe(1);
    $saved = MetricAlignmentDraft::first();
    expect($saved->metric_id)->toBe($metric->id)
        ->and($saved->draft['summary']['text'])->toBe($draftData['summary']['text']);
});

it('sets status to failed and saves the error on gateway failure', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $metric->refresh();

    $briefBuilder = app(BuildMetricAlignmentBrief::class);
    $brief = $briefBuilder->build($metric);
    $fingerprint = BriefFingerprint::calculate($brief);
    $revision = $metric->brief_source_revision;

    MetricAlignmentDraftAgent::fake([
        fn () => throw new RuntimeException('LLM offline'),
    ])->preventStrayPrompts();

    Log::spy();

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        $brief,
        $fingerprint,
        $revision
    );

    $job->handle(
        app(GenerateMetricAlignmentDraft::class),
        $briefBuilder
    );

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('failed')
        ->and($metric->draft_generation_error)->toBe('Draft generation is temporarily unavailable. Try again.');

    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('sets status to failed without calling the gateway if the brief is stale before the job starts', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $metric->refresh();

    $briefBuilder = app(BuildMetricAlignmentBrief::class);
    $brief = $briefBuilder->build($metric);
    $fingerprint = BriefFingerprint::calculate($brief);
    $revision = $metric->brief_source_revision;

    // Simulate another process updating the revision before the job finishes
    $metric->increment('brief_source_revision');

    $mockGateway = Mockery::mock(MetricAlignmentDraftGateway::class);
    $mockGateway->shouldNotReceive('generate');

    $action = new GenerateMetricAlignmentDraft(
        app(MetricAlignmentDraftPromptBuilder::class),
        $mockGateway,
        app(MetricAlignmentDraftOutputValidator::class)
    );

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        $brief,
        $fingerprint,
        $revision
    );

    $job->handle(
        $action,
        $briefBuilder
    );

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('failed')
        ->and($metric->draft_generation_error)->toBe('The metric source data changed before draft generation started. Please try again.');

    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('sets status to failed if the brief source revision changes during the LLM call', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $metric->refresh();

    $briefBuilder = app(BuildMetricAlignmentBrief::class);
    $brief = $briefBuilder->build($metric);
    $fingerprint = BriefFingerprint::calculate($brief);
    $revision = $metric->brief_source_revision;

    $draftData = validJobGeneratedDraft($metric);
    MetricAlignmentDraftAgent::fake([
        function () use ($metric, $draftData) {
            $metric->increment('brief_source_revision');

            return $draftData;
        },
    ])->preventStrayPrompts();

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        $brief,
        $fingerprint,
        $revision
    );

    $job->handle(
        app(GenerateMetricAlignmentDraft::class),
        $briefBuilder
    );

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('failed')
        ->and($metric->draft_generation_error)->toBe('The metric source data changed while generating the draft. Please try again.');

    expect(MetricAlignmentDraft::count())->toBe(0);
});

it('rethrows unexpected exceptions from handle', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'draft_generation_status' => 'generating',
    ]);
    $metric->refresh();

    $briefBuilder = app(BuildMetricAlignmentBrief::class);
    $brief = $briefBuilder->build($metric);
    $fingerprint = BriefFingerprint::calculate($brief);
    $revision = $metric->brief_source_revision;

    $mockGateway = Mockery::mock(MetricAlignmentDraftGateway::class);
    $mockGateway->shouldReceive('generate')
        ->once()
        ->andThrow(new RuntimeException('Unexpected DB/gateway error'));

    $action = new GenerateMetricAlignmentDraft(
        app(MetricAlignmentDraftPromptBuilder::class),
        $mockGateway,
        app(MetricAlignmentDraftOutputValidator::class)
    );

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        $brief,
        $fingerprint,
        $revision
    );

    expect(fn () => $job->handle($action, $briefBuilder))
        ->toThrow(RuntimeException::class, 'Unexpected DB/gateway error');

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('generating');
});

it('sets status to failed and saves the error message when the job fails permanently via failed()', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'draft_generation_status' => 'generating',
    ]);
    $metric->refresh();

    $job = new GenerateMetricAlignmentDraftJob(
        (string) $metric->id,
        [],
        'fingerprint',
        1
    );

    $job->failed(new RuntimeException('Job timeout or max attempts exhausted'));

    $metric->refresh();
    expect($metric->draft_generation_status)->toBe('failed')
        ->and($metric->draft_generation_error)->toBe('An unexpected error occurred during draft generation. Please try again.');
});
