<?php

namespace App\Actions\FormAssignments;

use App\Enums\FormAssignmentStatus;
use App\Exceptions\AutosaveThrottledException;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\FormProgress;
use App\Support\FormSubmissionToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class AutosaveDraft
{
    private const THROTTLE_ATTEMPTS = 1;

    private const THROTTLE_DECAY_SECONDS = 1;

    /**
     * @param  array<string, mixed>  $draft
     */
    public function handle(
        FormAssignment $assignment,
        FormSubmission $submission,
        User $actor,
        array $draft,
        ?string $submissionToken = null
    ): FormSubmission {
        $this->assertAssignmentCompatibility($assignment, $submission);
        $this->assertActorOwnsSubmission($submission, $actor);
        $this->ensureValidToken($submission, $submissionToken);

        $form = $assignment->form;

        $throttleKey = $this->throttleKey($form->id, $actor->id);

        if (RateLimiter::tooManyAttempts($throttleKey, self::THROTTLE_ATTEMPTS)) {
            $retryAfter = max(1, RateLimiter::availableIn($throttleKey) ?: self::THROTTLE_DECAY_SECONDS);

            throw new AutosaveThrottledException($retryAfter);
        }

        $submission->forceFill([
            'answers_json' => $draft,
        ])->save();

        RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);

        FormProgress::sync($form->refresh());

        if ($assignment->status !== FormAssignmentStatus::COMPLETED) {
            $assignment->forceFill([
                'status' => FormAssignmentStatus::IN_PROGRESS,
            ])->save();
        }

        $assignment->touch();

        return $submission->fresh();
    }

    private function assertAssignmentCompatibility(FormAssignment $assignment, FormSubmission $submission): void
    {
        $assignment->loadMissing('form');

        $form = $assignment->form;

        if (! $form) {
            throw new RuntimeException('Form assignment is missing an associated form.');
        }

        if ($submission->form_id !== $form->id) {
            throw new RuntimeException('Form submission does not belong to the provided assignment.');
        }
    }

    private function assertActorOwnsSubmission(FormSubmission $submission, User $actor): void
    {
        if ((string) $submission->user_id !== (string) $actor->id) {
            throw new AuthorizationException('You may only update your own submission.');
        }
    }

    private function ensureValidToken(FormSubmission $submission, ?string $submissionToken): void
    {
        if ($submissionToken === null || $submissionToken === '') {
            return;
        }

        if (! FormSubmissionToken::verify($submission, $submissionToken)) {
            throw new AuthorizationException('Invalid submission token.');
        }
    }

    private function throttleKey(string $formId, string $userId): string
    {
        return sprintf('form-autosave:%s:%s', $formId, $userId);
    }
}
