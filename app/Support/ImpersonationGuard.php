<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

class ImpersonationGuard
{
    public function __construct(
        private readonly ?MembershipRole $role,
        private readonly ?Membership $membership,
        private readonly bool $impersonating,
    ) {}

    public static function resolve(
        User $user,
        Organization|string $organization,
        ?Request $request = null
    ): self {
        $organizationId = $organization instanceof Organization
            ? (string) $organization->getKey()
            : (string) $organization;

        $request ??= request();

        $impersonatedOrgId = $request?->attributes->get('impersonated_organization_id');
        $impersonatedRole = $request?->attributes->get('impersonated_role');

        if ($impersonatedOrgId && (string) $impersonatedOrgId === $organizationId) {
            $role = is_string($impersonatedRole)
                ? MembershipRole::tryFrom($impersonatedRole)
                : null;

            return new self($role, null, $role !== null);
        }

        $membership = $user->memberships()
            ->where('organization_id', $organizationId)
            ->first();

        $role = $membership?->role;

        if ($role && ! $role instanceof MembershipRole) {
            $role = MembershipRole::tryFrom((string) $role);
        }

        return new self($role, $membership, false);
    }

    public function role(): ?MembershipRole
    {
        return $this->role;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function impersonating(): bool
    {
        return $this->impersonating;
    }

    public function allowsAtLeast(MembershipRole|string $minimum): bool
    {
        $minimumRole = $minimum instanceof MembershipRole
            ? $minimum
            : MembershipRole::from($minimum);

        if (! $this->role) {
            return false;
        }

        return match ($minimumRole) {
            MembershipRole::OWNER => $this->role === MembershipRole::OWNER,
            MembershipRole::ADMIN => in_array(
                $this->role,
                [MembershipRole::OWNER, MembershipRole::ADMIN],
                true,
            ),
            MembershipRole::MEMBER => in_array(
                $this->role,
                [MembershipRole::OWNER, MembershipRole::ADMIN, MembershipRole::MEMBER],
                true,
            ),
            MembershipRole::VIEWER => true,
        };
    }
}
