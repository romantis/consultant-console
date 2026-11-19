<?php

namespace App\Actions\OrgMetrics;

use App\Models\CanonicalMetric;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Support\Str;

class EnsureOrgMetricExists
{
    public function handle(Organization $organization, CanonicalMetric $canonicalMetric): OrgMetric
    {
        return OrgMetric::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'canonical_metric_id' => $canonicalMetric->id,
            ],
            [
                'key' => $canonicalMetric->key,
                'name' => $canonicalMetric->name,
                'slug' => Str::slug($canonicalMetric->name ?: $canonicalMetric->key),
                'alias' => null,
            ]
        );
    }
}
