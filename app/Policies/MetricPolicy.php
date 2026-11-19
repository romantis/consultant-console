<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Metric;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class MetricPolicy
{
    public function viewAny(User $user): bool
    {
        return Staff::isInternalStaff($user)
            || $user->memberships()->exists()
            || $user->metricParticipants()->exists();
    }

    public function view(User $user, Metric $metric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $metric->organization_id)) {
            return true;
        }

        $context = ImpersonationGuard::resolve($user, $metric->organization_id);

        if (! $context->allowsAtLeast(MembershipRole::VIEWER)) {
            return false;
        }

        if ($context->allowsAtLeast(MembershipRole::ADMIN)) {
            return true;
        }

        return $metric->participants()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Metric $metric): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $metric->organization_id)) {
            return true;
        }

        return $this->roleAtLeast($user, $metric->organization_id, MembershipRole::ADMIN);
    }

    public function review(User $user, Metric $metric): bool
    {
        return $this->update($user, $metric);
    }

    public function signOff(User $user, Metric $metric): bool
    {
        $managerOverrideEnabled = (bool) config(
            'metric-harmony.features.internal_first_manager_signoff_override',
            false
        );

        if ($managerOverrideEnabled) {
            if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
                return true;
            }

            if (Staff::canOperateAssignedDelivery($user, $metric->organization_id)) {
                return true;
            }
        }

        $context = ImpersonationGuard::resolve($user, $metric->organization_id);

        if (! $context->allowsAtLeast(MembershipRole::MEMBER)) {
            return false;
        }

        $isApprover = $metric->participants()
            ->where('responsibility', 'approver')
            ->where('user_id', $user->getKey())
            ->exists();

        if ($isApprover) {
            return true;
        }

        return $managerOverrideEnabled && $context->allowsAtLeast(MembershipRole::ADMIN);
    }

    public function requestChange(User $user, Metric $metric): bool
    {
        if (! $this->metricIsFinalized($metric)) {
            return false;
        }

        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $metric->organization_id)) {
            return true;
        }

        return $this->roleAtLeast($user, $metric->organization_id, MembershipRole::ADMIN);
    }

    protected function roleAtLeast(User $user, string $orgId, MembershipRole $min): bool
    {
        return ImpersonationGuard::resolve($user, $orgId)->allowsAtLeast($min);
    }

    private function metricIsFinalized(Metric $metric): bool
    {
        $status = $metric->status instanceof MetricStatus
            ? $metric->status
            : ($metric->status ? MetricStatus::from((string) $metric->status) : null);

        return $status === MetricStatus::FINALIZED;
    }
}
