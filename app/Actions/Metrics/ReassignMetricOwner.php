<?php

namespace App\Actions\Metrics;

use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReassignMetricOwner
{
    /**
     * @return array{metric_id:string, final_definition_id:string, owner_participant_id:string, owner_participant:array<string, mixed>}
     */
    public function handle(Metric $metric, string $ownerParticipantId, ?User $actor = null): array
    {
        $finalDefinition = $metric->finalDefinition;

        if (! $finalDefinition) {
            $finalDefinition = $metric->finalDefinition()->create([
                'user_id' => $actor?->getKey(),
                'status' => 'Draft',
                'definition_json' => [],
            ]);
        }

        if ($finalDefinition->signed_off_at) {
            throw ValidationException::withMessages([
                'owner_participant_id' => __('Final definition is locked after sign-off.'),
            ]);
        }

        $participant = MetricParticipant::query()
            ->with('user:id,name,email')
            ->where('metric_id', $metric->getKey())
            ->where('id', $ownerParticipantId)
            ->first();

        if (! $participant) {
            throw ValidationException::withMessages([
                'owner_participant_id' => __('Select a valid owner for this metric.'),
            ]);
        }

        if (! $participant->user_id) {
            throw ValidationException::withMessages([
                'owner_participant_id' => __('Select an accepted participant for the metric owner.'),
            ]);
        }

        $finalDefinition->forceFill([
            'owner_participant_id' => $participant->getKey(),
            'user_id' => $actor?->getKey(),
        ])->save();

        $finalDefinition->setRelation('ownerParticipant', $participant);

        return [
            'metric_id' => (string) $metric->getKey(),
            'final_definition_id' => (string) $finalDefinition->getKey(),
            'owner_participant_id' => (string) $participant->getKey(),
            'owner_participant' => [
                'id' => (string) $participant->getKey(),
                'responsibility' => $participant->responsibility,
                'user' => $participant->user?->only(['id', 'name', 'email']),
            ],
        ];
    }
}
