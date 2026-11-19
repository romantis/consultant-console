<?php

namespace App\Http\Requests\Workshops;

use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEngagementWorkshopSessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $composedSchedule = $this->composeScheduleFromSplitInputs();

        if ($composedSchedule === null) {
            return;
        }

        $this->merge($composedSchedule);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['prohibited'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'facilitator_membership_id' => [
                'nullable',
                'string',
                Rule::exists('memberships', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $this->organizationId())
                ),
            ],
            'metric_ids' => ['required', 'array', 'min:1'],
            'metric_ids.*' => [
                'string',
                Rule::exists('metrics', 'id')->where(
                    fn ($query) => $query->where('engagement_id', $this->engagementId())
                ),
            ],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [
                'string',
                Rule::exists('engagement_participants', 'id')->where(
                    fn ($query) => $query->where('engagement_id', $this->engagementId())
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if (! $startsAt || ! $endsAt) {
                return;
            }

            $start = Carbon::parse((string) $startsAt);
            $end = Carbon::parse((string) $endsAt);
            $timezone = trim((string) $this->input('timezone', ''));

            if ($timezone === '') {
                return;
            }

            try {
                $startInTimezone = $start->copy()->setTimezone($timezone);
                $endInTimezone = $end->copy()->setTimezone($timezone);
            } catch (\Throwable) {
                return;
            }

            if ($start->diffInMinutes($end, false) < 15) {
                $validator->errors()->add(
                    'ends_at',
                    __('Workshop sessions must be at least 15 minutes long.')
                );
            }

            if ($startInTimezone->toDateString() !== $endInTimezone->toDateString()) {
                $validator->errors()->add(
                    'ends_at',
                    __('Workshop sessions must start and end on the same day.')
                );
            }

            $startMinutes = ((int) $startInTimezone->format('H') * 60)
                + (int) $startInTimezone->format('i');
            $endMinutes = ((int) $endInTimezone->format('H') * 60)
                + (int) $endInTimezone->format('i');

            if ($startMinutes < (8 * 60) || $startMinutes > (17 * 60)) {
                $validator->errors()->add(
                    'starts_at',
                    __('Workshop session start time must be between 08:00 and 17:00.')
                );
            }

            if (($startMinutes % 5) !== 0) {
                $validator->errors()->add(
                    'starts_at',
                    __('Workshop session start time must use 5-minute increments.')
                );
            }

            if ($endMinutes > (19 * 60)) {
                $validator->errors()->add(
                    'ends_at',
                    __('Workshop session end time must be no later than 19:00.')
                );
            }

            if (($endMinutes % 5) !== 0) {
                $validator->errors()->add(
                    'ends_at',
                    __('Workshop session end time must use 5-minute increments.')
                );
            }

            if ($this->isLocalWorkshopForceStartEnabled()) {
                return;
            }

            if ($start->lessThan(now()->addMinutes(15))) {
                $validator->errors()->add(
                    'starts_at',
                    __('Workshop sessions must be scheduled at least 15 minutes in advance.')
                );
            }
        });
    }

    private function engagementId(): ?string
    {
        $engagement = $this->route('engagement');

        if ($engagement instanceof Engagement) {
            return (string) $engagement->getKey();
        }

        return is_string($engagement) ? $engagement : null;
    }

    private function organizationId(): ?string
    {
        $organization = $this->route('organization');

        if ($organization instanceof Organization) {
            return (string) $organization->getKey();
        }

        return is_string($organization) ? $organization : null;
    }

    private function isLocalWorkshopForceStartEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('metric-harmony.workshops.force_start_enabled');
    }

    /**
     * @return array{starts_at: string, ends_at: string}|null
     */
    private function composeScheduleFromSplitInputs(): ?array
    {
        $sessionDate = $this->input('session_date');
        $startsTime = $this->input('starts_time');
        $endsTime = $this->input('ends_time');
        $timezone = $this->input('timezone');

        if (
            ! is_string($sessionDate)
            || ! is_string($startsTime)
            || ! is_string($endsTime)
            || ! is_string($timezone)
        ) {
            return null;
        }

        $sessionDate = trim($sessionDate);
        $startsTime = trim($startsTime);
        $endsTime = trim($endsTime);
        $timezone = trim($timezone);

        if (
            $sessionDate === ''
            || $startsTime === ''
            || $endsTime === ''
            || $timezone === ''
        ) {
            return null;
        }

        try {
            $startsAt = Carbon::parse(
                "{$sessionDate} {$startsTime}",
                $timezone
            );
            $endsAt = Carbon::parse(
                "{$sessionDate} {$endsTime}",
                $timezone
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $startsAt || ! $endsAt) {
            return null;
        }

        return [
            'starts_at' => $startsAt->utc()->toIso8601String(),
            'ends_at' => $endsAt->utc()->toIso8601String(),
        ];
    }
}
