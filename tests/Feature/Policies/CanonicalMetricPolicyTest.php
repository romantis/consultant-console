<?php

use App\Enums\MembershipRole;
use App\Models\CanonicalMetric;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Policies\CanonicalMetricPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStaffUserForCanonical(): User
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

it('allows staff to manage canonical metrics', function () {
    $user = makeStaffUserForCanonical();
    $metric = CanonicalMetric::factory()->create();
    $policy = app(CanonicalMetricPolicy::class);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $metric))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->update($user, $metric))->toBeTrue()
        ->and($policy->delete($user, $metric))->toBeTrue();
});

it('blocks non-staff users from managing canonical metrics', function () {
    $user = User::factory()->create();
    $metric = CanonicalMetric::factory()->create();
    $policy = app(CanonicalMetricPolicy::class);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $metric))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->update($user, $metric))->toBeFalse()
        ->and($policy->delete($user, $metric))->toBeFalse();
});
