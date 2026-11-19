<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Policies\EngagementPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('wizardOrganizationRoles', [
    'owner can update organization' => [MembershipRole::OWNER, true],
    'admin can update organization' => [MembershipRole::ADMIN, true],
    'member cannot update organization' => [MembershipRole::MEMBER, false],
    'viewer cannot update organization' => [MembershipRole::VIEWER, false],
]);

it('enforces organization update gate required to enter the engagement wizard', function (MembershipRole $role, bool $expected) {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    Membership::factory()
        ->role($role)
        ->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

    $policy = app(OrganizationPolicy::class);

    expect($policy->update($user, $organization))->toBe($expected);
})->with('wizardOrganizationRoles');

it('denies wizard access for users without a membership in the organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $policy = app(OrganizationPolicy::class);

    expect($policy->update($user, $organization))->toBeFalse();
});

it('denies wizard access for admins of other organizations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    Membership::factory()
        ->admin()
        ->create([
            'organization_id' => Organization::factory()->create()->id,
            'user_id' => $user->id,
        ]);

    $policy = app(OrganizationPolicy::class);

    expect($policy->update($user, $organization))->toBeFalse();
});

dataset('wizardLaunchRoles', [
    'owner can launch engagement' => [MembershipRole::OWNER, true],
    'admin can launch engagement' => [MembershipRole::ADMIN, true],
    'member cannot launch engagement' => [MembershipRole::MEMBER, false],
    'viewer cannot launch engagement' => [MembershipRole::VIEWER, false],
    'user without membership cannot launch engagement' => [null, false],
]);

it('enforces engagement creation gate before launching the wizard', function (?MembershipRole $role, bool $expected) {
    $user = User::factory()->create();

    if ($role instanceof MembershipRole) {
        Membership::factory()
            ->role($role)
            ->create(['user_id' => $user->id]);
    }

    $policy = app(EngagementPolicy::class);

    expect($policy->create($user))->toBe($expected);
})->with('wizardLaunchRoles');
