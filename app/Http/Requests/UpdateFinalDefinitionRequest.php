<?php

namespace App\Http\Requests;

use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Support\Metrics\ConsultantSignals;
use App\Support\Metrics\ValidationGuidanceFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateFinalDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Metric $metric */
        $metric = $this->route('metric');
        $finalDefinition = $metric->finalDefinition;
        if (! $finalDefinition) {
            $finalDefinition = FinalDefinition::make(['metric_id' => $metric->id]);
            $finalDefinition->setRelation('metric', $metric);
        }

        return $this->user()?->can('update', $finalDefinition) ?? false;
    }

    public function rules(): array
    {
        /** @var Metric $metric */
        $metric = $this->route('metric');

        return array_merge([
            'definition' => ['required', 'array'],
            'definition.*.field' => ['required', 'string'],
            'definition.*.label' => ['required', 'string'],
            'definition.*.value' => ['nullable', 'string'],
            'definition.*.companion_of' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'owner_participant_id' => [
                'nullable',
                Rule::exists('metric_participants', 'id')
                    ->where(fn ($query) => $metric ? $query->where('metric_id', $metric->id) : $query),
            ],
        ], ConsultantSignals::rules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('definition', []);
            if (! is_array($items)) {
                return;
            }

            $fields = collect($items)
                ->map(fn ($item) => is_array($item) ? ($item['field'] ?? null) : null)
                ->filter(fn ($field) => is_string($field) && $field !== '')
                ->values()
                ->all();

            $fieldSet = array_flip($fields);

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $companionOf = $item['companion_of'] ?? null;
                if (! is_string($companionOf)) {
                    continue;
                }

                $companionOf = trim($companionOf);
                if ($companionOf === '') {
                    continue;
                }

                $field = $item['field'] ?? null;
                if (! is_string($field) || $field === '') {
                    continue;
                }

                if ($companionOf === $field) {
                    $validator->errors()->add(
                        "definition.$index.companion_of",
                        __('Companion field cannot reference itself.')
                    );

                    continue;
                }

                if (! array_key_exists($companionOf, $fieldSet)) {
                    $validator->errors()->add(
                        "definition.$index.companion_of",
                        __('Companion field must reference another field included in this definition payload.')
                    );
                }
            }
        });
    }

    public function definitionPayload(): array
    {
        $items = $this->validated()['definition'] ?? [];

        $payload = [];
        foreach ($items as $item) {
            if (ValidationGuidanceFields::contains($item['field'])) {
                continue;
            }

            $entry = [
                'label' => $item['label'],
                'value' => $item['value'] ?? null,
            ];

            $companionOf = $item['companion_of'] ?? null;
            if (is_string($companionOf) && trim($companionOf) !== '') {
                $entry['companion_of'] = trim($companionOf);
            }

            $payload[$item['field']] = $entry;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function consultantSignalPayload(): array
    {
        return ConsultantSignals::payload($this->validated());
    }
}
