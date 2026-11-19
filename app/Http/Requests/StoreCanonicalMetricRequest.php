<?php

namespace App\Http\Requests;

use App\Models\CanonicalMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCanonicalMetricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CanonicalMetric::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        if (isset($payload['key']) && is_string($payload['key'])) {
            $payload['key'] = Str::lower(trim($payload['key']));
        }

        if (isset($payload['name']) && is_string($payload['name'])) {
            $payload['name'] = trim($payload['name']);
        }

        if (isset($payload['description']) && is_string($payload['description'])) {
            $payload['description'] = trim($payload['description']);
        }

        $this->replace($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('canonical_metrics', 'key'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
