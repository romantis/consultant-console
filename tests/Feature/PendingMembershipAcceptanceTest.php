<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\MembershipAuditLog;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('directs invited users with existing accounts to the login page', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);
    User::factory()->create(['email' => 'invitee@example.com']);

    $this->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Sign in with invitee@example.com to accept your invitation.')
        ->assertSessionHas('auth.login.email', 'invitee@example.com');
});

it('shows the invite registration flow when the email is new', function () {
    $organization = Organization::factory()->create(['name' => 'Metric Harmony']);
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);

    $response = $this->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]));

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/AcceptInvite')
        ->where('email', 'invitee@example.com')
        ->where('organization.name', 'Metric Harmony')
    );
});

it('allows invite acceptance for matching accounts', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
        'role' => MembershipRole::ADMIN,
    ]);
    $user = User::factory()->create([
        'email' => 'invitee@example.com',
    ]);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]))
        ->assertRedirect(route('home'))
        ->assertSessionHas('success', 'Invite accepted.');

    expect($organization->memberships()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeFalse();

    $membership = $organization->memberships()->where('user_id', $user->id)->firstOrFail();

    expect($membership->affiliation)->toBe($pending->affiliation);

    $creationLog = MembershipAuditLog::query()
        ->where('membership_id', $membership->id)
        ->where('action', 'membership.created')
        ->first();

    expect($creationLog)->not->toBeNull()
        ->and($creationLog->actor_membership_id)->toBe($membership->id)
        ->and($creationLog->properties['actor_user_id'] ?? null)->toBe($user->id);
});

it('redirects accepted internal staff operators to profile settings', function () {
    $internal = makeInternalOrg();
    $pending = PendingMembership::factory()->member()->create([
        'organization_id' => $internal->id,
        'email' => 'operator@example.com',
    ]);
    $user = User::factory()->create([
        'email' => 'operator@example.com',
    ]);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Invite accepted.');

    expect($internal->memberships()
        ->where('user_id', $user->id)
        ->where('role', MembershipRole::MEMBER->value)
        ->exists())->toBeTrue();
});

it('assigns the organization role stored on the pending invite when accepted', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $pendingAdmin = PendingMembership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'email' => 'admin-invitee@example.com',
    ]);
    $pendingViewer = PendingMembership::factory()->viewer()->create([
        'organization_id' => $organization->id,
        'email' => 'viewer-invitee@example.com',
    ]);

    $adminUser = User::factory()->create(['email' => 'admin-invitee@example.com']);
    $viewerUser = User::factory()->create(['email' => 'viewer-invitee@example.com']);

    $this->actingAs($adminUser)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pendingAdmin->id]))
        ->assertRedirect(route('home'));

    $this->actingAs($viewerUser)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pendingViewer->id]))
        ->assertRedirect(route('home'));

    $adminMembership = $organization->memberships()->where('user_id', $adminUser->id)->firstOrFail();
    $viewerMembership = $organization->memberships()->where('user_id', $viewerUser->id)->firstOrFail();

    expect($adminMembership->role)->toBe(MembershipRole::ADMIN)
        ->and($viewerMembership->role)->toBe(MembershipRole::VIEWER);
});

it('allows invited users to create an account and accept their invite', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);

    $url = URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]);

    $this->post($url, [
        'name' => 'Invitee User',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('home'))
        ->assertSessionHas('success', 'Invite accepted.');

    $user = User::query()->where('email', 'invitee@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user?->email_verified_at)->not->toBeNull();

    $membership = $organization->memberships()->where('user_id', $user->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership?->affiliation)->toBe($pending->affiliation)
        ->and($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeFalse();
});

it('redirects newly registered invitees straight into the acceptance flow', function () {
    config()->set('console.allow_public_registration', true);

    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);

    $registerResponse = $this->post(route('register.store', [
        'pending_membership_id' => $pending->id,
    ]), [
        'name' => 'Invitee User',
        'email' => 'invitee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $registerResponse->assertRedirect();

    $redirectLocation = $registerResponse->headers->get('Location');

    expect($redirectLocation)->toContain('/invites/'.$pending->id.'/accept');

    $finalResponse = $this->followRedirects($registerResponse);

    $finalResponse->assertInertia(fn (Assert $page) => $page
        ->component('Client/Handbook')
        ->where('flash.success', 'Invite accepted.')
    );

    $user = User::query()->where('email', 'invitee@example.com')->firstOrFail();

    expect($user->memberships()->where('organization_id', $organization->id)->exists())->toBeTrue()
        ->and($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

it('does not redirect newly registered invitees when their invite has expired', function () {
    config()->set('console.allow_public_registration', true);
    config()->set('invitations.pending.accept_ttl_minutes', 60);

    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $registerResponse = $this->post(route('register.store', [
        'pending_membership_id' => $pending->id,
    ]), [
        'name' => 'Invitee User',
        'email' => 'invitee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $registerResponse->assertRedirect(route('home'))
        ->assertSessionHas('error', 'Your invitation has expired. Ask the inviter to send a new one.');

    $user = User::query()->where('email', 'invitee@example.com')->firstOrFail();

    expect($organization->memberships()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('audits ownership transfers when invites are accepted', function () {
    $organization = Organization::factory()->create();
    $currentOwner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]))
        ->assertRedirect(route('home'));

    $newOwnerMembership = $organization->memberships()->where('user_id', $user->id)->firstOrFail();

    $creationLog = MembershipAuditLog::query()
        ->where('membership_id', $newOwnerMembership->id)
        ->where('action', 'membership.created')
        ->first();

    expect($creationLog)->not->toBeNull()
        ->and($creationLog->actor_membership_id)->toBe($newOwnerMembership->id);

    $demotionLog = MembershipAuditLog::query()
        ->where('membership_id', $currentOwner->id)
        ->where('action', 'membership.role.updated')
        ->first();

    expect($demotionLog)->not->toBeNull()
        ->and($demotionLog->actor_membership_id)->toBe($newOwnerMembership->id)
        ->and($demotionLog->properties['from'] ?? null)->toBe(MembershipRole::OWNER->value)
        ->and($demotionLog->properties['to'] ?? null)->toBe(MembershipRole::ADMIN->value);
});

it('rejects invite acceptance when emails do not match', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);
    $user = User::factory()->create([
        'email' => 'other@example.com',
    ]);

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]))
        ->assertForbidden();

    expect($organization->memberships()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeTrue();
});

it('throttles repeated invite acceptance attempts', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invitee@example.com',
    ]);
    $user = User::factory()->create([
        'email' => 'other@example.com',
    ]);

    $url = URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]);

    $this->actingAs($user);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->get($url)->assertForbidden();
    }

    $this->get($url)->assertStatus(429);
});
