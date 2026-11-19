<?php

namespace App\Actions\OrgMetrics;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Actions\Engagements\AutoFinalizeEngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Jobs\NotifyOrgMetricSignOff;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveOrgMetricSignOff
{
    public function __construct(
        private readonly LogOrgMetricEvent $logOrgMetricEvent,
        private readonly AutoFinalizeEngagementStatus $autoFinalizeEngagementStatus
    ) {}

    public function handle(
        Metric $metric,
        FinalDefinition $finalDefinition,
        ?string $comment = null,
        ?User $actor = null
    ): OrgFinalDefinition {
        $orgMetric = $this->resolveOrgMetric($metric);

        $orgMetric->loadMissing('participants');

        $ownerParticipant = $this->resolveOwnerParticipant($orgMetric, $finalDefinition);
        $this->assertActorIsOrgApprover($orgMetric, $actor);

        $orgDefinition = $this->resolvePendingDefinition($orgMetric, $metric);
        $version = $orgDefinition?->version ?? $this->nextVersion($orgMetric);
        $signedOffAt = now();

        DB::transaction(function () use (
            $metric,
            $finalDefinition,
            $orgMetric,
            $ownerParticipant,
            $version,
            $signedOffAt,
            &$orgDefinition,
            $comment,
            $actor
        ) {
            $payload = [
                'org_metric_id' => $orgMetric->getKey(),
                'source_metric_id' => $metric->getKey(),
                'source_metric_template_id' => $metric->metric_template_id,
                'status' => 'Approved',
                'version' => $version,
                'definition_json' => $finalDefinition->definition_json ?? [],
                'notes' => $finalDefinition->notes,
                ...$this->promotedConsultantSignals($finalDefinition),
                'owner_participant_id' => $ownerParticipant->getKey(),
                'signed_off_at' => $signedOffAt,
                'signed_off_by' => $actor?->getKey(),
            ];

            if ($orgDefinition) {
                $orgDefinition->forceFill($payload)->save();
            } else {
                $orgDefinition = OrgFinalDefinition::create($payload);
            }

            $finalDefinition->forceFill([
                'status' => 'Approved',
                'signed_off_at' => $signedOffAt,
                'signed_off_by' => $actor?->getKey(),
            ])->save();

            $metric->markStatus(MetricStatus::FINALIZED);

            $this->logOrgMetricEvent->handle(
                $orgMetric,
                'org-metric.sign-off.approved',
                array_filter([
                    'org_final_definition_id' => (string) $orgDefinition->getKey(),
                    'source_metric_id' => (string) $metric->getKey(),
                    'decision' => 'approve',
                    'comment' => $comment,
                    'signed_off_at' => $signedOffAt->copy()->toIso8601String(),
                ], static fn ($value) => $value !== null && $value !== ''),
                $actor,
                $orgDefinition
            );
        });

        $orgDefinition?->refresh();

        $metric->loadMissing('engagement');
        if ($metric->engagement) {
            $this->autoFinalizeEngagementStatus->handle($metric->engagement, $metric);
        }

        if ($orgDefinition) {
            NotifyOrgMetricSignOff::dispatch(
                (string) $orgMetric->getKey(),
                (string) $metric->getKey(),
                (string) $metric->organization_id,
                (string) $metric->engagement_id,
                NotifyOrgMetricSignOff::EVENT_APPROVED,
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
                'metric_id' => __('Select an organization metric before approving sign-off.'),
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
                'final_definition_id' => __('Assign an org-level owner before approving sign-off.'),
            ]);
        }

        $finalDefinition->loadMissing('ownerParticipant.user');

        $ownerUserId = $finalDefinition->ownerParticipant?->user_id;
        $matched = $ownerUserId
            ? $orgOwners->firstWhere('user_id', $ownerUserId)
            : null;

        return $matched ?? $orgOwners->first();
    }

    private function assertActorIsOrgApprover(OrgMetric $orgMetric, ?User $actor): void
    {
        if (! $actor) {
            return;
        }

        $isApprover = $orgMetric->participants
            ->contains(fn (OrgMetricParticipant $participant) => $participant->responsibility === 'approver'
                && (string) $participant->user_id === (string) $actor->getKey());

        if ($isApprover) {
            return;
        }

        $managerOverrideEnabled = (bool) config(
            'metric-harmony.features.internal_first_manager_signoff_override',
            false
        );

        if (! $managerOverrideEnabled) {
            throw ValidationException::withMessages([
                'decision' => __('Only org-level approvers can approve sign-off.'),
            ]);
        }

        if (Staff::isStaffOwner($actor) || Staff::isStaffAdmin($actor)) {
            return;
        }

        if (Staff::canOperateAssignedDelivery($actor, (string) $orgMetric->organization_id)) {
            return;
        }

        $context = ImpersonationGuard::resolve($actor, (string) $orgMetric->organization_id);

        if (! $context->allowsAtLeast(MembershipRole::ADMIN)) {
            throw ValidationException::withMessages([
                'decision' => __('Only org-level approvers can approve sign-off.'),
            ]);
        }
    }

    private function resolvePendingDefinition(OrgMetric $orgMetric, Metric $metric): ?OrgFinalDefinition
    {
        return OrgFinalDefinition::query()
            ->where('org_metric_id', $orgMetric->getKey())
            ->where('source_metric_id', $metric->getKey())
            ->whereNull('signed_off_at')
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

    /**
     * @return array{
     *   operational_status:?string,
     *   operational_status_notes:?string,
     *   alignment_summary:?string,
     *   implementation_gaps:?array<string, mixed>,
     *   validation_steps:?string,
     *   common_mismatch_causes:?string,
     *   executive_takeaway:?string
     * }
     */
    private function promotedConsultantSignals(FinalDefinition $finalDefinition): array
    {
        return [
            'operational_status' => $finalDefinition->operational_status,
            'operational_status_notes' => $finalDefinition->operational_status_notes,
            'alignment_summary' => $finalDefinition->alignment_summary,
            'implementation_gaps' => $finalDefinition->implementation_gaps,
            'validation_steps' => $finalDefinition->validation_steps,
            'common_mismatch_causes' => $finalDefinition->common_mismatch_causes,
            'executive_takeaway' => $finalDefinition->executive_takeaway,
        ];
    }
}
