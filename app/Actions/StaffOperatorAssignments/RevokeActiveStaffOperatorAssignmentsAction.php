<?php

namespace App\Actions\StaffOperatorAssignments;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;

class RevokeActiveStaffOperatorAssignmentsAction
{
    public function __construct(
        private readonly RevokeStaffOperatorAssignmentAction $revokeStaffOperatorAssignmentAction
    ) {}

    public function handle(User $targetUser, User $actor): int
    {
        $assignments = StaffOperatorAssignment::query()
            ->active()
            ->forUser($targetUser)
            ->orderBy('granted_at')
            ->get();

        $revokedCount = 0;

        foreach ($assignments as $assignment) {
            $this->revokeStaffOperatorAssignmentAction->handle($assignment, $actor);
            $revokedCount++;
        }

        return $revokedCount;
    }

    public function revokeForMembershipChange(
        Organization $organization,
        Membership $membership,
        User $actor,
        MembershipRole $currentRole,
        ?MembershipRole $newRole = null
    ): int {
        $canonicalInternalOrganization = Staff::canonicalInternalOrganization();

        if (! $canonicalInternalOrganization || ! $canonicalInternalOrganization->is($organization)) {
            return 0;
        }

        if ($currentRole !== MembershipRole::MEMBER) {
            return 0;
        }

        if ($newRole === MembershipRole::MEMBER) {
            return 0;
        }

        $targetUser = $membership->user;

        if (! $targetUser instanceof User) {
            return 0;
        }

        return $this->handle($targetUser, $actor);
    }
}
