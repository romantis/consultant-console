<?php

namespace App\Actions\MetricLibrary;

use App\Models\CanonicalMetric;

class ArchiveCanonicalMetric
{
    public function handle(CanonicalMetric $metric): CanonicalMetric
    {
        if ($metric->is_active) {
            $metric->forceFill(['is_active' => false])->save();
        }

        return $metric;
    }
}
