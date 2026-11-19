<?php

use App\Models\Discrepancy;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\User;
use Database\Seeders\MetricTemplateSeeder;
use Database\Seeders\StorySeeds\DiscrepancyResolutionStorySeeder;

it('seeds CAC and CPL discrepancy snapshots around cost base values', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(DiscrepancyResolutionStorySeeder::class);

    $discrepancy = Discrepancy::query()
        ->where('field_key', 'costs_included')
        ->whereHas('metric', fn ($query) => $query->where('key', 'cac_cpl'))
        ->firstOrFail();

    $values = collect($discrepancy->values_snapshot['values'] ?? []);

    expect($discrepancy->values_snapshot['label'] ?? null)->toBe('Costs Included')
        ->and($values)->toHaveCount(2)
        ->and($values->every(fn (array $entry): bool => is_array($entry['raw'] ?? null)))->toBeTrue();

    $rawValues = $values
        ->pluck('raw')
        ->flatten()
        ->values()
        ->all();

    expect($rawValues)
        ->toContain('marketing_comp')
        ->toContain('sales_comp')
        ->toContain('overhead')
        ->toContain('direct_campaign_only')
        ->not->toContain('marketing_qualified')
        ->not->toContain('any_contact');
});

it('keeps churn timing source answers internally consistent', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(DiscrepancyResolutionStorySeeder::class);

    $financeDefinition = MetricDefinition::query()
        ->whereHas('metric', fn ($query) => $query->where('key', 'churn'))
        ->whereHas('user', fn ($query) => $query->where('email', 'lia.price@orion.test'))
        ->firstOrFail();

    expect($financeDefinition->normalized_json['fields']['churn_timing']['normalized'] ?? null)
        ->toBe('end_of_cycle')
        ->and($financeDefinition->normalized_json['meta']['churn_timing_notes']['normalized'] ?? '')
        ->toContain('end of the billing period')
        ->and($financeDefinition->normalized_json['meta']['churn_pain_points']['normalized'] ?? '')
        ->toContain('finance waits for end-of-cycle recognition')
        ->not->toContain('we do not wait until the billing cycle ends');
});

it('exposes seeded CAC and CPL submissions as material workshop prep conflicts', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(DiscrepancyResolutionStorySeeder::class);

    $metric = Metric::query()
        ->with(['organization', 'engagement'])
        ->where('key', 'cac_cpl')
        ->firstOrFail();
    $staff = User::query()->where('email', 'admin@e.test')->firstOrFail();

    $definitions = MetricDefinition::query()
        ->whereBelongsTo($metric)
        ->get();

    expect($definitions)->toHaveCount(2)
        ->and($definitions->pluck('status')->unique()->values()->all())->toBe(['Submitted']);

    $this->actingAs($staff)
        ->get(route('org.engagements.metrics.prep', [
            'organization' => $metric->organization,
            'engagement' => $metric->engagement,
            'metric' => $metric,
        ]))
        ->assertOk()
        ->assertInertia(function ($page): void {
            $page->component('Metrics/WorkshopPrep')
                ->where('metric_alignment_brief.summary.source_counts.submitted_answers', 2);

            $brief = $page->toArray()['props']['metric_alignment_brief'];
            $materialConflictFields = collect($brief['material_conflicts'])->pluck('field_key');
            $missingEvidenceFields = collect($brief['missing_evidence'])->pluck('field_key');
            $expectedConflictFields = [
                'costs_included',
                'lead_definition',
                'customer_definition',
                'cost_timing',
                'deduplication_method',
                'overhead_handling',
            ];

            expect($materialConflictFields)->toContain(...$expectedConflictFields)
                ->and($missingEvidenceFields)->not->toContain(...$expectedConflictFields);

            $cueContexts = collect($brief['cue_contexts'] ?? []);
            $costsIncludedContext = $cueContexts->firstWhere('field_key', 'costs_included');
            expect($costsIncludedContext)->not->toBeNull();

            $matrixKeys = collect($costsIncludedContext['matrix_context'] ?? [])->pluck('field_key')->all();
            expect($matrixKeys)->toContain('cac_example', 'cac_pain_points', 'additional_notes');
        });
});
