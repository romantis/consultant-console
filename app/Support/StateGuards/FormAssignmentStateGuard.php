<?php

namespace App\Support\StateGuards;

use App\Enums\FormAssignmentStatus;
use App\Enums\MetricStatus;
use App\Models\Metric;
use Illuminate\Validation\ValidationException;

class FormAssignmentStateGuard
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function ensureValid(
        FormAssignmentStatus|string|null $from,
        FormAssignmentStatus|string $to,
        ?Metric $metric = null,
        array $context = []
    ): void {
        $fromStatus = $from instanceof FormAssignmentStatus
            ? $from
            : ($from ? FormAssignmentStatus::from($from) : null);

        $toStatus = $to instanceof FormAssignmentStatus
            ? $to
            : FormAssignmentStatus::from($to);

        if (! $fromStatus || $fromStatus === $toStatus) {
            return;
        }

        $allowed = match ($fromStatus) {
            FormAssignmentStatus::OPEN => [FormAssignmentStatus::IN_PROGRESS, FormAssignmentStatus::COMPLETED],
            FormAssignmentStatus::IN_PROGRESS => [FormAssignmentStatus::COMPLETED, FormAssignmentStatus::REOPENED],
            FormAssignmentStatus::COMPLETED => [FormAssignmentStatus::REOPENED],
            FormAssignmentStatus::REOPENED => [FormAssignmentStatus::IN_PROGRESS, FormAssignmentStatus::COMPLETED],
        };

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __(
                    'Illegal form assignment status transition (:from → :to).',
                    ['from' => $fromStatus->value, 'to' => $toStatus->value]
                ),
            ]);
        }

        if ($fromStatus === FormAssignmentStatus::COMPLETED && $toStatus === FormAssignmentStatus::REOPENED) {
            $allowReopen = (bool) ($context['allow_reopen'] ?? false);

            $metricStatus = $context['metric_status'] ?? ($metric?->status ?? null);

            if ($metricStatus && ! $metricStatus instanceof MetricStatus) {
                $metricStatus = MetricStatus::from($metricStatus);
            }

            if ($allowReopen) {
                return;
            }

            if ($metricStatus instanceof MetricStatus && $metricStatus === MetricStatus::FINALIZED) {
                throw ValidationException::withMessages([
                    'status' => __('Cannot reopen assignment once the metric is finalized.'),
                ]);
            }
        }
    }
}
