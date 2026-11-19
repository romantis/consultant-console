<?php

namespace App\Support\Metrics;

use App\Models\Metric;

class RecomputeStatusPresenter
{
    public static function present(Metric $metric): array
    {
        $status = $metric->recompute_status ?: 'idle';
        $changedAt = $metric->definitions_changed_at;
        $finishedAt = $metric->recompute_finished_at;
        $pendingChanges = ! $changedAt || ! $finishedAt || $changedAt->greaterThan($finishedAt);

        return [
            'status' => $status,
            'queued_at' => optional($metric->recompute_queued_at)->toIso8601String(),
            'finished_at' => optional($metric->recompute_finished_at)->toIso8601String(),
            'changed_at' => optional($changedAt)->toIso8601String(),
            'pending_changes' => $pendingChanges,
        ];
    }
}
