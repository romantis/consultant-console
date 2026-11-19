<?php

namespace App\Support\StateGuards;

use App\Enums\MetricStatus;
use Illuminate\Validation\ValidationException;

class MetricStateGuard
{
    public static function ensureValid(
        MetricStatus|string|null $from,
        MetricStatus|string $to
    ): void {
        $fromStatus = $from instanceof MetricStatus
            ? $from
            : ($from ? MetricStatus::from($from) : null);

        $toStatus = $to instanceof MetricStatus
            ? $to
            : MetricStatus::from($to);

        if (! $fromStatus || $fromStatus === $toStatus) {
            return;
        }

        $allowed = match ($fromStatus) {
            MetricStatus::PROPOSED => [MetricStatus::AWAITING_FORMS, MetricStatus::IN_REVIEW],
            MetricStatus::AWAITING_FORMS => [MetricStatus::IN_REVIEW],
            MetricStatus::IN_REVIEW => [MetricStatus::IN_WORKSHOP, MetricStatus::FINALIZED],
            MetricStatus::IN_WORKSHOP => [MetricStatus::IN_REVIEW, MetricStatus::FINALIZED],
            MetricStatus::FINALIZED => [MetricStatus::DEPRECATED],
            MetricStatus::DEPRECATED => [],
        };

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __(
                    'Illegal metric status transition (:from → :to).',
                    ['from' => $fromStatus->value, 'to' => $toStatus->value]
                ),
            ]);
        }
    }
}
