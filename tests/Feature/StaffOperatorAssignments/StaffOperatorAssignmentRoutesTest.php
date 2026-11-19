<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('console.internal_org_slug', 'consultant-hq');
});

it('lets staff owners and admins grant and revoke operator assignments through the route surface', function (MembershipRole $actorRole) {
    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $organization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $actor = User::factory()->create();
    $operator = User::factory()->create();

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->role($actorRole)
        ->create();

    Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $this->actingAs($actor)
        ->from(route('org.show', $organization))
        ->post(route('org.staff-operator-assignments.store', $organization), [
            'user_id' => $operator->id,
            'reason' => ' support coverage ',
            'notes' => ' launch coverage ',
        ])
        ->assertRedirect(route('org.show', $organization))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Operator assignment granted.');

    $assignment = StaffOperatorAssignment::query()->firstOrFail();

    expect($assignment->user_id)->toBe($operator->id)
        ->and($assignment->organization_id)->toBe($organization->id)
        ->and($assignment->reason)->toBe('support coverage')
        ->and($assignment->notes)->toBe('launch coverage')
        ->and($assignment->revoked_at)->toBeNull();

    $this->actingAs($actor)
        ->from(route('org.show', $organization))
        ->delete(route('org.staff-operator-assignments.destroy', [$organization, $assignment]), [
            'reason' => ' rotating coverage ',
            'notes' => ' reassigned ',
        ])
        ->assertRedirect(route('org.show', $organization))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Operator assignment revoked.');

    expect($assignment->fresh()?->revoked_by_user_id)->toBe($actor->id)
        ->and($assignment->fresh()?->revoked_at)->not->toBeNull();
})->with([
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('forbids operators and non-staff users from granting operator assignments', function (?MembershipRole $actorRole) {
    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $organization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $actor = User::factory()->create();
    $targetUser = User::factory()->create();

    if ($actorRole instanceof MembershipRole) {
        Membership::factory()
            ->for($internal)
            ->for($actor, 'user')
            ->role($actorRole)
            ->create();
    }

    Membership::factory()
        ->for($internal)
        ->for($targetUser, 'user')
        ->member()
        ->create();

    $this->actingAs($actor)
        ->from(route('org.show', $organization))
        ->post(route('org.staff-operator-assignments.store', $organization), [
            'user_id' => $targetUser->id,
        ])
        ->assertForbidden();

    expect(StaffOperatorAssignment::query()->count())->toBe(0);
})->with([
    'operator' => [MembershipRole::MEMBER],
    'non-staff' => [null],
]);

it('rejects revoking assignments across organizations', function () {
    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $organization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $otherOrganization = Organization::factory()->create([
        'name' => 'Other Client Org',
        'slug' => 'other-client-org',
        'is_internal' => false,
    ]);

    $owner = User::factory()->create();
    $operator = User::factory()->create();

    Membership::factory()
        ->for($internal)
        ->for($owner, 'user')
        ->owner()
        ->create();

    Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $assignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($otherOrganization, 'organization')
        ->create([
            'granted_by_user_id' => $owner->id,
        ]);

    $this->actingAs($owner)
        ->delete(route('org.staff-operator-assignments.destroy', [$organization, $assignment]))
        ->assertNotFound();

    expect($assignment->fresh()?->revoked_at)->toBeNull()
        ->and($assignment->fresh()?->revoked_by_user_id)->toBeNull();
});
