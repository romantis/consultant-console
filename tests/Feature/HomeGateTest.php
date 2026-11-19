<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('guest sees welcome', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

it('staff (no client orgs) is redirected to org.create', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    // mark as staff via Internal org membership
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value, // or OWNER
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('org.create'));
});

it('non-staff (no orgs) sees the handbook missing-org response', function () {
    $user = User::factory()->create(); // no memberships at all

    $this->actingAs($user)
        ->get('/')
        ->assertNotFound();
});

it('user with a client org lands on the handbook from home', function () {
    $user = User::factory()->create();
    $client = Organization::factory()->create([
        'name' => 'Acme, Inc.',
        'slug' => 'acme',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $client->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Handbook')
            ->where('organization.id', (string) $client->id)
        );
});

it('shared props omit internal organizations for non-staff users', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $client = Organization::factory()->create([
        'name' => 'External Co.',
        'slug' => 'external-co',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::VIEWER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $client->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Handbook')
            ->where('organizations', [
                [
                    'id' => (string) $client->id,
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'owned' => true,
                ],
            ])
            ->where('staffWorkspace', null)
            ->where('auth.user.is_staff', false)
        );
});

it('shared props prefer a client organization for non-staff users', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $client = Organization::factory()->create([
        'name' => 'Preferred Client',
        'slug' => 'preferred-client',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::VIEWER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $client->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('activeOrganizationId', (string) $client->id)
            ->where('clientNavigation.organization.id', (string) $client->id)
            ->where('auth.user.is_internal_staff', false)
        );
});

it('shared props clear internal active organizations for staff operators', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $response = $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $internal->id)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('activeOrganizationId', null)
            ->where('clientNavigation', null)
            ->where('organizations', [])
            ->where('auth.user.is_staff', false)
            ->where('auth.user.is_internal_staff', true)
        );
});

it('shared props clear unassigned active organizations for staff operators', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $unassignedClient = Organization::factory()->create([
        'name' => 'Unassigned Client',
        'slug' => 'unassigned-client',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $response = $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $unassignedClient->id)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE)
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('activeOrganizationId', null)
            ->where('clientNavigation', null)
            ->where('engagements', [])
            ->where('organizations', [])
            ->where('auth.user.is_internal_staff', true)
        );
});

it('shared props keep assigned active organizations for staff operators', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $assignedClient = Organization::factory()->create([
        'name' => 'Assigned Client',
        'slug' => 'assigned-client',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $assignedClient->id,
    ]);

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $assignedClient->id)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('activeOrganizationId', (string) $assignedClient->id)
            ->where('organizations.0.id', (string) $assignedClient->id)
            ->where('auth.user.is_internal_staff', true)
        );
});

it('shared props expose consultant workspace details for staff', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $client = Organization::factory()->create([
        'name' => 'Beta Org',
        'slug' => 'beta-org',
        'is_internal' => false,
    ]);

    $other = Organization::factory()->create([
        'name' => 'Alpha Org',
        'slug' => 'alpha-org',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $client->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('staffWorkspace.id', (string) $internal->id)
            ->where('staffWorkspace.slug', $internal->slug)
            ->where('organizations', function ($organizations) use ($client, $other) {
                if ($organizations instanceof Collection) {
                    $organizations = $organizations->all();
                }

                expect($organizations)->toBeArray();
                expect($organizations)->toHaveCount(3);

                expect($organizations[0])->toMatchArray([
                    'id' => 'impersonation-off',
                    'name' => 'Impersonation off',
                    'slug' => 'impersonation-off',
                    'owned' => false,
                ]);

                expect($organizations[1])->toMatchArray([
                    'id' => (string) $other->id,
                    'name' => $other->name,
                    'slug' => $other->slug,
                    'owned' => false,
                ]);

                expect($organizations[2])->toMatchArray([
                    'id' => (string) $client->id,
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'owned' => true,
                ]);

                return true;
            })
            ->where('auth.user.is_staff', true)
        );
});

it('shared props mark client organizations as manageable for staff admins without direct membership', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $client = Organization::factory()->create([
        'name' => 'Northwind Client',
        'slug' => 'northwind-client',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('organizations', function ($organizations) use ($client) {
                $rows = collect($organizations instanceof Collection ? $organizations->all() : $organizations);
                $clientRow = $rows->firstWhere('id', (string) $client->id);

                expect($clientRow)->not->toBeNull()
                    ->and($clientRow['owned'] ?? null)->toBeFalse()
                    ->and($clientRow['can_manage'] ?? null)->toBeTrue();

                return true;
            })
            ->where('auth.user.staff_role', 'admin')
        );
});

it('persists staff-selected client organizations without direct memberships', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();
    $client = Organization::factory()->create([
        'name' => 'Gamma Org',
        'slug' => 'gamma-org',
        'is_internal' => false,
    ]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $client->id)
        ->get(route('owner.console'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Console')
            ->where('activeOrganizationId', (string) $client->id)
        );
});

it('home clears the internal active organization cookie for staff operators', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $internal->id)
        ->get(route('home'))
        ->assertRedirect(route('profile.edit'))
        ->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});
