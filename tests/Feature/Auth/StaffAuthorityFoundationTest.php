<?php

use App\Enums\MembershipRole;
use App\Enums\StaffRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;

it('resolves staff roles from the configured canonical internal organization', function (
    MembershipRole $membershipRole,
    StaffRole $expectedRole,
    bool $canOperateDelivery,
    bool $canGovernInternal,
    bool $canImpersonate,
) {
    config()->set('console.internal_org_slug', 'consultant-hq');

    $legacyInternal = Organization::factory()->create([
        'name' => 'Legacy Internal',
        'slug' => 'legacy-internal',
        'is_internal' => true,
    ]);

    $canonicalInternal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($legacyInternal)
        ->for($user, 'user')
        ->owner()
        ->create();

    Membership::factory()
        ->for($canonicalInternal)
        ->for($user, 'user')
        ->role($membershipRole)
        ->create();

    expect(Staff::canonicalInternalOrganization()?->is($canonicalInternal))->toBeTrue()
        ->and(Staff::staffRole($user))->toBe($expectedRole)
        ->and(Staff::isInternalStaff($user))->toBeTrue()
        ->and(Staff::isStaffOwner($user))->toBe($expectedRole === StaffRole::OWNER)
        ->and(Staff::isStaffAdmin($user))->toBe($expectedRole === StaffRole::ADMIN)
        ->and(Staff::isStaffOperator($user))->toBe($expectedRole === StaffRole::OPERATOR)
        ->and(Staff::canOperateDelivery($user))->toBe($canOperateDelivery)
        ->and(Staff::canGovernInternal($user))->toBe($canGovernInternal)
        ->and(Staff::canImpersonate($user))->toBe($canImpersonate);
})->with([
    'owner' => [MembershipRole::OWNER, StaffRole::OWNER, true, true, true],
    'admin' => [MembershipRole::ADMIN, StaffRole::ADMIN, true, false, true],
    'operator' => [MembershipRole::MEMBER, StaffRole::OPERATOR, false, false, false],
]);

it('falls back to a single internal organization when the configured canonical slug is missing', function () {
    config()->set('console.internal_org_slug', 'missing-internal-org');

    $singleInternal = Organization::factory()->create([
        'name' => 'Legacy Internal',
        'slug' => 'legacy-internal',
        'is_internal' => true,
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($singleInternal)
        ->for($user, 'user')
        ->owner()
        ->create();

    expect(Staff::canonicalInternalOrganization()?->is($singleInternal))->toBeTrue()
        ->and(Staff::staffRole($user))->toBe(StaffRole::OWNER)
        ->and(Staff::isInternalStaff($user))->toBeTrue()
        ->and(Staff::isStaff($user))->toBeTrue()
        ->and(Staff::internalOrganizationFor($user)?->is($singleInternal))->toBeTrue();
});

it('fails closed when the configured canonical internal organization is missing and multiple internal orgs exist', function () {
    config()->set('console.internal_org_slug', 'missing-internal-org');

    $firstInternal = Organization::factory()->create([
        'name' => 'Legacy Internal One',
        'slug' => 'legacy-internal-one',
        'is_internal' => true,
    ]);

    $secondInternal = Organization::factory()->create([
        'name' => 'Legacy Internal Two',
        'slug' => 'legacy-internal-two',
        'is_internal' => true,
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($firstInternal)
        ->for($user, 'user')
        ->owner()
        ->create();

    Membership::factory()
        ->for($secondInternal)
        ->for($user, 'user')
        ->admin()
        ->create();

    expect(Staff::canonicalInternalOrganization())->toBeNull()
        ->and(Staff::staffRole($user))->toBeNull()
        ->and(Staff::isInternalStaff($user))->toBeFalse()
        ->and(Staff::isStaff($user))->toBeFalse()
        ->and(Staff::internalOrganizationFor($user))->toBeNull();
});

it('keeps legacy broad staff checks narrow until operator assignments exist', function () {
    config()->set('console.internal_org_slug', 'consultant-hq');

    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $clientOrganization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $operator = User::factory()->create();

    Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    expect(Staff::isInternalStaff($operator))->toBeTrue()
        ->and(Staff::isStaff($operator))->toBeFalse()
        ->and(Staff::internalOrganizationFor($operator))->toBeNull()
        ->and(Staff::canOperateAssignedDelivery($operator, $clientOrganization))->toBeFalse();
});

it('allows operators to operate assigned organizations while the assignment is active and fails closed after revoke', function () {
    config()->set('console.internal_org_slug', 'consultant-hq');

    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $clientOrganization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $operator = User::factory()->create();
    $grantor = User::factory()->create();

    Membership::factory()
        ->for($internal)
        ->for($operator, 'user')
        ->member()
        ->create();

    $assignment = StaffOperatorAssignment::factory()
        ->for($operator, 'user')
        ->for($clientOrganization, 'organization')
        ->create([
            'granted_by_user_id' => $grantor->id,
        ]);

    expect(Staff::hasOperatorAssignments($operator))->toBeTrue()
        ->and(Staff::canOperateAssignedDelivery($operator, $clientOrganization))->toBeTrue()
        ->and(Staff::canOperateAssignedDelivery($operator, $internal))->toBeFalse();

    $assignment->forceFill([
        'revoked_by_user_id' => $grantor->id,
        'revoked_at' => now(),
    ])->save();

    expect(Staff::hasOperatorAssignments($operator))->toBeFalse()
        ->and(Staff::canOperateAssignedDelivery($operator, $clientOrganization))->toBeFalse();
});

it('lets owner and admin roles operate non-internal orgs through the transitional assigned-delivery helper', function (
    MembershipRole $membershipRole,
) {
    config()->set('console.internal_org_slug', 'consultant-hq');

    $internal = Organization::factory()->create([
        'name' => 'Consultant HQ',
        'slug' => 'consultant-hq',
        'is_internal' => true,
    ]);

    $clientOrganization = Organization::factory()->create([
        'name' => 'Client Org',
        'slug' => 'client-org',
        'is_internal' => false,
    ]);

    $user = User::factory()->create();

    Membership::factory()
        ->for($internal)
        ->for($user, 'user')
        ->role($membershipRole)
        ->create();

    expect(Staff::internalOrganizationFor($user)?->is($internal))->toBeTrue()
        ->and(Staff::canOperateAssignedDelivery($user, $clientOrganization))->toBeTrue()
        ->and(Staff::canOperateAssignedDelivery($user, $internal))->toBeFalse();
})->with([
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);
