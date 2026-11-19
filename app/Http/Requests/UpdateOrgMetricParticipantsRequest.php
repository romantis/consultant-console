<?php

namespace App\Http\Requests;

use App\Models\Membership;
use App\Models\OrgMetric;
use App\Models\PendingMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateOrgMetricParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OrgMetric $orgMetric */
        $orgMetric = $this->route('orgMetric');

        return $this->user()?->can('manageParticipants', $orgMetric) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        $payload['owners'] = collect($payload['owners'] ?? [])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values()
            ->all();

        $payload['approvers'] = collect($payload['approvers'] ?? [])
            ->filter(fn ($value) => is_string($value))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values()
            ->all();

        $this->replace($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owners' => ['nullable', 'array', 'max:1'],
            'owners.*' => ['string', 'distinct'],
            'approvers' => ['nullable', 'array', 'max:1'],
            'approvers.*' => ['string', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $orgMetric = $this->route('orgMetric');
            if (! $orgMetric instanceof OrgMetric) {
                return;
            }

            $membershipIds = Membership::query()
                ->where('organization_id', $orgMetric->organization_id)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values();

            $pendingIds = PendingMembership::query()
                ->where('organization_id', $orgMetric->organization_id)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values();

            $this->validateParticipants('owners', $membershipIds, $pendingIds, $validator);
            $this->validateParticipants('approvers', $membershipIds, $pendingIds, $validator);
        });
    }

    private function validateParticipants(
        string $field,
        Collection $membershipIds,
        Collection $pendingIds,
        Validator $validator
    ): void {
        $values = collect($this->input($field, []));

        $values->each(function ($value, int $index) use ($field, $membershipIds, $pendingIds, $validator) {
            if (! is_string($value)) {
                return;
            }

            $identifier = trim($value);

            if ($identifier === '') {
                $validator->errors()->add("{$field}.{$index}", __('Participant is required.'));

                return;
            }

            if (Str::startsWith($identifier, 'invite:')) {
                $validator->errors()->add("{$field}.{$index}", __('Invites must be created from engagement workflows.'));

                return;
            }

            if (Str::startsWith($identifier, 'pending:')) {
                $pendingId = (string) Str::after($identifier, 'pending:');

                if ($pendingId === '' || $pendingIds->doesntContain($pendingId)) {
                    $validator->errors()->add("{$field}.{$index}", __('Selected participant could not be found.'));
                }

                return;
            }

            if ($membershipIds->contains($identifier) || $pendingIds->contains($identifier)) {
                return;
            }

            $validator->errors()->add("{$field}.{$index}", __('Selected participant could not be found.'));
        });
    }
}
