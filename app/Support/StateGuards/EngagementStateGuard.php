<?php

namespace App\Support\StateGuards;

use App\Enums\EngagementStatus;
use Illuminate\Validation\ValidationException;

class EngagementStateGuard
{
    public static function ensureValid(
        EngagementStatus|string|null $from,
        EngagementStatus|string $to
    ): void {
        $fromStatus = $from instanceof EngagementStatus
            ? $from
            : ($from ? EngagementStatus::fromLegacy($from) : null);

        $toStatus = $to instanceof EngagementStatus
            ? $to
            : EngagementStatus::fromLegacy($to);

        if (! $fromStatus || $fromStatus === $toStatus) {
            return;
        }

        $allowed = $fromStatus->allowedTransitions();

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __(
                    'Illegal engagement status transition (:from → :to).',
                    ['from' => $fromStatus->value, 'to' => $toStatus->value]
                ),
            ]);
        }
    }
}
