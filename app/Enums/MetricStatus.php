<?php

namespace App\Enums;

enum MetricStatus: string
{
    case PROPOSED = 'Proposed';
    case AWAITING_FORMS = 'AwaitingForms';
    case IN_REVIEW = 'InReview';
    case IN_WORKSHOP = 'InWorkshop';
    case FINALIZED = 'Finalized';
    case DEPRECATED = 'Deprecated';
}
