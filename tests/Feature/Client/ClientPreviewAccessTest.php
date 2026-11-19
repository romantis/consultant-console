<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

function clientAccessMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

it('allows staff owner and admin to access client handbook with an active impersonation session', function (MembershipRole $role) {
    $actor = clientAccessMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($actor)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $organization->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::VIEWER->value,
        ])
        ->get(route('client.handbook.index'))
        ->assertOk();
})->with([
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('denies staff owner and admin from client handbook without an active impersonation session', function (MembershipRole $role) {
    $actor = clientAccessMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);

    // Cookie alone is NOT enough anymore (reverted direct preview)
    $this->actingAs($actor)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $organization->id)
        ->get(route('client.handbook.index'))
        ->assertForbidden();
})->with([
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('denies assigned operators from client handbook even with a delegated organization cookie', function () {
    $operator = clientAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    // Operators are denied direct client-plane path per spec
    $this->actingAs($operator)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $organization->id)
        ->get(route('client.handbook.index'))
        ->assertForbidden();
});

it('denies non-staff users without membership from client handbook', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $organization->id)
        ->get(route('client.handbook.index'))
        ->assertNotFound(); // Clears context and redirects to dashboard/404
});
