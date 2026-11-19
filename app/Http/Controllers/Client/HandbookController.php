<?php

namespace App\Http\Controllers\Client;

use App\Actions\Handbook\SelectOfficialMetrics;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSessionMetric;
use App\Models\FinalDefinition;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\ImpersonationGuard;
use App\Support\Metrics\DefinitionFieldValue;
use App\Support\Metrics\FinalDefinitionRequirements;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HandbookController extends Controller
{
    private const URGENCY_NEEDS_ATTENTION = 'needs_attention';

    private const URGENCY_IN_PROGRESS = 'in_progress';

    private const URGENCY_FINALIZED = 'finalized';

    public function __construct(
        private readonly SelectOfficialMetrics $officialSelector
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $isInternalStaff = Staff::isInternalStaff($user);
        $impersonatedOrganizationId = Impersonation::currentOrganizationId($request);
        $canUseClientMembershipContext = ! $isInternalStaff;

        if ($isInternalStaff && ! $impersonatedOrganizationId && ! $request->attributes->get('active_organization_id')) {
            return redirect()->route('dashboard');
        }

        $activeOrganizationId = $request->attributes->get('active_organization_id');

        $organization = null;

        if ($activeOrganizationId) {
            $organization = Organization::query()
                ->whereKey($activeOrganizationId)
                ->where('is_internal', false)
                ->first();

            $hasDirectClientMembership = $organization
                && $canUseClientMembershipContext
                && $user->memberships()
                    ->where('organization_id', $organization->id)
                    ->exists();

            if (! $organization || (! $impersonatedOrganizationId && ! $hasDirectClientMembership)) {
                Cookie::queue(Cookie::forget(Impersonation::ACTIVE_ORGANIZATION_COOKIE));
                $activeOrganizationId = null;
                $organization = null;
                $request->attributes->set('active_organization_id', null);
            }
        }

        if (! $activeOrganizationId && $canUseClientMembershipContext) {
            $preferredMembership = $user->memberships()
                ->whereHas('organization', fn ($query) => $query->where('is_internal', false))
                ->with('organization:id')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($preferredMembership?->organization) {
                $activeOrganizationId = (string) $preferredMembership->organization->id;
                $request->attributes->set('active_organization_id', $activeOrganizationId);
                Impersonation::queueActiveOrganizationCookie($activeOrganizationId);
                $organization = $preferredMembership->organization;
            }
        }

        if (! $activeOrganizationId) {
            abort(404, 'Select an organization to view the handbook.');
        }

        if (! $organization) {
            /** @var Organization|null $organization */
            $organization = Organization::query()
                ->whereKey($activeOrganizationId)
                ->where('is_internal', false)
                ->first();
        }

        if (! $organization) {
            abort(404);
        }

        $context = ImpersonationGuard::resolve($user, $organization, $request);

        if (! $context->impersonating() && ! $context->membership()) {
            abort(403);
        }

        $engagementOptions = $this->resolveEngagements($organization);
        $selectedEngagementId = $this->determineEngagementId($request, $engagementOptions);

        $metrics = $this->resolveMetrics($organization, $selectedEngagementId, $user, $context);
        $selectedEngagementSummary = $this->buildSelectedEngagementSummary(
            $organization,
            $engagementOptions,
            $selectedEngagementId
        );

        return Inertia::render('Client/Handbook', [
            'organization' => $organization->only(['id', 'name']),
            'engagements' => $engagementOptions->map(fn (Engagement $engagement) => [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus ? $engagement->status->value : (string) $engagement->status,
            ]),
            'activeEngagementId' => $selectedEngagementId,
            'selected_engagement_summary' => $selectedEngagementSummary,
            'metrics' => $metrics->values()->all(),
        ]);
    }

    /**
     * @return Collection<int, Engagement>
     */
    protected function resolveEngagements(Organization $organization): Collection
    {
        return $organization->engagements()
            ->newQuery()
            ->select(['id', 'title', 'status', 'review_cadence', 'next_review_on'])
            ->orderBy('title')
            ->get();
    }

    protected function determineEngagementId(
        Request $request,
        Collection $engagements
    ): ?string {
        $requested = (string) $request->query('engagement', '');
        $requested = $requested !== '' ? $requested : null;

        if ($requested && $engagements->contains(fn (Engagement $engagement) => (string) $engagement->id === $requested)) {
            return $requested;
        }

        return null;
    }

    /**
     * @param  Collection<int, Engagement>  $engagements
     * @return array{
     *   engagement_id:string,
     *   engagement_title:?string,
     *   next_review_on:?string,
     *   review_cadence:?string,
     *   operational_ready_count:int,
     *   needs_follow_up_count:int,
     *   implementation_gap_count:int
     * }|null
     */
    protected function buildSelectedEngagementSummary(
        Organization $organization,
        Collection $engagements,
        ?string $engagementId
    ): ?array {
        if (! $engagementId) {
            return null;
        }

        /** @var Engagement|null $engagement */
        $engagement = $engagements->first(
            fn (Engagement $candidate): bool => (string) $candidate->getKey() === $engagementId
        );

        if (! $engagement) {
            return null;
        }

        $candidateMetrics = Metric::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->getKey())
            ->where('status', MetricStatus::FINALIZED->value)
            ->get();

        $officialMetrics = $this->officialSelector->handle($organization, $candidateMetrics);
        $operationalReadyCount = 0;
        $needsFollowUpCount = 0;
        $implementationGapCount = 0;

        foreach ($officialMetrics as $metric) {
            $officialDefinition = $this->resolveOfficialDefinition($metric);

            if (! $officialDefinition) {
                continue;
            }

            if ($officialDefinition->operational_status === 'operationally_ready') {
                $operationalReadyCount++;
            }

            if ($officialDefinition->operational_status === 'needs_follow_up') {
                $needsFollowUpCount++;
            }

            if ($this->hasImplementationGap($officialDefinition->implementation_gaps)) {
                $implementationGapCount++;
            }
        }

        return [
            'engagement_id' => (string) $engagement->getKey(),
            'engagement_title' => $engagement->title,
            'next_review_on' => optional($engagement->next_review_on)->toDateString(),
            'review_cadence' => $engagement->review_cadence,
            'operational_ready_count' => $operationalReadyCount,
            'needs_follow_up_count' => $needsFollowUpCount,
            'implementation_gap_count' => $implementationGapCount,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolveMetrics(
        Organization $organization,
        ?string $engagementId,
        User $user,
        ImpersonationGuard $context
    ): Collection {
        $activeEngagementStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::activePhases()
        );
        $listEngagementStatuses = array_values(array_unique(array_merge(
            $activeEngagementStatuses,
            [EngagementStatus::FINALIZED->value]
        )));
        $engagementFilterStatuses = $engagementId
            ? $listEngagementStatuses
            : $activeEngagementStatuses;

        $query = OrgMetric::query()
            ->where('organization_id', $organization->id)
            ->where(function ($orgMetricQuery) use ($engagementId, $engagementFilterStatuses) {
                $orgMetricQuery->whereHas('metrics', function ($metricQuery) use ($engagementId, $engagementFilterStatuses) {
                    $metricQuery
                        ->whereHas('engagement', function ($engagementQuery) use ($engagementFilterStatuses) {
                            $engagementQuery->whereIn('status', $engagementFilterStatuses);
                        })
                        ->when(
                            $engagementId,
                            fn ($query) => $query->where('engagement_id', $engagementId)
                        );
                });

                if (! $engagementId) {
                    $orgMetricQuery->orWhereHas('finalDefinitions', function ($definitionQuery) {
                        $definitionQuery->whereNotNull('signed_off_at');
                    });
                }
            })
            ->with([
                'canonicalMetric:id,name',
                'metrics' => function ($metricQuery) use (
                    $engagementId,
                    $listEngagementStatuses
                ) {
                    $metricQuery
                        ->select([
                            'id',
                            'org_metric_id',
                            'organization_id',
                            'engagement_id',
                            'metric_template_id',
                            'name',
                            'status',
                            'last_submitted_at',
                            'recompute_status',
                            'recompute_queued_at',
                            'recompute_finished_at',
                            'definitions_changed_at',
                        ])
                        ->whereHas(
                            'engagement',
                            fn ($engagementQuery) => $engagementQuery->whereIn('status', $listEngagementStatuses)
                        )
                        ->when(
                            $engagementId,
                            fn ($query) => $query->where('engagement_id', $engagementId)
                        )
                        ->with([
                            'engagement:id,title,status',
                            'template:id,form_schema_json',
                            'finalDefinition.ownerParticipant.user',
                            'participants' => function ($participantQuery) {
                                $participantQuery->select(['id', 'metric_id', 'user_id', 'responsibility']);
                            },
                        ]);
                },
            ])
            ->orderBy('name')
            ->get();

        $metricIds = $query
            ->flatMap(fn (OrgMetric $orgMetric) => $orgMetric->metrics->pluck('id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $assignmentsByMetricId = $this->resolveFormAssignments($metricIds, $user);
        $assignmentCountsByMetricId = $this->resolveContributorAssignmentCounts($metricIds);
        $openAssignmentsByMetricId = collect($assignmentCountsByMetricId)
            ->map(fn (array $counts): int => max(0, $counts['total'] - $counts['submitted']))
            ->all();
        $workshopSessionMetricIds = $this->resolveWorkshopSessionMetricIds($organization, $metricIds);
        $inProgressWorkshopSessionMetricIds = $this->resolveInProgressWorkshopSessionMetricIds($organization, $metricIds);

        return $query
            ->map(function (OrgMetric $orgMetric) use (
                $organization,
                $assignmentsByMetricId,
                $assignmentCountsByMetricId,
                $openAssignmentsByMetricId,
                $workshopSessionMetricIds,
                $inProgressWorkshopSessionMetricIds,
                $user,
                $context
            ) {
                $metric = $this->selectHandbookMetric($orgMetric->metrics);
                $final = $metric?->finalDefinition;
                $needsAttention = $metric ? $this->resolveNeedsAttention($metric, $final) : false;
                $urgency = $metric ? $this->resolveUrgency($metric, $needsAttention) : null;
                $assignmentCounts = $metric ? ($assignmentCountsByMetricId[(string) $metric->getKey()] ?? null) : null;
                $stageLabel = $metric ? $this->resolveStageLabel($metric, $final, $assignmentCounts) : null;
                $canonicalName = $orgMetric->canonicalMetric?->name ?? $orgMetric->name;

                return [
                    'id' => (string) $orgMetric->getKey(),
                    'alias' => $orgMetric->alias,
                    'name' => $canonicalName,
                    'engagement_metric_id' => $metric ? (string) $metric->getKey() : null,
                    'status' => $metric
                        ? ($metric->status instanceof MetricStatus ? $metric->status->value : (string) $metric->status)
                        : null,
                    'urgency' => $urgency,
                    'needs_attention' => $needsAttention,
                    'stage_label' => $stageLabel,
                    'engagement' => $metric?->engagement?->only(['id', 'title']),
                    'owner' => $this->resolveOwnerField($final),
                    'signed_off_at' => optional($final?->signed_off_at)->toIso8601String(),
                    'actions' => $this->buildMetricActions(
                        $organization,
                        $orgMetric,
                        $metric,
                        $user,
                        $context,
                        $assignmentsByMetricId,
                        $openAssignmentsByMetricId,
                        $workshopSessionMetricIds,
                        $inProgressWorkshopSessionMetricIds
                    ),
                ];
            })
            ->sort(function (array $left, array $right): int {
                $rank = $this->urgencyRank((string) ($left['urgency'] ?? '')) <=> $this->urgencyRank((string) ($right['urgency'] ?? ''));

                if ($rank !== 0) {
                    return $rank;
                }

                $leftName = $left['alias'] ?: $left['name'];
                $rightName = $right['alias'] ?: $right['name'];

                return strcasecmp($leftName, $rightName);
            })
            ->values();
    }

    protected function resolveOfficialDefinition(Metric $metric): FinalDefinition|OrgFinalDefinition|null
    {
        $officialDefinition = $metric->getRelationValue('officialDefinition');

        if ($officialDefinition instanceof FinalDefinition || $officialDefinition instanceof OrgFinalDefinition) {
            return $officialDefinition;
        }

        $fallback = $metric->finalDefinition;

        return $fallback instanceof FinalDefinition ? $fallback : null;
    }

    protected function hasImplementationGap(mixed $implementationGaps): bool
    {
        if (! is_array($implementationGaps)) {
            return false;
        }

        return collect($implementationGaps)
            ->contains(fn (mixed $value): bool => DefinitionFieldValue::isFilled($value));
    }

    /**
     * @param  Collection<int, string>  $metricIds
     * @return array<string, array{form_id: string, submitted_at: string|null}>
     */
    protected function resolveFormAssignments(Collection $metricIds, User $user): array
    {
        if ($metricIds->isEmpty()) {
            return [];
        }

        $assignments = FormAssignment::query()
            ->select([
                'form_assignment.metric_id',
                'form_assignment.form_id',
                'form_assignment.user_id',
                'form_submissions.submitted_at as submission_submitted_at',
            ])
            ->leftJoin('form_submissions', function ($join): void {
                $join->on('form_submissions.form_id', '=', 'form_assignment.form_id')
                    ->on('form_submissions.user_id', '=', 'form_assignment.user_id');
            })
            ->where('form_assignment.user_id', $user->id)
            ->where('form_assignment.responsibility', 'Contributor')
            ->whereIn('form_assignment.metric_id', $metricIds->all())
            ->whereIn('form_assignment.status', [
                FormAssignmentStatus::OPEN->value,
                FormAssignmentStatus::IN_PROGRESS->value,
                FormAssignmentStatus::REOPENED->value,
                FormAssignmentStatus::COMPLETED->value,
            ])
            ->orderByRaw(
                'CASE form_assignment.status WHEN ? THEN 0 WHEN ? THEN 0 WHEN ? THEN 0 ELSE 1 END',
                [
                    FormAssignmentStatus::OPEN->value,
                    FormAssignmentStatus::IN_PROGRESS->value,
                    FormAssignmentStatus::REOPENED->value,
                ]
            )
            ->orderByRaw('COALESCE(form_assignment.due_at, form_assignment.created_at) ASC')
            ->get();

        $byMetricId = [];

        foreach ($assignments as $assignment) {
            $metricId = (string) $assignment->metric_id;

            if ($metricId === '' || isset($byMetricId[$metricId])) {
                continue;
            }

            $byMetricId[$metricId] = [
                'form_id' => (string) $assignment->form_id,
                'submitted_at' => $assignment->submission_submitted_at
                    ? (string) $assignment->submission_submitted_at
                    : null,
            ];
        }

        return $byMetricId;
    }

    /**
     * @param  Collection<int, string>  $metricIds
     * @return array<string, array{submitted: int, total: int}>
     */
    protected function resolveContributorAssignmentCounts(Collection $metricIds): array
    {
        if ($metricIds->isEmpty()) {
            return [];
        }

        $rows = FormAssignment::query()
            ->selectRaw(
                'metric_id, COUNT(*) as total_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as submitted_count',
                [FormAssignmentStatus::COMPLETED->value]
            )
            ->whereIn('metric_id', $metricIds->all())
            ->where('responsibility', 'Contributor')
            ->groupBy('metric_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $metricId = (string) $row->metric_id;

            if ($metricId === '') {
                continue;
            }

            $counts[$metricId] = [
                'submitted' => (int) ($row->submitted_count ?? 0),
                'total' => (int) ($row->total_count ?? 0),
            ];
        }

        return $counts;
    }

    /**
     * @param  array<string, array{form_id: string, submitted_at: string|null}>  $assignmentsByMetricId
     * @param  array<string, int>  $openAssignmentsByMetricId
     * @param  array<string, bool>  $workshopSessionMetricIds
     * @param  array<string, bool>  $inProgressWorkshopSessionMetricIds
     * @return array<int, array{key: string, label: string, href: string, disabled?: bool, disabled_reason?: string|null, workshop_live?: bool}>
     */
    protected function buildMetricActions(
        Organization $organization,
        OrgMetric $orgMetric,
        ?Metric $metric,
        User $user,
        ImpersonationGuard $context,
        array $assignmentsByMetricId,
        array $openAssignmentsByMetricId,
        array $workshopSessionMetricIds,
        array $inProgressWorkshopSessionMetricIds
    ): array {
        if (! $metric || ! $metric->engagement_id) {
            return [];
        }

        $actions = [];
        $metricId = (string) $metric->getKey();
        $assignment = $assignmentsByMetricId[$metricId] ?? null;

        if ($assignment && empty($assignment['submitted_at'])) {
            $actions[] = [
                'key' => 'open_form',
                'label' => 'Open form',
                'href' => route('forms.show', ['form' => $assignment['form_id']]),
            ];
        }

        $engagementStatus = $metric->engagement?->status;
        $resolvedEngagementStatus = $engagementStatus instanceof EngagementStatus
            ? $engagementStatus
            : EngagementStatus::tryFromLegacy($engagementStatus !== null ? (string) $engagementStatus : null);
        $metricStatus = $metric->status instanceof MetricStatus
            ? $metric->status
            : MetricStatus::tryFrom((string) $metric->status);
        $isInternalStaff = Staff::isInternalStaff($user);
        $hasWorkshopSession = (bool) ($workshopSessionMetricIds[$metricId] ?? false);

        $showWorkshopAction = $resolvedEngagementStatus === EngagementStatus::IN_WORKSHOP
            && $metricStatus !== MetricStatus::FINALIZED
            && ($isInternalStaff || $hasWorkshopSession);

        $statusGateReason = null;
        if (! $isInternalStaff) {
            if ($resolvedEngagementStatus !== EngagementStatus::IN_WORKSHOP) {
                $statusGateReason = 'Workshop access is available once the engagement is in workshop.';
            } elseif (! $hasWorkshopSession) {
                $statusGateReason = 'Workshop session is not scheduled for this metric.';
            }
        }

        $readinessReason = null;
        if ($resolvedEngagementStatus === EngagementStatus::IN_WORKSHOP && $hasWorkshopSession) {
            $openAssignments = $openAssignmentsByMetricId[$metricId] ?? 0;
            $readinessReason = $openAssignments > 0
                ? 'Waiting on form submissions for this metric.'
                : null;
        }

        $disabledReason = $statusGateReason ?? $readinessReason;

        if ($showWorkshopAction && (! $disabledReason || $isInternalStaff)) {
            $actions[] = [
                'key' => 'workshop',
                'label' => 'Workshop',
                'href' => route('client.handbook.workshop', [
                    'organization' => $organization->getKey(),
                    'engagement' => $metric->engagement_id,
                    'metric' => $metricId,
                ]),
                'disabled' => $disabledReason !== null,
                'disabled_reason' => $disabledReason,
                'workshop_live' => (bool) ($inProgressWorkshopSessionMetricIds[$metricId] ?? false),
            ];
        }

        $finalDefinition = $metric->finalDefinition;
        $isSignedOff = (bool) ($finalDefinition?->signed_off_at);

        $metricPageUrl = route('client.handbook.metrics.page', [
            'organization' => $organization->getKey(),
            'engagement' => $metric->engagement_id,
            'orgMetric' => $orgMetric->getKey(),
        ]);

        $canManage = $context->allowsAtLeast(MembershipRole::ADMIN);
        $canReassignOwner = $canManage && ! $isSignedOff;

        if ($canReassignOwner) {
            $actions[] = [
                'key' => 'reassign_owner',
                'label' => 'Reassign owner',
                'href' => $metricPageUrl.'#role-actions',
            ];
        }

        return $actions;
    }

    /**
     * @param  Collection<int, string>  $metricIds
     * @return array<string, bool>
     */
    protected function resolveWorkshopSessionMetricIds(
        Organization $organization,
        Collection $metricIds
    ): array {
        return $this->resolveWorkshopSessionMetricIdsByStatus(
            $organization,
            $metricIds,
            [
                EngagementWorkshopSessionStatus::SCHEDULED->value,
                EngagementWorkshopSessionStatus::IN_PROGRESS->value,
                EngagementWorkshopSessionStatus::COMPLETED->value,
            ]
        );
    }

    /**
     * @param  Collection<int, string>  $metricIds
     * @return array<string, bool>
     */
    protected function resolveInProgressWorkshopSessionMetricIds(
        Organization $organization,
        Collection $metricIds
    ): array {
        return $this->resolveWorkshopSessionMetricIdsByStatus(
            $organization,
            $metricIds,
            [EngagementWorkshopSessionStatus::IN_PROGRESS->value]
        );
    }

    /**
     * @param  Collection<int, string>  $metricIds
     * @param  array<int, string>  $sessionStatuses
     * @return array<string, bool>
     */
    protected function resolveWorkshopSessionMetricIdsByStatus(
        Organization $organization,
        Collection $metricIds,
        array $sessionStatuses
    ): array {
        if ($metricIds->isEmpty()) {
            return [];
        }

        return EngagementWorkshopSessionMetric::query()
            ->select('engagement_workshop_session_metrics.metric_id')
            ->join(
                'engagement_workshop_sessions',
                'engagement_workshop_sessions.id',
                '=',
                'engagement_workshop_session_metrics.session_id'
            )
            ->where('engagement_workshop_sessions.organization_id', $organization->getKey())
            ->whereIn('engagement_workshop_session_metrics.metric_id', $metricIds->all())
            ->whereIn('engagement_workshop_sessions.status', $sessionStatuses)
            ->pluck('engagement_workshop_session_metrics.metric_id')
            ->map(fn ($metricId) => (string) $metricId)
            ->unique()
            ->mapWithKeys(fn (string $metricId): array => [$metricId => true])
            ->all();
    }

    protected function selectHandbookMetric(Collection $metrics): ?Metric
    {
        if ($metrics->isEmpty()) {
            return null;
        }

        return $metrics
            ->sort(function (Metric $left, Metric $right): int {
                $leftUrgency = $this->resolveUrgency($left, $this->resolveNeedsAttention($left, $left->finalDefinition));
                $rightUrgency = $this->resolveUrgency($right, $this->resolveNeedsAttention($right, $right->finalDefinition));

                $rank = $this->urgencyRank($leftUrgency) <=> $this->urgencyRank($rightUrgency);

                if ($rank !== 0) {
                    return $rank;
                }

                return strcasecmp($left->name, $right->name);
            })
            ->first();
    }

    protected function resolveOwnerField(?FinalDefinition $finalDefinition): ?array
    {
        if ($finalDefinition?->owner_participant_id && $finalDefinition->ownerParticipant) {
            return [
                'key' => 'owner_participant',
                'label' => 'Metric Owner',
                'value' => $finalDefinition->ownerParticipant->user?->name ?? 'Pending invite',
                'participant_id' => (string) $finalDefinition->ownerParticipant->getKey(),
                'user' => $finalDefinition->ownerParticipant->user?->only(['id', 'name', 'email']),
            ];
        }

        return $finalDefinition ? $this->extractOwnerField($finalDefinition->definition_json ?? []) : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $definition
     * @return array{key: string, label: string, value: mixed}|null
     */
    protected function extractOwnerField(array $definition): ?array
    {
        foreach ($definition as $key => $field) {
            if (! is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? Str::headline((string) $key);
            $value = $field['value'] ?? null;
            $normalizedKey = Str::of((string) $key)->lower()->value();
            $normalizedLabel = Str::of($label)->lower()->value();

            if (str_contains($normalizedKey, 'owner') || str_contains($normalizedLabel, 'owner')) {
                return [
                    'key' => (string) $key,
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return null;
    }

    protected function resolveNeedsAttention(Metric $metric, ?FinalDefinition $finalDefinition): bool
    {
        $status = $metric->status instanceof MetricStatus
            ? $metric->status
            : MetricStatus::tryFrom((string) $metric->status);

        if (in_array($status, [MetricStatus::FINALIZED, MetricStatus::DEPRECATED], true)) {
            return false;
        }

        if (! $finalDefinition) {
            return false;
        }

        $readiness = $this->resolveDefinitionReadiness($metric, $finalDefinition);

        return ! $readiness['is_ready'];
    }

    protected function resolveUrgency(Metric $metric, bool $needsAttention): string
    {
        $status = $metric->status instanceof MetricStatus
            ? $metric->status
            : MetricStatus::tryFrom((string) $metric->status);

        if (in_array($status, [MetricStatus::FINALIZED, MetricStatus::DEPRECATED], true)) {
            return self::URGENCY_FINALIZED;
        }

        return $needsAttention ? self::URGENCY_NEEDS_ATTENTION : self::URGENCY_IN_PROGRESS;
    }

    /**
     * @return array{is_ready: bool, has_consensus_values: bool, has_owner: bool, has_approvers: bool}
     */
    protected function resolveDefinitionReadiness(Metric $metric, FinalDefinition $finalDefinition): array
    {
        $requirements = FinalDefinitionRequirements::forMetric($metric, $finalDefinition);
        $missingKeys = $requirements['missing_keys'] ?? [];
        $hasConsensusValues = empty($missingKeys);
        $hasOwner = (bool) ($requirements['owner_eligible'] ?? false);
        $hasApprovers = $metric->participants
            ->filter(fn ($participant) => $participant->responsibility === 'approver' && filled($participant->user_id))
            ->isNotEmpty();

        return [
            'is_ready' => $hasConsensusValues && $hasOwner,
            'has_consensus_values' => $hasConsensusValues,
            'has_owner' => $hasOwner,
            'has_approvers' => $hasApprovers,
        ];
    }

    /**
     * @param  array{submitted: int, total: int}|null  $assignmentCounts
     */
    protected function resolveStageLabel(
        Metric $metric,
        ?FinalDefinition $finalDefinition,
        ?array $assignmentCounts = null
    ): string {
        $status = $metric->status instanceof MetricStatus
            ? $metric->status
            : MetricStatus::tryFrom((string) $metric->status);

        if (in_array($status, [MetricStatus::FINALIZED, MetricStatus::DEPRECATED], true)) {
            return 'Signed off';
        }

        if ($finalDefinition?->signed_off_at) {
            return 'Signed off';
        }

        if ($finalDefinition?->status === 'PendingApproval') {
            return 'Sign-off pending';
        }

        if (! $finalDefinition) {
            return $this->formatCollectingLabel($assignmentCounts);
        }

        $readiness = $this->resolveDefinitionReadiness($metric, $finalDefinition);

        if ($readiness['is_ready']) {
            return 'Ready for sign-off';
        }

        if ($finalDefinition->consensus_started_at) {
            return 'Reconciling inputs';
        }

        return $this->formatCollectingLabel($assignmentCounts);
    }

    /**
     * @param  array{submitted: int, total: int}|null  $assignmentCounts
     */
    protected function formatCollectingLabel(?array $assignmentCounts): string
    {
        if (! $assignmentCounts || $assignmentCounts['total'] <= 0) {
            return 'Collecting inputs';
        }

        $submitted = $assignmentCounts['submitted'];
        $total = $assignmentCounts['total'];

        return sprintf('Collecting inputs %d/%d', $submitted, $total);
    }

    protected function urgencyRank(string $urgency): int
    {
        return match ($urgency) {
            self::URGENCY_NEEDS_ATTENTION => 0,
            self::URGENCY_IN_PROGRESS => 1,
            self::URGENCY_FINALIZED => 2,
            default => 3,
        };
    }
}
