<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Organization;
use App\Models\User;
use App\Support\Staff;

class EngagementPolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::canOperateDelivery($user) || Staff::hasOperatorAssignments($user);
    }

    public function view(User $user, Engagement $engagement): bool
    {
        return $this->canOperateEngagement($user, $engagement, MembershipRole::VIEWER);
    }

    public function create(User $user): bool
    {
        return Staff::canOperateDelivery($user);
    }

    public function launch(User $user): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Engagement $engagement): bool
    {
        return $this->canOperateEngagement($user, $engagement, MembershipRole::ADMIN);
    }

    public function delete(User $user, Engagement $engagement): bool
    {
        /** @var Organization|null $organization */
        $organization = $engagement->organization()->first();

        if ($organization?->is_internal) {
            return false;
        }

        return Staff::isStaffOwner($user);
    }

    public function restore(User $user, Engagement $engagement): bool
    {
        return $this->delete($user, $engagement);
    }

    public function forceDelete(User $user, Engagement $engagement): bool
    {
        return $this->delete($user, $engagement);
    }

    private function canOperateEngagement(User $user, Engagement $engagement, MembershipRole $minimumRole): bool
    {
        /** @var Organization|null $organization */
        $organization = $engagement->organization()->first();

        if ($organization?->is_internal) {
            return false;
        }

        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        return $minimumRole !== MembershipRole::OWNER
            && Staff::canOperateAssignedDelivery($user, $engagement->organization_id);
    }
}
