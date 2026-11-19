<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Models\OrgMetric;
use App\Models\User;

class UpdateOrgMetricDefaultTemplate
{
    public function __construct(private readonly LogOrgMetricEvent $logOrgMetricEvent) {}

    public function handle(OrgMetric $orgMetric, ?string $templateId, ?User $actor = null): OrgMetric
    {
        $normalized = is_string($templateId) ? trim($templateId) : null;

        if ($normalized === '') {
            $normalized = null;
        }

        if ((string) $orgMetric->default_metric_template_id === (string) $normalized) {
            return $orgMetric;
        }

        $previous = $orgMetric->default_metric_template_id
            ? (string) $orgMetric->default_metric_template_id
            : null;

        $orgMetric->forceFill([
            'default_metric_template_id' => $normalized,
        ])->save();

        $this->logOrgMetricEvent->handle(
            $orgMetric,
            'org-metric.default-template.updated',
            [
                'default_template_id' => $normalized,
                'previous_default_template_id' => $previous,
            ],
            $actor
        );

        return $orgMetric;
    }
}
