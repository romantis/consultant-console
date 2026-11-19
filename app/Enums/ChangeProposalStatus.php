<?php

namespace App\Enums;

enum ChangeProposalStatus: string
{
    case OPEN = 'Open';
    case APPROVED = 'Approved';
    case REJECTED = 'Rejected';
    case MERGED = 'Merged';
}
