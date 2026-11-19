<?php

namespace App\Actions\Workshops;

use App\Actions\Client\BuildClientMetricDetail;
use App\Actions\Metrics\ApproveSignOff;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use App\Support\Metrics\FieldRoleSchema;
use App\Support\Metrics\FinalDefinitionRequirements;
use App\Support\Metrics\OrgMetricAggregation;
use App\Support\Metrics\RecomputeStatusPresenter;
use App\Support\Staff;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BuildWorkshopPayload
{
    public function __construct(
        private readonly BuildClientMetricDetail $clientDetailBuilder,
        private readonly OrgMetricAggregation $orgMetricAggregation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Metric $metric, ?User $user = null): array
    {
        $metric->loadMissing([
            'orgMetric',
            'participants.user',
            'participants.engagementParticipant.membership',
            'participants.engagementParticipant.pendingMembership',
        ]);
        $detail = $this->clientDetailBuilder->handle($metric);
        $fieldRoles = FieldRoleSchema::forMetric($metric);

        $ownerOptions = $metric->participants
            ->map(fn ($participant) => [
                'id' => (string) $participant->getKey(),
                'label' => $participant->user?->name ?? 'Pending invite',
                'responsibility' => Str::headline($participant->responsibility ?? ''),
                'affiliation' => $this->resolveParticipantAffiliation($participant),
                'is_default' => ($participant->responsibility ?? '') === 'approver',
                'user' => $participant->user?->only(['id', 'name', 'email']),
                'disabled' => $participant->user === null,
            ])
            ->values()
            ->all();

        $metricPayload = $detail['metric'] ?? [];
        $metricPayload['field_roles'] = $fieldRoles['field_roles'] ?? [];
        $metricPayload['unclassified'] = $fieldRoles['unclassified'] ?? [];
        $metricPayload['engagement_id'] = (string) $metric->engagement_id;
        $metricPayload['owner_options'] = $ownerOptions;

        $finalDefinition = $metric->finalDefinition;
        if (! $finalDefinition) {
            $finalDefinition = FinalDefinition::make([
                'metric_id' => $metric->id,
                'definition_json' => [],
            ]);
            $finalDefinition->setRelation('metric', $metric);
        }

        $requirements = FinalDefinitionRequirements::forMetric($metric, $finalDefinition);
        $isStaffViewer = $user ? Staff::isStaff($user) : false;
        $consultantDefinitions = $isStaffViewer
            ? $this->loadConsultantDefinitions($metric)
            : ['draft' => null, 'final' => null];

        $permissions = [
            'update_final_definition' => $user
                ? Gate::forUser($user)->allows('update', $finalDefinition)
                : false,
            'sign_off' => $user
                ? Gate::forUser($user)->allows('signOff', $metric)
                : false,
            'sign_off_message' => ApproveSignOff::signOffReadinessMessage($requirements),
        ];

        return [
            'metric' => $metricPayload,
            'draft_definition' => $isStaffViewer
                ? $this->mergeConsultantSignals(
                    $detail['draft_definition'] ?? null,
                    $consultantDefinitions['draft']
                )
                : $this->stripConsultantSignals($detail['draft_definition'] ?? null),
            'final_definition' => $isStaffViewer
                ? $this->mergeConsultantSignals(
                    $detail['final_definition'] ?? null,
                    $consultantDefinitions['final']
                )
                : $this->stripConsultantSignals($detail['final_definition'] ?? null),
            'final_definition_history' => $detail['final_definition_history'] ?? [],
            'official_signals' => $isStaffViewer
                ? $this->buildOfficialSignalsPayload($metric)
                : null,
            'final_definition_requirements' => $requirements,
            'permissions' => $permissions,
            'recompute' => RecomputeStatusPresenter::present($metric),
        ];
    }

    /**
     * @return array{draft: ?FinalDefinition, final: ?FinalDefinition}
     */
    private function loadConsultantDefinitions(Metric $metric): array
    {
        $definitions = FinalDefinition::query()
            ->where('metric_id', $metric->getKey())
            ->with(['signedOffBy:id,name'])
            ->orderByDesc('version')
            ->orderByDesc('created_at')
            ->get();

        return [
            'draft' => $definitions->first(fn (FinalDefinition $definition) => ! $definition->signed_off_at),
            'final' => $definitions->first(fn (FinalDefinition $definition) => $definition->signed_off_at),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function mergeConsultantSignals(?array $payload, ?FinalDefinition $definition): ?array
    {
        if (! $definition) {
            return $payload;
        }

        $merged = $payload ?? $this->buildDefinitionPayload($definition);
        $merged['alignment_summary'] = $definition->alignment_summary;
        $merged['operational_status'] = $definition->operational_status;
        $merged['operational_status_notes'] = $definition->operational_status_notes;
        $merged['implementation_gaps'] = $definition->implementation_gaps;
        $merged['validation_steps'] = $definition->validation_steps;
        $merged['common_mismatch_causes'] = $definition->common_mismatch_causes;
        $merged['executive_takeaway'] = $definition->executive_takeaway;

        return $merged;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function stripConsultantSignals(?array $payload): ?array
    {
        if (! $payload) {
            return $payload;
        }

        foreach ([
            'alignment_summary',
            'operational_status',
            'operational_status_notes',
            'implementation_gaps',
            'validation_steps',
            'common_mismatch_causes',
            'executive_takeaway',
        ] as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildOfficialSignalsPayload(Metric $metric): ?array
    {
        $orgMetric = $metric->orgMetric;

        if (! $orgMetric) {
            return null;
        }

        $officialDefinition = $this->orgMetricAggregation->latestSignedOffDefinition($orgMetric);

        if (! ($officialDefinition instanceof FinalDefinition || $officialDefinition instanceof OrgFinalDefinition)) {
            return null;
        }

        $officialDefinition->loadMissing('signedOffBy:id,name');

        return [
            'id' => (string) $officialDefinition->getKey(),
            'source' => $officialDefinition instanceof OrgFinalDefinition
                ? 'org_final_definition'
                : 'final_definition',
            'status' => $officialDefinition->status,
            'version' => $officialDefinition->version,
            'signed_off_at' => optional($officialDefinition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $officialDefinition->signedOffBy?->only(['id', 'name']),
            'alignment_summary' => $officialDefinition->alignment_summary,
            'operational_status' => $officialDefinition->operational_status,
            'operational_status_notes' => $officialDefinition->operational_status_notes,
            'implementation_gaps' => $officialDefinition->implementation_gaps,
            'validation_steps' => $officialDefinition->validation_steps,
            'common_mismatch_causes' => $officialDefinition->common_mismatch_causes,
            'executive_takeaway' => $officialDefinition->executive_takeaway,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefinitionPayload(FinalDefinition $definition): array
    {
        $definition->loadMissing('ownerParticipant.user', 'signedOffBy');

        return [
            'id' => (string) $definition->getKey(),
            'status' => $definition->status,
            'version' => $definition->version,
            'definition_json' => $definition->definition_json ?? [],
            'notes' => $definition->notes,
            'owner_participant_id' => $definition->owner_participant_id
                ? (string) $definition->owner_participant_id
                : null,
            'owner_participant' => $definition->ownerParticipant ? [
                'id' => (string) $definition->ownerParticipant->getKey(),
                'responsibility' => $definition->ownerParticipant->responsibility,
                'user' => $definition->ownerParticipant->user?->only(['id', 'name', 'email']),
            ] : null,
            'owner' => null,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'alignment_summary' => $definition->alignment_summary,
            'operational_status' => $definition->operational_status,
            'operational_status_notes' => $definition->operational_status_notes,
            'implementation_gaps' => $definition->implementation_gaps,
            'validation_steps' => $definition->validation_steps,
            'common_mismatch_causes' => $definition->common_mismatch_causes,
        ];
    }

    private function resolveParticipantAffiliation(MetricParticipant $participant): ?string
    {
        $engagementParticipant = $participant->engagementParticipant;
        $affiliation = $engagementParticipant?->membership?->affiliation
            ?? $engagementParticipant?->pendingMembership?->affiliation;

        if (! is_string($affiliation)) {
            return null;
        }

        $affiliation = trim($affiliation);

        return $affiliation !== '' ? $affiliation : null;
    }
}
