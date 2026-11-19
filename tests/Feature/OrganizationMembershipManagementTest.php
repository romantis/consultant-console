<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\MembershipAuditLog;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows owners to attach existing users as members', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($ownerMembership->user)
        ->post(route('org.members.store', $organization), [
            'email' => $invitee->email,
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasNoErrors();

    expect($organization->memberships()->where('user_id', $invitee->id)->exists())->toBeTrue();
});

it('allows staff owners and admins to add members without client membership', function (
    MembershipRole $staffRole,
    MembershipRole $assignedRole
) {
    $internalOrganization = makeInternalOrg();
    $actor = User::factory()->create();
    $organization = Organization::factory()->create();
    $invitee = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $actor->id,
        'role' => $staffRole->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($actor)
        ->post(route('org.members.store', $organization), [
            'user_id' => (string) $invitee->id,
            'role' => $assignedRole->value,
        ])
        ->assertSessionHasNoErrors();

    expect($organization->memberships()
        ->where('user_id', $invitee->id)
        ->where('role', $assignedRole->value)
        ->exists())->toBeTrue();
})->with([
    'staff owner assigning admin' => [MembershipRole::OWNER, MembershipRole::ADMIN],
    'staff admin assigning member' => [MembershipRole::ADMIN, MembershipRole::MEMBER],
]);

