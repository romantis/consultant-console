<?php

namespace App\Listeners\Engagements;

use App\Events\Engagements\EngagementInvitationEnded;
use App\Models\EngagementParticipant;
use App\Models\FormAssignment;
use App\Models\MetricParticipant;

class DeactivateEngagementParticipants
{
    public function handle(EngagementInvitationEnded $event): void
    {
        if (empty($event->engagementParticipantIds)) {
            return;
        }

        EngagementParticipant::query()
            ->whereIn('id', $event->engagementParticipantIds)
            ->update(['active' => false]);

        $metricParticipantIds = MetricParticipant::query()
            ->whereIn('engagement_participant_id', $event->engagementParticipantIds)
            ->pluck('id');

        if ($metricParticipantIds->isEmpty()) {
            return;
        }

        MetricParticipant::query()
            ->whereIn('id', $metricParticipantIds)
            ->update([
                'engagement_participant_id' => null,
                'user_id' => null,
                'subject_type' => null,
                'subject_id' => null,
            ]);

        FormAssignment::query()
            ->whereIn('metric_participant_id', $metricParticipantIds)
            ->delete();
    }
}
