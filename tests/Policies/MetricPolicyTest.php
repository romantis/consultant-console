<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use App\Policies\MetricPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('changeRequestRoles', [
    'owner can request change' => [MembershipRole::OWNER, true],
    'admin can request change' => [MembershipRole::ADMIN, true],
    'member cannot request change' => [MembershipRole::MEMBER, false],
    'viewer cannot request change' => [MembershipRole::VIEWER, false],
]);

it('gates change proposal requests by membership role', function (MembershipRole $role, bool $expected) {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->role($role)
        ->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

    $policy = app(MetricPolicy::class);

    expect($policy->requestChange($user, $metric))->toBe($expected);
})->with('changeRequestRoles');

it('requires the metric to be finalized before accepting change requests', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);

    $user = User::factory()->create();

    Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $policy = app(MetricPolicy::class);

    expect($policy->requestChange($user, $metric))->toBeFalse();
});

it('allows consultant staff to request changes across organizations', function () {
    $internal = Organization::factory()->create();
    $internal->forceFill(['is_internal' => true])->save();
    $clientOrg = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $clientOrg->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $clientOrg->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $staffUser = User::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffUser->id,
    ]);

    $policy = app(MetricPolicy::class);

    expect($policy->requestChange($staffUser, $metric))->toBeTrue();
});
