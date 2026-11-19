<?php

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createOwnerUser(): User
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
        ->role(MembershipRole::OWNER)
        ->create();

    return $user;
}

function legacyEncryptedActiveOrganizationCookie(string $value): string
{
    /** @var Encrypter $encrypter */
    $encrypter = app(Encrypter::class);

    $prefixed = CookieValuePrefix::create(Impersonation::ACTIVE_ORGANIZATION_COOKIE, $encrypter->getKey()).$value;

    return $encrypter->encrypt($prefixed, false);
}

it('allows staff owners to impersonate an organization', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::OWNER->value,
        ]);

    $response->assertRedirect(route('org.show', $organization));
    $response->assertSessionHas(Impersonation::SESSION_KEY, (string) $organization->id);
    $response->assertPlainCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $organization->id);
});

it('keeps the selected organization when a legacy encrypted cookie is provided', function () {
    $user = User::factory()->create();
    $alpha = Organization::factory()->create(['name' => 'Alpha Org']);
    $zulu = Organization::factory()->create(['name' => 'Zulu Org']);

    Membership::factory()->for($alpha)->for($user, 'user')->role(MembershipRole::OWNER)->create();
    Membership::factory()->for($zulu)->for($user, 'user')->role(MembershipRole::OWNER)->create();

    $legacyCookie = legacyEncryptedActiveOrganizationCookie((string) $zulu->id);

    $request = HttpRequest::create('/', 'GET', [], [
        Impersonation::ACTIVE_ORGANIZATION_COOKIE => $legacyCookie,
    ]);

    expect(Impersonation::resolveActiveOrganizationCookie($request))->toBe((string) $zulu->id);

    $response = $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, $legacyCookie)
        ->get(route('org.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeOrganizationId', (string) $zulu->id));
    $response->assertPlainCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $zulu->id);
});

it('requires a role and grants the impersonated role access to organizations', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::OWNER->value,
            'justification' => 'owner.console',
        ])
        ->assertRedirect(route('org.show', $organization))
        ->assertSessionHas(Impersonation::SESSION_ROLE_KEY, MembershipRole::OWNER->value);

    $this->actingAs($user)
        ->get(route('org.show', $organization))
        ->assertOk();
});

it('restricts impersonated viewer sessions to read-only access', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::VIEWER->value,
            'justification' => 'owner.console.viewer',
        ])
        ->assertRedirect(route('org.show', $organization));

    $this->actingAs($user)
        ->get(route('org.show', $organization))
        ->assertOk();

    $this->actingAs($user)
        ->put(route('org.settings.update', $organization), [
            'name' => $organization->name,
            'settings_json' => [
                'locale' => 'en-US',
                'currency' => 'USD',
            ],
        ])
        ->assertForbidden();
});

it('exposes impersonation payload for layout selection when toggling sessions', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user);

    $this->post(route('owner.impersonation.start', $organization), [
        'role' => MembershipRole::OWNER->value,
        'justification' => 'layout guard',
    ])->assertRedirect(route('org.show', $organization));

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonation.organization.id', (string) $organization->id)
            ->where('impersonation.role', MembershipRole::OWNER->value)
            ->where('auth.user.is_staff', true)
        );

    $this->delete(route('owner.impersonation.stop'), [
        'justification' => 'layout guard',
    ])->assertRedirect(route('owner.console'));

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('impersonation', null)
        );

    $startLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STARTED)
        ->latest('id')
        ->first();

    $stopLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STOPPED)
        ->latest('id')
        ->first();

    expect($startLog)->not()->toBeNull()
        ->and($startLog->organization_id)->toBe($organization->id)
        ->and($startLog->justification)->toBe('layout guard');

    expect($stopLog)->not()->toBeNull()
        ->and($stopLog->organization_id)->toBe($organization->id)
        ->and($stopLog->justification)->toBe('layout guard');
});

it('logs an audit entry when impersonation starts', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::OWNER->value,
            'justification' => 'owner.console.test',
        ])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STARTED)
        ->latest('id')
        ->first();

    expect($log)->not()->toBeNull()
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('impersonation.started')
        ->and($log->subject_type)->toBe(Organization::class)
        ->and($log->subject_id)->toBe($organization->id)
        ->and($log->justification)->toBe('owner.console.test');
});

