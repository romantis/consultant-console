<?php

namespace App\Http\Requests;

use App\Models\OrgMetric;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrgMetricAliasRequest extends FormRequest
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

        if (array_key_exists('alias', $payload) && is_string($payload['alias'])) {
            $payload['alias'] = trim($payload['alias']);
        }

        $this->replace($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alias' => ['nullable', 'string', 'max:255'],
        ];
    }
}
