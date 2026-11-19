<?php

namespace App\Support\Metrics;

use Illuminate\Validation\Rule;

final class ConsultantSignals
{
    /**
     * @var array<int, string>
     */
    public const OPERATIONAL_STATUSES = [
        'operationally_ready',
        'needs_follow_up',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'alignment_summary' => ['nullable', 'string'],
            'operational_status' => ['nullable', 'string', Rule::in(self::OPERATIONAL_STATUSES)],
            'operational_status_notes' => ['nullable', 'string'],
            'implementation_gaps' => ['nullable', 'array:current_state,gap,owner,suggested_next_step,next_step'],
            'implementation_gaps.current_state' => ['nullable', 'string'],
            'implementation_gaps.gap' => ['nullable', 'string'],
            'implementation_gaps.owner' => ['nullable', 'string'],
            'implementation_gaps.suggested_next_step' => ['nullable', 'string'],
            'implementation_gaps.next_step' => ['nullable', 'string'],
            'validation_steps' => ['nullable', 'string'],
            'common_mismatch_causes' => ['nullable', 'string'],
            'executive_takeaway' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payload(array $validated): array
    {
        $payload = [];

        if (array_key_exists('alignment_summary', $validated)) {
            $payload['alignment_summary'] = $validated['alignment_summary'];
        }

        if (array_key_exists('operational_status', $validated)) {
            $payload['operational_status'] = $validated['operational_status'];
        }

        if (array_key_exists('operational_status_notes', $validated)) {
            $payload['operational_status_notes'] = $validated['operational_status_notes'];
        }

        if (array_key_exists('implementation_gaps', $validated)) {
            $payload['implementation_gaps'] = self::normalizeImplementationGaps(
                $validated['implementation_gaps']
            );
        }

        if (array_key_exists('validation_steps', $validated)) {
            $payload['validation_steps'] = $validated['validation_steps'];
        }

        if (array_key_exists('common_mismatch_causes', $validated)) {
            $payload['common_mismatch_causes'] = $validated['common_mismatch_causes'];
        }

        if (array_key_exists('executive_takeaway', $validated)) {
            $payload['executive_takeaway'] = $validated['executive_takeaway'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $implementationGaps
     * @return array<string, string>|null
     */
    private static function normalizeImplementationGaps(?array $implementationGaps): ?array
    {
        if ($implementationGaps === null) {
            return null;
        }

        $payload = array_filter([
            'current_state' => $implementationGaps['current_state'] ?? null,
            'gap' => $implementationGaps['gap'] ?? null,
            'owner' => $implementationGaps['owner'] ?? null,
            'suggested_next_step' => $implementationGaps['suggested_next_step']
                ?? $implementationGaps['next_step']
                ?? null,
        ], static fn ($value) => $value !== null);

        return $payload === [] ? null : $payload;
    }
}
