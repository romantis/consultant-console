<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\ChangeProposal;
use App\Models\MetricParticipant;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class ChangeProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::isInternalStaff($user) || $user->memberships()->exists();
    }

    public function view(User $user, ChangeProposal $proposal): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $proposal->organization_id)) {
            return true;
        }

        return $this->hasOrgMembership($user, $proposal) || $this->isAssignedApprover($user, $proposal);
    }

    public function decide(User $user, ChangeProposal $proposal): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $proposal->organization_id)) {
            return true;
        }

        return $this->hasOrgPrivileges($user, $proposal) || $this->isAssignedApprover($user, $proposal);
    }

    public function merge(User $user, ChangeProposal $proposal): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $proposal->organization_id)) {
            return true;
        }

        return $this->hasOrgPrivileges($user, $proposal);
    }

    private function hasOrgMembership(User $user, ChangeProposal $proposal): bool
    {
        if (! $proposal->organization_id) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $proposal->organization_id)
            ->allowsAtLeast(MembershipRole::VIEWER);
    }

    private function hasOrgPrivileges(User $user, ChangeProposal $proposal): bool
    {
        if (! $proposal->organization_id) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $proposal->organization_id)
            ->allowsAtLeast(MembershipRole::ADMIN);
    }

    private function isAssignedApprover(User $user, ChangeProposal $proposal): bool
    {
        $approverIds = collect($proposal->approver_ids ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => $id !== '')
            ->values();

        if ($approverIds->isEmpty()) {
            return false;
        }

        return MetricParticipant::query()
            ->whereIn('id', $approverIds->all())
            ->where('user_id', $user->getKey())
            ->where('responsibility', 'approver')
            ->exists();
    }
}
