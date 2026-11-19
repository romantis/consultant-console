<?php

namespace App\Events\Engagements;

use App\Models\Membership;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EngagementInvitationAccepted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<int, string>  $engagementParticipantIds
     */
    public function __construct(
        public Membership $membership,
        public array $engagementParticipantIds = []
    ) {}
}
