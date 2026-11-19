<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\FinalDefinition;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class FinalDefinitionPolicy
{
    public function view(User $user, FinalDefinition $finalDefinition): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $finalDefinition->metric->organization_id)) {
            return true;
        }

        return $this->roleAtLeast($user, $finalDefinition->metric->organization_id, MembershipRole::VIEWER);
    }

    public function update(User $user, FinalDefinition $finalDefinition): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $finalDefinition->metric->organization_id)) {
            return true;
        }

        if (
            $this->isMetricOwner($user, $finalDefinition)
            && $this->roleAtLeast($user, $finalDefinition->metric->organization_id, MembershipRole::MEMBER)
        ) {
            return true;
        }

        return $this->roleAtLeast($user, $finalDefinition->metric->organization_id, MembershipRole::ADMIN);
    }

    protected function roleAtLeast(User $user, string $orgId, MembershipRole $min): bool
    {
        return ImpersonationGuard::resolve($user, $orgId)->allowsAtLeast($min);
    }

    protected function isMetricOwner(User $user, FinalDefinition $finalDefinition): bool
    {
        $ownerUserId = $finalDefinition->ownerParticipant?->user_id;

        if (! $ownerUserId) {
            return false;
        }

        return (string) $ownerUserId === (string) $user->getKey();
    }
}
