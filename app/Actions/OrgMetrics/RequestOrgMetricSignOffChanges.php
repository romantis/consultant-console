<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Enums\MetricStatus;
use App\Jobs\NotifyOrgMetricSignOff;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestOrgMetricSignOffChanges
{
    public function __construct(private readonly LogOrgMetricEvent $logOrgMetricEvent) {}

    public function handle(
        Metric $metric,
        FinalDefinition $finalDefinition,
        ?string $comment = null,
        ?User $actor = null
    ): OrgFinalDefinition {
        $orgMetric = $this->resolveOrgMetric($metric);

        $orgMetric->loadMissing('participants');

        $ownerParticipant = $this->resolveOwnerParticipant($orgMetric, $finalDefinition);

        $orgDefinition = $this->resolveLatestDefinition($orgMetric, $metric);
        $version = $orgDefinition?->version ?? $this->nextVersion($orgMetric);

        DB::transaction(function () use (
            $metric,
            $finalDefinition,
            $orgMetric,
            $ownerParticipant,
            $version,
            &$orgDefinition,
            $comment,
            $actor
        ) {
            $payload = [
                'org_metric_id' => $orgMetric->getKey(),
                'source_metric_id' => $metric->getKey(),
                'source_metric_template_id' => $metric->metric_template_id,
                'status' => 'NeedsRevision',
                'version' => $version,
                'definition_json' => $finalDefinition->definition_json ?? [],
                'notes' => $finalDefinition->notes,
                'owner_participant_id' => $ownerParticipant->getKey(),
                'signed_off_at' => null,
                'signed_off_by' => null,
            ];

            if ($orgDefinition) {
                $orgDefinition->forceFill($payload)->save();
            } else {
                $orgDefinition = OrgFinalDefinition::create($payload);
            }

            $finalDefinition->forceFill([
                'status' => 'NeedsRevision',
                'signed_off_at' => null,
                'signed_off_by' => null,
            ])->save();

            $metric->markStatus(MetricStatus::IN_REVIEW);

            $this->logOrgMetricEvent->handle(
                $orgMetric,
                'org-metric.sign-off.changes-requested',
                array_filter([
                    'org_final_definition_id' => (string) $orgDefinition->getKey(),
                    'source_metric_id' => (string) $metric->getKey(),
                    'decision' => 'request_changes',
                    'comment' => $comment,
                ], static fn ($value) => $value !== null && $value !== ''),
                $actor,
                $orgDefinition
            );
        });

        $orgDefinition?->refresh();

        if ($orgDefinition) {
            NotifyOrgMetricSignOff::dispatch(
                (string) $orgMetric->getKey(),
                (string) $metric->getKey(),
                (string) $metric->organization_id,
                (string) $metric->engagement_id,
                NotifyOrgMetricSignOff::EVENT_CHANGES_REQUESTED,
                $actor?->getKey(),
                $comment
            )->afterCommit();
        }

        return $orgDefinition;
    }

    private function resolveOrgMetric(Metric $metric): OrgMetric
    {
        $orgMetric = $metric->orgMetric;

        if (! $orgMetric) {
            throw ValidationException::withMessages([
                'metric_id' => __('Select an organization metric before requesting changes.'),
            ]);
        }

        return $orgMetric;
    }

    private function resolveOwnerParticipant(OrgMetric $orgMetric, FinalDefinition $finalDefinition): OrgMetricParticipant
    {
        $orgOwners = $orgMetric->participants
            ->filter(fn (OrgMetricParticipant $participant) => $participant->responsibility === 'owner' && $participant->user_id)
            ->values();

        if ($orgOwners->isEmpty()) {
            throw ValidationException::withMessages([
                'final_definition_id' => __('Assign an org-level owner before requesting changes.'),
            ]);
        }

        $finalDefinition->loadMissing('ownerParticipant.user');

        $ownerUserId = $finalDefinition->ownerParticipant?->user_id;
        $matched = $ownerUserId
            ? $orgOwners->firstWhere('user_id', $ownerUserId)
            : null;

        return $matched ?? $orgOwners->first();
    }

    private function resolveLatestDefinition(OrgMetric $orgMetric, Metric $metric): ?OrgFinalDefinition
    {
        return OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->where('source_metric_id', $metric->getKey())
            ->orderByDesc('version')
            ->first();
    }

    private function nextVersion(OrgMetric $orgMetric): int
    {
        $latest = OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->max('version');

        return ((int) $latest) + 1;
    }
}
