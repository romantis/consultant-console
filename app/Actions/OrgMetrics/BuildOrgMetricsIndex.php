<?php

namespace App\Actions\OrgMetrics;

use App\Enums\EngagementStatus;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildOrgMetricsIndex
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Organization $organization): array
    {
        $orgMetrics = OrgMetric::query()
            ->where('organization_id', $organization->id)
            ->with('canonicalMetric:id,name,key')
            ->orderBy('alias')
            ->orderBy('name')
            ->get();

        $orgMetricIds = $orgMetrics->pluck('id')->all();

        if ($orgMetricIds === []) {
            return ['metrics' => []];
        }

        $activeStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::activePhases()
        );

        $terminalStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::terminalPhases()
        );

        $activeEngagements = $this->fetchEngagementsByStatus(
            $organization,
            $orgMetricIds,
            $activeStatuses
        );

        $draftEngagements = $this->fetchEngagementsByStatus(
            $organization,
            $orgMetricIds,
            [EngagementStatus::DRAFT->value]
        );

        $pastCounts = Metric::query()
            ->select('metrics.org_metric_id', DB::raw('COUNT(DISTINCT metrics.engagement_id) as engagements_count'))
            ->join('engagements', 'engagements.id', '=', 'metrics.engagement_id')
            ->where('metrics.organization_id', $organization->id)
            ->whereIn('metrics.org_metric_id', $orgMetricIds)
            ->whereIn('engagements.status', $terminalStatuses)
            ->groupBy('metrics.org_metric_id')
            ->pluck('engagements_count', 'org_metric_id');

        $orgSignOffs = OrgFinalDefinition::query()
            ->whereIn('org_metric_id', $orgMetricIds)
            ->whereNotNull('signed_off_at')
            ->with('signedOffBy:id,name')
            ->orderByDesc('signed_off_at')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('org_metric_id')
            ->map(fn (Collection $definitions) => $definitions->first());

        $missingOrgSignOffIds = array_values(array_diff(
            $orgMetricIds,
            $orgSignOffs->keys()->all()
        ));

        $fallbackSignOffs = collect();

        if ($missingOrgSignOffIds !== []) {
            $fallbackSignOffs = FinalDefinition::query()
                ->select('final_definitions.*', 'metrics.org_metric_id as org_metric_id')
                ->join('metrics', 'metrics.id', '=', 'final_definitions.metric_id')
                ->where('metrics.organization_id', $organization->id)
                ->whereIn('metrics.org_metric_id', $missingOrgSignOffIds)
                ->whereNotNull('final_definitions.signed_off_at')
                ->with('signedOffBy:id,name')
                ->orderByDesc('final_definitions.signed_off_at')
                ->orderByDesc('final_definitions.updated_at')
                ->get()
                ->groupBy('org_metric_id')
                ->map(fn (Collection $definitions) => $definitions->first());
        }

        $metrics = $orgMetrics->map(function (OrgMetric $orgMetric) use (
            $activeEngagements,
            $draftEngagements,
            $pastCounts,
            $orgSignOffs,
            $fallbackSignOffs
        ) {
            $orgMetricId = (string) $orgMetric->getKey();
            $active = $activeEngagements->get($orgMetricId) ?? $draftEngagements->get($orgMetricId);
            $latestSignOff = $orgSignOffs->get($orgMetricId) ?? $fallbackSignOffs->get($orgMetricId);

            return [
                'id' => $orgMetricId,
                'alias' => $orgMetric->alias,
                'canonical_name' => $orgMetric->canonicalMetric?->name ?? $orgMetric->name,
                'canonical_key' => $orgMetric->canonicalMetric?->key ?? $orgMetric->key,
                'active_engagement' => $active,
                'latest_sign_off' => $this->buildSignOffPayload($latestSignOff),
                'past_engagements_count' => (int) ($pastCounts[$orgMetricId] ?? 0),
            ];
        })->values()->all();

        return [
            'metrics' => $metrics,
        ];
    }

    /**
     * @param  array<int, string>  $orgMetricIds
     * @param  array<int, string>  $statuses
     * @return Collection<string, array<string, string|null>>
     */
    private function fetchEngagementsByStatus(
        Organization $organization,
        array $orgMetricIds,
        array $statuses
    ): Collection {
        if ($orgMetricIds === [] || $statuses === []) {
            return collect();
        }

        return Metric::query()
            ->select([
                'metrics.org_metric_id',
                'engagements.id as engagement_id',
                'engagements.title',
                'engagements.status as engagement_status',
            ])
            ->join('engagements', 'engagements.id', '=', 'metrics.engagement_id')
            ->where('metrics.organization_id', $organization->id)
            ->whereIn('metrics.org_metric_id', $orgMetricIds)
            ->whereIn('engagements.status', $statuses)
            ->orderByDesc('engagements.updated_at')
            ->get()
            ->groupBy('org_metric_id')
            ->map(function (Collection $rows) {
                $row = $rows->first();

                return [
                    'id' => (string) $row->engagement_id,
                    'title' => $row->title,
                    'status' => $row->engagement_status,
                ];
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSignOffPayload(OrgFinalDefinition|FinalDefinition|null $definition): ?array
    {
        if (! $definition) {
            return null;
        }

        $definition->loadMissing('signedOffBy:id,name');

        return [
            'id' => (string) $definition->getKey(),
            'status' => $definition->status,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
        ];
    }
}
