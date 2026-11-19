<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns forbidden for staff hitting client routes without impersonation', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $this->actingAs($staff)
        ->get(route('client.handbook.index'))
        ->assertForbidden();
});

it('returns forbidden for internal staff operators hitting client routes without impersonation', function () {
    $operator = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($operator, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $this->actingAs($operator)
        ->get(route('client.handbook.index'))
        ->assertForbidden();
});

it('returns not found for non-staff internal viewers hitting client handbook with an internal active org', function () {
    $viewer = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($viewer, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $this->actingAs($viewer)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $internal->id)
        ->get(route('client.handbook.index'))
        ->assertNotFound()
        ->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});

it('returns not found for internal organizations on client metric detail routes even with direct memberships', function () {
    $viewer = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    $engagement = Engagement::factory()->for($internal)->create();

    Membership::factory()
        ->for($internal, 'organization')
        ->for($viewer, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $metric = Metric::factory()
        ->for($internal)
        ->for($engagement)
        ->create();

    $this->actingAs($viewer)
        ->getJson(route('client.handbook.metrics.show', [$internal, $engagement, $metric]))
        ->assertNotFound();
});

it('allows staff with an active impersonation session to reach client routes', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    $client = Organization::factory()->create();

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $member = User::factory()->create();
    Membership::factory()
        ->for($client, 'organization')
        ->for($member, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $this->actingAs($staff)
        ->withImpersonationSession($client, MembershipRole::ADMIN)
        ->get(route('client.handbook.index'))
        ->assertOk();
});

it('returns not found for owner reassignment endpoint when org metric overrides are disabled', function () {
    config()->set('metric-harmony.features.org_metric_overrides', false);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $admin = User::factory()->create();

    Membership::factory()
        ->for($organization, 'organization')
        ->for($admin, 'user')
        ->admin()
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->create();

    $this->actingAs($admin)
        ->patchJson(route('client.handbook.metrics.owner.update', [$organization, $engagement, $metric]), [
            'owner_participant_id' => 'participant-not-used',
        ])
        ->assertNotFound();
});

it('returns forbidden for members hitting owner reassignment endpoint even when overrides are enabled', function () {
    config()->set('metric-harmony.features.org_metric_overrides', true);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $member = User::factory()->create();

    Membership::factory()
        ->for($organization, 'organization')
        ->for($member, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->create();

    $this->actingAs($member)
        ->patchJson(route('client.handbook.metrics.owner.update', [$organization, $engagement, $metric]), [
            'owner_participant_id' => 'participant-not-used',
        ])
        ->assertForbidden();
});
