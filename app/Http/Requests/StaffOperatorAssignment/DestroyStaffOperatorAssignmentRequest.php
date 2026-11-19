<?php

namespace App\Http\Requests\StaffOperatorAssignment;

use App\Models\Organization;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Foundation\Http\FormRequest;

class DestroyStaffOperatorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $organization = $this->route('organization');

        return $actor instanceof User
            && $organization instanceof Organization
            && ! $organization->is_internal
            && Staff::canOperateDelivery($actor);
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        foreach (['reason', 'notes'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        $this->replace($payload);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
