<?php

namespace App\Http\Controllers\Organization;

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\FinalDefinition;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\NotificationAttempt;
use App\Models\Organization;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Support\Engagements\EngagementRailReadiness;
use App\Support\Metrics\FinalDefinitionProgress;
use App\Support\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EngagementShowController extends Controller
{
    public function __invoke(Organization $organization, Engagement $engagement): Response
    {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $user = request()->user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('view', $engagement);

        $canManageEngagement = Gate::allows('update', $engagement);

        $forms = $engagement->forms()
            ->when(! $canManageEngagement && $user, fn ($query) => $query
                ->whereHas('assignments', fn ($assignmentQuery) => $assignmentQuery->where('user_id', $user->id)))
            ->orderByDesc('created_at')
            ->get([
                'id',
                'title',
                'status',
                'metric_id',
                'template_key',
                'created_at',
            ]);

        $metrics = $engagement->metrics()
            ->when(! $canManageEngagement && $user, fn ($query) => $query
                ->whereHas('participants', fn ($participantQuery) => $participantQuery->where('user_id', $user->id)))
            ->with([
                'template:id,name,key,form_schema_json',
                'definitions:id,metric_id,normalized_json,submitted_at,updated_at,created_at',
                'finalDefinition:id,metric_id,status,signed_off_at,signed_off_by,owner_participant_id,definition_json',
                'finalDefinition.signedOffBy:id,name',
                'finalDefinition.ownerParticipant:id,user_id',
                'finalDefinition.ownerParticipant.user:id,name',
                'participants:id,metric_id,engagement_participant_id,user_id,responsibility,role_tags_json',
                'participants.engagementParticipant:id,engagement_id,participant_type,membership_id,pending_membership_id,membership_role,stakeholder_role,active',
                'participants.engagementParticipant.membership:id,organization_id,user_id,role,affiliation',
                'participants.engagementParticipant.membership.user:id,name,email',
                'participants.engagementParticipant.pendingMembership:id,name,email,affiliation',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'status',
                'metric_template_id',
                'last_submitted_at',
            ]);

        $metricStatusCounts = collect(MetricStatus::cases())
            ->mapWithKeys(fn (MetricStatus $status) => [$status->value => 0])
            ->all();

        foreach ($metrics as $metric) {
            $status = $metric->status instanceof MetricStatus ? $metric->status->value : (string) $metric->status;
            $metricStatusCounts[$status] = ($metricStatusCounts[$status] ?? 0) + 1;
        }

        $workshopScheduleReadinessByMetricId = $this->resolveWorkshopScheduleReadinessByMetric($metrics);

        $hasDiscrepancies = $engagement->discrepancies()
            ->when(! $canManageEngagement && $user, fn ($query) => $query
                ->whereHas('metric.participants', fn ($participantQuery) => $participantQuery->where('user_id', $user->id)))
            ->exists();

        $workshops = $canManageEngagement
            ? $engagement->workshopSessions()
                ->with([
                    'facilitatorMembership.user:id,name',
                    'metrics:id,session_id,metric_id',
                    'participants:id,session_id,engagement_participant_id',
                ])
                ->withCount([
                    'metrics as metric_count',
                    'participants as participant_count',
                ])
                ->orderBy('starts_at')
                ->get([
                    'id',
                    'organization_id',
                    'engagement_id',
                    'facilitator_membership_id',
                    'starts_at',
                    'ends_at',
                    'timezone',
                    'status',
                    'notes',
                ])
                ->map(fn ($session) => [
                    'id' => (string) $session->id,
                    'organization_id' => (string) $session->organization_id,
                    'engagement_id' => (string) $session->engagement_id,
                    'status' => $session->status?->value ?? (string) $session->status,
                    'facilitator_membership_id' => $session->facilitator_membership_id
                        ? (string) $session->facilitator_membership_id
                        : null,
                    'facilitator_name' => $session->facilitatorMembership?->user?->name,
                    'starts_at' => optional($session->starts_at)?->toIso8601String(),
                    'ends_at' => optional($session->ends_at)?->toIso8601String(),
                    'timezone' => (string) $session->timezone,
                    'notes' => $session->notes,
                    'metric_ids' => $session->metrics
                        ->map(fn ($metricLink) => (string) $metricLink->metric_id)
                        ->values()
                        ->all(),
                    'participant_ids' => $session->participants
                        ->map(fn ($participantLink) => (string) $participantLink->engagement_participant_id)
                        ->values()
                        ->all(),
                    'metric_count' => (int) ($session->metric_count ?? $session->metrics->count()),
                    'participant_count' => (int) ($session->participant_count ?? $session->participants->count()),
                ])
                ->values()
                ->all()
            : [];

        $activity = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('subject_type', Engagement::class)
            ->where('subject_id', $engagement->id)
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => (string) $log->id,
                'action' => $log->action,
                'created_at' => optional($log->created_at)->toIso8601String(),
                'summary' => $log->properties['summary'] ?? null,
                'user' => $log->user ? [
                    'id' => (string) $log->user->id,
                    'name' => $log->user->name,
                ] : null,
            ])
            ->values()
            ->all();

        $formSummary = $this->buildContributorFormSummary($forms);
        $formItems = $this->attachViewerSubmissionAccess(
            $formSummary['items'],
            $this->resolveViewerContributorFormIds($user, $forms)
        );
        $formStatusCounts = $formSummary['status_counts'];

        $formsPayload = [
            'total' => $forms->count(),
            'status_counts' => array_map('intval', $formStatusCounts),
            'last_submission_at' => $formSummary['last_submission_at'],
            'items' => $formItems,
            'templates' => MetricTemplate::query()
                ->orderBy('name')
                ->get(['key', 'name'])
                ->values(),
            'members' => $organization->memberships()
                ->with(['user:id,name'])
                ->get()
                ->map(fn ($membership) => [
                    'id' => (string) $membership->user->id,
                    'name' => $membership->user->name,
                ])
                ->values(),
            'can_create' => Gate::allows('update', $organization),
            'refresh_url' => route('org.engagements.forms.index', [
                'organization' => $organization,
                'engagement' => $engagement,
            ]),
        ];

        $engagementParticipants = $engagement->participants()
            ->with([
                'membership.user:id,name,email',
                'pendingMembership:id,name,email,affiliation',
            ])
            ->orderByDesc('active')
            ->orderBy('participant_type')
            ->get();

        $participantMembershipIds = $engagementParticipants
            ->where('participant_type', EngagementParticipant::TYPE_MEMBERSHIP)
            ->pluck('membership_id')
            ->filter()
            ->map(fn ($membershipId) => (string) $membershipId)
            ->all();

        $staffCandidateOptions = $organization->memberships()
            ->with('user:id,name,email')
            ->get()
            ->filter(function (Membership $membership) use ($participantMembershipIds) {
                $membershipId = (string) $membership->id;
                $memberUser = $membership->user;

                return $memberUser
                    && Staff::isStaff($memberUser)
                    && ! in_array($membershipId, $participantMembershipIds, true);
            })
            ->map(fn (Membership $membership) => [
                'id' => 'membership:'.$membership->id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'affiliation' => $membership->affiliation,
                'type' => EngagementParticipant::TYPE_MEMBERSHIP,
                'is_active' => true,
                'is_pending' => false,
                'is_staff' => true,
                'membership_role' => $membership->role?->value,
                'stakeholder_role' => null,
            ])
            ->values();

        $assignmentOptions = $engagementParticipants
            ->map(function (EngagementParticipant $participant) {
                $user = $participant->membership?->user;

                return [
                    'id' => (string) $participant->id,
                    'name' => $user?->name
                        ?? $participant->pendingMembership?->name
                        ?? $participant->pendingMembership?->email
                        ?? null,
                    'email' => $user?->email ?? $participant->pendingMembership?->email,
                    'affiliation' => $participant->membership?->affiliation
                        ?? $participant->pendingMembership?->affiliation,
                    'type' => $participant->participant_type,
                    'is_active' => (bool) $participant->active,
                    'is_pending' => $participant->participant_type === EngagementParticipant::TYPE_PENDING,
                    'is_staff' => $user ? Staff::isInternalStaff($user) : false,
                    'membership_role' => $participant->membership_role?->value,
                    'stakeholder_role' => $participant->stakeholder_role?->value,
                ];
            })
            ->concat($staffCandidateOptions)
            ->sortBy([
                ['is_active', 'desc'],
                ['name', 'asc'],
                ['email', 'asc'],
            ])
            ->values()
            ->all();

        $metricItems = $metrics
            ->map(fn ($metric) => [
                'id' => (string) $metric->id,
                'name' => $metric->name,
                'status' => $metric->status instanceof MetricStatus ? $metric->status->value : (string) $metric->status,
                'last_submitted_at' => optional($metric->last_submitted_at)->toIso8601String(),
                'workshop_schedule_readiness' => $workshopScheduleReadinessByMetricId[(string) $metric->id] ?? [
                    'state' => 'missing_forms',
                    'forms_total' => 0,
                    'forms_ready' => 0,
                ],
                'template' => $metric->template ? [
                    'key' => $metric->template->key,
                    'name' => $metric->template->name,
                ] : null,
                'final_definition' => $metric->finalDefinition ? [
                    'status' => $metric->finalDefinition->status,
                    'signed_off_at' => optional($metric->finalDefinition->signed_off_at)->toIso8601String(),
                    'signed_off_by' => $metric->finalDefinition->signedOffBy ? [
                        'id' => (string) $metric->finalDefinition->signedOffBy->id,
                        'name' => $metric->finalDefinition->signedOffBy->name,
                    ] : null,
                ] : null,
                'final_definition_progress' => FinalDefinitionProgress::forMetric($metric, $metric->finalDefinition),
                'participants' => $metric->participants
                    ->map(function ($participant) {
                        $assignee = $participant->engagementParticipant;
                        $membership = $assignee?->membership;
                        $pendingMembership = $assignee?->pendingMembership;

                        return [
                            'id' => (string) $participant->id,
                            'responsibility' => (string) $participant->responsibility,
                            'role_tags' => $participant->role_tags_json ?? [],
                            'assignee' => $assignee ? [
                                'id' => (string) $assignee->id,
                                'name' => $membership?->user?->name
                                    ?? $pendingMembership?->name
                                    ?? $pendingMembership?->email
                                    ?? null,
                                'email' => $membership?->user?->email ?? $pendingMembership?->email,
                                'affiliation' => $membership?->affiliation
                                    ?? $pendingMembership?->affiliation,
                                'type' => $assignee->participant_type,
                                'is_active' => (bool) $assignee->active,
                                'is_pending' => $assignee->participant_type === EngagementParticipant::TYPE_PENDING,
                                'membership_role' => $assignee->membership_role?->value,
                                'stakeholder_role' => $assignee->stakeholder_role?->value,
                            ] : null,
                        ];
                    })
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $metricsPayload = [
            'total' => $metrics->count(),
            'status_counts' => array_map('intval', $metricStatusCounts),
            'items' => $metricItems,
            'assignment_options' => $assignmentOptions,
        ];

        $allFormsCompleted = $forms->isNotEmpty()
            && $formStatusCounts['Completed'] === $forms->count();
        $metricsReadyStates = [
            MetricStatus::PROPOSED->value,
            MetricStatus::AWAITING_FORMS->value,
        ];
        $metricsStatuses = collect($metricItems)->pluck('status')->all();
        $hasBlockedMetrics = collect($metricsStatuses)
            ->contains(fn (string $status) => in_array($status, $metricsReadyStates, true));
        $hasWorkshopCandidates = collect($metricsStatuses)
            ->contains(fn (string $status) => in_array($status, [
                MetricStatus::IN_REVIEW->value,
                MetricStatus::IN_WORKSHOP->value,
            ], true))
            || $hasDiscrepancies;
        $hasActiveAssignments = collect($metricItems)
            ->every(function (array $metric) {
                $participants = $metric['participants'] ?? [];
                if (empty($participants)) {
                    return false;
                }

                foreach ($participants as $participant) {
                    $assignee = $participant['assignee'] ?? null;
                    if (! $assignee) {
                        continue;
                    }

                    if (($assignee['is_active'] ?? false) && ! ($assignee['is_pending'] ?? false)) {
                        return true;
                    }
                }

                return false;
            });
        $workshopReady = $metrics->isNotEmpty()
            && $allFormsCompleted
            && ! $hasBlockedMetrics
            && $hasWorkshopCandidates
            && $hasActiveAssignments;

        $deliverables = [
            'finalized_at' => optional($engagement->finalized_at)->toIso8601String(),
            'total' => 0,
            'items' => [],
        ];

        if ($engagement->finalized_at && $metrics->isNotEmpty()) {
            $definitions = FinalDefinition::query()
                ->whereIn('metric_id', $metrics->modelKeys())
                ->whereNotNull('signed_off_at')
                ->where('signed_off_at', '<=', $engagement->finalized_at)
                ->with(['signedOffBy:id,name'])
                ->orderByDesc('signed_off_at')
                ->orderByDesc('version')
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy('metric_id')
                ->map(fn ($group) => $group->first());

            $metricsById = $metrics->keyBy('id');

            $items = $definitions
                ->map(function (FinalDefinition $definition) use ($metricsById) {
                    $metric = $metricsById->get($definition->metric_id);

                    if (! $metric) {
                        return null;
                    }

                    return [
                        'metric_id' => (string) $metric->id,
                        'metric_name' => $metric->name,
                        'template' => $metric->template ? [
                            'key' => $metric->template->key,
                            'name' => $metric->template->name,
                        ] : null,
                        'signed_off_at' => optional($definition->signed_off_at)->toIso8601String(),
                        'signed_off_by' => $definition->signedOffBy ? [
                            'id' => (string) $definition->signedOffBy->id,
                            'name' => $definition->signedOffBy->name,
                        ] : null,
                    ];
                })
                ->filter()
                ->sortBy('metric_name')
                ->values();

            $deliverables['items'] = $items;
            $deliverables['total'] = $items->count();
        }

        $railReadiness = $this->buildRailReadiness(
            $engagement,
            $forms->count(),
            $formStatusCounts,
            $metricStatusCounts,
            $workshops,
            (int) $deliverables['total']
        );

        $timeline = [
            'engagement' => [
                'starts_on' => optional($engagement->starts_on)->toDateString(),
                'ends_on' => optional($engagement->ends_on)->toDateString(),
                'status' => $engagement->status instanceof EngagementStatus ? $engagement->status->value : (string) $engagement->status,
            ],
            'forms' => $formItems,
            'workshops' => $workshops,
            'final_definitions' => collect($metricItems)
                ->map(fn ($item) => [
                    'metric_id' => $item['id'],
                    'metric_name' => $item['name'],
                    'status' => $item['final_definition']['status'] ?? null,
                    'signed_off_at' => $item['final_definition']['signed_off_at'] ?? null,
                ])
                ->filter(fn ($definition) => ! empty($definition['signed_off_at']))
                ->values()
                ->all(),
        ];

        $currentStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);
        $reminderBaseQuery = ReminderSchedule::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id);
        $attemptBaseQuery = NotificationAttempt::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id);
        $lastScheduleChangeAt = (clone $reminderBaseQuery)
            ->orderByDesc('updated_at')
            ->value('updated_at');
        $lastAttemptAt = (clone $attemptBaseQuery)
            ->orderByDesc('attempted_at')
            ->value('attempted_at');
        $recentReminderAttempts = (clone $attemptBaseQuery)
            ->with([
                'metric:id,name',
                'formAssignment:id,user_id',
                'formAssignment.user:id,email',
                'reminder:id,window',
            ])
            ->orderByDesc('attempted_at')
            ->limit(10)
            ->get([
                'id',
                'metric_id',
                'form_assignment_id',
                'notification_id',
                'channel',
                'status',
                'provider_response',
                'attempted_at',
            ])
            ->map(function (NotificationAttempt $attempt) {
                $response = $attempt->provider_response ?? [];

                return [
                    'id' => (string) $attempt->id,
                    'status' => (string) $attempt->status,
                    'channel' => (string) $attempt->channel,
                    'window' => $attempt->reminder?->window,
                    'attempted_at' => optional($attempt->attempted_at)->toIso8601String(),
                    'metric_name' => $attempt->metric?->name,
                    'recipient_email' => $attempt->formAssignment?->user?->email
                        ?? data_get($response, 'recipients.0'),
                    'reason' => data_get($response, 'reason')
                        ?? data_get($response, 'message'),
                ];
            })
            ->values()
            ->all();
        $remindersPayload = [
            'last_schedule_change_at' => $lastScheduleChangeAt
                ? Carbon::parse((string) $lastScheduleChangeAt)->toIso8601String()
                : null,
            'last_attempt_at' => $lastAttemptAt
                ? Carbon::parse((string) $lastAttemptAt)->toIso8601String()
                : null,
            'pending_due_now_count' => (int) (clone $reminderBaseQuery)
                ->pending()
                ->where('deliver_at', '<=', now())
                ->count(),
            'failed_attempts_24h' => (int) (clone $attemptBaseQuery)
                ->where('status', NotificationAttempt::STATUS_FAILED)
                ->where('attempted_at', '>=', now()->subDay())
                ->count(),
            'recent_attempts' => $recentReminderAttempts,
        ];

        return Inertia::render('Engagements/Show', [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'timezone' => data_get($organization->settings_json ?? [], 'timezone'),
            ],
            'engagement' => [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $timeline['engagement']['status'],
                'starts_on' => $timeline['engagement']['starts_on'],
                'ends_on' => $timeline['engagement']['ends_on'],
                'repository' => [
                    'driver' => $engagement->repository_driver,
                    'reference' => $engagement->repository_reference,
                    'notes' => $engagement->repository_notes,
                ],
                'stakeholders' => collect($engagement->stakeholders_json ?? [])
                    ->map(fn ($stakeholder) => [
                        'name' => $stakeholder['name'] ?? null,
                        'email' => $stakeholder['email'] ?? null,
                        'role' => $stakeholder['role'] ?? null,
                        'department' => $stakeholder['department']
                            ?? $stakeholder['affiliation']
                            ?? null,
                    ])
                    ->values()
                    ->all(),
                'checklist' => array_replace(
                    $engagement->onboarding_checklist_json ?? [],
                    [
                        'workshop_ready' => $workshopReady
                            && (bool) data_get($engagement->onboarding_checklist_json ?? [], 'stakeholders_invited'),
                    ],
                ),
            ],
            'forms' => $formsPayload,
            'metrics' => $metricsPayload,
            'deliverables' => $deliverables,
            'rail_readiness' => $railReadiness->toArray(),
            'workshops' => $workshops,
            'timeline' => $timeline,
            'activity' => $activity,
            'reminders' => $remindersPayload,
            'permissions' => [
                'can_update' => Gate::allows('update', $engagement),
                'can_send_reminders' => Gate::allows('update', $engagement),
                'can_change_status' => Gate::allows('update', $engagement),
                'can_manage_assignments' => Gate::allows('update', $engagement),
            ],
            'statusOptions' => collect(EngagementStatus::transitionOptions($currentStatus))
                ->map(fn (EngagementStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->value,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  Collection<int, Form>  $forms
     * @return array{
     *     items: array<int, array{
     *         id: string,
     *         title: string|null,
     *         status: string,
     *         metric_id: string|null,
     *         template_key: string|null,
     *         created_at: string|null,
     *         submitted_count: int,
     *         draft_count: int,
     *         assignment_count: int,
     *         last_submission_at: string|null,
     *         next_due_at: string|null
     *     }>,
     *     status_counts: array<string, int>,
     *     last_submission_at: string|null
     * }
     */
    private function buildContributorFormSummary(Collection $forms): array
    {
        $statsByForm = $this->resolveContributorAssignmentStats(
            $forms->map(fn (Form $form) => (string) $form->id)->all()
        );

        $statusCounts = [
            'Open' => 0,
            'InProgress' => 0,
            'Completed' => 0,
        ];

        $lastSubmissionAt = null;

        $items = $forms
            ->map(function (Form $form) use (&$statusCounts, $statsByForm, &$lastSubmissionAt) {
                $formId = (string) $form->id;
                $stats = $statsByForm[$formId] ?? [
                    'assignment_count' => 0,
                    'submitted_count' => 0,
                    'draft_count' => 0,
                    'last_submission_at' => null,
                    'next_due_at' => null,
                ];

                $status = $this->resolveContributorFormStatus(
                    $stats['assignment_count'],
                    $stats['submitted_count']
                );
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

                $lastSubmission = $stats['last_submission_at'] instanceof Carbon
                    ? $stats['last_submission_at']
                    : null;
                if ($lastSubmission) {
                    if (! $lastSubmissionAt || $lastSubmission->greaterThan($lastSubmissionAt)) {
                        $lastSubmissionAt = $lastSubmission;
                    }
                }

                $nextDueAt = $stats['next_due_at'] instanceof Carbon
                    ? $stats['next_due_at']->toIso8601String()
                    : null;

                return [
                    'id' => $formId,
                    'title' => $form->title,
                    'status' => $status,
                    'metric_id' => $form->metric_id ? (string) $form->metric_id : null,
                    'template_key' => $form->template_key,
                    'created_at' => optional($form->created_at)->toIso8601String(),
                    'submitted_count' => $stats['submitted_count'],
                    'draft_count' => $stats['draft_count'],
                    'assignment_count' => $stats['assignment_count'],
                    'last_submission_at' => $lastSubmission?->toIso8601String(),
                    'next_due_at' => $nextDueAt,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'status_counts' => array_map('intval', $statusCounts),
            'last_submission_at' => $lastSubmissionAt?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $viewerContributorFormIds
     * @return array<int, array<string, mixed>>
     */
    private function attachViewerSubmissionAccess(array $items, array $viewerContributorFormIds): array
    {
        if ($viewerContributorFormIds === []) {
            return array_map(
                fn (array $item) => $item + ['viewer_can_submit' => false],
                $items
            );
        }

        $lookup = array_fill_keys($viewerContributorFormIds, true);

        return array_map(
            fn (array $item) => $item + [
                'viewer_can_submit' => isset($lookup[$item['id'] ?? '']),
            ],
            $items
        );
    }

    /**
     * @param  Collection<int, Form>  $forms
     * @return array<int, string>
     */
    private function resolveViewerContributorFormIds(?User $user, Collection $forms): array
    {
        if (! $user || $forms->isEmpty()) {
            return [];
        }

        return FormAssignment::query()
            ->where('user_id', $user->id)
            ->where('responsibility', 'Contributor')
            ->whereIn('form_id', $forms->pluck('id')->all())
            ->pluck('form_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $formIds
     * @return array<string, array{
     *     assignment_count: int,
     *     submitted_count: int,
     *     draft_count: int,
     *     last_submission_at: Carbon|null,
     *     next_due_at: Carbon|null
     * }>
     */
    private function resolveContributorAssignmentStats(array $formIds): array
    {
        if ($formIds === []) {
            return [];
        }

        $assignments = FormAssignment::query()
            ->select([
                'form_assignment.form_id',
                'form_assignment.user_id',
                'form_assignment.due_at',
                'form_submissions.submitted_at as submission_submitted_at',
            ])
            ->leftJoin('form_submissions', function ($join): void {
                $join->on('form_submissions.form_id', '=', 'form_assignment.form_id')
                    ->on('form_submissions.user_id', '=', 'form_assignment.user_id');
            })
            ->whereIn('form_assignment.form_id', $formIds)
            ->where('form_assignment.responsibility', 'Contributor')
            ->get();

        $stats = [];

        foreach ($assignments as $assignment) {
            $formId = (string) $assignment->form_id;
            if (! isset($stats[$formId])) {
                $stats[$formId] = [
                    'assignment_count' => 0,
                    'submitted_count' => 0,
                    'draft_count' => 0,
                    'last_submission_at' => null,
                    'next_due_at' => null,
                ];
            }

            $stats[$formId]['assignment_count'] += 1;

            $submittedAt = $assignment->submission_submitted_at
                ? Carbon::parse($assignment->submission_submitted_at)
                : null;

            if ($submittedAt) {
                $stats[$formId]['submitted_count'] += 1;
                $currentLast = $stats[$formId]['last_submission_at'];
                if (! $currentLast || $submittedAt->greaterThan($currentLast)) {
                    $stats[$formId]['last_submission_at'] = $submittedAt;
                }

                continue;
            }

            $dueAt = $assignment->due_at;
            if ($dueAt && ! $dueAt instanceof Carbon) {
                $dueAt = Carbon::parse($dueAt);
            }

            if ($dueAt) {
                $currentNext = $stats[$formId]['next_due_at'];
                if (! $currentNext || $dueAt->lessThan($currentNext)) {
                    $stats[$formId]['next_due_at'] = $dueAt;
                }
            }
        }

        foreach ($stats as $formId => $summary) {
            $stats[$formId]['draft_count'] = max(
                $summary['assignment_count'] - $summary['submitted_count'],
                0
            );
        }

        return $stats;
    }

    private function resolveContributorFormStatus(int $assignmentCount, int $submittedCount): string
    {
        if ($assignmentCount === 0 || $submittedCount === 0) {
            return 'Open';
        }

        if ($submittedCount < $assignmentCount) {
            return 'InProgress';
        }

        return 'Completed';
    }

    /**
     * @param  array<string, int>  $formStatusCounts
     * @param  array<string, int>  $metricStatusCounts
     * @param  array<int, array<string, mixed>>  $workshops
     */
    private function buildRailReadiness(
        Engagement $engagement,
        int $formsTotal,
        array $formStatusCounts,
        array $metricStatusCounts,
        array $workshops,
        int $deliverablesTotal
    ): EngagementRailReadiness {
        $checklist = $engagement->onboarding_checklist_json ?? [];

        $intakeReady = (bool) data_get($checklist, 'metrics_selected')
            && (bool) data_get($checklist, 'forms_bootstrapped');
        $formsReady = $formsTotal > 0
            && (int) ($formStatusCounts['Completed'] ?? 0) >= $formsTotal;
        $reviewReady = $this->hasValidWorkshopGateSession($workshops);

        $metricsTotal = array_sum(array_map('intval', $metricStatusCounts));
        $finalizedMetricsTotal = (int) ($metricStatusCounts[MetricStatus::FINALIZED->value] ?? 0)
            + (int) ($metricStatusCounts[MetricStatus::DEPRECATED->value] ?? 0);
        $signOffReady = $metricsTotal > 0 && $finalizedMetricsTotal >= $metricsTotal;

        $handbookReady = $engagement->finalized_at !== null && $deliverablesTotal > 0;

        $blockers = [];

        if (! $intakeReady) {
            $blockers[] = 'Select metrics and bootstrap forms before kickoff.';
        }
        if (! $formsReady) {
            $blockers[] = 'Complete all contributor form submissions before entering review.';
        }
        if (! $reviewReady) {
            $blockers[] = 'Schedule, start, or complete a workshop session before entering In Workshop.';
        }
        if (! $signOffReady) {
            $blockers[] = 'Finalize or deprecate all metrics before closing the engagement.';
        }
        if (! $handbookReady) {
            $blockers[] = $engagement->finalized_at
                ? 'Sign off at least one final definition before handbook delivery.'
                : 'Finalize the engagement before handbook delivery.';
        }

        return new EngagementRailReadiness(
            intakeReady: $intakeReady,
            formsReady: $formsReady,
            reviewReady: $reviewReady,
            signOffReady: $signOffReady,
            handbookReady: $handbookReady,
            blockers: $blockers
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $workshops
     */
    private function hasValidWorkshopGateSession(array $workshops): bool
    {
        foreach ($workshops as $workshop) {
            $status = strtolower(trim((string) ($workshop['status'] ?? '')));

            if (in_array($status, ['scheduled', 'inprogress', 'completed'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Metric>  $metrics
     * @return array<string, array{state: 'ready'|'missing_forms'|'pending_submissions', forms_total: int, forms_ready: int}>
     */
    private function resolveWorkshopScheduleReadinessByMetric(Collection $metrics): array
    {
        if ($metrics->isEmpty()) {
            return [];
        }

        $metricIds = $metrics->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $forms = Form::query()
            ->whereIn('metric_id', $metricIds)
            ->get(['id', 'metric_id']);
        $formsByMetricId = $forms->groupBy(fn (Form $form) => (string) $form->metric_id);

        $submissionStatsByFormId = collect();
        if ($forms->isNotEmpty()) {
            $submissionStatsByFormId = FormAssignment::query()
                ->selectRaw(
                    'form_assignment.form_id, COUNT(*) as assignment_count, SUM(CASE WHEN form_submissions.submitted_at IS NOT NULL THEN 1 ELSE 0 END) as submitted_count'
                )
                ->leftJoin('form_submissions', function ($join): void {
                    $join->on('form_submissions.form_id', '=', 'form_assignment.form_id')
                        ->on('form_submissions.user_id', '=', 'form_assignment.user_id');
                })
                ->whereIn('form_assignment.form_id', $forms->pluck('id')->all())
                ->where(function ($query): void {
                    $query->whereNull('form_assignment.responsibility')
                        ->orWhereRaw('LOWER(form_assignment.responsibility) = ?', ['contributor']);
                })
                ->groupBy('form_assignment.form_id')
                ->get()
                ->keyBy(fn ($row) => (string) $row->form_id);
        }

        return collect($metricIds)
            ->mapWithKeys(function (string $metricId) use ($formsByMetricId, $submissionStatsByFormId): array {
                /** @var Collection<int, Form> $metricForms */
                $metricForms = $formsByMetricId->get($metricId, collect());
                $formsTotal = $metricForms->count();

                if ($formsTotal === 0) {
                    return [$metricId => [
                        'state' => 'missing_forms',
                        'forms_total' => 0,
                        'forms_ready' => 0,
                    ]];
                }

                $formsReady = $metricForms
                    ->filter(function (Form $form) use ($submissionStatsByFormId): bool {
                        $stats = $submissionStatsByFormId->get((string) $form->getKey());
                        $assignmentCount = (int) ($stats->assignment_count ?? 0);
                        $submittedCount = (int) ($stats->submitted_count ?? 0);

                        return $assignmentCount > 0 && $submittedCount >= $assignmentCount;
                    })
                    ->count();

                return [$metricId => [
                    'state' => $formsReady === $formsTotal ? 'ready' : 'pending_submissions',
                    'forms_total' => $formsTotal,
                    'forms_ready' => $formsReady,
                ]];
            })
            ->all();
    }
}
