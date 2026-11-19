<?php

namespace App\Providers;

use App\Events\Engagements\EngagementInvitationAccepted;
use App\Events\Engagements\EngagementInvitationDeclined;
use App\Events\Engagements\EngagementInvitationRevoked;
use App\Listeners\Engagements\DeactivateEngagementParticipants;
use App\Listeners\Engagements\UpdateMetricParticipantSubjects;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EngagementInvitationAccepted::class => [
            UpdateMetricParticipantSubjects::class,
        ],
        EngagementInvitationDeclined::class => [
            DeactivateEngagementParticipants::class,
        ],
        EngagementInvitationRevoked::class => [
            DeactivateEngagementParticipants::class,
        ],
    ];
}
