<?php

namespace Tests\Concerns;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Support\Impersonation;

trait InteractsWithImpersonation
{
    /**
     * Build a session payload representing an active impersonation context.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function impersonationSessionPayload(
        Organization|string $organization,
        ?MembershipRole $role = null,
        array $overrides = []
    ): array {
        $organizationId = $organization instanceof Organization
            ? (string) $organization->getKey()
            : (string) $organization;

        $session = [
            Impersonation::SESSION_KEY => $organizationId,
        ];

        if ($role) {
            $session[Impersonation::SESSION_ROLE_KEY] = $role->value;
        }

        return array_merge($session, $overrides);
    }

    /**
     * Apply the impersonation session payload to the current test session.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function withImpersonationSession(
        Organization|string $organization,
        ?MembershipRole $role = null,
        array $overrides = []
    ): static {
        $this->withSession(
            $this->impersonationSessionPayload($organization, $role, $overrides),
        );

        return $this;
    }
}
