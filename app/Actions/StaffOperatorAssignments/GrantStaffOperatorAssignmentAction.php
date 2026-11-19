<?php

namespace App\Actions\StaffOperatorAssignments;

use App\Actions\Audit\LogStaffOperatorAssignmentEvent;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrantStaffOperatorAssignmentAction
{
    public function __construct(
        private readonly LogStaffOperatorAssignmentEvent $logStaffOperatorAssignmentEvent
    ) {}

    public function handle(
        User $targetUser,
        Organization $organization,
        User $actor,
        ?string $reason = null,
        ?string $notes = null
    ): StaffOperatorAssignment {
        $this->assertActorCanGrant($actor);
        $this->assertTargetIsEligible($targetUser, $organization);

        $normalizedReason = $this->normalizeText($reason);
        $normalizedNotes = $this->normalizeText($notes);

        return DB::transaction(function () use (
            $targetUser,
            $organization,
            $actor,
            $normalizedReason,
            $normalizedNotes
        ): StaffOperatorAssignment {
            $activeAssignmentExists = StaffOperatorAssignment::query()
                ->active()
                ->forUser($targetUser)
                ->forOrganization($organization)
                ->exists();

            if ($activeAssignmentExists) {
                throw ValidationException::withMessages([
                    'user_id' => __('This operator already has an active assignment for the selected organization.'),
                ]);
            }

            $assignment = StaffOperatorAssignment::create([
                'user_id' => $targetUser->getKey(),
                'organization_id' => $organization->getKey(),
                'granted_by_user_id' => $actor->getKey(),
                'revoked_by_user_id' => null,
                'granted_at' => now(),
                'revoked_at' => null,
                'reason' => $normalizedReason,
                'notes' => $normalizedNotes,
            ]);

            $this->logStaffOperatorAssignmentEvent->granted(
                $assignment,
                $actor,
                array_filter([
                    'reason' => $normalizedReason,
                    'notes' => $normalizedNotes,
                ], static fn ($value) => $value !== null && $value !== '')
            );

            return $assignment;
        });
    }

    private function assertActorCanGrant(User $actor): void
    {
        if (! Staff::canOperateDelivery($actor)) {
            throw ValidationException::withMessages([
                'actor_id' => __('Only staff owners and staff admins can grant operator assignments.'),
            ]);
        }
    }

    private function assertTargetIsEligible(User $targetUser, Organization $organization): void
    {
        if ($organization->is_internal) {
            throw ValidationException::withMessages([
                'organization_id' => __('Operator assignments can only target non-internal organizations.'),
            ]);
        }

        if (! Staff::isStaffOperator($targetUser)) {
            throw ValidationException::withMessages([
                'user_id' => __('Only internal staff operators can receive operator assignments.'),
            ]);
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
