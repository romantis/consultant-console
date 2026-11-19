<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LaunchEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        $payload['stakeholders'] = collect($payload['stakeholders'] ?? [])
            ->filter(fn ($stakeholder) => is_array($stakeholder))
            ->map(function (array $stakeholder) {
                if (isset($stakeholder['participant_id']) && is_string($stakeholder['participant_id'])) {
                    $stakeholder['participant_id'] = trim($stakeholder['participant_id']);
                }

                if (isset($stakeholder['email']) && is_string($stakeholder['email'])) {
                    $stakeholder['email'] = mb_strtolower(trim($stakeholder['email']));
                }

                if (isset($stakeholder['role']) && is_string($stakeholder['role'])) {
                    $stakeholder['role'] = ucfirst(mb_strtolower($stakeholder['role']));
                }

                if (isset($stakeholder['department']) && is_string($stakeholder['department'])) {
                    $stakeholder['department'] = trim($stakeholder['department']);
                }

                if (isset($stakeholder['name']) && is_string($stakeholder['name'])) {
                    $stakeholder['name'] = trim($stakeholder['name']);
                }

                return $stakeholder;
            })
            ->values()
            ->all();

        $payload['metrics'] = collect($payload['metrics'] ?? [])
            ->filter(fn ($metric) => is_array($metric))
            ->map(function (array $metric) {
                if (isset($metric['canonical_metric_id']) && is_string($metric['canonical_metric_id'])) {
                    $metric['canonical_metric_id'] = trim($metric['canonical_metric_id']);
                }

                if (isset($metric['template_id']) && is_string($metric['template_id'])) {
                    $metric['template_id'] = trim($metric['template_id']);

                    if ($metric['template_id'] === '') {
                        $metric['template_id'] = null;
                    }
                }

                if (isset($metric['key']) && is_string($metric['key'])) {
                    $metric['key'] = trim($metric['key']);
                }

                if (isset($metric['name']) && is_string($metric['name'])) {
                    $metric['name'] = trim($metric['name']);
                }

                if (isset($metric['description']) && is_string($metric['description'])) {
                    $metric['description'] = trim($metric['description']);
                }

                $metric['participants'] = collect($metric['participants'] ?? [])
                    ->filter(fn ($participant) => is_array($participant))
                    ->map(function (array $participant) {
                        if (isset($participant['participant_id']) && is_string($participant['participant_id'])) {
                            $participant['participant_id'] = trim($participant['participant_id']);
                        }

                        if (isset($participant['role']) && is_string($participant['role'])) {
                            $participant['role'] = ucfirst(mb_strtolower($participant['role']));
                        }

                        return $participant;
                    })
                    ->values()
                    ->all();

                return $metric;
            })
            ->values()
            ->all();

        $payload['invited_emails'] = collect($payload['invited_emails'] ?? [])
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();

        $this->replace($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $repositoryFeatureEnabled = (bool) config('metric-harmony.features.repository_drivers', false);
        $minMetrics = (int) config('metric-harmony.engagements.metrics_min', 3);
        $maxMetrics = (int) config('metric-harmony.engagements.metrics_max', 10);

        $repositoryDriverRules = ['nullable', 'string', 'max:100'];
        if ($repositoryFeatureEnabled) {
            // TODO: Drop once repository adapters are live; driver input can stay optional.
            array_unshift($repositoryDriverRules, 'required_with:handbook_repository');
        }

        return [
            'organization_id' => ['required', 'string', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'timeline_start' => ['nullable', 'date'],
            'timeline_end' => ['nullable', 'date', 'after_or_equal:timeline_start'],
            'handbook_repository' => ['nullable', 'array'],
            'handbook_repository.driver' => $repositoryDriverRules,
            'handbook_repository.identifier' => ['nullable', 'string', 'max:255'],
            'handbook_repository.notes' => ['nullable', 'string'],
            'stakeholders' => ['required', 'array', 'min:1'],
            'stakeholders.*.participant_id' => ['nullable', 'string'],
            'stakeholders.*.email' => ['required', 'string', 'email'],
            'stakeholders.*.role' => ['nullable', 'string', Rule::in(['Admin', 'Member', 'Viewer'])],
            'stakeholders.*.department' => ['nullable', 'string', 'max:255'],
            'stakeholders.*.name' => ['nullable', 'string', 'max:255'],
            'metrics' => ['required', 'array', "min:{$minMetrics}", "max:{$maxMetrics}"],
            'metrics.*.template_id' => ['nullable', 'string', 'exists:metric_templates,id'],
            'metrics.*.canonical_metric_id' => ['required', 'string', 'exists:canonical_metrics,id'],
            'metrics.*.key' => ['nullable', 'string', 'max:120'],
            'metrics.*.name' => ['nullable', 'string', 'max:255'],
            'metrics.*.description' => ['nullable', 'string'],
            'metrics.*.required_departments' => ['nullable', 'array'],
            'metrics.*.required_departments.*' => ['string', 'max:120'],
            'metrics.*.due_date' => ['nullable', 'date'],
            'metrics.*.participants' => ['required', 'array', 'min:1'],
            'metrics.*.participants.*.participant_id' => ['required', 'string'],
            'metrics.*.participants.*.role' => ['required', 'string', Rule::in(['Contributor', 'Approver', 'Observer'])],
            'metrics.*.participants.*.role_tags' => ['nullable', 'array'],
            'metrics.*.participants.*.role_tags.*' => ['string', 'max:120'],
            'invited_emails' => ['nullable', 'array'],
            'invited_emails.*' => ['string', 'email'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $stakeholders = collect($this->input('stakeholders', []));

            $stakeholders->each(function ($stakeholder, int $index) use ($validator) {
                $participantId = is_array($stakeholder) ? trim((string) ($stakeholder['participant_id'] ?? '')) : '';
                $email = is_array($stakeholder) ? trim((string) ($stakeholder['email'] ?? '')) : '';

                if ($email === '') {
                    $validator->errors()->add(
                        "stakeholders.$index.email",
                        __('Stakeholder email is required.')
                    );
                }

                if ($participantId === '' && $email === '') {
                    $validator->errors()->add(
                        "stakeholders.$index.participant_id",
                        __('Provide an existing participant or invite email for each stakeholder.')
                    );
                }
            });

            $normalizedInvitePrefixes = $stakeholders
                ->filter(fn ($stakeholder) => is_array($stakeholder))
                ->pluck('email')
                ->filter()
                ->map(fn ($email) => 'invite:'.mb_strtolower((string) $email))
                ->merge(
                    collect($this->input('invited_emails', []))
                        ->filter()
                        ->map(fn ($email) => 'invite:'.mb_strtolower((string) $email))
                )
                ->unique()
                ->values();

            $metrics = collect($this->input('metrics', []));

            foreach ($metrics as $index => $metric) {
                $participants = collect($metric['participants'] ?? []);
                $roles = $participants
                    ->map(fn ($participant) => mb_strtolower((string) ($participant['role'] ?? '')))
                    ->filter();

                if ($roles->filter(fn (string $role) => $role === 'contributor')->isEmpty()) {
                    $validator->errors()->add(
                        "metrics.$index.participants",
                        __('Each metric must include at least one contributor.')
                    );
                }

                $observerCount = $roles->filter(fn (string $role) => $role === 'observer')->count();

                if ($observerCount > 5) {
                    $validator->errors()->add(
                        "metrics.$index.participants",
                        __('Metrics cannot include more than five observers.')
                    );
                }

                $participants->each(function ($participant, int $participantIndex) use (
                    $index,
                    $validator,
                    $normalizedInvitePrefixes
                ) {
                    if (! is_array($participant)) {
                        return;
                    }

                    $identifier = (string) ($participant['participant_id'] ?? '');

                    if (! str_starts_with($identifier, 'invite:')) {
                        return;
                    }

                    $normalized = 'invite:'.mb_strtolower((string) mb_substr($identifier, 7));

                    if ($normalizedInvitePrefixes->doesntContain($normalized)) {
                        $validator->errors()->add(
                            "metrics.$index.participants.$participantIndex.participant_id",
                            __('Invited participants must be present in stakeholders or invited_emails.')
                        );
                    }
                });
            }
        });
    }
}
