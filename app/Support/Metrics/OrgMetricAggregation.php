<?php

namespace App\Support\Metrics;

use App\Models\FinalDefinition;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class OrgMetricAggregation
{
    public function latestSignedOffDefinition(OrgMetric $orgMetric): FinalDefinition|OrgFinalDefinition|null
    {
        return $this->signedOffDefinitions($orgMetric)->first();
    }

    /**
     * @return EloquentCollection<int, FinalDefinition|OrgFinalDefinition>
     */
    public function signedOffHistory(OrgMetric $orgMetric): EloquentCollection
    {
        return $this->signedOffDefinitions($orgMetric);
    }

    /**
     * @return EloquentCollection<int, FinalDefinition|OrgFinalDefinition>
     */
    protected function signedOffDefinitions(OrgMetric $orgMetric): EloquentCollection
    {
        $orgDefinitions = OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereNotNull('signed_off_at')
            ->with([
                'signedOffBy:id,name',
                'ownerParticipant.user:id,name,email',
                'metric:id,engagement_id,org_metric_id,name,status,last_submitted_at',
                'metric.engagement:id,title',
            ])
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get();

        if ($orgDefinitions->isNotEmpty()) {
            return $orgDefinitions;
        }

        return FinalDefinition::query()
            ->whereNotNull('signed_off_at')
            ->whereIn('metric_id', $orgMetric->metrics()->select('id'))
            ->with([
                'signedOffBy:id,name',
                'ownerParticipant.user:id,name,email',
                'metric:id,engagement_id,org_metric_id,name,status,last_submitted_at',
                'metric.engagement:id,title',
            ])
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get();
    }
}
