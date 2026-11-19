<?php

namespace App\Http\Requests;

use App\Enums\EngagementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class EngagementOverrideRequest extends FormRequest
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
        return [
            'status' => ['required', new Enum(EngagementStatus::class)],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => [
                Rule::requiredIf(fn (): bool => $this->boolean('override')),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function requestedStatus(): EngagementStatus
    {
        return EngagementStatus::fromLegacy((string) $this->validated('status'));
    }

    public function wantsOverride(): bool
    {
        return $this->boolean('override');
    }

    public function overrideReason(): ?string
    {
        $reason = $this->validated('override_reason');

        if (! is_scalar($reason) && $reason !== null) {
            return null;
        }

        $normalized = trim((string) $reason);

        return $normalized === '' ? null : $normalized;
    }
}
