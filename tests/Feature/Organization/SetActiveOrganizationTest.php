<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

function setActiveOrgMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

it('allows staff owner and admin to set any client organization as active', function (MembershipRole $role) {
    $actor = setActiveOrgMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($actor)
        ->post(route('org.active'), ['organization_id' => (string) $organization->id])
        ->assertNoContent()
        ->assertCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
})->with([
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('allows assigned operators to set their assigned organization as active', function () {
    $operator = setActiveOrgMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->post(route('org.active'), ['organization_id' => (string) $organization->id])
        ->assertNoContent()
        ->assertCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});

it('denies unassigned operators from setting a client organization as active', function () {
    $operator = setActiveOrgMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($operator)
        ->post(route('org.active'), ['organization_id' => (string) $organization->id])
        ->assertForbidden();
});

it('allows any user to set their member organization as active', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $this->actingAs($user)
        ->post(route('org.active'), ['organization_id' => (string) $organization->id])
        ->assertNoContent()
        ->assertCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});

it('denies users from setting organizations they do not have access to as active', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($user)
        ->post(route('org.active'), ['organization_id' => (string) $organization->id])
        ->assertForbidden();
});

it('denies staff owners from setting a missing organization as active', function () {
    $actor = setActiveOrgMakeStaffActor($this->internalOrg, MembershipRole::OWNER);

    $this->actingAs($actor)
        ->post(route('org.active'), ['organization_id' => (string) Str::ulid()])
        ->assertForbidden();
});

it('denies staff owners from setting a non-canonical internal organization as active', function () {
    $actor = setActiveOrgMakeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $otherInternal = Organization::factory()->create(['is_internal' => true]);

    $this->actingAs($actor)
        ->post(route('org.active'), ['organization_id' => (string) $otherInternal->id])
        ->assertForbidden();
});
