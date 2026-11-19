<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ImpersonationGuard;

class AuditLogPolicy
{
    public function view(User $user, AuditLog $log): bool
    {
        return $this->roleAtLeast($user, $log->organization_id, MembershipRole::ADMIN);
    }

    protected function roleAtLeast(User $user, string $orgId, MembershipRole $min): bool
    {
        return ImpersonationGuard::resolve($user, $orgId)->allowsAtLeast($min);
    }
}
