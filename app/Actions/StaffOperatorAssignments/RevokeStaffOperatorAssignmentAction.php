<?php

namespace App\Actions\StaffOperatorAssignments;

use App\Actions\Audit\LogStaffOperatorAssignmentEvent;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeStaffOperatorAssignmentAction
{
    public function __construct(
        private readonly LogStaffOperatorAssignmentEvent $logStaffOperatorAssignmentEvent
    ) {}

    public function handle(
        StaffOperatorAssignment $assignment,
        User $actor,
        ?string $reason = null,
        ?string $notes = null
    ): StaffOperatorAssignment {
        if (! Staff::canOperateDelivery($actor)) {
            throw ValidationException::withMessages([
                'actor_id' => __('Only staff owners and staff admins can revoke operator assignments.'),
            ]);
        }

        $normalizedReason = $this->normalizeText($reason);
        $normalizedNotes = $this->normalizeText($notes);

        return DB::transaction(function () use (
            $assignment,
            $actor,
            $normalizedReason,
            $normalizedNotes
        ): StaffOperatorAssignment {
            $lockedAssignment = StaffOperatorAssignment::query()
                ->with(['organization', 'user'])
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());

            if ($lockedAssignment->revoked_at) {
                throw ValidationException::withMessages([
                    'assignment_id' => __('This operator assignment has already been revoked.'),
                ]);
            }

            $lockedAssignment->forceFill([
                'revoked_by_user_id' => $actor->getKey(),
                'revoked_at' => now(),
            ])->save();

            $this->logStaffOperatorAssignmentEvent->revoked(
                $lockedAssignment,
                $actor,
                array_filter([
                    'reason' => $normalizedReason,
                    'notes' => $normalizedNotes,
                ], static fn ($value) => $value !== null && $value !== '')
            );

            return $lockedAssignment;
        });
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
