<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetricParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $participants = collect($this->input('participants', []))
            ->filter(fn ($entry) => is_array($entry))
            ->map(function (array $entry) {
                return [
                    'engagement_participant_id' => isset($entry['engagement_participant_id'])
                        ? trim((string) $entry['engagement_participant_id'])
                        : null,
                    'responsibility' => isset($entry['responsibility'])
                        ? trim(strtolower((string) $entry['responsibility']))
                        : null,
                    'role_tags' => isset($entry['role_tags']) && is_array($entry['role_tags'])
                        ? collect($entry['role_tags'])
                            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                            ->map(fn (string $tag) => trim($tag))
                            ->values()
                            ->all()
                        : [],
                ];
            })
            ->values()
            ->all();

        $this->merge(['participants' => $participants]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participants' => ['required', 'array'],
            'participants.*.engagement_participant_id' => ['required', 'string', 'distinct', 'max:80'],
            'participants.*.responsibility' => ['required', 'string', Rule::in(['contributor', 'approver', 'observer'])],
            'participants.*.role_tags' => ['nullable', 'array'],
            'participants.*.role_tags.*' => ['string', 'max:50'],
        ];
    }
}
