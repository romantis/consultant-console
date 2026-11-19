<?php

namespace App\Enums;

enum EngagementWorkshopSessionStatus: string
{
    case SCHEDULED = 'Scheduled';
    case IN_PROGRESS = 'InProgress';
    case COMPLETED = 'Completed';
    case CANCELED = 'Canceled';
    case MISSED = 'Missed';
}
