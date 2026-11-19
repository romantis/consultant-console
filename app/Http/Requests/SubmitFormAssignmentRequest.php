<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubmitFormAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'payload' => ['required', 'array'],
            'attestation' => ['nullable', 'array'],
            'attestation.confirm_accuracy' => ['required_with:attestation', 'boolean'],
            'attestation.comment' => ['nullable', 'string'],
            'submission_token' => ['nullable', 'string'],
        ];
    }
}
