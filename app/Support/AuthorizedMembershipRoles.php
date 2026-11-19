<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;

class AuthorizedMembershipRoles
{
    /**
     * @return MembershipRole[]
     */
    public static function forUser(User $user, Organization $organization): array
    {
        $context = ImpersonationGuard::resolve($user, $organization);
        $role = $context->role();

        if ($context->impersonating()) {
            return self::forMembershipRole($role);
        }

        if (! $organization->is_internal) {
            if (Staff::isStaffOwner($user)) {
                return self::forMembershipRole(MembershipRole::OWNER);
            }

            if (Staff::isStaffAdmin($user)) {
                return self::forMembershipRole(MembershipRole::ADMIN);
            }
        }

        return self::forMembershipRole($role);
    }

    /**
     * @return MembershipRole[]
     */
    public static function forClientOrganizationCreation(User $user): array
    {
        if (Staff::isStaffOwner($user)) {
            return self::forMembershipRole(MembershipRole::OWNER);
        }

        if (Staff::isStaffAdmin($user)) {
            return self::forMembershipRole(MembershipRole::ADMIN);
        }

        return [];
    }

    /**
     * @return MembershipRole[]
     */
    private static function forMembershipRole(?MembershipRole $role): array
    {
        return match ($role) {
            MembershipRole::OWNER => [
                MembershipRole::OWNER,
                MembershipRole::ADMIN,
                MembershipRole::MEMBER,
                MembershipRole::VIEWER,
            ],
            MembershipRole::ADMIN => [
                MembershipRole::ADMIN,
                MembershipRole::MEMBER,
                MembershipRole::VIEWER,
            ],
            default => [],
        };
    }

    /**
     * @param  MembershipRole[]  $roles
     * @return array<int, array{value: string, label: string}>
     */
    public static function toOptions(array $roles): array
    {
        return array_map(
            fn (MembershipRole $role) => [
                'value' => $role->value,
                'label' => ucfirst($role->value),
            ],
            $roles,
        );
    }

    /**
     * @param  MembershipRole[]  $roles
     * @return string[]
     */
    public static function toValues(array $roles): array
    {
        return array_map(fn (MembershipRole $role) => $role->value, $roles);
    }
}