it('prevents staff admins without client membership from assigning owners', function () {
    $internalOrganization = makeInternalOrg();
    $actor = User::factory()->create();
    $organization = Organization::factory()->create();
    $invitee = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $actor->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($actor)
        ->post(route('org.members.store', $organization), [
            'user_id' => (string) $invitee->id,
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasErrors('role');

    expect($organization->memberships()->where('user_id', $invitee->id)->exists())->toBeFalse();
});

it('does not expose or process legacy consultant attachment through organization settings', function () {
    $internalOrganization = Organization::factory()->create([
        'is_internal' => true,
    ]);
    $consultantUser = User::factory()->create([
        'email' => 'consultant@example.com',
    ]);
    $consultantMembership = Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $consultantUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    // The actor must be staff to see consultant candidates in the new model
    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $ownerMembership->user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($ownerMembership->user)
        ->get(route('org.settings.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Settings')
            ->missing('consultant_candidates'));

    $this->actingAs($ownerMembership->user)
        ->put(route('org.settings.update', $organization), [
            'name' => $organization->name,
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
                'create' => [
                    [
                        'membership_id' => (string) $consultantMembership->id,
                    ],
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($organization->memberships()->where('user_id', $consultantUser->id)->exists())->toBeFalse();
    expect($organization->pendingMemberships()->where('email', 'consultant@example.com')->exists())->toBeFalse();
});

it('prevents attaching internal staff to a client organization through direct member add', function () {
    $internalOrganization = makeInternalOrg();
    $organization = Organization::factory()->create();
    $actor = User::factory()->create();
    $internalStaffUser = User::factory()->create([
        'email' => 'operator@example.com',
    ]);

    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $actor->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $internalStaffUser->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($actor)
        ->post(route('org.members.store', $organization), [
            'email' => $internalStaffUser->email,
            'role' => MembershipRole::MEMBER->value,
        ])
        ->assertSessionHasErrors([
            'email' => 'Internal staff must be assigned through staff operator assignments.',
        ]);

    expect($organization->memberships()->where('user_id', $internalStaffUser->id)->exists())->toBeFalse();
});

it('prevents attaching internal staff to a client organization through settings membership create payload', function () {
    $internalOrganization = makeInternalOrg();
    $organization = Organization::factory()->create();
    $actor = User::factory()->create();
    $internalStaffUser = User::factory()->create([
        'email' => 'staff-admin@example.com',
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $internalOrganization->id,
        'user_id' => $internalStaffUser->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($actor)
        ->put(route('org.settings.update', $organization), [
            'name' => $organization->name,
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'create' => [
                    [
                        'email' => $internalStaffUser->email,
                        'role' => MembershipRole::MEMBER->value,
                    ],
                ],
                'update' => [],
                'delete' => [],
                'pending_update' => [],
                'pending_delete' => [],
            ],
        ])
        ->assertSessionHasErrors([
            'memberships.create.0.email' => 'Internal staff must be assigned through staff operator assignments.',
        ]);

    expect($organization->memberships()->where('user_id', $internalStaffUser->id)->exists())->toBeFalse();
    expect($organization->pendingMemberships()->where('email', $internalStaffUser->email)->exists())->toBeFalse();
});

it('limits role options for admins when managing members', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $admin = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($admin->user)
        ->get(route('org.settings.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Settings')
            ->where('roles', function (array|Collection $roles) {
                expect($roles)->toHaveCount(3);
                expect(collect($roles)->pluck('value')->all())
                    ->toBe([
                        MembershipRole::ADMIN->value,
                        MembershipRole::MEMBER->value,
                        MembershipRole::VIEWER->value,
                    ]);

                return true;
            }));

    $this->actingAs($owner->user)
        ->get(route('org.settings.edit', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Settings')
            ->where('roles', function (array|Collection $roles) {
                expect($roles)->toHaveCount(4);
                expect(collect($roles)->pluck('value')->all())
                    ->toBe([
                        MembershipRole::OWNER->value,
                        MembershipRole::ADMIN->value,
                        MembershipRole::MEMBER->value,
                        MembershipRole::VIEWER->value,
                    ]);

                return true;
            }));
});

it('audits membership creation when owners add existing users', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($ownerMembership->user)
        ->post(route('org.members.store', $organization), [
            'email' => $invitee->email,
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasNoErrors();

    $membership = $organization->memberships()->where('user_id', $invitee->id)->firstOrFail();

    $log = MembershipAuditLog::query()
        ->where('membership_id', $membership->id)
        ->where('action', 'membership.created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_membership_id)->toBe($ownerMembership->id)
        ->and($log->properties['actor_user_id'] ?? null)->toBe($ownerMembership->user_id)
        ->and($log->properties['membership_user_id'] ?? null)->toBe($invitee->id)
        ->and($log->properties['role'] ?? null)->toBe(MembershipRole::ADMIN->value);
});

it('records pending memberships when no user exists', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($ownerMembership->user)
        ->post(route('org.members.store', $organization), [
            'email' => 'future@example.com',
            'name' => 'Future Teammate',
            'role' => MembershipRole::MEMBER->value,
        ])
        ->assertSessionHasNoErrors();

    expect($organization->pendingMemberships()->where('email', 'future@example.com')->exists())->toBeTrue();
});

it('prevents admins from inviting new owners', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $admin = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($admin->user)
        ->post(route('org.members.store', $organization), [
            'email' => 'new-owner@example.com',
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasErrors('role');
});

it('prevents duplicate invites', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'dupe@example.com',
    ]);

    $this->actingAs($ownerMembership->user)
        ->post(route('org.members.store', $organization), [
            'email' => 'dupe@example.com',
            'role' => MembershipRole::MEMBER->value,
        ])
        ->assertSessionHasErrors('email');
});

it('prevents admins from promoting pending invites to owner', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $admin = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($admin->user)
        ->put(route('org.pending-members.update', [$organization, $pending]), [
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasErrors('role');
});

it('blocks inviting existing members regardless of email casing', function () {
    $organization = Organization::factory()->create();
    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($ownerMembership->user)
        ->post(route('org.members.store', $organization), [
            'email' => strtoupper($member->user->email),
            'role' => MembershipRole::MEMBER->value,
        ])
        ->assertSessionHasErrors('email');
});

it('blocks non-admins from managing memberships', function () {
    $organization = Organization::factory()->create();
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);
    $targetMember = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($member->user)
        ->post(route('org.members.store', $organization), [
            'email' => 'nope@example.com',
            'role' => MembershipRole::MEMBER->value,
        ])
        ->assertForbidden();

    $this->actingAs($member->user)
        ->put(route('org.members.update', [$organization, $targetMember]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertForbidden();

    $this->actingAs($member->user)
        ->delete(route('org.members.destroy', [$organization, $targetMember]))
        ->assertForbidden();

    $this->actingAs($member->user)
        ->put(route('org.pending-members.update', [$organization, $pending]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertForbidden();

    $this->actingAs($member->user)
        ->delete(route('org.pending-members.destroy', [$organization, $pending]))
        ->assertForbidden();
});

it('updates roles for existing members', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($owner->user)
        ->put(route('org.members.update', [$organization, $member]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->role)->toBe(MembershipRole::ADMIN);
});

it('audits membership role updates', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($owner->user)
        ->put(route('org.members.update', [$organization, $member]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasNoErrors();

    $log = MembershipAuditLog::query()
        ->where('membership_id', $member->id)
        ->where('action', 'membership.role.updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_membership_id)->toBe($owner->id)
        ->and($log->properties['from'] ?? null)->toBe(MembershipRole::MEMBER->value)
        ->and($log->properties['to'] ?? null)->toBe(MembershipRole::ADMIN->value);
});

it('requires an owner to promote another member to owner', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $admin = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($admin->user)
        ->put(route('org.members.update', [$organization, $admin]), [
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(MembershipRole::ADMIN)
        ->and($owner->fresh()->role)->toBe(MembershipRole::OWNER);
});

it('transfers ownership when an owner promotes another member', function () {
    $organization = Organization::factory()->create();
    $currentOwner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($currentOwner->user)
        ->put(route('org.members.update', [$organization, $member]), [
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->role)->toBe(MembershipRole::OWNER)
        ->and($currentOwner->fresh()->role)->toBe(MembershipRole::ADMIN);
});

it('prevents the last owner from demoting themselves', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($owner->user)
        ->put(route('org.members.update', [$organization, $owner]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasErrors('role');

    expect($owner->fresh()->role)->toBe(MembershipRole::OWNER);
});

it('reassigns ownership when adding an existing user as an owner', function () {
    $organization = Organization::factory()->create();
    $currentOwner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $invitee = User::factory()->create();

    $this->actingAs($currentOwner->user)
        ->post(route('org.members.store', $organization), [
            'email' => $invitee->email,
            'role' => MembershipRole::OWNER->value,
        ])
        ->assertSessionHasNoErrors();

    $newOwner = $organization->memberships()->where('user_id', $invitee->id)->first();

    expect($newOwner)->not()->toBeNull()
        ->and($newOwner->role)->toBe(MembershipRole::OWNER)
        ->and($currentOwner->fresh()->role)->toBe(MembershipRole::ADMIN);
});

it('removes pending invites', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($owner->user)
        ->delete(route('org.pending-members.destroy', [$organization, $pending]))
        ->assertSessionHasNoErrors();

    expect($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeFalse();
});

it('audits membership removals', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($owner->user)
        ->delete(route('org.members.destroy', [$organization, $member]))
        ->assertSessionHasNoErrors();

    $log = MembershipAuditLog::query()
        ->where('membership_id', $member->id)
        ->where('action', 'membership.deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_membership_id)->toBe($owner->id)
        ->and($log->properties['role'] ?? null)->toBe(MembershipRole::MEMBER->value);
});

it('rejects membership updates across organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $otherMembership = Membership::factory()->create([
        'organization_id' => $otherOrganization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($owner->user)
        ->put(route('org.members.update', [$organization, $otherMembership]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertNotFound();

    expect($otherMembership->fresh()->role)->toBe(MembershipRole::MEMBER);
});

it('rejects membership removals across organizations', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $otherMembership = Membership::factory()->create([
        'organization_id' => $otherOrganization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($owner->user)
        ->delete(route('org.members.destroy', [$organization, $otherMembership]))
        ->assertNotFound();

    expect(Membership::whereKey($otherMembership->id)->exists())->toBeTrue();
});

it('rejects membership updates when the user filter mismatches the membership', function () {
    $organization = Organization::factory()->create();
    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);
    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);
    $differentUser = User::factory()->create();

    $this->actingAs($owner->user)
        ->put(route('org.members.update', [$organization, $member]), [
            'role' => MembershipRole::ADMIN->value,
            'user_id' => $differentUser->id,
        ])
        ->assertNotFound();

    expect($member->fresh()->role)->toBe(MembershipRole::MEMBER);
});

it('can seed memberships during organization creation', function () {
    $staffUser = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $payload = [
        'name' => 'New Org',
        'slug' => 'new-org',
        'memberships' => [
            'create' => [
                [
                    'email' => $existingUser->email,
                    'role' => MembershipRole::ADMIN->value,
                    'affiliation' => 'Internal Ops',
                ],
                [
                    'email' => 'pending@example.com',
                    'name' => 'Pending Person',
                    'role' => MembershipRole::MEMBER->value,
                    'affiliation' => 'Advisory Group',
                ],
            ],
        ],
    ];

    $this->actingAs($staffUser)
        ->post(route('org.store'), $payload)
        ->assertRedirect();

    $organization = Organization::where('slug', 'new-org')->firstOrFail();

    $createdMembership = $organization->memberships()->where('user_id', $existingUser->id)->firstOrFail();
    $pendingInvite = $organization->pendingMemberships()->where('email', 'pending@example.com')->firstOrFail();

    expect($createdMembership->affiliation)->toBe('Internal Ops')
        ->and($pendingInvite->affiliation)->toBe('Advisory Group')
        ->and($organization->memberships()->where('user_id', $staffUser->id)->exists())->toBeFalse();
});

it('prevents staff admins from seeding client owners during organization creation', function () {
    $staffUser = User::factory()->create();
    $internal = makeInternalOrg();
    $clientOwner = User::factory()->create(['email' => 'client-owner@example.com']);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($staffUser)
        ->post(route('org.store'), [
            'name' => 'Admin Seeded Org',
            'slug' => 'admin-seeded-org',
            'memberships' => [
                'create' => [
                    [
                        'email' => $clientOwner->email,
                        'role' => MembershipRole::OWNER->value,
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors([
            'memberships.create.0.role' => 'You are not allowed to assign this role.',
        ]);

    expect(Organization::where('slug', 'admin-seeded-org')->exists())->toBeFalse();
});

it('allows staff owners to seed client owners during organization creation', function () {
    $staffUser = User::factory()->create();
    $internal = makeInternalOrg();
    $clientOwner = User::factory()->create(['email' => 'client-owner@example.com']);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($staffUser)
        ->post(route('org.store'), [
            'name' => 'Owner Seeded Org',
            'slug' => 'owner-seeded-org',
            'memberships' => [
                'create' => [
                    [
                        'email' => $clientOwner->email,
                        'role' => MembershipRole::OWNER->value,
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $organization = Organization::where('slug', 'owner-seeded-org')->firstOrFail();

    expect($organization->memberships()
        ->where('user_id', $clientOwner->id)
        ->where('role', MembershipRole::OWNER->value)
        ->exists())->toBeTrue()
        ->and($organization->memberships()->where('user_id', $staffUser->id)->exists())->toBeFalse();
});

it('updates organization settings and memberships atomically', function () {
    $organization = Organization::factory()->create([
        'name' => 'Acme',
        'settings_json' => ['locale' => 'en', 'currency' => 'USD'],
    ]);

    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $memberToPromote = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
        'affiliation' => 'Sales Team',
    ]);

    $memberToRemove = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $pendingToUpdate = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
        'name' => 'Pending Person',
        'affiliation' => 'Legacy Partner',
    ]);

    $pendingToRemove = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::VIEWER->value,
    ]);

    $existingUser = User::factory()->create(['email' => 'new-member@example.com']);

    $payload = [
        'name' => 'Renamed Org',
        'settings_json' => [
            'locale' => 'de',
            'currency' => 'EUR',
            'timezone' => 'America/New_York',
        ],
        'memberships' => [
            'update' => [
                [
                    'membership_id' => (string) $memberToPromote->id,
                    'role' => MembershipRole::ADMIN->value,
                    'affiliation' => 'Operations',
                ],
            ],
            'delete' => [(string) $memberToRemove->id],
            'pending_update' => [
                [
                    'pending_id' => (string) $pendingToUpdate->id,
                    'role' => MembershipRole::ADMIN->value,
                    'name' => 'Updated Pending',
                    'affiliation' => 'Strategic Advisors',
                ],
            ],
            'pending_delete' => [(string) $pendingToRemove->id],
            'create' => [
                [
                    'email' => $existingUser->email,
                    'role' => MembershipRole::MEMBER->value,
                    'affiliation' => 'Finance Guild',
                ],
                [
                    'email' => 'future@example.com',
                    'name' => 'Future Teammate',
                    'role' => MembershipRole::VIEWER->value,
                    'affiliation' => 'Community Board',
                ],
            ],
        ],
    ];

    $this->actingAs($owner->user)
        ->put(route('org.settings.update', $organization), $payload)
        ->assertSessionHas('success');

    $organization->refresh();

    expect($organization->name)->toBe('Renamed Org')
        ->and($organization->settings_json['locale'] ?? null)->toBe('de')
        ->and($organization->settings_json['currency'] ?? null)->toBe('EUR')
        ->and($organization->settings_json['timezone'] ?? null)->toBe('America/New_York');

    $refreshedPromoted = $memberToPromote->fresh();
    expect($refreshedPromoted->role)->toBe(MembershipRole::ADMIN)
        ->and($refreshedPromoted->affiliation)->toBe('Operations');
    expect($organization->memberships()->whereKey($memberToRemove->id)->exists())->toBeFalse();

    $updatedPending = $organization->pendingMemberships()->whereKey($pendingToUpdate->id)->firstOrFail();
    expect($updatedPending->role)->toBe(MembershipRole::ADMIN)
        ->and($updatedPending->name)->toBe('Updated Pending')
        ->and($updatedPending->affiliation)->toBe('Strategic Advisors');

    expect($organization->pendingMemberships()->whereKey($pendingToRemove->id)->exists())->toBeFalse();
    $createdMembership = $organization->memberships()->where('user_id', $existingUser->id)->firstOrFail();
    $newPending = $organization->pendingMemberships()->where('email', 'future@example.com')->firstOrFail();

    expect(MembershipAuditLog::query()
        ->where('membership_id', $createdMembership->id)
        ->where('action', 'membership.created')
        ->exists())->toBeTrue();

    expect($createdMembership->affiliation)->toBe('Finance Guild');
    expect($newPending->affiliation)->toBe('Community Board');

    expect(MembershipAuditLog::query()
        ->where('membership_id', $memberToPromote->id)
        ->where('action', 'membership.role.updated')
        ->exists())->toBeTrue();

    expect(MembershipAuditLog::query()
        ->where('membership_id', $memberToRemove->id)
        ->where('action', 'membership.deleted')
        ->exists())->toBeTrue();
});

it('rejects invalid organization scheduling timezone values', function () {
    $organization = Organization::factory()->create([
        'name' => 'Acme',
        'settings_json' => ['locale' => 'en', 'currency' => 'USD'],
    ]);

    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($owner->user)
        ->from(route('org.settings.edit', $organization))
        ->put(route('org.settings.update', $organization), [
            'name' => 'Acme',
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'Mars/Phobos',
            ],
            'memberships' => [],
        ])
        ->assertSessionHasErrors('settings_json.timezone');
});

it('rolls back membership changes when update validation fails', function () {
    $organization = Organization::factory()->create([
        'name' => 'Original Org',
        'settings_json' => ['locale' => 'en', 'currency' => 'USD'],
    ]);

    $owner = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $member = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::VIEWER->value,
    ]);

    $payload = [
        'name' => 'Should Not Persist',
        'settings_json' => ['locale' => 'fr', 'currency' => 'CHF'],
        'memberships' => [
            'update' => [
                ['membership_id' => (string) $member->id, 'role' => MembershipRole::ADMIN->value],
            ],
            'delete' => [(string) $owner->id],
            'pending_delete' => [(string) $pending->id],
        ],
    ];

    $this->actingAs($owner->user)
        ->from(route('org.settings.edit', $organization))
        ->put(route('org.settings.update', $organization), $payload)
        ->assertSessionHasErrors('memberships.delete.0');

    $organization->refresh();

    expect($organization->name)->toBe('Original Org')
        ->and($organization->settings_json['locale'] ?? null)->toBe('en')
        ->and($organization->settings_json['currency'] ?? null)->toBe('USD');

    expect($organization->memberships()->whereKey($owner->id)->exists())->toBeTrue();
    expect($member->fresh()->role)->toBe(MembershipRole::MEMBER);
    expect($organization->pendingMemberships()->whereKey($pending->id)->exists())->toBeTrue();

    expect(MembershipAuditLog::query()
        ->where('membership_id', $member->id)
        ->where('action', 'membership.role.updated')
        ->exists())->toBeFalse();
});
