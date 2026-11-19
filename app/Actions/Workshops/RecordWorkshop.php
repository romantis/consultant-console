<?php

namespace App\Actions\Workshops;

use App\Actions\Audit\LogMetricEvent;
use App\Enums\MetricStatus;
use App\Models\EngagementWorkshopSession;
use App\Models\Metric;
use App\Models\ReconciliationWorkshop;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordWorkshop
{
    public function __construct(
        private LogMetricEvent $logMetricEvent
    ) {}

    /**
     * @param  array{session_id: string, facilitator_id: string|null, scheduled_at: \DateTimeInterface|string, participants?: array<int, array<string, mixed>>, decisions?: array<string, mixed>|null, notes?: string|null, transcript_url?: string|null}  $attributes
     */
    public function handle(Metric $metric, array $attributes, ?User $actor = null): ReconciliationWorkshop
    {
        $sessionId = trim((string) ($attributes['session_id'] ?? ''));
        if ($sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => __('Select a workshop session before recording execution.'),
            ]);
        }

        $session = EngagementWorkshopSession::query()
            ->whereKey($sessionId)
            ->where('organization_id', $metric->organization_id)
            ->where('engagement_id', $metric->engagement_id)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_id' => __('Selected workshop session must belong to this engagement.'),
            ]);
        }

        $scheduledAt = $attributes['scheduled_at'];
        if ($scheduledAt instanceof \DateTimeInterface) {
            $scheduledAt = Carbon::instance($scheduledAt);
        } else {
            $scheduledAt = Carbon::parse((string) $scheduledAt);
        }

        $workshop = $metric->workshops()->create([
            'engagement_id' => $metric->engagement_id,
            'session_id' => $session->getKey(),
            'facilitator_id' => $attributes['facilitator_id'],
            'scheduled_at' => $scheduledAt,
            'participants' => $attributes['participants'] ?? [],
            'decision_snapshot' => $attributes['decisions'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'transcript_url' => $attributes['transcript_url'] ?? null,
        ]);

        if ($metric->status !== MetricStatus::IN_WORKSHOP) {
            $metric->markStatus(MetricStatus::IN_WORKSHOP);
        }

        $this->logMetricEvent->handle(
            $metric,
            'metric.workshop.recorded',
            array_filter([
                'workshop_id' => (string) $workshop->getKey(),
                'session_id' => (string) $workshop->session_id,
                'facilitator_id' => $workshop->facilitator_id ? (string) $workshop->facilitator_id : null,
                'scheduled_at' => optional($workshop->scheduled_at)->toIso8601String(),
                'participant_ids' => collect($workshop->participants ?? [])
                    ->map(fn ($participant) => Arr::get($participant, 'id'))
                    ->filter()
                    ->values()
                    ->all(),
                'notes' => $workshop->notes,
                'decisions' => $workshop->decision_snapshot,
                'transcript_url' => $workshop->transcript_url,
            ], fn ($value) => $value !== null),
            $actor,
            $workshop
        );

        return $workshop->fresh();
    }
}
