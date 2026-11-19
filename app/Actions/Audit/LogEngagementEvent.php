<?php

namespace App\Actions\Audit;

use App\Enums\EngagementStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LogEngagementEvent
{
    public const ACTION_STATUS_OVERRIDE_APPLIED = 'engagement.status.override_applied';

    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Engagement $engagement,
        string $action,
        array $properties = [],
        ?User $actor = null,
        ?Model $subject = null
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $engagement->organization_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => array_filter(
                array_merge([
                    'engagement_id' => $engagement->getKey(),
                ], $properties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }

    public function logStatusOverrideApplied(
        Engagement $engagement,
        EngagementStatus $fromStatus,
        EngagementStatus $toStatus,
        string $blockedReason,
        string $overrideReason,
        ?User $actor = null
    ): AuditLog {
        return $this->handle(
            $engagement,
            self::ACTION_STATUS_OVERRIDE_APPLIED,
            [
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
                'blocked_reason' => $blockedReason,
                'override_reason' => $overrideReason,
                'scope' => [
                    'organization_id' => $engagement->organization_id,
                    'engagement_id' => $engagement->getKey(),
                ],
            ],
            $actor,
            $engagement
        );
    }
}
