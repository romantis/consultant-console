<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;

class OrgFinalDefinitionPolicy
{
    public function view(User $user, OrgFinalDefinition $definition): bool
    {
        if (Staff::isStaff($user)) {
            return true;
        }

        $orgMetric = $definition->orgMetric;
        if (! $orgMetric) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $orgMetric->organization_id)
            ->allowsAtLeast(MembershipRole::VIEWER);
    }

    public function update(User $user, OrgFinalDefinition $definition): bool
    {
        if (Staff::isStaff($user)) {
            return true;
        }

        $orgMetric = $definition->orgMetric;
        if (! $orgMetric) {
            return false;
        }

        return ImpersonationGuard::resolve($user, $orgMetric->organization_id)
            ->allowsAtLeast(MembershipRole::ADMIN);
    }
}
