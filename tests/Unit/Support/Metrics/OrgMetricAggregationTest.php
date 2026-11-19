<?php

use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Support\Metrics\OrgMetricAggregation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('selects the latest signed-off definition across engagements', function () {
    $organization = Organization::factory()->create();

    $orgMetric = OrgMetric::query()->create([
        'organization_id' => $organization->id,
        'key' => 'revenue',
        'slug' => 'revenue',
        'name' => 'Revenue',
    ]);

    $engagementA = Engagement::factory()->for($organization)->create();
    $engagementB = Engagement::factory()->for($organization)->create();

    $metricA = Metric::factory()
        ->for($organization)
        ->for($engagementA)
        ->create([
            'org_metric_id' => $orgMetric->id,
        ]);

    $metricB = Metric::factory()
        ->for($organization)
        ->for($engagementB)
        ->create([
            'org_metric_id' => $orgMetric->id,
        ]);

    $older = FinalDefinition::factory()
        ->for($metricA)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDays(3),
        ]);

    $newer = FinalDefinition::factory()
        ->for($metricB)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
        ]);

    FinalDefinition::factory()
        ->for($metricB)
        ->create([
            'version' => 2,
            'status' => 'Draft',
            'signed_off_at' => null,
        ]);

    $aggregation = new OrgMetricAggregation;

    $latest = $aggregation->latestSignedOffDefinition($orgMetric);
    $history = $aggregation->signedOffHistory($orgMetric);

    expect((string) $latest?->getKey())->toBe((string) $newer->getKey());
    expect($history)->toHaveCount(2)
        ->and((string) $history->first()->getKey())->toBe((string) $newer->getKey())
        ->and((string) $history->last()->getKey())->toBe((string) $older->getKey());
});

it('prefers org-level signed-off definitions when available', function () {
    $organization = Organization::factory()->create();

    $orgMetric = OrgMetric::query()->create([
        'organization_id' => $organization->id,
        'key' => 'retention',
        'slug' => 'retention',
        'name' => 'Retention',
    ]);

    $engagement = Engagement::factory()->for($organization)->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'org_metric_id' => $orgMetric->id,
        ]);

    FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDays(2),
        ]);

    $orgDefinition = OrgFinalDefinition::factory()
        ->create([
            'org_metric_id' => $orgMetric->id,
            'source_metric_id' => $metric->id,
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
        ]);

    $aggregation = new OrgMetricAggregation;

    $latest = $aggregation->latestSignedOffDefinition($orgMetric);
    $history = $aggregation->signedOffHistory($orgMetric);

    expect((string) $latest?->getKey())->toBe((string) $orgDefinition->getKey());
    expect($history)->toHaveCount(1)
        ->and((string) $history->first()->getKey())->toBe((string) $orgDefinition->getKey());
});
