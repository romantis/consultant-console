<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\OrgMetric;
use App\Support\Metrics\ConsultantSignals;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrgMetricSignalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        /** @var OrgMetric|null $orgMetric */
        $orgMetric = $this->route('orgMetric');

        if (! $user || ! $organization || ! $orgMetric) {
            return false;
        }

        if ($orgMetric->organization_id !== $organization->id) {
            return false;
        }

        return $user->can('updateSignals', $orgMetric);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ConsultantSignals::rules();
    }

    /**
     * @return array<string, mixed>
     */
    public function consultantSignalPayload(): array
    {
        return ConsultantSignals::payload($this->validated());
    }
}
