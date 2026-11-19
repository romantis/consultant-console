<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * STAFF can access org.create
 */
it('staff can open the create-org page', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    // mark user as staff via internal org membership (OWNER/ADMIN)
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->get(route('org.create'))
        ->assertOk();
});

/**
 * Regular authed users are forbidden
 */
it('regular user cannot open the create-org page', function () {
    $user = User::factory()->create();  // no internal membership

    $this->actingAs($user)
        ->get(route('org.create'))
        ->assertForbidden();
});

/**
 * Guests are redirected to login (web auth middleware)
 */
it('guest is redirected when opening the create-org page', function () {
    $this->get(route('org.create'))
        ->assertRedirect(); // typically to login
});

/**
 * Registration routes respect allow_public_registration
 * (Wayfinder still generates names; middleware enforces the toggle)
 */
it('register is 404 when public registration disabled', function () {
    config()->set('console.allow_public_registration', false);

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [])->assertNotFound();
});

it('register page is available when public registration enabled', function () {
    config()->set('console.allow_public_registration', true);

    $this->get(route('register'))->assertOk();
});
