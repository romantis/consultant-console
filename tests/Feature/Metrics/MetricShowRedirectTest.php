<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects metric show to the workshop view for staff', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $staffOrg = Organization::factory()->create([
        'is_internal' => true,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    Membership::factory()
        ->for($staffOrg)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $metric = Metric::factory()->create([
        'engagement_id' => $engagement->id,
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.metrics.show', [
            $organization,
            $metric,
            'engagement' => $engagement->id,
        ]))
        ->assertRedirect(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]));
});

it('blocks non-staff metric show before the engagement is in workshop', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $metric = Metric::factory()->create([
        'engagement_id' => $engagement->id,
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.metrics.show', [
            $organization,
            $metric,
            'engagement' => $engagement->id,
        ]))
        ->assertForbidden();
});
