<?php

namespace App\Actions\Engagements;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;

class AutoFinalizeEngagementStatus
{
    public function __construct(
        private readonly UpdateEngagementStatus $updateEngagementStatus,
        private readonly LogEngagementEvent $logEngagementEvent
    ) {}

    public function handle(Engagement $engagement, ?Metric $triggerMetric = null): bool
    {
        $engagement->refresh();

        if ($engagement->status !== EngagementStatus::IN_WORKSHOP) {
            return false;
        }

        if (! in_array(EngagementStatus::FINALIZED, $engagement->status->allowedTransitions(), true)) {
            return false;
        }

        if ($triggerMetric && (string) $triggerMetric->engagement_id !== (string) $engagement->getKey()) {
            return false;
        }

        $metricsTotal = Metric::query()
            ->where('engagement_id', $engagement->getKey())
            ->count();

        if ($metricsTotal === 0) {
            return false;
        }

        $finalizedTotal = Metric::query()
            ->where('engagement_id', $engagement->getKey())
            ->whereIn('status', [
                MetricStatus::FINALIZED->value,
                MetricStatus::DEPRECATED->value,
            ])
            ->count();

        if ($finalizedTotal < $metricsTotal) {
            return false;
        }

        $fromStatus = $engagement->status;

        $this->updateEngagementStatus->handle($engagement, EngagementStatus::FINALIZED);

        $this->logEngagementEvent->handle(
            $engagement,
            'engagement.status.auto_advanced',
            array_filter([
                'from_status' => $fromStatus->value,
                'to_status' => EngagementStatus::FINALIZED->value,
                'trigger' => 'metrics_finalized',
                'metrics_total' => $metricsTotal,
                'metrics_finalized' => $finalizedTotal,
                'trigger_metric_id' => $triggerMetric?->getKey(),
            ], static fn ($value) => $value !== null),
            null,
            $triggerMetric
        );

        return true;
    }
}
