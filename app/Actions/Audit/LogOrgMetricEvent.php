<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class LogOrgMetricEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(
        OrgMetric $orgMetric,
        string $action,
        array $properties = [],
        ?User $actor = null,
        ?Model $subject = null
    ): AuditLog {
        $baseProperties = array_filter([
            'org_metric_id' => (string) $orgMetric->getKey(),
            'canonical_metric_id' => (string) $orgMetric->canonical_metric_id,
            'org_metric_key' => $orgMetric->key,
        ], static fn ($value) => $value !== null && $value !== '');

        return AuditLog::create([
            'organization_id' => $orgMetric->organization_id,
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : OrgMetric::class,
            'subject_id' => $subject?->getKey() ?? $orgMetric->getKey(),
            'properties' => array_filter(
                array_merge($baseProperties, $properties),
                static fn ($value) => $value !== null && $value !== ''
            ),
        ]);
    }
}
