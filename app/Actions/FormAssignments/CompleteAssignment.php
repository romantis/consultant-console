<?php

namespace App\Actions\FormAssignments;

use App\Actions\Engagements\AutoAdvanceEngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\FormProgress;
use App\Support\FormSubmissionToken;
use App\Support\Metrics\MetricDefinitionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class CompleteAssignment
{
    public function __construct(
        private readonly MetricDefinitionService $metricDefinitions,
        private readonly AutoAdvanceEngagementStatus $autoAdvanceEngagementStatus
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $attestation
     */
    public function handle(
        FormAssignment $assignment,
        FormSubmission $submission,
        User $actor,
        array $payload,
        ?array $attestation = null,
        ?string $submissionToken = null
    ): FormSubmission {
        $this->assertAssignmentCompatibility($assignment, $submission);
        $this->assertActorOwnsSubmission($submission, $actor);
        $this->ensureValidToken($submission, $submissionToken);

        $form = $assignment->form;

        $this->validateAgainstSchema($form->schema_json ?? [], $payload);

        $submission->forceFill([
            'answers_json' => $payload,
            'submitted_at' => now(),
        ])->save();

        $form->refresh();

        FormProgress::sync($form);

        $form->refresh();

        RateLimiter::clear($this->throttleKey($form->id, $actor->id));

        $freshSubmission = $submission->fresh();

        $this->metricDefinitions->recordFromSubmission($freshSubmission);

        $form->loadMissing('engagement');
        if ($form->engagement) {
            $this->autoAdvanceEngagementStatus->handle($form->engagement, $form);
        }

        $assignment->forceFill([
            'status' => FormAssignmentStatus::COMPLETED,
        ])->save();

        $assignment->touch();

        return $freshSubmission;
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

    private function validateAgainstSchema(array $schema, array $payload): void
    {
        $rules = [];

        foreach ($schema['fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;

            if (! $name) {
                continue;
            }

            $fieldRules = [];

            if (! empty($field['required'])) {
                $fieldRules[] = 'required';
            }

            if (($field['type'] ?? null) === 'number') {
                $fieldRules[] = 'numeric';
            }

            if (($field['type'] ?? null) === 'checkbox_group') {
                $fieldRules[] = 'array';

                if (! empty($field['required'])) {
                    $fieldRules[] = 'min:1';
                }
            }

            if (! empty($fieldRules)) {
                $rules["answers_json.{$name}"] = $fieldRules;
            }

            if (
                in_array($field['type'] ?? null, ['checkbox_group', 'select'], true)
                && ! empty($field['with_other'])
                && $name
            ) {
                $rules["answers_json.{$name}__other_text"] = ['nullable', 'string', 'max:1000'];
            }
        }

        if (empty($rules)) {
            return;
        }

        $validator = Validator::make(
            ['answers_json' => $payload],
            $rules
        );

        $validator->after(function ($validator) use ($schema, $payload): void {
            foreach ($schema['fields'] ?? [] as $field) {
                $name = $field['name'] ?? null;

                if (! $name || empty($field['with_other'])) {
                    continue;
                }

                $type = $field['type'] ?? null;
                $selectedOther = false;

                if ($type === 'checkbox_group') {
                    $values = $payload[$name] ?? [];

                    if (! is_array($values)) {
                        continue;
                    }

                    foreach ($values as $value) {
                        if (is_string($value) && strcasecmp($value, 'Other') === 0) {
                            $selectedOther = true;
                            break;
                        }
                    }
                } elseif ($type === 'select') {
                    $value = $payload[$name] ?? null;
                    if (is_string($value) && strcasecmp($value, 'Other') === 0) {
                        $selectedOther = true;
                    }
                }

                if (! $selectedOther) {
                    continue;
                }

                $otherKey = $name.'__other_text';
                $otherValue = $payload[$otherKey] ?? null;

                if (! is_string($otherValue) || trim($otherValue) === '') {
                    $validator->errors()->add(
                        "answers_json.{$otherKey}",
                        __('Please describe the "Other" selection.')
                    );
                }
            }
        });

        $validator->validate();
    }

    private function throttleKey(string $formId, string $userId): string
    {
        return sprintf('form-autosave:%s:%s', $formId, $userId);
    }
}
