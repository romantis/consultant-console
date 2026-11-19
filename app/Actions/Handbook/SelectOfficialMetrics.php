<?php

namespace App\Actions\Handbook;

use App\Enums\MetricStatus;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SelectOfficialMetrics
{
    public function handle(Organization $organization, ?EloquentCollection $metrics = null): EloquentCollection
    {
        $metrics ??= Metric::query()
            ->where('organization_id', $organization->id)
            ->where('status', MetricStatus::FINALIZED)
            ->get();

        $metrics = $metrics
            ->filter(fn (Metric $metric): bool => (string) $metric->organization_id === (string) $organization->getKey())
            ->filter(fn (Metric $metric): bool => $metric->status === MetricStatus::FINALIZED)
            ->values();

        if ($metrics->isEmpty()) {
            return $metrics;
        }

        $orgDefinitions = $this->loadOrgDefinitions($metrics);

        if ($orgDefinitions->isNotEmpty()) {
            return $this->selectByOrgDefinitions($metrics, $orgDefinitions);
        }

        $definitions = FinalDefinition::query()
            ->whereIn('metric_id', $metrics->modelKeys())
            ->whereNotNull('signed_off_at')
            ->with([
                'signedOffBy:id,name',
                'ownerParticipant.user:id,name,email',
                'metric:id,engagement_id,org_metric_id,metric_template_id,name,status',
                'metric.engagement:id,title,starts_on,ends_on,review_cadence,next_review_on',
            ])
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('metric_id')
            ->map(fn (EloquentCollection $group) => $group->first());

        $metrics = $metrics
            ->filter(fn (Metric $metric) => $definitions->has($metric->getKey()))
            ->values();

        foreach ($metrics as $metric) {
            $definition = $definitions[$metric->getKey()];

            $metric->setRelation('finalDefinition', $definition);
            $metric->setRelation('officialDefinition', $definition);
            $metric->setRelation('officialDefinitionHistory', new EloquentCollection);
        }

        return $this->selectLatestOfficialMetrics($metrics);
    }

    /**
     * @return EloquentCollection<int, OrgFinalDefinition>
     */
    protected function loadOrgDefinitions(EloquentCollection $metrics): EloquentCollection
    {
        $orgMetricIds = $metrics
            ->pluck('org_metric_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($orgMetricIds === []) {
            return new EloquentCollection;
        }

        return OrgFinalDefinition::query()
            ->whereIn('org_metric_id', $orgMetricIds)
            ->whereNotNull('signed_off_at')
            ->with([
                'signedOffBy:id,name',
                'ownerParticipant.user:id,name,email',
                'metric:id,engagement_id,org_metric_id,metric_template_id,name,status',
                'metric.engagement:id,title,starts_on,ends_on,review_cadence,next_review_on',
            ])
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, OrgFinalDefinition>  $orgDefinitions
     */
    protected function selectByOrgDefinitions(
        EloquentCollection $metrics,
        EloquentCollection $orgDefinitions
    ): EloquentCollection {
        $groupedDefinitions = $orgDefinitions->groupBy('org_metric_id');
        $definitionsByOrgMetric = $groupedDefinitions
            ->map(fn (EloquentCollection $group) => $group->first());

        $metricsById = $metrics->keyBy(fn (Metric $metric) => (string) $metric->getKey());

        $selected = $definitionsByOrgMetric
            ->map(function (OrgFinalDefinition $definition) use ($metrics, $metricsById) {
                $metric = $metricsById->get((string) $definition->source_metric_id);

                if (! $metric && $definition->org_metric_id) {
                    $metric = $metrics->firstWhere('org_metric_id', $definition->org_metric_id);
                }

                return $metric;
            })
            ->filter()
            ->values();

        if ($selected->isEmpty()) {
            return $selected;
        }

        foreach ($selected as $metric) {
            $group = $groupedDefinitions->get((string) $metric->org_metric_id, new EloquentCollection);
            $officialDefinition = $group->first();
            $history = $group
                ->slice(1)
                ->values();

            if (! $officialDefinition) {
                continue;
            }

            $metric->setRelation('officialDefinition', $officialDefinition);
            $metric->setRelation('officialDefinitionHistory', $history);
        }

        return $selected;
    }

    protected function selectLatestOfficialMetrics(EloquentCollection $metrics): EloquentCollection
    {
        return $metrics
            ->groupBy(fn (Metric $metric) => $this->groupKey($metric))
            ->map(function (EloquentCollection $group) {
                return $group
                    ->sortByDesc(fn (Metric $metric) => $this->sortKey($metric))
                    ->first();
            })
            ->filter()
            ->values();
    }

    protected function groupKey(Metric $metric): string
    {
        if ($metric->org_metric_id) {
            return 'org:'.$metric->org_metric_id;
        }

        if ($metric->metric_template_id) {
            return 'template:'.$metric->metric_template_id;
        }

        $key = $metric->key ?: (string) $metric->getKey();

        return 'key:'.$key;
    }

    protected function sortKey(Metric $metric): string
    {
        $final = $metric->finalDefinition;
        $signedOffAt = $final?->signed_off_at?->getTimestamp() ?? 0;
        $definitionUpdatedAt = $final?->updated_at?->getTimestamp() ?? 0;
        $definitionsChangedAt = $metric->definitions_changed_at?->getTimestamp() ?? 0;
        $metricUpdatedAt = $metric->updated_at?->getTimestamp() ?? 0;

        return sprintf(
            '%012d%012d%012d%012d',
            $signedOffAt,
            $definitionUpdatedAt,
            $definitionsChangedAt,
            $metricUpdatedAt
        );
    }
}
