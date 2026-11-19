<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use App\Policies\OrgMetricPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStaffUserForOrgMetrics(): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

it('allows staff to view and update org metrics', function () {
    $user = makeStaffUserForOrgMetrics();
    $orgMetric = OrgMetric::factory()->create();
    $policy = app(OrgMetricPolicy::class);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $orgMetric))->toBeTrue()
        ->and($policy->update($user, $orgMetric))->toBeTrue()
        ->and($policy->manageParticipants($user, $orgMetric))->toBeTrue();
});

it('allows org viewers to view but not update org metrics', function () {
    $orgMetric = OrgMetric::factory()->create();
    $user = User::factory()->create();

    Membership::factory()->viewer()->create([
        'organization_id' => $orgMetric->organization_id,
        'user_id' => $user->id,
    ]);

    $policy = app(OrgMetricPolicy::class);

    expect($policy->view($user, $orgMetric))->toBeTrue()
        ->and($policy->update($user, $orgMetric))->toBeFalse()
        ->and($policy->manageParticipants($user, $orgMetric))->toBeFalse();
});

it('allows org metric owners to update org metrics', function () {
    $orgMetric = OrgMetric::factory()->create();
    $user = User::factory()->create();

    Membership::factory()->member()->create([
        'organization_id' => $orgMetric->organization_id,
        'user_id' => $user->id,
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'user_id' => $user->id,
        'responsibility' => 'owner',
    ]);

    $policy = app(OrgMetricPolicy::class);

    expect($policy->update($user, $orgMetric))->toBeTrue()
        ->and($policy->manageParticipants($user, $orgMetric))->toBeFalse();
});
