<?php

namespace App\Http\Controllers;

use App\Actions\FormAssignments\AutosaveDraft;
use App\Actions\FormAssignments\CompleteAssignment;
use App\Enums\FormAssignmentStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Exceptions\AutosaveThrottledException;
use App\Http\Requests\AutosaveFormAssignmentRequest;
use App\Http\Requests\SubmitFormAssignmentRequest;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\MetricDefinition;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Support\FormSubmissionToken;
use App\Support\ImpersonationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormAssignmentController extends Controller
{
    private const AUTOSAVE_THROTTLE_MESSAGE = 'Autosave temporarily throttled.';

    public function __construct(
        private readonly AutosaveDraft $autosaveDraft,
        private readonly CompleteAssignment $completeAssignment
    ) {}

    public function show(Request $request, FormAssignment $assignment): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $this->ensureAssignmentAccessible($assignment, $user);

        $assignment->loadMissing(['form', 'metric']);

        $form = $assignment->form;
        abort_unless($form, 404);

        $submission = $this->resolveSubmission($assignment, $user);

        $cadence = ReminderSchedule::query()
            ->where('form_assignment_id', $assignment->id)
            ->orderBy('deliver_at')
            ->pluck('window')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $status = $assignment->status;
        if ($status instanceof FormAssignmentStatus) {
            $statusValue = $status->value;
        } elseif (is_string($status) && $status !== '') {
            $statusValue = $status;
        } else {
            $statusValue = FormAssignmentStatus::OPEN->value;
        }

        return response()->json([
            'id' => (string) $assignment->id,
            'status' => $statusValue,
            'due_at' => optional($assignment->due_at)->toIso8601String(),
            'reminder_cadence' => array_values($cadence),
            'schema' => $form->schema_json ?? [],
            'draft' => [
                'id' => (string) $submission->id,
                'payload' => $submission->answers_json ?? [],
                'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                'token' => FormSubmissionToken::make($submission),
            ],
        ]);
    }

    public function autosave(
        AutosaveFormAssignmentRequest $request,
        FormAssignment $assignment
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $this->ensureAssignmentAccessible($assignment, $user);
        $this->ensureContributorAssignment($assignment);

        $submission = $this->resolveSubmission($assignment, $user);

        try {
            $this->autosaveDraft->handle(
                assignment: $assignment,
                submission: $submission,
                actor: $user,
                draft: $request->validated('draft'),
                submissionToken: $request->input('submission_token')
            );
        } catch (AutosaveThrottledException $exception) {
            return response()
                ->json([
                    'message' => $exception->getMessage() ?: self::AUTOSAVE_THROTTLE_MESSAGE,
                    'retry_after' => $exception->retryAfter(),
                ], 409)
                ->header('Retry-After', (string) $exception->retryAfter());
        }

        return response()->noContent();
    }

    public function submit(
        SubmitFormAssignmentRequest $request,
        FormAssignment $assignment
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $this->ensureAssignmentAccessible($assignment, $user);
        $this->ensureContributorAssignment($assignment);

        $submission = $this->resolveSubmission($assignment, $user);

        $payload = $request->validated('payload');

        $attestationInput = $request->input('attestation');
        $attestation = is_array($attestationInput) ? $attestationInput : null;

        $submission = $this->completeAssignment->handle(
            assignment: $assignment,
            submission: $submission,
            actor: $user,
            payload: $payload,
            attestation: $attestation,
            submissionToken: $request->input('submission_token')
        );

        $definition = MetricDefinition::query()
            ->where('form_submission_id', $submission->id)
            ->latest('updated_at')
            ->first();

        return response()->json([
            'id' => (string) $submission->id,
            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
            'normalized_definition' => $definition?->normalized_json ?? [],
            'metric_definition_id' => $definition?->id ? (string) $definition->id : null,
        ]);
    }

    private function ensureAssignmentAccessible(FormAssignment $assignment, User $user): void
    {
        $assignment->loadMissing([
            'form.organization',
            'metric',
        ]);

        $form = $assignment->form;
        abort_unless($form, 404);

        $metric = $assignment->metric ?? $form->metric;
        if ($metric && $metric->status instanceof MetricStatus) {
            if ($metric->status === MetricStatus::FINALIZED) {
                abort(403);
            }
        } elseif ($metric && is_string($metric->status) && $metric->status === MetricStatus::FINALIZED->value) {
            abort(403);
        }

        if ((string) $assignment->user_id === (string) $user->id) {
            return;
        }

        $organizationId = $form->organization_id;

        $context = ImpersonationGuard::resolve($user, $organizationId);

        if (! $context->allowsAtLeast(MembershipRole::ADMIN)) {
            abort(403);
        }
    }

    private function resolveSubmission(FormAssignment $assignment, User $user): FormSubmission
    {
        return FormSubmission::firstOrCreate(
            [
                'form_id' => $assignment->form_id,
                'user_id' => $user->id,
            ],
            [
                'answers_json' => [],
            ]
        );
    }

    private function ensureContributorAssignment(FormAssignment $assignment): void
    {
        if (mb_strtolower((string) $assignment->responsibility) !== 'contributor') {
            abort(403);
        }
    }
}
