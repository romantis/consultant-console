<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'engagement_id' => ['required', 'ulid', 'exists:engagements,id'],
            'template_key' => ['required', 'exists:metric_templates,key'],
            'title' => ['required', 'string', 'max:160'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
