<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shares handbook metrics for active engagements and signed-off org definitions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $engagementOld = Engagement::factory()
        ->for($organization)
        ->create([
            'starts_on' => now()->subDays(10),
            'status' => EngagementStatus::FINALIZED->value,
        ]);

    $engagementActive = Engagement::factory()
        ->for($organization)
        ->create([
            'starts_on' => now()->subDay(),
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ]);

    $metricOld = Metric::factory()
        ->for($organization)
        ->for($engagementOld)
        ->state([
            'status' => MetricStatus::FINALIZED,
            'name' => 'Legacy Metric',
        ])
        ->create();

    $metricActive = Metric::factory()
        ->for($organization)
        ->for($engagementActive)
        ->state([
            'status' => MetricStatus::IN_REVIEW,
            'name' => 'Revenue Growth',
        ])
        ->create();

    $metricActiveOrgMetric = OrgMetric::query()->find($metricActive->org_metric_id);
    $metricActiveOrgMetric?->forceFill(['alias' => 'Growth Rate'])->save();

    $signedOffOrgMetric = OrgMetric::factory()
        ->for($organization)
        ->create(['name' => 'Customer Health']);

    $signedOffOrgMetric->canonicalMetric()
        ->update(['name' => 'Customer Health']);

    OrgFinalDefinition::factory()
        ->for($signedOffOrgMetric)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
        ]);

    $activeOrgMetricId = (string) $metricActive->org_metric_id;
    $signedOffOrgMetricId = (string) $signedOffOrgMetric->id;
    $activeHref = route('client.handbook.metrics.page', [$organization, $engagementActive->id, $activeOrgMetricId]);
    $signedOffHref = route('client.handbook.metrics.page', [$organization, $engagementActive->id, $signedOffOrgMetricId]);

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clientNavigation.handbookMetrics', function ($metrics) use ($activeHref, $signedOffHref, $activeOrgMetricId, $signedOffOrgMetricId) {
                $metrics = collect($metrics)->keyBy('id');
                $activeRow = $metrics->get($activeOrgMetricId);
                $signedOffRow = $metrics->get($signedOffOrgMetricId);

                if ($metrics->count() !== 2 || ! $activeRow || ! $signedOffRow) {
                    return false;
                }

                return ($activeRow['href'] ?? null) === $activeHref
                    && ($activeRow['name'] ?? null) === 'Growth Rate'
                    && ($signedOffRow['href'] ?? null) === $signedOffHref
                    && ($signedOffRow['name'] ?? null) === 'Customer Health';
            })
        );
});

it('shares an empty handbook metrics list when no active engagement or org definitions exist', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $engagement = Engagement::factory()
        ->for($organization)
        ->create([
            'starts_on' => now()->subDay(),
            'status' => EngagementStatus::FINALIZED->value,
        ]);

    Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'status' => MetricStatus::FINALIZED,
            'name' => 'Dormant Metric',
        ])
        ->create();

    OrgMetric::factory()
        ->for($organization)
        ->create(['name' => 'Empty Metric']);

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clientNavigation.handbookMetrics', [])
        );
});
