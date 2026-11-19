<?php

namespace App\Http\Controllers;

use App\Actions\FormAssignments\AutosaveDraft;
use App\Actions\FormAssignments\CompleteAssignment;
use App\Exceptions\AutosaveThrottledException;
use App\Http\Requests\SubmitFormSubmissionRequest;
use App\Http\Requests\UpdateFormSubmissionRequest;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;

class FormSubmissionController extends Controller
{
    private const AUTOSAVE_THROTTLE_MESSAGE = 'Autosave temporarily throttled.';

    public function __construct(
        private readonly AutosaveDraft $autosaveDraft,
        private readonly CompleteAssignment $completeAssignment
    ) {}

    public function autosave(UpdateFormSubmissionRequest $request, Form $form)
    {
        $assignment = FormAssignment::query()
            ->where('form_id', $form->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $assignment || mb_strtolower((string) $assignment->responsibility) !== 'contributor') {
            abort(403);
        }

        $submission = FormSubmission::firstOrCreate(
            ['form_id' => $form->id, 'user_id' => $request->user()->id],
            []
        );

        $this->authorize('save', $submission);

        try {
            $submission = $this->autosaveDraft->handle(
                assignment: $assignment,
                submission: $submission,
                actor: $request->user(),
                draft: $request->validated()['answers_json'],
                submissionToken: $request->input('submission_token')
            );
        } catch (AutosaveThrottledException $exception) {
            if ($request->expectsJson()) {
                return response()
                    ->json([
                        'message' => $exception->getMessage() ?: self::AUTOSAVE_THROTTLE_MESSAGE,
                        'retry_after' => $exception->retryAfter(),
                    ], 409)
                    ->header('Retry-After', (string) $exception->retryAfter());
            }

            abort(409, $exception->getMessage() ?: self::AUTOSAVE_THROTTLE_MESSAGE);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'submitted' => (bool) $submission->submitted_at,
            ]);
        }

        return redirect()->route('forms.show', $form)->with('status', 'Draft saved.');
    }

    public function submit(SubmitFormSubmissionRequest $request, Form $form)
    {
        $assignment = FormAssignment::query()
            ->where('form_id', $form->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $assignment || mb_strtolower((string) $assignment->responsibility) !== 'contributor') {
            abort(403);
        }

        $submission = FormSubmission::firstOrCreate(['form_id' => $form->id, 'user_id' => $request->user()->id], []);
        $this->authorize('submit', $submission);

        $attestation = $request->input('attestation');
        $attestation = is_array($attestation) ? $attestation : null;

        $submission = $this->completeAssignment->handle(
            assignment: $assignment,
            submission: $submission,
            actor: $request->user(),
            payload: $request->input('answers_json', $submission->answers_json ?? []),
            attestation: $attestation,
            submissionToken: $request->input('submission_token')
        );

        return redirect()->route('forms.show', $form)->with('success', 'Submitted');
    }
}
