<?php

namespace App\Events\Engagements;

use App\Models\PendingMembership;

abstract class EngagementInvitationEnded
{
    /**
     * @param  array<int, string>  $engagementParticipantIds
     */
    public function __construct(
        public readonly PendingMembership $pendingMembership,
        public readonly array $engagementParticipantIds,
    ) {}
}
