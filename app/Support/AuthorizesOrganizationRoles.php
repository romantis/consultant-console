<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

trait AuthorizesOrganizationRoles
{
    protected function authorizeOrganizationRole(User $user, Organization $organization, MembershipRole $minimumRole): void
    {
        Gate::forUser($user)->authorize('roleAtLeast', [$organization, $minimumRole]);
    }

    protected function userHasOrganizationRoleAtLeast(User $user, Organization $organization, MembershipRole $minimumRole): bool
    {
        return Gate::forUser($user)->allows('roleAtLeast', [$organization, $minimumRole]);
    }
}
