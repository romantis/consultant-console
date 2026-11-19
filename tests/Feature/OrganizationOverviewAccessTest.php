<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

it('lets staff see every non-internal organization on the organizations index', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $ownedOrganization = Organization::factory()->create([
        'name' => 'Bravo Org',
        'slug' => 'bravo-org',
        'is_internal' => false,
    ]);
    $visibleOrganization = Organization::factory()->create([
        'name' => 'Alpha Org',
        'slug' => 'alpha-org',
        'is_internal' => false,
    ]);
    $hiddenInternalOrganization = Organization::factory()->create([
        'name' => 'Internal Lab',
        'slug' => 'internal-lab',
        'is_internal' => true,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $ownedOrganization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get(route('org.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Index')
            ->where('organizations', [
                [
                    'id' => (string) $visibleOrganization->id,
                    'name' => $visibleOrganization->name,
                    'slug' => $visibleOrganization->slug,
                    'can_manage' => true,
                ],
                [
                    'id' => (string) $ownedOrganization->id,
                    'name' => $ownedOrganization->name,
                    'slug' => $ownedOrganization->slug,
                    'can_manage' => true,
                ],
            ])
            ->missing('organizations.2')
            ->where('staffWorkspace.id', (string) $internal->id)
        );

    expect($hiddenInternalOrganization->is_internal)->toBeTrue();
});

it('lets staff open a non-internal organization overview without direct membership', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get(route('org.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Show')
            ->where('organization.id', (string) $organization->id)
            ->where('can_update', true)
        );
});

it('shares operator assignment staffing data for delivery staff on non-internal organization overview', function () {
    $user = User::factory()->create();
    $operator = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER->value,
        'affiliation' => 'Delivery Ops',
    ]);

    $assignment = StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'granted_by_user_id' => $user->id,
        'reason' => 'coverage',
        'notes' => 'launch support',
    ]);

    $unassignedOperator = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $unassignedOperator->id,
        'role' => MembershipRole::MEMBER->value,
        'affiliation' => 'QA',
    ]);

    $this->actingAs($user)
        ->get(route('org.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Show')
            ->where('can_manage_operator_assignments', true)
            ->has('operator_assignments', 1)
            ->where('operator_assignments.0.id', (string) $assignment->id)
            ->where('operator_assignments.0.user.id', (string) $operator->id)
            ->where('operator_assignments.0.user.email', $operator->email)
            ->where('operator_assignments.0.granted_by.id', (string) $user->id)
            ->where('operator_assignments.0.reason', 'coverage')
            ->where('operator_assignments.0.notes', 'launch support')
            ->where('operator_candidates', function (array|Collection $candidates) use ($unassignedOperator, $operator) {
                $rows = collect($candidates);

                return $rows->contains(fn ($candidate) => ($candidate['id'] ?? null) === (string) $unassignedOperator->id)
                    && ! $rows->contains(fn ($candidate) => ($candidate['id'] ?? null) === (string) $operator->id);
            })
        );
});

it('still denies staff access to internal organizations without a direct internal membership to that org', function () {
    $user = User::factory()->create();
    $staffWorkspace = makeInternalOrg();
    $otherInternalOrganization = Organization::factory()->create([
        'name' => 'Skunkworks',
        'slug' => 'skunkworks',
        'is_internal' => true,
    ]);

    Membership::factory()->create([
        'organization_id' => $staffWorkspace->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->get(route('org.show', $otherInternalOrganization))
        ->assertForbidden();
});
