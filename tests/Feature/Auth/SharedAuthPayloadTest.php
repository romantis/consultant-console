<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares explicit staff authority fields for staff owners', function () {
    config()->set('console.internal_org_slug', 'metric-harmony');

    $internal = makeInternalOrg();
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.is_staff', true)
            ->where('auth.user.is_internal_staff', true)
            ->where('auth.user.staff_role', 'owner')
            ->where('auth.user.can_operate_delivery', true)
            ->where('auth.user.has_operator_assignments', false)
            ->where('auth.user.can_impersonate', true)
            ->where('auth.user.can_govern_internal', true)
        );
});

it('shares operator authority without broad delivery access before assignments exist', function () {
    config()->set('console.internal_org_slug', 'metric-harmony');

    $internal = makeInternalOrg();
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.is_staff', false)
            ->where('auth.user.is_internal_staff', true)
            ->where('auth.user.staff_role', 'operator')
            ->where('auth.user.can_operate_delivery', false)
            ->where('auth.user.has_operator_assignments', false)
            ->where('auth.user.can_impersonate', false)
            ->where('auth.user.can_govern_internal', false)
        );
});

it('shares an operator assignment hint when the user has an active assignment', function () {
    config()->set('console.internal_org_slug', 'metric-harmony');

    $internal = makeInternalOrg();
    $clientOrganization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $user = User::factory()->create();
    $grantor = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    StaffOperatorAssignment::factory()
        ->for($user, 'user')
        ->for($clientOrganization, 'organization')
        ->create([
            'granted_by_user_id' => $grantor->id,
        ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.is_staff', false)
            ->where('auth.user.is_internal_staff', true)
            ->where('auth.user.staff_role', 'operator')
            ->where('auth.user.can_operate_delivery', false)
            ->where('auth.user.has_operator_assignments', true)
            ->where('auth.user.can_impersonate', false)
            ->where('auth.user.can_govern_internal', false)
        );
});

it('shares fail-closed staff authority fields for non-staff users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('auth.user.is_staff', false)
            ->where('auth.user.is_internal_staff', false)
            ->where('auth.user.staff_role', null)
            ->where('auth.user.can_operate_delivery', false)
            ->where('auth.user.has_operator_assignments', false)
            ->where('auth.user.can_impersonate', false)
            ->where('auth.user.can_govern_internal', false)
        );
});
