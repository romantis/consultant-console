<?php

namespace App\Actions\Audit;

use App\Enums\ChangeProposalStatus;
use App\Models\AuditLog;
use App\Models\ChangeProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LogChangeProposalEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        ChangeProposal $proposal,
        string $action,
        array $properties = [],
        ?User $actor = null,
        ?Model $subject = null
    ): AuditLog {
        $baseProperties = array_filter([
            'proposal_id' => (string) $proposal->getKey(),
            'metric_id' => (string) $proposal->metric_id,
            'engagement_id' => (string) $proposal->engagement_id,
            'proposal_status' => $proposal->status instanceof ChangeProposalStatus
                ? $proposal->status->value
                : (string) $proposal->status,
        ], static fn ($value) => $value !== null && $value !== '');

        return AuditLog::create([
            'organization_id' => $proposal->organization_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : ChangeProposal::class,
            'subject_id' => $subject?->getKey() ?? $proposal->getKey(),
            'properties' => array_filter(
                array_merge($baseProperties, $properties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }
}
