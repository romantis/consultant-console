<?php

namespace App\Http\Requests\Workshops;

use Illuminate\Foundation\Http\FormRequest;

class TransitionEngagementWorkshopSessionRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:20000'],
            'force_start' => ['sometimes', 'boolean'],
        ];
    }
}
