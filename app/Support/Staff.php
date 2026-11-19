<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;

class Staff
{
    public static function canonicalInternalOrganization(): ?Organization
    {
        $slug = trim((string) config('console.internal_org_slug'));

        if ($slug !== '') {
            $configuredOrganization = Organization::query()
                ->select('id', 'name', 'slug', 'is_internal')
                ->where('slug', $slug)
                ->where('is_internal', true)
                ->first();

            if ($configuredOrganization) {
                return $configuredOrganization;
            }
        }

        $internalOrganizations = Organization::query()
            ->select('id', 'name', 'slug', 'is_internal')
            ->where('is_internal', true)
            ->limit(2)
            ->get();

        if ($internalOrganizations->count() !== 1) {
            return null;
        }

        return $internalOrganizations->first();
    }

    public static function staffRole(User $user): ?StaffRole
    {
        $membershipRole = self::internalMembershipRole($user);

        if (! $membershipRole) {
            return null;
        }

        return StaffRole::fromInternalMembership($membershipRole);
    }

    public static function isInternalStaff(User $user): bool
    {
        return static::staffRole($user) !== null;
    }

    public static function isStaff(User $user): bool
    {
        return static::canOperateDelivery($user);
    }

    public static function internalOrganizationFor(User $user): ?Organization
    {
        $internalOrganization = static::canonicalInternalOrganization();

        if (! $internalOrganization || ! static::canOperateDelivery($user)) {
            return null;
        }

        return $internalOrganization;
    }

    public static function isStaffOwner(User $user): bool
    {
        return static::staffRole($user) === StaffRole::OWNER;
    }

    public static function isStaffAdmin(User $user): bool
    {
        return static::staffRole($user) === StaffRole::ADMIN;
    }

    public static function isStaffOperator(User $user): bool
    {
        return static::staffRole($user) === StaffRole::OPERATOR;
    }

    public static function canOperateDelivery(User $user): bool
    {
        return static::staffRole($user)?->canOperateDelivery() ?? false;
    }

    public static function hasOperatorAssignments(User $user, ?StaffRole $staffRole = null): bool
    {
        $staffRole ??= static::staffRole($user);

        if ($staffRole !== StaffRole::OPERATOR) {
            return false;
        }

        return StaffOperatorAssignment::query()
            ->active()
            ->forUser($user)
            ->exists();
    }

    public static function canGovernInternal(User $user): bool
    {
        return static::staffRole($user)?->canGovernInternal() ?? false;
    }

    public static function canImpersonate(User $user): bool
    {
        return static::staffRole($user)?->canImpersonate() ?? false;
    }

    public static function canOperateAssignedDelivery(User $user, Organization|string $organization): bool
    {
        $organization = self::resolveOrganization($organization);
        $staffRole = static::staffRole($user);

        if (! $organization || $organization->is_internal) {
            return false;
        }

        if ($staffRole?->canOperateDelivery()) {
            return true;
        }

        if ($staffRole !== StaffRole::OPERATOR) {
            return false;
        }

        return self::hasActiveOperatorAssignment($user, $organization);
    }

    private static function internalMembershipRole(User $user): ?MembershipRole
    {
        $internalOrganization = static::canonicalInternalOrganization();

        if (! $internalOrganization) {
            return null;
        }

        $role = $user->memberships()
            ->where('organization_id', $internalOrganization->id)
            ->value('role');

        if ($role instanceof MembershipRole) {
            return $role;
        }

        if (! is_string($role)) {
            return null;
        }

        return MembershipRole::tryFrom($role);
    }

    private static function resolveOrganization(Organization|string $organization): ?Organization
    {
        if ($organization instanceof Organization) {
            return $organization;
        }

        return Organization::query()
            ->select('id', 'is_internal')
            ->find($organization);
    }

    private static function hasActiveOperatorAssignment(User $user, Organization $organization): bool
    {
        return StaffOperatorAssignment::query()
            ->active()
            ->forUser($user)
            ->forOrganization($organization)
            ->exists();
    }
}
