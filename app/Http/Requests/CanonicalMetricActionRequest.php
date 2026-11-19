<?php

namespace App\Http\Requests;

use App\Models\CanonicalMetric;
use Illuminate\Foundation\Http\FormRequest;

class CanonicalMetricActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CanonicalMetric $metric */
        $metric = $this->route('canonicalMetric');

        return $this->user()?->can('delete', $metric) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
