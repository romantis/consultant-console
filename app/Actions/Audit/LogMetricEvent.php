<?php

namespace App\Actions\Audit;

use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LogMetricEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        Metric $metric,
        string $action,
        array $properties = [],
        ?User $actor = null,
        ?Model $subject = null
    ): AuditLog {
        $baseProperties = array_filter([
            'metric_id' => (string) $metric->getKey(),
            'engagement_id' => (string) $metric->engagement_id,
            'metric_status' => $metric->status instanceof MetricStatus
                ? $metric->status->value
                : (string) $metric->status,
        ], static fn ($value) => $value !== null && $value !== '');

        return AuditLog::create([
            'organization_id' => $metric->organization_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : Metric::class,
            'subject_id' => $subject?->getKey() ?? $metric->getKey(),
            'properties' => array_filter(
                array_merge($baseProperties, $properties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }
}
