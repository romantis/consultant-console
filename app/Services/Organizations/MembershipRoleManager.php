<?php

namespace App\Services\Organizations;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipRoleManager
{
    public function __construct(private MembershipAuditLogger $membershipAuditLogger) {}

    public function syncRole(
        Membership $membership,
        MembershipRole $targetRole,
        User $actor,
        bool $force = false
    ): void {
        $currentRole = $membership->role instanceof MembershipRole
            ? $membership->role
            : MembershipRole::from($membership->role);

        if (! $force && $currentRole === $targetRole) {
            return;
        }

        DB::transaction(function () use ($membership, $targetRole, $actor) {
            $this->guardOwnerPromotion($membership, $targetRole, $actor);
            $this->guardLastOwnerDemotion($membership, $targetRole);

            if ($targetRole === MembershipRole::OWNER) {
                $this->replaceOrganizationOwner($membership, $actor);

                return;
            }

            $membership->update(['role' => $targetRole->value]);
        });

        $membership->refresh();

        if ($currentRole !== $targetRole) {
            $this->membershipAuditLogger->membershipRoleChanged($membership, $actor, $currentRole, $targetRole);
        }
    }

    private function guardOwnerPromotion(Membership $membership, MembershipRole $targetRole, User $actor): void
    {
        if ($targetRole !== MembershipRole::OWNER) {
            return;
        }

        $actorIsOwner = Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', $actor->id)
            ->where('role', MembershipRole::OWNER->value)
            ->exists();

        if (! $actorIsOwner) {
            throw ValidationException::withMessages([
                'role' => 'Only organization owners can assign the owner role.',
            ]);
        }
    }

    private function guardLastOwnerDemotion(Membership $membership, MembershipRole $targetRole): void
    {
        if ($membership->role !== MembershipRole::OWNER || $targetRole === MembershipRole::OWNER) {
            return;
        }

        $otherOwnerExists = Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('role', MembershipRole::OWNER->value)
            ->whereKeyNot($membership->id)
            ->exists();

        if (! $otherOwnerExists) {
            throw ValidationException::withMessages([
                'role' => 'The organization requires at least one owner.',
            ]);
        }
    }

    private function replaceOrganizationOwner(Membership $membership, User $actor): void
    {
        $otherOwners = Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('role', MembershipRole::OWNER->value)
            ->whereKeyNot($membership->id)
            ->get();

        foreach ($otherOwners as $otherOwner) {
            $otherOwner->update(['role' => MembershipRole::ADMIN->value]);
            $this->membershipAuditLogger->membershipRoleChanged(
                $otherOwner->fresh(),
                $actor,
                MembershipRole::OWNER,
                MembershipRole::ADMIN,
            );
        }

        $membership->update(['role' => MembershipRole::OWNER->value]);
    }
}
