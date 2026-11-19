<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;

beforeEach(function () {
    config()->set('console.internal_org_slug', 'metric-harmony');
});

it('revokes active operator assignments when a canonical internal membership is demoted away from operator', function () {
    $internal = makeInternalOrg();
    $actor = User::factory()->create();
    $operator = User::factory()->create();
    $firstOrganization = Organization::factory()->create([
        'is_internal' => false,
    ]);
    $secondOrganization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->owner()
        ->create();

    $membership = Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $firstAssignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($firstOrganization, 'organization')
        ->create([
            'granted_by_user_id' => $actor->id,
        ]);

    $secondAssignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($secondOrganization, 'organization')
        ->create([
            'granted_by_user_id' => $actor->id,
        ]);

    $this->actingAs($actor)
        ->put(route('org.members.update', [$internal, $membership]), [
            'role' => MembershipRole::ADMIN->value,
        ])
        ->assertSessionHasNoErrors();

    expect($firstAssignment->fresh()?->revoked_at)->not->toBeNull()
        ->and($secondAssignment->fresh()?->revoked_at)->not->toBeNull()
        ->and(StaffOperatorAssignment::query()->active()->forUser($operator)->count())->toBe(0);
});

it('revokes active operator assignments when a canonical internal membership is deleted', function () {
    $internal = makeInternalOrg();
    $actor = User::factory()->create();
    $operator = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->owner()
        ->create();

    $membership = Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $assignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($organization, 'organization')
        ->create([
            'granted_by_user_id' => $actor->id,
        ]);

    $this->actingAs($actor)
        ->delete(route('org.members.destroy', [$internal, $membership]))
        ->assertSessionHasNoErrors();

    expect($assignment->fresh()?->revoked_at)->not->toBeNull()
        ->and(StaffOperatorAssignment::query()->active()->forUser($operator)->count())->toBe(0);
});

it('revokes active operator assignments when settings bulk update changes a canonical internal membership away from operator', function () {
    $internal = makeInternalOrg();
    $actor = User::factory()->create();
    $operator = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->owner()
        ->create();

    $membership = Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $assignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($organization, 'organization')
        ->create([
            'granted_by_user_id' => $actor->id,
        ]);

    $this->actingAs($actor)
        ->put(route('org.settings.update', $internal), [
            'name' => $internal->name,
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'update' => [
                    [
                        'membership_id' => (string) $membership->id,
                        'role' => MembershipRole::ADMIN->value,
                    ],
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($assignment->fresh()?->revoked_at)->not->toBeNull()
        ->and(StaffOperatorAssignment::query()->active()->forUser($operator)->count())->toBe(0);
});

it('revokes active operator assignments when settings bulk update deletes a canonical internal membership', function () {
    $internal = makeInternalOrg();
    $actor = User::factory()->create();
    $operator = User::factory()->create();
    $organization = Organization::factory()->create([
        'is_internal' => false,
    ]);

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->owner()
        ->create();

    $membership = Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $assignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($organization, 'organization')
        ->create([
            'granted_by_user_id' => $actor->id,
        ]);

    $this->actingAs($actor)
        ->put(route('org.settings.update', $internal), [
            'name' => $internal->name,
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
            'memberships' => [
                'delete' => [(string) $membership->id],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect($assignment->fresh()?->revoked_at)->not->toBeNull()
        ->and($membership->fresh())->toBeNull()
        ->and(StaffOperatorAssignment::query()->active()->forUser($operator)->count())->toBe(0);
});
