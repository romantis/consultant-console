<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubmitFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        // Field-level rules built in controller from the form schema; keep base shape here.
        return [
            'answers_json' => ['required', 'array'],
            'submission_token' => ['nullable', 'string'],
        ];
    }
}
