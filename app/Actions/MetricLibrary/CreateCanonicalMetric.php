<?php

namespace App\Actions\MetricLibrary;

use App\Models\CanonicalMetric;

class CreateCanonicalMetric
{
    /**
     * @param  array{key:string,name:string,description?:string|null}  $data
     */
    public function handle(array $data): CanonicalMetric
    {
        return CanonicalMetric::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);
    }
}
