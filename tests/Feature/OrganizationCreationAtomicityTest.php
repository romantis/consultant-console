<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Organizations\MembershipRoleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('does not attach the staff creator as a client owner during organization creation', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->post(route('org.store'), [
            'name' => 'Acme Inc',
            'slug' => 'acme-inc',
        ])
        ->assertRedirect();

    $organization = Organization::where('slug', 'acme-inc')->firstOrFail();

    expect(Membership::where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->exists())->toBeFalse();
});

it('rolls back organization creation when syncing a provided member fails', function () {
    $user = User::factory()->create();
    $additionalUser = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $roleManager = Mockery::mock(MembershipRoleManager::class);
    $roleManager->shouldReceive('syncRole')->once()->andThrow(new Exception('sync failure'));
    app()->instance(MembershipRoleManager::class, $roleManager);

    $this->withoutExceptionHandling();

    try {
        expect(fn () => $this->actingAs($user)->post(route('org.store'), [
            'name' => 'Beta Org',
            'slug' => 'beta-org',
            'members' => [
                [
                    'email' => $additionalUser->email,
                    'role' => MembershipRole::ADMIN->value,
                ],
            ],
        ]))->toThrow(Exception::class);
    } finally {
        app()->forgetInstance(MembershipRoleManager::class);
    }

    expect(Organization::where('slug', 'beta-org')->exists())->toBeFalse();
    expect(Membership::whereHas('organization', fn ($query) => $query->where('slug', 'beta-org'))->exists())->toBeFalse();
});

it('rolls back organization creation when pending membership creation fails', function () {
    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->withoutExceptionHandling();

    Event::listen('eloquent.creating: '.PendingMembership::class, function () {
        throw new Exception('pending failure');
    });

    try {
        expect(fn () => $this->actingAs($user)->post(route('org.store'), [
            'name' => 'Gamma Org',
            'slug' => 'gamma-org',
            'members' => [
                [
                    'email' => 'new-person@example.com',
                    'name' => 'New Person',
                    'role' => MembershipRole::MEMBER->value,
                ],
            ],
        ]))->toThrow(Exception::class);
    } finally {
        Event::forget('eloquent.creating: '.PendingMembership::class);
    }

    expect(Organization::where('slug', 'gamma-org')->exists())->toBeFalse();
    expect(PendingMembership::where('email', 'new-person@example.com')->exists())->toBeFalse();
    expect(Membership::whereHas('organization', fn ($query) => $query->where('slug', 'gamma-org'))->exists())->toBeFalse();
});
