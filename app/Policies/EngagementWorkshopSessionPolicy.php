<?php

namespace App\Policies;

use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Support\Facades\Gate;

class EngagementWorkshopSessionPolicy
{
    public function view(User $user, EngagementWorkshopSession $session): bool
    {
        return Gate::forUser($user)->allows('view', $session->engagement);
    }

    public function create(User $user, Engagement $engagement): bool
    {
        return $this->canManage($user, $engagement->organization_id);
    }

    public function update(User $user, EngagementWorkshopSession $session): bool
    {
        return $this->canManage($user, $session->organization_id);
    }

    public function start(User $user, EngagementWorkshopSession $session): bool
    {
        return $this->canManage($user, $session->organization_id);
    }

    public function complete(User $user, EngagementWorkshopSession $session): bool
    {
        return $this->canManage($user, $session->organization_id);
    }

    public function cancel(User $user, EngagementWorkshopSession $session): bool
    {
        return $this->canManage($user, $session->organization_id);
    }

    private function canManage(User $user, string $organizationId): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        return Staff::canOperateAssignedDelivery($user, $organizationId);
    }
}
