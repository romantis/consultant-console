<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

it('allows staff owner to view any non-internal organization', function () {
    $owner = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $clientOrg = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($owner)
        ->get(route('org.show', $clientOrg))
        ->assertOk();
});

it('allows staff admin to view any non-internal organization', function () {
    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $clientOrg = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($admin)
        ->get(route('org.show', $clientOrg))
        ->assertOk();
});

it('allows staff owner to manage canonical internal organization settings', function () {
    $actor = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $actor->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($actor)
        ->get(route('org.settings.edit', $this->internalOrg))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Settings')
            ->where('organization.id', (string) $this->internalOrg->id)
        );

    $newName = 'Metric Harmony owner';

    $this->actingAs($actor)
        ->put(route('org.settings.update', $this->internalOrg), [
            'name' => $newName,
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
            'consultants' => [
                'create' => [],
            ],
        ])
        ->assertRedirect();

    expect($this->internalOrg->refresh()->name)->toBe($newName);
});

it('denies staff owner from managing non-canonical internal organization settings', function () {
    $actor = User::factory()->create();
    $otherInternalOrganization = Organization::factory()->create([
        'name' => 'Skunkworks',
        'slug' => 'skunkworks',
        'is_internal' => true,
    ]);

    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $actor->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($actor)
        ->get(route('org.settings.edit', $otherInternalOrganization))
        ->assertForbidden();

    $this->actingAs($actor)
        ->put(route('org.settings.update', $otherInternalOrganization), [
            'name' => 'Skunkworks Updated',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
        ])
        ->assertForbidden();

    expect($otherInternalOrganization->refresh()->name)->toBe('Skunkworks');
});

it('denies staff admin from managing canonical internal organization settings', function () {
    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($admin)
        ->get(route('org.settings.edit', $this->internalOrg))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('org.settings.update', $this->internalOrg), [
            'name' => 'Metric Harmony admin',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
            'consultants' => [
                'create' => [],
            ],
        ])
        ->assertForbidden();

    expect($this->internalOrg->refresh()->name)->not->toBe('Metric Harmony admin');
});

it('still allows staff admin to manage non-internal organization settings without client membership', function () {
    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $clientOrg = Organization::factory()->create([
        'is_internal' => false,
        'name' => 'Client Org',
    ]);

    $this->actingAs($admin)
        ->get(route('org.settings.edit', $clientOrg))
        ->assertOk();

    $this->actingAs($admin)
        ->put(route('org.settings.update', $clientOrg), [
            'name' => 'Client Org Updated',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
            'consultants' => [
                'create' => [],
            ],
        ])
        ->assertRedirect();

    expect($clientOrg->refresh()->name)->toBe('Client Org Updated');
});

it('allows staff operator to view assigned non-internal organization', function () {
    $operator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value, // OPERATOR
    ]);

    $clientOrg = Organization::factory()->create(['is_internal' => false]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $clientOrg->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.show', $clientOrg))
        ->assertOk();
});

it('denies staff operator from assigned organization settings management', function () {
    $operator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value, // OPERATOR
    ]);

    $clientOrg = Organization::factory()->create([
        'is_internal' => false,
        'name' => 'Original Org Name',
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $clientOrg->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.settings.edit', $clientOrg))
        ->assertForbidden();

    $this->actingAs($operator)
        ->put(route('org.settings.update', $clientOrg), [
            'name' => 'Changed Org Name',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
        ])
        ->assertForbidden();

    expect($clientOrg->refresh()->name)->toBe('Original Org Name');
});

it('denies staff operator from canonical internal organization settings management', function () {
    $operator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($operator)
        ->get(route('org.settings.edit', $this->internalOrg))
        ->assertForbidden();

    $this->actingAs($operator)
        ->put(route('org.settings.update', $this->internalOrg), [
            'name' => 'Should Not Change',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
            'consultants' => [
                'create' => [],
            ],
        ])
        ->assertForbidden();

    expect($this->internalOrg->refresh()->name)->not->toBe('Should Not Change');
});

it('denies staff operator from viewing unassigned non-internal organization', function () {
    $operator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value, // OPERATOR
    ]);

    $clientOrg = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($operator)
        ->get(route('org.show', $clientOrg))
        ->assertForbidden();
});

it('denies non-staff from viewing organization without membership', function () {
    $user = User::factory()->create();
    $clientOrg = Organization::factory()->create(['is_internal' => false]);

    $this->actingAs($user)
        ->get(route('org.show', $clientOrg))
        ->assertForbidden();
});

it('shows all non-internal organizations to staff owner and admin on index', function () {
    $owner = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $org1 = Organization::factory()->create(['is_internal' => false, 'name' => 'Org 1']);
    $org2 = Organization::factory()->create(['is_internal' => false, 'name' => 'Org 2']);

    $this->actingAs($owner)
        ->get(route('org.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 2)
            ->where('organizations.0.name', 'Org 1')
            ->where('organizations.1.name', 'Org 2')
        );
});

it('shows only assigned non-internal organizations to staff operator on index', function () {
    $operator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $this->internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value, // OPERATOR
    ]);

    $assignedOrg = Organization::factory()->create(['is_internal' => false, 'name' => 'Assigned Org']);
    $unassignedOrg = Organization::factory()->create(['is_internal' => false, 'name' => 'Unassigned Org']);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrg->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 1)
            ->where('organizations.0.id', (string) $assignedOrg->id)
            ->where('organizations.0.can_manage', false)
        );
});
