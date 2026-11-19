<?php

namespace App\Actions\Metrics;

use App\Models\Metric;
use App\Models\MetricTemplate;
use Illuminate\Support\Facades\DB;

class UpdateMetricTemplate
{
    /**
     * Update the template and atomically increment revision of all associated metrics.
     */
    public function handle(MetricTemplate $template, array $data): void
    {
        DB::transaction(function () use ($template, $data) {
            // 1. Lock all related metrics in a stable order by ID to prevent deadlocks
            $metrics = Metric::where('metric_template_id', $template->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // 2. Perform template update
            $template->update($data);

            // 3. Increment the brief revision of locked metrics
            foreach ($metrics as $metric) {
                $metric->incrementBriefSourceRevision();
            }
        });
    }
}
