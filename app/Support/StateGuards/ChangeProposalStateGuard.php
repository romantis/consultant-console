<?php

namespace App\Support\StateGuards;

use App\Enums\ChangeProposalStatus;
use Illuminate\Validation\ValidationException;

class ChangeProposalStateGuard
{
    public static function ensureValid(
        ChangeProposalStatus|string|null $from,
        ChangeProposalStatus|string $to
    ): void {
        $fromStatus = $from instanceof ChangeProposalStatus
            ? $from
            : ($from ? ChangeProposalStatus::from($from) : null);

        $toStatus = $to instanceof ChangeProposalStatus
            ? $to
            : ChangeProposalStatus::from($to);

        if (! $fromStatus || $fromStatus === $toStatus) {
            return;
        }

        $allowed = match ($fromStatus) {
            ChangeProposalStatus::OPEN => [ChangeProposalStatus::APPROVED, ChangeProposalStatus::REJECTED],
            ChangeProposalStatus::APPROVED => [ChangeProposalStatus::MERGED],
            ChangeProposalStatus::REJECTED => [],
            ChangeProposalStatus::MERGED => [],
        };

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __(
                    'Illegal change proposal status transition (:from → :to).',
                    ['from' => $fromStatus->value, 'to' => $toStatus->value]
                ),
            ]);
        }
    }
}
