<?php

namespace App\Actions\OrgMetrics;

use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use Illuminate\Validation\ValidationException;

class CreateMergedOrgFinalDefinition
{
    /**
     * @param  bool  $hasImplementationGapsOverride  True when the merge payload explicitly supplied
     *                                               `implementation_gaps`; false means carry forward the
     *                                               previous official org-level value.
     */
    public function handle(
        Metric $metric,
        FinalDefinition $mergedDefinition,
        bool $hasImplementationGapsOverride
    ): OrgFinalDefinition {
        $orgMetric = $this->resolveOrgMetric($metric);

        $orgMetric->loadMissing('participants');

        $previousOfficial = $this->resolveLatestOfficialDefinition($orgMetric);
        $ownerParticipant = $this->resolveOwnerParticipant($orgMetric, $mergedDefinition);
        $version = max(
            (int) ($mergedDefinition->version ?? 1),
            $this->nextVersion($orgMetric)
        );

        return OrgFinalDefinition::create([
            'org_metric_id' => $orgMetric->getKey(),
            'source_metric_id' => $metric->getKey(),
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => $version,
            'definition_json' => $mergedDefinition->definition_json ?? [],
            'notes' => $mergedDefinition->notes,
            'alignment_summary' => null,
            'operational_status' => null,
            'operational_status_notes' => null,
            'implementation_gaps' => $this->implementationGapsForMergedRevision(
                $mergedDefinition,
                $previousOfficial,
                $hasImplementationGapsOverride
            ),
            'validation_steps' => null,
            'common_mismatch_causes' => null,
            'owner_participant_id' => $ownerParticipant->getKey(),
            'signed_off_at' => $mergedDefinition->signed_off_at,
            'signed_off_by' => $mergedDefinition->signed_off_by,
        ]);
    }

    private function resolveOrgMetric(Metric $metric): OrgMetric
    {
        $orgMetric = $metric->orgMetric;

        if (! $orgMetric) {
            throw ValidationException::withMessages([
                'metric_id' => __('Select an organization metric before merging changes.'),
            ]);
        }

        return $orgMetric;
    }

    private function resolveOwnerParticipant(OrgMetric $orgMetric, FinalDefinition $mergedDefinition): OrgMetricParticipant
    {
        $orgOwners = $orgMetric->participants
            ->filter(fn (OrgMetricParticipant $participant) => $participant->responsibility === 'owner' && $participant->user_id)
            ->values();

        if ($orgOwners->isEmpty()) {
            throw ValidationException::withMessages([
                'final_definition_id' => __('Assign an org-level owner before merging changes.'),
            ]);
        }

        $mergedDefinition->loadMissing('ownerParticipant.user');

        $ownerUserId = $mergedDefinition->ownerParticipant?->user_id;
        $matched = $ownerUserId
            ? $orgOwners->firstWhere('user_id', $ownerUserId)
            : null;

        return $matched ?? $orgOwners->first();
    }

    private function resolveLatestOfficialDefinition(OrgMetric $orgMetric): ?OrgFinalDefinition
    {
        return OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereNotNull('signed_off_at')
            ->orderByDesc('version')
            ->orderByDesc('signed_off_at')
            ->first();
    }

    private function nextVersion(OrgMetric $orgMetric): int
    {
        $latest = OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->max('version');

        return ((int) $latest) + 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function implementationGapsForMergedRevision(
        FinalDefinition $mergedDefinition,
        ?OrgFinalDefinition $previousOfficial,
        bool $hasImplementationGapsOverride
    ): ?array {
        if ($hasImplementationGapsOverride) {
            return $mergedDefinition->implementation_gaps;
        }

        return $previousOfficial?->implementation_gaps;
    }
}
