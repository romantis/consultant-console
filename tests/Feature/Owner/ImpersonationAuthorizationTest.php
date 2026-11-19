<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createInternalStaffUser(MembershipRole $role): User
{
    $user = User::factory()->create();

    $internal = Organization::factory()->create([
        'is_internal' => true,
        'name' => 'Metric Harmony',
        'slug' => 'metric-harmony',
    ]);

    Membership::factory()
        ->for($internal)
        ->for($user, 'user')
        ->role($role)
        ->create();

    return $user;
}

it('allows only staff owners to access the owner console', function (MembershipRole $role, int $expectedStatus) {
    $user = createInternalStaffUser($role);

    $this->actingAs($user)
        ->get(route('owner.console'))
        ->assertStatus($expectedStatus);
})->with([
    'owner' => [MembershipRole::OWNER, 200],
    'admin' => [MembershipRole::ADMIN, 403],
    'operator' => [MembershipRole::MEMBER, 403],
]);

it('allows staff admins to start and stop impersonation for non-internal organizations', function () {
    $user = createInternalStaffUser(MembershipRole::ADMIN);
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::ADMIN->value,
            'justification' => 'admin support',
        ])
        ->assertRedirect(route('org.show', $organization))
        ->assertSessionHas(Impersonation::SESSION_KEY, (string) $organization->id);

    $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $organization->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::ADMIN->value,
        ])
        ->delete(route('owner.impersonation.stop'), [
            'justification' => 'done supporting',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing(Impersonation::SESSION_KEY);
});

it('forbids staff operators from starting and stopping impersonation', function () {
    $operator = createInternalStaffUser(MembershipRole::MEMBER);
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    $this->actingAs($operator)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::VIEWER->value,
            'justification' => 'nosey operator',
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $organization->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::VIEWER->value,
        ])
        ->delete(route('owner.impersonation.stop'))
        ->assertForbidden();
});
