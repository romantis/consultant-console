<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\HandbookExport;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class HandbookExportPolicy
{
    public function view(User $user, HandbookExport $export): bool
    {
        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, (string) $export->organization_id)) {
            return true;
        }

        return $this->hasRoleAtLeast($user, $export, MembershipRole::VIEWER);
    }

    public function download(User $user, HandbookExport $export): bool
    {
        return $this->view($user, $export);
    }

    public function publish(User $user, HandbookExport $export): bool
    {
        if (! config('metric-harmony.features.public_handbook_links', false)) {
            return false;
        }

        if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
            return true;
        }

        if (Staff::canOperateAssignedDelivery($user, (string) $export->organization_id)) {
            return true;
        }

        return $this->hasRoleAtLeast($user, $export, MembershipRole::ADMIN);
    }

    public function unpublish(User $user, HandbookExport $export): bool
    {
        return $this->publish($user, $export);
    }

    public function accessPublic(?User $user, HandbookExport $export): bool
    {
        if (! config('metric-harmony.features.public_handbook_links', false)) {
            return false;
        }

        return $export->isPublished() && $export->isReady();
    }

    protected function hasRoleAtLeast(User $user, HandbookExport $export, MembershipRole $minimum): bool
    {
        if (! $export->organization_id) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $export->organization_id)->allowsAtLeast($minimum);
    }
}
