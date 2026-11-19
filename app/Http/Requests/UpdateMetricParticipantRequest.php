<?php

namespace App\Http\Requests;

use App\Models\Engagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetricParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $engagement = $this->route('engagement');
        $participantRule = Rule::exists('engagement_participants', 'id');

        if ($engagement instanceof Engagement) {
            $participantRule->where(fn ($query) => $query->where('engagement_id', $engagement->id));
        }

        return [
            'engagement_participant_id' => ['required', 'string', $participantRule],
            'responsibility' => ['required', 'string', Rule::in(['contributor', 'approver', 'observer'])],
            'role_tags' => ['nullable', 'array'],
            'role_tags.*' => ['string', 'max:50'],
        ];
    }
}
