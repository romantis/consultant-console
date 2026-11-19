<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AutosaveFormAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'draft' => ['required', 'array'],
            'submission_token' => ['nullable', 'string'],
        ];
    }
}
