<?php

namespace App\Enums;

enum FormAssignmentStatus: string
{
    case OPEN = 'Open';
    case IN_PROGRESS = 'InProgress';
    case COMPLETED = 'Completed';
    case REOPENED = 'Reopened';
}
