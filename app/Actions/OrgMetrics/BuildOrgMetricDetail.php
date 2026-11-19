<?php

namespace App\Actions\OrgMetrics;

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\PendingMembership;
use App\Support\Metrics\FinalDefinitionProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildOrgMetricDetail
{
    /**
     * @return array<string, mixed>
     */
    public function handle(OrgMetric $orgMetric): array
    {
        $orgMetric->loadMissing([
            'organization:id,name',
            'canonicalMetric:id,key,name,description,is_active',
        ]);

        $templates = MetricTemplate::query()
            ->where('canonical_metric_id', $orgMetric->canonical_metric_id)
            ->orderBy('name')
            ->get();

        $templateUsage = Metric::query()
            ->select('metric_template_id', DB::raw('COUNT(DISTINCT engagement_id) as engagements_count'), DB::raw('MAX(updated_at) as last_used_at'))
            ->where('organization_id', $orgMetric->organization_id)
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereNotNull('metric_template_id')
            ->groupBy('metric_template_id')
            ->get()
            ->keyBy(fn ($row) => (string) $row->metric_template_id);

        $defaultTemplateId = $orgMetric->default_metric_template_id
            ? (string) $orgMetric->default_metric_template_id
            : null;

        $templateVariants = $templates->map(function (MetricTemplate $template) use ($templateUsage, $defaultTemplateId) {
            $usage = $templateUsage->get((string) $template->getKey());
            $lastUsedAt = $usage?->last_used_at;

            return [
                'id' => (string) $template->getKey(),
                'key' => $template->key,
                'name' => $template->name,
                'description' => $template->description,
                'engagements_count' => (int) ($usage->engagements_count ?? 0),
                'last_used_at' => $lastUsedAt
                    ? Carbon::parse($lastUsedAt)->toIso8601String()
                    : null,
                'is_default' => $defaultTemplateId !== null
                    && (string) $template->getKey() === $defaultTemplateId,
            ];
        })->values()->all();

        $activeEngagement = $this->resolveActiveEngagement($orgMetric);

        $participants = $orgMetric->participants()
            ->with(['user:id,name,email', 'subject'])
            ->get();

        $participantsPayload = $this->buildParticipantsPayload($orgMetric, $participants);

        $definitions = OrgFinalDefinition::query()
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

        $officialDefinition = $definitions->first();
        $history = $definitions;

        $historyPayload = $history
            ->filter(fn (FinalDefinition|OrgFinalDefinition $definition) => ! $officialDefinition
                || $definition->getKey() !== $officialDefinition->getKey())
            ->map(fn (FinalDefinition|OrgFinalDefinition $definition) => $this->buildHistoryEntry($definition))
            ->values()
            ->all();

        $canonical = $orgMetric->canonicalMetric;

        return [
            'org_metric' => [
                'id' => (string) $orgMetric->getKey(),
                'alias' => $orgMetric->alias,
                'key' => $orgMetric->key,
                'name' => $orgMetric->name,
                'default_metric_template_id' => $defaultTemplateId,
                'canonical' => [
                    'id' => $canonical ? (string) $canonical->getKey() : null,
                    'key' => $canonical?->key ?? $orgMetric->key,
                    'name' => $canonical?->name ?? $orgMetric->name,
                    'description' => $canonical?->description,
                    'is_active' => (bool) ($canonical?->is_active ?? false),
                ],
            ],
            'active_engagement' => $activeEngagement,
            'template_variants' => $templateVariants,
            'participants' => $participantsPayload,
            'official_definition' => $this->buildDefinitionPayload($officialDefinition),
            'official_definition_history' => $historyPayload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveActiveEngagement(OrgMetric $orgMetric): ?array
    {
        $statuses = array_merge(
            [EngagementStatus::DRAFT->value],
            array_map(static fn (EngagementStatus $status) => $status->value, EngagementStatus::activePhases())
        );

        $metric = Metric::query()
            ->select('metrics.*')
            ->join('engagements', 'engagements.id', '=', 'metrics.engagement_id')
            ->where('metrics.organization_id', $orgMetric->organization_id)
            ->where('metrics.org_metric_id', $orgMetric->getKey())
            ->whereIn('engagements.status', $statuses)
            ->with([
                'engagement:id,title,status',
                'template:id,form_schema_json',
                'definitions:id,metric_id,normalized_json,submitted_at,updated_at,created_at',
                'finalDefinition:id,metric_id,owner_participant_id,signed_off_at,definition_json',
                'finalDefinition.ownerParticipant:id,user_id',
                'finalDefinition.ownerParticipant.user:id,name',
            ])
            ->orderByDesc('engagements.updated_at')
            ->first();

        if (! $metric || ! $metric->engagement) {
            return null;
        }

        $engagementStatus = $metric->engagement->status instanceof EngagementStatus
            ? $metric->engagement->status->value
            : (string) $metric->engagement->status;

        return [
            'id' => (string) $metric->engagement->getKey(),
            'title' => $metric->engagement->title,
            'status' => $engagementStatus,
            'metric' => [
                'id' => (string) $metric->getKey(),
                'status' => $metric->status instanceof MetricStatus
                    ? $metric->status->value
                    : (string) $metric->status,
                'last_submitted_at' => optional($metric->last_submitted_at)->toIso8601String(),
                'definitions_changed_at' => optional($metric->definitions_changed_at)->toIso8601String(),
                'final_definition_progress' => FinalDefinitionProgress::forMetric($metric, $metric->finalDefinition),
            ],
        ];
    }

    /**
     * @param  Collection<int, OrgMetricParticipant>  $participants
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildParticipantsPayload(OrgMetric $orgMetric, Collection $participants): array
    {
        $filtered = $participants->filter(fn (OrgMetricParticipant $participant) => in_array(
            $participant->responsibility,
            ['owner', 'approver'],
            true
        ));

        $owners = $filtered
            ->where('responsibility', 'owner')
            ->map(fn (OrgMetricParticipant $participant) => $this->buildParticipantEntry($participant))
            ->values()
            ->all();

        $approvers = $filtered
            ->where('responsibility', 'approver')
            ->map(fn (OrgMetricParticipant $participant) => $this->buildParticipantEntry($participant))
            ->values()
            ->all();

        $memberships = Membership::query()
            ->where('organization_id', $orgMetric->organization_id)
            ->with('user:id,name,email')
            ->get();

        $pendingMemberships = PendingMembership::query()
            ->where('organization_id', $orgMetric->organization_id)
            ->orderBy('email')
            ->get();

        $options = $memberships
            ->map(fn (Membership $membership) => $this->buildMembershipOption($membership))
            ->merge($pendingMemberships->map(fn (PendingMembership $pending) => $this->buildPendingOption($pending)))
            ->sortBy('label')
            ->values()
            ->all();

        return [
            'owners' => $owners,
            'approvers' => $approvers,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParticipantEntry(OrgMetricParticipant $participant): array
    {
        $subject = $participant->subject;
        $label = $participant->user?->name
            ?? ($subject?->name ?? $subject?->email ?? 'Pending invite');
        $email = $participant->user?->email ?? $subject?->email;

        return [
            'id' => (string) $participant->getKey(),
            'participant_id' => $this->participantIdentifier($participant->subject_type, $participant->subject_id),
            'label' => $label,
            'email' => $email,
            'is_pending' => $participant->user_id === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMembershipOption(Membership $membership): array
    {
        $label = $membership->user?->name ?? $membership->user?->email ?? 'Member';

        return [
            'participant_id' => (string) $membership->getKey(),
            'label' => $label,
            'email' => $membership->user?->email,
            'is_pending' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPendingOption(PendingMembership $pending): array
    {
        $label = $pending->name ?? $pending->email ?? 'Pending invite';

        return [
            'participant_id' => 'pending:'.(string) $pending->getKey(),
            'label' => $label,
            'email' => $pending->email,
            'is_pending' => true,
        ];
    }

    private function participantIdentifier(?string $subjectType, ?string $subjectId): ?string
    {
        if (! $subjectId) {
            return null;
        }

        if ($subjectType === PendingMembership::class) {
            return 'pending:'.$subjectId;
        }

        return (string) $subjectId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildDefinitionPayload(FinalDefinition|OrgFinalDefinition|null $definition): ?array
    {
        if (! $definition) {
            return null;
        }

        $definition->loadMissing('signedOffBy:id,name', 'metric.engagement');

        return [
            'id' => (string) $definition->getKey(),
            'status' => $definition->status,
            'version' => $definition->version,
            'notes' => $definition->notes,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'metric' => $definition->metric ? [
                'id' => (string) $definition->metric->getKey(),
                'name' => $definition->metric->name,
                'engagement' => $definition->metric->engagement?->only(['id', 'title']),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoryEntry(FinalDefinition|OrgFinalDefinition $definition): array
    {
        $definition->loadMissing('signedOffBy:id,name', 'metric.engagement');

        return [
            'id' => (string) $definition->getKey(),
            'status' => $definition->status,
            'version' => $definition->version,
            'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
            'signed_off_by' => $definition->signedOffBy?->only(['id', 'name']),
            'metric' => $definition->metric ? [
                'id' => (string) $definition->metric->getKey(),
                'name' => $definition->metric->name,
                'engagement' => $definition->metric->engagement?->only(['id', 'title']),
            ] : null,
        ];
    }
}
