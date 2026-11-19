<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Discrepancy;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class DiscrepancyPolicy
{
    public function view(User $user, Discrepancy $discrepancy): bool
    {
        return $this->roleAtLeast($user, $discrepancy->metric->organization_id, MembershipRole::VIEWER);
    }

    public function resolve(User $user, Discrepancy $discrepancy): bool
    {
        $metric = $discrepancy->metric;
        if (! $metric) {
            return false;
        }

        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        return Staff::canOperateAssignedDelivery($user, (string) $metric->organization_id);
    }

    protected function roleAtLeast(User $user, string $orgId, MembershipRole $min): bool
    {
        return ImpersonationGuard::resolve($user, $orgId)->allowsAtLeast($min);
    }
}
