<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Database\Eloquent\Model;

class LogStaffOperatorAssignmentEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        StaffOperatorAssignment $assignment,
        string $action,
        array $properties = [],
        ?User $actor = null,
        ?Model $subject = null
    ): AuditLog {
        $baseProperties = array_filter([
            'assignment_id' => (string) $assignment->getKey(),
            'user_id' => (string) $assignment->user_id,
            'organization_id' => (string) $assignment->organization_id,
            'granted_by_user_id' => (string) $assignment->granted_by_user_id,
            'revoked_by_user_id' => $assignment->revoked_by_user_id ? (string) $assignment->revoked_by_user_id : null,
            'granted_at' => $assignment->granted_at?->toIso8601String(),
            'revoked_at' => $assignment->revoked_at?->toIso8601String(),
            'actor_staff_role' => $actor ? Staff::staffRole($actor)?->value : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return AuditLog::create([
            'organization_id' => $assignment->organization_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : StaffOperatorAssignment::class,
            'subject_id' => $subject?->getKey() ?? $assignment->getKey(),
            'properties' => array_filter(
                array_merge($baseProperties, $properties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function granted(
        StaffOperatorAssignment $assignment,
        ?User $actor = null,
        array $properties = []
    ): AuditLog {
        return $this->handle(
            $assignment,
            AuditLog::ACTION_STAFF_OPERATOR_ASSIGNMENT_GRANTED,
            $properties,
            $actor,
            $assignment
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function revoked(
        StaffOperatorAssignment $assignment,
        ?User $actor = null,
        array $properties = []
    ): AuditLog {
        return $this->handle(
            $assignment,
            AuditLog::ACTION_STAFF_OPERATOR_ASSIGNMENT_REVOKED,
            $properties,
            $actor,
            $assignment
        );
    }
}
