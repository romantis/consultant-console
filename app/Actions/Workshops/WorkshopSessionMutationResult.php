<?php

namespace App\Actions\Workshops;

use App\Models\EngagementWorkshopSession;

/**
 * @phpstan-type WorkshopSessionConflictWarningList array<int, WorkshopSessionConflictWarning>
 */
readonly class WorkshopSessionMutationResult
{
    /**
     * @param  array<int, WorkshopSessionConflictWarning>  $warnings
     */
    public function __construct(
        public EngagementWorkshopSession $session,
        public array $warnings = []
    ) {}
}
