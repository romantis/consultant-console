<?php

namespace App\Actions\MetricLibrary;

use App\Models\CanonicalMetric;

class UpdateCanonicalMetric
{
    /**
     * @param  array{
     *     name?:string,
     *     description?:string|null,
     *     is_active?:bool,
     *     default_metric_template_id?:string|null
     * }  $data
     */
    public function handle(CanonicalMetric $metric, array $data): CanonicalMetric
    {
        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }

        if (array_key_exists('is_active', $data)) {
            $updates['is_active'] = $data['is_active'];
        }

        if (array_key_exists('default_metric_template_id', $data)) {
            $updates['default_metric_template_id'] = $data['default_metric_template_id'];
        }

        if ($updates !== []) {
            $metric->update($updates);
        }

        return $metric;
    }
}
