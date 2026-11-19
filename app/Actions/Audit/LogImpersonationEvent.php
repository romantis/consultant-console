<?php

namespace App\Actions\Audit;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class LogImpersonationEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Organization $organization,
        string $action,
        ?User $actor = null,
        array $properties = [],
        ?MembershipRole $impersonatedRole = null,
        ?string $result = null
    ): AuditLog {
        $resolvedProperties = array_merge($properties, [
            'impersonated_role' => $impersonatedRole?->value,
            'result' => $result,
        ]);

        return AuditLog::create([
            'organization_id' => $organization->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => Organization::class,
            'subject_id' => $organization->getKey(),
            'properties' => array_filter(
                array_merge([
                    'impersonated_organization_id' => (string) $organization->getKey(),
                    'organization_slug' => $organization->slug,
                    'organization_name' => $organization->name,
                ], $resolvedProperties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }

    public function started(
        Organization $organization,
        ?User $actor = null,
        ?string $justification = null,
        ?MembershipRole $role = null
    ): AuditLog {
        return $this->handle(
            $organization,
            AuditLog::ACTION_IMPERSONATION_STARTED,
            $actor,
            [
                'justification' => $justification,
            ],
            $role,
            AuditLog::RESULT_STARTED,
        );
    }

    public function stopped(
        Organization $organization,
        ?User $actor = null,
        ?string $justification = null,
        ?MembershipRole $role = null
    ): AuditLog {
        return $this->handle(
            $organization,
            AuditLog::ACTION_IMPERSONATION_STOPPED,
            $actor,
            [
                'justification' => $justification,
            ],
            $role,
            AuditLog::RESULT_STOPPED,
        );
    }

    public function roleChanged(
        Organization $organization,
        ?User $actor = null,
        ?string $justification = null,
        ?MembershipRole $role = null
    ): AuditLog {
        return $this->handle(
            $organization,
            AuditLog::ACTION_IMPERSONATION_ROLE_CHANGED,
            $actor,
            [
                'justification' => $justification,
            ],
            $role,
            AuditLog::RESULT_ROLE_CHANGED,
        );
    }

    public function denied(
        Organization $organization,
        ?User $actor = null,
        ?string $justification = null,
        ?string $attemptedRole = null
    ): AuditLog {
        $impersonatedRole = $attemptedRole ? MembershipRole::tryFrom($attemptedRole) : null;

        return $this->handle(
            $organization,
            AuditLog::ACTION_IMPERSONATION_DENIED,
            $actor,
            [
                'justification' => $justification,
                'attempted_role' => $attemptedRole,
            ],
            $impersonatedRole,
            AuditLog::RESULT_DENIED,
        );
    }
}
