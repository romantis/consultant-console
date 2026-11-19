<?php

namespace App\Http\Requests;

use App\Models\CanonicalMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCanonicalMetricRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CanonicalMetric $metric */
        $metric = $this->route('canonicalMetric');

        return $this->user()?->can('update', $metric) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        if (isset($payload['name']) && is_string($payload['name'])) {
            $payload['name'] = trim($payload['name']);
        }

        if (array_key_exists('description', $payload) && is_string($payload['description'])) {
            $payload['description'] = trim($payload['description']);
        }

        if (array_key_exists('default_metric_template_id', $payload) && is_string($payload['default_metric_template_id'])) {
            $payload['default_metric_template_id'] = trim($payload['default_metric_template_id']);

            if ($payload['default_metric_template_id'] === '' || $payload['default_metric_template_id'] === '__none__') {
                $payload['default_metric_template_id'] = null;
            }
        }

        $this->replace($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CanonicalMetric $metric */
        $metric = $this->route('canonicalMetric');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'default_metric_template_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('metric_templates', 'id')->where(
                    fn ($query) => $query->where('canonical_metric_id', $metric->getKey())
                ),
            ],
        ];
    }
}
