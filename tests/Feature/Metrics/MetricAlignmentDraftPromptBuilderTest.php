<?php

use App\Support\Metrics\Drafting\MetricAlignmentDraftPromptBuilder;
use App\Support\Metrics\Drafting\MetricAlignmentDraftSourceIndex;
use App\Support\Metrics\Evaluation\MetricAlignmentEvaluationDataset;
use Illuminate\Support\Str;

it('builds a versioned prompt with deterministic input and explicit source bindings', function () {
    $case = app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'))[0];

    $prompt = app(MetricAlignmentDraftPromptBuilder::class)->build($case->input);

    expect($prompt)
        ->toContain('Prompt contract version: 1.2.1')
        ->toContain('Prioritize material conflicts first, then missing evidence, then decisions needing confirmation.')
        ->toContain('Allowed ID bindings:')
        ->toContain('"field:churn_event:material_conflict"')
        ->toContain('"question:churn_event"')
        ->toContain('"option:churn_event:use-definition-finance"')
        ->toContain('"submitted_answer:definition-finance:churn_event"')
        ->toContain('Deterministic metric alignment brief JSON:')
        ->toContain('"metric_id":"metric-gross-churn"')
        ->not->toContain('Case purpose:');

    $bindingsSection = Str::after($prompt, "Allowed ID bindings:\n");
    $bindingsJson = Str::before($bindingsSection, "\n\nDeterministic metric alignment brief JSON:");
    $briefJson = Str::after($prompt, "Deterministic metric alignment brief JSON:\n");

    expect($bindingsJson)->not->toContain("\n");
    expect($briefJson)->not->toContain("\n");

    $bindings = MetricAlignmentDraftSourceIndex::fromBrief($case->input)->allowedBindings();
    expect(json_decode($bindingsJson, true))->toBe($bindings);
    expect(json_decode($briefJson, true))->toBe($case->input);
});

it('rejects deterministic input that cannot be encoded as JSON', function () {
    $brief = [
        'summary' => [
            'metric_id' => 'metric-invalid',
            'metric_name' => "\xB1\x31",
        ],
    ];

    expect(fn () => app(MetricAlignmentDraftPromptBuilder::class)->build($brief))
        ->toThrow(
            RuntimeException::class,
            'Could not encode the deterministic metric alignment brief for drafting.',
        );
});

it('achieves at least 30% byte reduction compared to pretty-printed baseline on all synthetic cases', function () {
    $baselines = [
        'material-conflict-with-guidance' => 9264,
        'missing-evidence-no-history' => 5298,
        'guided-aligned-needs-confirmation' => 7685,
        'conflict-with-context-and-non-issue' => 14303,
        'aligned-no-action' => 4595,
    ];

    $cases = app(MetricAlignmentEvaluationDataset::class)
        ->load(config('metric-alignment-evaluation.dataset'));

    $builder = app(MetricAlignmentDraftPromptBuilder::class);

    foreach ($cases as $case) {
        $baselineBytes = $baselines[$case->id] ?? null;
        expect($baselineBytes)->not->toBeNull("Baseline for {$case->id} not defined in test.");

        $prompt = $builder->build($case->input);
        $candidateBytes = strlen($prompt);

        $reduction = ($baselineBytes - $candidateBytes) / $baselineBytes;

        expect($reduction)->toBeGreaterThanOrEqual(0.30, "Case {$case->id} reduction was only ".round($reduction * 100, 2)."% (candidate: {$candidateBytes} bytes, baseline: {$baselineBytes} bytes)");
    }
});