it('prevents impersonation of internal organizations and logs a denied audit entry', function () {
    $user = createOwnerUser();
    $internal = Organization::factory()->create(['is_internal' => true]);

    $response = $this->actingAs($user)
        ->from(route('owner.console'))
        ->post(route('owner.impersonation.start', $internal), [
            'role' => MembershipRole::OWNER->value,
            'justification' => 'internal guardrail',
        ]);

    $response->assertRedirect(route('owner.console'));
    $response->assertSessionHas('impersonation_error', 'You are not allowed to impersonate this organization.');

    $log = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_DENIED)
        ->latest('id')
        ->first();

    expect($log)->not()->toBeNull()
        ->and($log->organization_id)->toBe($internal->id)
        ->and($log->result)->toBe(AuditLog::RESULT_DENIED)
        ->and($log->justification)->toBe('internal guardrail')
        ->and($log->properties['attempted_role'])->toBe(MembershipRole::OWNER->value)
        ->and($log->impersonated_role)->toBe(MembershipRole::OWNER->value);
});

it('prevents non-staff users from impersonating', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization))
        ->assertForbidden();
});

it('allows staff owners to stop impersonating and restores the previous selection', function () {
    $user = createOwnerUser();
    $previous = Organization::factory()->create();
    $impersonated = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $impersonated->id,
            Impersonation::PREVIOUS_ACTIVE_SESSION_KEY => (string) $previous->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::OWNER->value,
        ])
        ->delete(route('owner.impersonation.stop'));

    $response->assertRedirect(route('owner.console'));
    $response->assertSessionMissing(Impersonation::SESSION_KEY);
    $response->assertSessionMissing(Impersonation::PREVIOUS_ACTIVE_SESSION_KEY);
    $response->assertSessionMissing(Impersonation::SESSION_JUSTIFICATION_KEY);
    $response->assertSessionHas('success', 'Stopped impersonating.');
    $response->assertPlainCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $previous->id);
});

it('restores plaintext active organization cookies after stopping when legacy values exist', function () {
    $user = createOwnerUser();
    $previous = Organization::factory()->create(['name' => 'Previous Org']);
    $impersonated = Organization::factory()->create(['name' => 'Impersonated Org']);

    $legacyCookie = legacyEncryptedActiveOrganizationCookie((string) $previous->id);

    $this->actingAs($user)
        ->withUnencryptedCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, $legacyCookie)
        ->post(route('owner.impersonation.start', $impersonated), [
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertRedirect(route('org.show', $impersonated));

    expect(session(Impersonation::PREVIOUS_ACTIVE_SESSION_KEY))->toBe((string) $previous->id)
        ->and(session(Impersonation::SESSION_KEY))->toBe((string) $impersonated->id);

    $response = $this->actingAs($user)
        ->delete(route('owner.impersonation.stop'));

    $response->assertRedirect(route('owner.console'));
    $response->assertPlainCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, (string) $previous->id);
});

it('logs an audit entry when impersonation stops', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $organization->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::OWNER->value,
        ])
        ->delete(route('owner.impersonation.stop'), [
            'justification' => 'owner.console.stop',
        ])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STOPPED)
        ->latest('id')
        ->first();

    expect($log)->not()->toBeNull()
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe('impersonation.stopped')
        ->and($log->subject_type)->toBe(Organization::class)
        ->and($log->subject_id)->toBe($organization->id)
        ->and($log->justification)->toBe('owner.console.stop');
});

it('carries the latest justification across stop and subsequent start requests when switching organizations', function () {
    $user = createOwnerUser();
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $first), [
            'role' => MembershipRole::ADMIN->value,
            'justification' => 'initial start',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $first->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::ADMIN->value,
            Impersonation::PREVIOUS_ACTIVE_SESSION_KEY => 'workspace-org',
        ])
        ->delete(route('owner.impersonation.stop'), [
            'justification' => 'Switch to beta org',
        ])
        ->assertRedirect(route('owner.console'));

    $stopLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STOPPED)
        ->latest('id')
        ->first();

    expect($stopLog)->not()->toBeNull()
        ->and($stopLog->justification)->toBe('Switch to beta org');

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $second), [
            'role' => MembershipRole::OWNER->value,
            'justification' => 'Switch to beta org',
        ])
        ->assertRedirect(route('org.show', $second));

    $startLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STARTED)
        ->latest('id')
        ->first();

    expect($startLog)->not()->toBeNull()
        ->and($startLog->justification)->toBe('Switch to beta org');
});

it('clears impersonation cookies when no previous selection exists', function () {
    $user = createOwnerUser();
    $impersonated = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $impersonated->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::OWNER->value,
        ])
        ->delete(route('owner.impersonation.stop'));

    $response->assertRedirect(route('owner.console'));
    $response->assertSessionMissing(Impersonation::SESSION_KEY);
    $response->assertSessionMissing(Impersonation::SESSION_JUSTIFICATION_KEY);
    $response->assertSessionHas('success', 'Stopped impersonating.');
    $response->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});

