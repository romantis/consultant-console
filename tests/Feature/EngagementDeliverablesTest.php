<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('anchors deliverables to finalized_at when engagement is finalized', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $metricBaseline = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'name' => 'Net Revenue Retention',
        'status' => MetricStatus::FINALIZED,
    ]);
    $metricStable = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'name' => 'Churn Rate',
        'status' => MetricStatus::FINALIZED,
    ]);
    $metricExcluded = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'name' => 'Support Load',
        'status' => MetricStatus::FINALIZED,
    ]);

    $signedBefore = Carbon::parse('2025-01-10 09:00:00');
    $finalizedAt = Carbon::parse('2025-01-12 12:00:00');
    $signedAfter = Carbon::parse('2025-01-15 16:00:00');

    FinalDefinition::factory()->create([
        'metric_id' => $metricBaseline->id,
        'status' => 'Signed',
        'version' => 1,
        'signed_off_at' => $signedBefore,
        'signed_off_by' => $user->id,
    ]);
    FinalDefinition::factory()->create([
        'metric_id' => $metricBaseline->id,
        'status' => 'Signed',
        'version' => 2,
        'signed_off_at' => $signedAfter,
        'signed_off_by' => $user->id,
    ]);
    FinalDefinition::factory()->create([
        'metric_id' => $metricStable->id,
        'status' => 'Signed',
        'version' => 1,
        'signed_off_at' => $signedBefore->copy()->addHours(3),
        'signed_off_by' => $user->id,
    ]);
    FinalDefinition::factory()->create([
        'metric_id' => $metricExcluded->id,
        'status' => 'Signed',
        'version' => 1,
        'signed_off_at' => $signedAfter->copy()->addHour(),
        'signed_off_by' => $user->id,
    ]);

    $this->travelTo($finalizedAt);

    $this->actingAs($user)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::FINALIZED->value,
        ])
        ->assertRedirect();

    $this->travelBack();

    expect($engagement->refresh()->finalized_at?->toIso8601String())
        ->toBe($finalizedAt->toIso8601String());

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Engagements/Show')
            ->where('deliverables.finalized_at', $finalizedAt->toIso8601String())
            ->where('deliverables.total', 2)
            ->where('deliverables.items', function ($items) use (
                $metricBaseline,
                $metricStable,
                $metricExcluded,
                $signedBefore,
                $user
            ) {
                $collection = collect($items);
                $baseline = $collection->firstWhere('metric_id', (string) $metricBaseline->id);
                $stable = $collection->firstWhere('metric_id', (string) $metricStable->id);
                $excluded = $collection->firstWhere('metric_id', (string) $metricExcluded->id);

                return $baseline
                    && $baseline['signed_off_at'] === $signedBefore->toIso8601String()
                    && ($baseline['signed_off_by']['name'] ?? null) === $user->name
                    && $stable
                    && $excluded === null;
            })
        );
});

it('shows empty deliverables before finalization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => 'Signed',
        'version' => 1,
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Engagements/Show')
            ->where('deliverables.finalized_at', null)
            ->where('deliverables.total', 0)
            ->where('deliverables.items', [])
        );
});
