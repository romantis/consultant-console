<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('staff admin can create organization and view it without client membership', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->post(route('org.store'), ['name' => 'Nova Org'])
        ->assertRedirect();

    $org = Organization::where('name', 'Nova Org')->firstOrFail();

    expect(Membership::where('organization_id', $org->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('org.show', $org))
        ->assertOk();
});

it('limits create organization member roles for staff admins', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->get(route('org.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Create')
            ->where('roles', [
                ['value' => MembershipRole::ADMIN->value, 'label' => 'Admin'],
                ['value' => MembershipRole::MEMBER->value, 'label' => 'Member'],
                ['value' => MembershipRole::VIEWER->value, 'label' => 'Viewer'],
            ])
        );
});

it('non-member cannot access another org', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $this->actingAs($user)
        ->get(route('org.show', $org))
        ->assertForbidden();
});
