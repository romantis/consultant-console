<?php

namespace App\Http\Controllers;

use App\Enums\EngagementStatus;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Support\EngagementChecklist;
use App\Support\EngagementContext;
use App\Support\FormSubmissionToken;
use App\Support\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FormController extends Controller
{
    public function index(Request $request, Organization $organization, Engagement $engagement): JsonResponse
    {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $this->authorize('view', $engagement);

        EngagementChecklist::ensureReadyFor(EngagementChecklist::MODULE_FORMS, $engagement);

        $forms = Form::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'status', 'template_key', 'created_at']);

        $summary = $this->buildContributorFormSummary($forms);
        $formItems = $this->attachViewerSubmissionAccess(
            $summary['items'],
            $this->resolveViewerContributorFormIds($request->user(), $forms)
        );
        $statusCounts = $summary['status_counts'];

        $templates = MetricTemplate::query()
            ->orderBy('name')
            ->get(['key', 'name']);

        $members = $organization->memberships()
            ->with(['user:id,name'])
            ->get()
            ->map(fn ($membership) => [
                'id' => (string) $membership->user->id,
                'name' => $membership->user->name,
            ])
            ->values();

        return response()->json([
            'total' => count($formItems),
            'status_counts' => array_map('intval', $statusCounts),
            'last_submission_at' => $summary['last_submission_at'],
            'items' => $formItems,
            'templates' => $templates,
            'members' => $members,
            'can_create' => Gate::allows('update', $organization),
            'engagement' => [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus
                    ? $engagement->status->value
                    : $engagement->status,
            ],
            'refresh_url' => route('org.engagements.forms.index', [
                'organization' => $organization,
                'engagement' => $engagement,
            ]),
        ]);
    }

    public function store(StoreFormRequest $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $data = $request->validated();
        $engagement = EngagementContext::resolveOrFail($organization, $data['engagement_id']);

        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        EngagementChecklist::ensureReadyFor(EngagementChecklist::MODULE_FORMS, $engagement);

        $schema = MetricTemplate::where('key', $data['template_key'])->value('form_schema_json') ?? ['fields' => []];

        $form = Form::create([
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
            'template_key' => $data['template_key'],
            'title' => $data['title'],
            'schema_json' => $schema,
            'status' => 'Open',
        ]);

        if (! empty($data['assignee_id'])) {
            FormAssignment::create([
                'form_id' => $form->id,
                'user_id' => $data['assignee_id'],
                'due_at' => $data['due_at'] ?? null,
            ]);
            FormSubmission::firstOrCreate(['form_id' => $form->id, 'user_id' => $data['assignee_id']], []);
        }

        return redirect()->route('forms.show', $form);
    }

    // Optional meta update endpoint (add a route only if/when you expose it in UI)
    public function update(UpdateFormRequest $request, Form $form)
    {
        $this->authorize('update', $form);
        $form->update($request->validated());

        return back()->with('success', 'Form updated.');
    }

    public function show(Request $request, Form $form)
    {
        $this->authorize('view', $form);

        $user = $request->user();

        $assignment = FormAssignment::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->first();

        $isContributorAssignment = $assignment
            && mb_strtolower((string) $assignment->responsibility) === 'contributor';
        $readOnly = ! $isContributorAssignment;

        $submission = null;
        $submissionToken = '';
        $submissionOwner = null;
        $submissionOptions = [];
        $activeSubmissionId = null;

        if (! $readOnly) {
            // Ensure the viewing user has a submission record (draft) to autosave
            $submission = FormSubmission::firstOrCreate(
                ['form_id' => $form->id, 'user_id' => $user->id],
                []
            );
            $submissionToken = FormSubmissionToken::make($submission);
        } else {
            $submissionOptions = $this->resolveSubmittedContributorOptions($form);
            $requestedSubmissionId = $request->query('submission');
            $activeOption = collect($submissionOptions)
                ->firstWhere('id', $requestedSubmissionId)
                ?? ($submissionOptions[0] ?? null);

            if ($activeOption) {
                $activeSubmissionId = $activeOption['id'];
                $submissionOwner = $activeOption['user'] ?? null;
                $submission = FormSubmission::query()
                    ->whereKey($activeSubmissionId)
                    ->first(['id', 'answers_json', 'submitted_at']);
            }
        }

        $assignmentForView = $isContributorAssignment ? $assignment : null;
        $dueAt = $assignmentForView?->due_at;
        $deadline = null;
        if ($dueAt) {
            $deadline = [
                'due_at' => $dueAt?->toIso8601String(),
                'is_overdue' => now()->greaterThan($dueAt),
                'hours_remaining' => now()->diffInHours($dueAt, false),
            ];
        }

        $affiliation = $request->user()
            ->memberships()
            ->where('organization_id', $form->organization_id)
            ->value('affiliation');

        $affiliation = is_string($affiliation) ? trim($affiliation) : null;
        if ($affiliation === '') {
            $affiliation = null;
        }

        $isInternalStaff = $user ? Staff::isInternalStaff($user) : false;

        return Inertia::render('Forms/Show', [
            'form' => $form->only(['id', 'title', 'schema_json', 'status']),
            'submission' => $submission?->only(['id', 'answers_json', 'submitted_at']),
            'submissionToken' => $submissionToken,
            'assignment' => $assignmentForView ? [
                'due_at' => $assignmentForView->due_at?->toIso8601String(),
            ] : null,
            'deadline' => $deadline,
            'memberAffiliation' => $affiliation,
            'readOnly' => $readOnly,
            'backLink' => $this->resolveBackLink($form, $isInternalStaff),
            'submissionOptions' => $submissionOptions,
            'activeSubmissionId' => $activeSubmissionId,
            'submissionOwner' => $submissionOwner,
        ]);
    }

    /**
     * @return array{label: string, href: string}
     */
    private function resolveBackLink(Form $form, bool $isInternalStaff): array
    {
        if ($isInternalStaff) {
            if ($form->organization_id && $form->engagement_id) {
                return [
                    'label' => 'Back to engagement',
                    'href' => route('org.engagements.show', [
                        'organization' => $form->organization_id,
                        'engagement' => $form->engagement_id,
                    ]),
                ];
            }

            if ($form->organization_id) {
                return [
                    'label' => 'Back to engagements',
                    'href' => route('org.engagements.index', $form->organization_id),
                ];
            }
        }

        $orgMetricId = $form->metric_id
            ? Metric::query()->whereKey($form->metric_id)->value('org_metric_id')
            : null;

        if ($orgMetricId && $form->organization_id && $form->engagement_id) {
            return [
                'label' => 'Back to metric',
                'href' => route('client.handbook.metrics.page', [
                    'organization' => $form->organization_id,
                    'engagement' => $form->engagement_id,
                    'orgMetric' => $orgMetricId,
                ]),
            ];
        }

        return [
            'label' => 'Back to forms',
            'href' => route('client.forms.index'),
        ];
    }

    /**
     * @param  Collection<int, Form>  $forms
     * @return array{
     *     items: array<int, array{
     *         id: string,
     *         title: string|null,
     *         template_key: string|null,
     *         status: string,
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
                    'template_key' => $form->template_key,
                    'status' => $status,
                    'created_at' => $form->created_at?->toIso8601String(),
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
     * @return array<int, array{id: string, submitted_at: string|null, user: array{id: string, name: string|null, email: string|null}|null}>
     */
    private function resolveSubmittedContributorOptions(Form $form): array
    {
        return FormSubmission::query()
            ->select([
                'form_submissions.id',
                'form_submissions.user_id',
                'form_submissions.submitted_at',
            ])
            ->join('form_assignment', function ($join): void {
                $join->on('form_assignment.form_id', '=', 'form_submissions.form_id')
                    ->on('form_assignment.user_id', '=', 'form_submissions.user_id');
            })
            ->where('form_submissions.form_id', $form->id)
            ->whereNotNull('form_submissions.submitted_at')
            ->where('form_assignment.responsibility', 'Contributor')
            ->with('user:id,name,email')
            ->orderByDesc('form_submissions.submitted_at')
            ->get()
            ->map(fn (FormSubmission $submission) => [
                'id' => (string) $submission->id,
                'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                'user' => $submission->user ? [
                    'id' => (string) $submission->user->id,
                    'name' => $submission->user->name,
                    'email' => $submission->user->email,
                ] : null,
            ])
            ->values()
            ->all();
    }
}