it('flashes an error when the stop route is called without an active session', function () {
    $user = createOwnerUser();

    $response = $this->actingAs($user)
        ->from(route('owner.console'))
        ->delete(route('owner.impersonation.stop'));

    $response->assertRedirect(route('owner.console'));
    $response->assertSessionHas('impersonation_error', 'No active impersonation session to stop.');
});

it('redirects staff back to the owner console when impersonation context expires', function () {
    $user = createOwnerUser();

    $response = $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => 'missing-org',
            Impersonation::SESSION_ROLE_KEY => MembershipRole::OWNER->value,
        ])
        ->withCookie(Impersonation::ACTIVE_ORGANIZATION_COOKIE, 'missing-org')
        ->get(route('org.index'));

    $response->assertRedirect(route('owner.console'));
    $response->assertSessionMissing(Impersonation::SESSION_KEY);
    $response->assertSessionHas('impersonation_error', 'Your impersonation session expired. Select an organization to continue.');
    $response->assertCookieExpired(Impersonation::ACTIVE_ORGANIZATION_COOKIE);
});

it('logs role changes and updates permissions without switching organizations', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertRedirect(route('org.show', $organization));

    $this->actingAs($user)
        ->put(route('org.settings.update', $organization), [
            'name' => 'Updated Org',
            'settings_json' => [
                'locale' => 'en-US',
                'currency' => 'USD',
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::VIEWER->value,
        ])
        ->assertRedirect(route('org.show', $organization))
        ->assertSessionHas(Impersonation::SESSION_ROLE_KEY, MembershipRole::VIEWER->value);

    $log = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_ROLE_CHANGED)
        ->latest('id')
        ->first();
    expect($log)
        ->not->toBeNull()
        ->and($log->action)->toBe('impersonation.role-changed')
        ->and($log->impersonated_role)->toBe(MembershipRole::VIEWER->value);

    $this->actingAs($user)
        ->put(route('org.settings.update', $organization), [
            'name' => 'Blocked Update',
            'settings_json' => [
                'locale' => 'en-US',
                'currency' => 'USD',
            ],
        ])
        ->assertForbidden();
});

it('rejects unsupported impersonation roles and logs a denied audit event', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('Accept', 'application/json')
        ->post(route('owner.impersonation.start', $organization), [
            'role' => 'superuser',
            'justification' => 'tamper attempt',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['role']);

    expect(session('impersonation_error'))
        ->toBe('Select a valid role to impersonate this organization.');

    $log = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_DENIED)
        ->latest('id')
        ->first();

    expect($log)->not()->toBeNull()
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->result)->toBe(AuditLog::RESULT_DENIED)
        ->and($log->properties['attempted_role'])->toBe('superuser');
});

it('captures impersonation metadata for start, stop, and denied events', function () {
    $user = createOwnerUser();
    $organization = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('owner.impersonation.start', $organization), [
            'role' => MembershipRole::ADMIN->value,
            'justification' => 'starting session',
        ])
        ->assertRedirect(route('org.show', $organization));

    $startLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STARTED)
        ->latest('id')
        ->first();

    expect($startLog)->not()->toBeNull()
        ->and($startLog->result)->toBe(AuditLog::RESULT_STARTED)
        ->and($startLog->impersonated_role)->toBe(MembershipRole::ADMIN->value)
        ->and($startLog->justification)->toBe('starting session');

    $this->actingAs($user)
        ->withSession([
            Impersonation::SESSION_KEY => (string) $organization->id,
            Impersonation::SESSION_ROLE_KEY => MembershipRole::ADMIN->value,
        ])
        ->delete(route('owner.impersonation.stop'), [
            'justification' => 'wrapping up',
        ])
        ->assertRedirect(route('owner.console'));

    $stopLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_STOPPED)
        ->latest('id')
        ->first();

    expect($stopLog)->not()->toBeNull()
        ->and($stopLog->result)->toBe(AuditLog::RESULT_STOPPED)
        ->and($stopLog->impersonated_role)->toBe(MembershipRole::ADMIN->value)
        ->and($stopLog->justification)->toBe('wrapping up');

    $internal = Organization::factory()->create(['is_internal' => true]);

    $this->actingAs($user)
        ->from(route('owner.console'))
        ->post(route('owner.impersonation.start', $internal), [
            'role' => MembershipRole::VIEWER->value,
            'justification' => 'denied attempt',
        ])
        ->assertRedirect(route('owner.console'));

    $deniedLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_IMPERSONATION_DENIED)
        ->latest('id')
        ->first();

    expect($deniedLog)->not()->toBeNull()
        ->and($deniedLog->result)->toBe(AuditLog::RESULT_DENIED)
        ->and($deniedLog->impersonated_role)->toBe(MembershipRole::VIEWER->value)
        ->and($deniedLog->justification)->toBe('denied attempt');
});
