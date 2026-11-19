<?php

use App\Actions\StaffOperatorAssignments\GrantStaffOperatorAssignmentAction;
use App\Actions\StaffOperatorAssignments\RevokeStaffOperatorAssignmentAction;
use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Validation\ValidationException;

it('grants operator assignments for staff owners and admins and logs the audit event', function (
    MembershipRole $actorRole,
    string $expectedActorStaffRole,
) {
    config()->set('console.internal_org_slug', 'consultant-hq');

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

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->role($actorRole)
        ->create();

    Membership::factory()
        ->for($internal)
        ->for($targetUser, 'user')
        ->member()
        ->create();

    $assignment = app(GrantStaffOperatorAssignmentAction::class)->handle(
        $targetUser,
        $organization,
        $actor,
        '  support coverage  ',
        '  assigned for launch  ',
    );

    expect($assignment)
        ->organization_id->toBe($organization->id)
        ->user_id->toBe($targetUser->id)
        ->granted_by_user_id->toBe($actor->id)
        ->revoked_by_user_id->toBeNull()
        ->reason->toBe('support coverage')
        ->notes->toBe('assigned for launch');

    expect(Staff::hasOperatorAssignments($targetUser))->toBeTrue()
        ->and(Staff::canOperateAssignedDelivery($targetUser, $organization))->toBeTrue();

    $auditLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_STAFF_OPERATOR_ASSIGNMENT_GRANTED)
        ->firstOrFail();

    expect($auditLog->organization_id)->toBe($organization->id)
        ->and($auditLog->user_id)->toBe($actor->id)
        ->and($auditLog->subject_type)->toBe(StaffOperatorAssignment::class)
        ->and($auditLog->subject_id)->toBe($assignment->id)
        ->and($auditLog->properties['actor_staff_role'])->toBe($expectedActorStaffRole)
        ->and($auditLog->properties['assignment_id'])->toBe($assignment->id)
        ->and($auditLog->properties['user_id'])->toBe($targetUser->id)
        ->and($auditLog->properties['organization_id'])->toBe($organization->id)
        ->and($auditLog->properties['reason'])->toBe('support coverage')
        ->and($auditLog->properties['notes'])->toBe('assigned for launch');
})->with([
    'owner' => [MembershipRole::OWNER, 'owner'],
    'admin' => [MembershipRole::ADMIN, 'admin'],
]);

it('denies grant attempts from operators and non-staff users', function (
    ?MembershipRole $actorRole,
) {
    config()->set('console.internal_org_slug', 'consultant-hq');

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

    if ($actorRole) {
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

    expect(fn () => app(GrantStaffOperatorAssignmentAction::class)->handle(
        $targetUser,
        $organization,
        $actor,
    ))->toThrow(ValidationException::class);

    expect(StaffOperatorAssignment::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'operator' => [MembershipRole::MEMBER],
    'non-staff' => [null],
]);

it('rejects duplicate active operator assignments', function () {
    config()->set('console.internal_org_slug', 'consultant-hq');

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

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->owner()
        ->create();

    Membership::factory()
        ->for($internal)
        ->for($targetUser, 'user')
        ->member()
        ->create();

    app(GrantStaffOperatorAssignmentAction::class)->handle(
        $targetUser,
        $organization,
        $actor,
    );

    expect(fn () => app(GrantStaffOperatorAssignmentAction::class)->handle(
        $targetUser,
        $organization,
        $actor,
    ))->toThrow(ValidationException::class);

    expect(StaffOperatorAssignment::query()->active()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditLog::ACTION_STAFF_OPERATOR_ASSIGNMENT_GRANTED)->count())->toBe(1);
});

it('revokes active operator assignments and logs the audit event', function () {
    config()->set('console.internal_org_slug', 'consultant-hq');

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

    Membership::factory()
        ->for($internal)
        ->for($actor, 'user')
        ->admin()
        ->create();

    Membership::factory()
        ->for($internal)
        ->for($targetUser, 'user')
        ->member()
        ->create();

    $assignment = app(GrantStaffOperatorAssignmentAction::class)->handle(
        $targetUser,
        $organization,
        $actor,
        'launch coverage',
    );

    $revokedAssignment = app(RevokeStaffOperatorAssignmentAction::class)->handle(
        $assignment,
        $actor,
        'launch wrapped',
        '  rotated to another operator  ',
    );

    expect($revokedAssignment->revoked_at)->not()->toBeNull()
        ->and($revokedAssignment->revoked_by_user_id)->toBe($actor->id)
        ->and(Staff::hasOperatorAssignments($targetUser))->toBeFalse()
        ->and(Staff::canOperateAssignedDelivery($targetUser, $organization))->toBeFalse();

    $auditLog = AuditLog::query()
        ->where('action', AuditLog::ACTION_STAFF_OPERATOR_ASSIGNMENT_REVOKED)
        ->firstOrFail();

    expect($auditLog->organization_id)->toBe($organization->id)
        ->and($auditLog->user_id)->toBe($actor->id)
        ->and($auditLog->subject_type)->toBe(StaffOperatorAssignment::class)
        ->and($auditLog->subject_id)->toBe($assignment->id)
        ->and($auditLog->properties['actor_staff_role'])->toBe('admin')
        ->and($auditLog->properties['assignment_id'])->toBe($assignment->id)
        ->and($auditLog->properties['reason'])->toBe('launch wrapped')
        ->and($auditLog->properties['notes'])->toBe('rotated to another operator');
});
