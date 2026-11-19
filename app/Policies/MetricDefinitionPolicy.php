<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\MetricDefinition;
use App\Models\User;
use App\Support\ImpersonationGuard;

class MetricDefinitionPolicy
{
    public function view(User $user, MetricDefinition $definition): bool
    {
        return $this->roleAtLeast($user, $definition->metric->organization_id, MembershipRole::VIEWER);
    }

    public function update(User $user, MetricDefinition $definition): bool
    {
        return $this->roleAtLeast($user, $definition->metric->organization_id, MembershipRole::ADMIN);
    }

    protected function roleAtLeast(User $user, string $orgId, MembershipRole $min): bool
    {
        return ImpersonationGuard::resolve($user, $orgId)->allowsAtLeast($min);
    }
}
