<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Models\OrgMetric;
use App\Models\User;

class UpdateOrgMetricAlias
{
    public function __construct(private readonly LogOrgMetricEvent $logOrgMetricEvent) {}

    public function handle(OrgMetric $orgMetric, ?string $alias, ?User $actor = null): OrgMetric
    {
        $normalized = is_string($alias) ? trim($alias) : null;

        if ($normalized === '') {
            $normalized = null;
        }

        if ($orgMetric->alias === $normalized) {
            return $orgMetric;
        }

        $previous = $orgMetric->alias;

        $orgMetric->forceFill([
            'alias' => $normalized,
        ])->save();

        $this->logOrgMetricEvent->handle(
            $orgMetric,
            'org-metric.alias.updated',
            [
                'alias' => $normalized,
                'previous_alias' => $previous,
            ],
            $actor
        );

        return $orgMetric;
    }
}
