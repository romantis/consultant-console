<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Policies\EngagementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('broadStaffRoles', [
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('allows broad staff to view and update non-internal engagement work without client membership', function (
    MembershipRole $role,
) {
    $internal = makeInternalOrg();
    $actor = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $actor->id,
        'role' => $role->value,
    ]);

    $policy = app(EngagementPolicy::class);

    expect($policy->view($actor, $engagement))->toBeTrue()
        ->and($policy->update($actor, $engagement))->toBeTrue()
        ->and($policy->create($actor))->toBeTrue()
        ->and($policy->launch($actor))->toBeTrue();
})->with('broadStaffRoles');

it('allows assigned operators to view and update delegated non-internal engagement work', function () {
    $internal = makeInternalOrg();
    $operator = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $organization->id,
    ]);

    $policy = app(EngagementPolicy::class);

    expect($policy->view($operator, $engagement))->toBeTrue()
        ->and($policy->update($operator, $engagement))->toBeTrue()
        ->and($policy->delete($operator, $engagement))->toBeFalse()
        ->and($policy->create($operator))->toBeFalse()
        ->and($policy->launch($operator))->toBeFalse();
});

it('denies operators outside their delegated organization scope', function () {
    $internal = makeInternalOrg();
    $operator = User::factory()->create();
    $assignedOrganization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $otherOrganization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    $policy = app(EngagementPolicy::class);

    expect($policy->view($operator, $engagement))->toBeFalse()
        ->and($policy->update($operator, $engagement))->toBeFalse()
        ->and($policy->delete($operator, $engagement))->toBeFalse();
});

it('denies non-staff organization members on consultant-plane engagement policies', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $policy = app(EngagementPolicy::class);

    expect($policy->view($member, $engagement))->toBeFalse()
        ->and($policy->update($member, $engagement))->toBeFalse()
        ->and($policy->delete($member, $engagement))->toBeFalse()
        ->and($policy->create($member))->toBeFalse()
        ->and($policy->launch($member))->toBeFalse();
});

it('lets only staff owners delete non-internal engagements without client membership', function () {
    $internal = makeInternalOrg();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $policy = app(EngagementPolicy::class);

    expect($policy->delete($owner, $engagement))->toBeTrue()
        ->and($policy->delete($admin, $engagement))->toBeFalse();
});
