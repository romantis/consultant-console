<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class OrganizationPolicy
{
    public function view(User $user, Organization $org): bool
    {
        $guard = ImpersonationGuard::resolve($user, $org);

        if ($guard->impersonating()) {
            return $guard->allowsAtLeast(MembershipRole::VIEWER);
        }

        if (Staff::canOperateAssignedDelivery($user, $org)) {
            return true;
        }

        return $guard->allowsAtLeast(MembershipRole::VIEWER);
    }

    public function create(User $user): bool
    {
        // Only internal staff (via Internal Org) can create client orgs
        return Staff::canImpersonate($user);
    }

    public function update(User $user, Organization $org): bool
    {
        $guard = ImpersonationGuard::resolve($user, $org);
        $minimumRole = MembershipRole::ADMIN;

        if ($guard->impersonating()) {
            return $guard->allowsAtLeast($minimumRole);
        }

        if ($org->is_internal) {
            $canonicalOrganization = Staff::canonicalInternalOrganization();

            return $canonicalOrganization !== null
                && $canonicalOrganization->is($org)
                && Staff::canGovernInternal($user);
        }

        if (Staff::canOperateDelivery($user) && ! $org->is_internal) {
            return true;
        }

        return $guard->allowsAtLeast($minimumRole);
    }

    public function delete(User $user, Organization $org): bool
    {
        $guard = ImpersonationGuard::resolve($user, $org);

        if ($guard->impersonating()) {
            return $guard->allowsAtLeast(MembershipRole::OWNER);
        }

        if (Staff::isStaffOwner($user) && ! $org->is_internal) {
            return true;
        }

        return $guard->allowsAtLeast(MembershipRole::OWNER);
    }

    public function impersonate(User $user, Organization $org): bool
    {
        return Staff::canImpersonate($user) && ! $org->is_internal;
    }

    public function impersonateAny(User $user): bool
    {
        return Staff::canImpersonate($user);
    }

    public function roleAtLeast(User $user, Organization $org, MembershipRole|string $min): bool
    {
        $guard = ImpersonationGuard::resolve($user, $org);
        $minimumRole = $min instanceof MembershipRole ? $min : MembershipRole::from($min);

        if ($guard->impersonating()) {
            return $guard->allowsAtLeast($minimumRole);
        }

        if (Staff::isStaffOwner($user) && ! $org->is_internal) {
            return true;
        }

        if (Staff::isStaffAdmin($user) && ! $org->is_internal) {
            return $minimumRole !== MembershipRole::OWNER;
        }

        return $guard->allowsAtLeast($minimumRole);
    }
}
