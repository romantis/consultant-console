<?php

namespace App\Http\Requests;

use App\Models\OrgMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrgMetricDefaultTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OrgMetric $orgMetric */
        $orgMetric = $this->route('orgMetric');

        return $this->user()?->can('update', $orgMetric) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

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
        /** @var OrgMetric $orgMetric */
        $orgMetric = $this->route('orgMetric');

        return [
            'default_metric_template_id' => [
                'nullable',
                'string',
                Rule::exists('metric_templates', 'id')->where(
                    fn ($query) => $query->where('canonical_metric_id', $orgMetric->canonical_metric_id)
                ),
            ],
        ];
    }
}
