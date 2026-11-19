<?php

namespace App\Listeners\Engagements;

use App\Events\Engagements\EngagementInvitationAccepted;
use App\Models\Membership;
use App\Models\MetricParticipant;

class UpdateMetricParticipantSubjects
{
    public function handle(EngagementInvitationAccepted $event): void
    {
        if (empty($event->engagementParticipantIds)) {
            return;
        }

        MetricParticipant::query()
            ->whereIn('engagement_participant_id', $event->engagementParticipantIds)
            ->update([
                'subject_type' => Membership::class,
                'subject_id' => $event->membership->id,
            ]);
    }
}
