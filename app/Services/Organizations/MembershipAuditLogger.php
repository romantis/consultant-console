<?php

namespace App\Services\Organizations;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\MembershipAuditLog;
use App\Models\User;

class MembershipAuditLogger
{
    public function membershipCreated(Membership $membership, User $actor, array $properties = []): void
    {
        $this->record('membership.created', $membership, $actor, $properties + [
            'role' => $this->resolveRoleValue($membership->role),
        ]);
    }

    public function membershipRoleChanged(Membership $membership, User $actor, MembershipRole $from, MembershipRole $to): void
    {
        if ($from === $to) {
            return;
        }

        $this->record('membership.role.updated', $membership, $actor, [
            'from' => $from->value,
            'to' => $to->value,
        ]);
    }

    public function membershipDeleted(Membership $membership, User $actor): void
    {
        $this->record('membership.deleted', $membership, $actor, [
            'role' => $this->resolveRoleValue($membership->role),
        ]);
    }

    private function record(string $action, Membership $membership, User $actor, array $properties): void
    {
        $actorMembership = $this->resolveActorMembership($membership, $actor);

        MembershipAuditLog::create([
            'organization_id' => $membership->organization_id,
            'membership_id' => $membership->id,
            'actor_membership_id' => $actorMembership?->id,
            'action' => $action,
            'properties' => array_merge([
                'actor_user_id' => $actor->id,
                'membership_user_id' => $membership->user_id,
            ], $properties),
        ]);
    }

    private function resolveActorMembership(Membership $membership, User $actor): ?Membership
    {
        if ($membership->user_id === $actor->id) {
            return $membership;
        }

        return Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', $actor->id)
            ->first();
    }

    private function resolveRoleValue(MembershipRole|string $role): string
    {
        return $role instanceof MembershipRole ? $role->value : $role;
    }
}
