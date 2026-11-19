<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Form;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class FormPolicy
{
    public function view(User $user, Form $form): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $form->organization_id)) {
            return true;
        }

        $context = ImpersonationGuard::resolve($user, $form->organization_id);

        if ($context->impersonating()) {
            return $context->allowsAtLeast(MembershipRole::VIEWER);
        }

        if ($context->allowsAtLeast(MembershipRole::ADMIN)) {
            return true;
        }

        return $form->assignments()
            ->where('user_id', $user->id)
            ->exists()
            || ($form->metric_id && $user->metricParticipants()->where('metric_id', $form->metric_id)->exists());
    }

    public function update(User $user, Form $form): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, $form->organization_id)) {
            return true;
        }

        return ImpersonationGuard::resolve($user, $form->organization_id)
            ->allowsAtLeast(MembershipRole::ADMIN);
    }
}
