<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\OrgMetric;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class OrgMetricPolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::isInternalStaff($user) || $user->memberships()->exists();
    }

    public function view(User $user, OrgMetric $orgMetric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, (string) $orgMetric->organization_id)) {
            return true;
        }

        return ImpersonationGuard::resolve($user, $orgMetric->organization_id)
            ->allowsAtLeast(MembershipRole::VIEWER);
    }

    public function update(User $user, OrgMetric $orgMetric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, (string) $orgMetric->organization_id)) {
            return true;
        }

        $context = ImpersonationGuard::resolve($user, $orgMetric->organization_id);

        if (! $context->allowsAtLeast(MembershipRole::MEMBER)) {
            return false;
        }

        if ($context->allowsAtLeast(MembershipRole::ADMIN)) {
            return true;
        }

        return $orgMetric->participants()
            ->where('responsibility', 'owner')
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function manageParticipants(User $user, OrgMetric $orgMetric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, (string) $orgMetric->organization_id)) {
            return true;
        }

        return ImpersonationGuard::resolve($user, $orgMetric->organization_id)
            ->allowsAtLeast(MembershipRole::ADMIN);
    }

    public function updateSignals(User $user, OrgMetric $orgMetric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        return Staff::canOperateAssignedDelivery($user, (string) $orgMetric->organization_id);
    }
}
