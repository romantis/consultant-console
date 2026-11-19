<?php

namespace App\Actions\Workshops;

use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\EngagementWorkshopSessionParticipant;
use Carbon\CarbonInterface;

class DetectWorkshopSessionConflicts
{
    /**
     * @param  array<int, string>  $requiredParticipantIds
     * @return array<int, WorkshopSessionConflictWarning>
     */
    public function handle(
        Engagement $engagement,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $facilitatorMembershipId,
        array $requiredParticipantIds,
        ?string $excludeSessionId = null
    ): array {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return [];
        }

        $overlappingSessions = EngagementWorkshopSession::query()
            ->where('engagement_id', $engagement->getKey())
            ->whereIn('status', [
                EngagementWorkshopSessionStatus::SCHEDULED->value,
                EngagementWorkshopSessionStatus::IN_PROGRESS->value,
            ])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when(
                $excludeSessionId,
                fn ($query, $sessionId) => $query->whereKeyNot($sessionId)
            )
            ->get(['id', 'facilitator_membership_id']);

        if ($overlappingSessions->isEmpty()) {
            return [];
        }

        /** @var array<string, WorkshopSessionConflictWarning> $warnings */
        $warnings = [];

        if ($facilitatorMembershipId) {
            $facilitatorConflicts = $overlappingSessions
                ->filter(fn (EngagementWorkshopSession $session) => (string) $session->facilitator_membership_id === $facilitatorMembershipId);

            foreach ($facilitatorConflicts as $session) {
                $warning = WorkshopSessionConflictWarning::facilitator((string) $session->getKey());
                $warnings[$warning->key()] = $warning;
            }
        }

        $participantIds = collect($requiredParticipantIds)
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values();

        if ($participantIds->isEmpty()) {
            return array_values($warnings);
        }

        $overlappingSessionIds = $overlappingSessions
            ->map(fn (EngagementWorkshopSession $session) => (string) $session->getKey())
            ->values();

        $participantConflicts = EngagementWorkshopSessionParticipant::query()
            ->whereIn('session_id', $overlappingSessionIds->all())
            ->whereIn('engagement_participant_id', $participantIds->all())
            ->get(['session_id', 'engagement_participant_id']);

        foreach ($participantConflicts as $participantConflict) {
            $warning = WorkshopSessionConflictWarning::participant(
                (string) $participantConflict->session_id,
                (string) $participantConflict->engagement_participant_id
            );
            $warnings[$warning->key()] = $warning;
        }

        return array_values($warnings);
    }
}

readonly class WorkshopSessionConflictWarning
{
    public function __construct(
        public string $type,
        public string $sessionId,
        public string $message,
        public ?string $engagementParticipantId = null
    ) {}

    public static function facilitator(string $sessionId): self
    {
        return new self(
            type: 'facilitator_overlap',
            sessionId: $sessionId,
            message: 'Facilitator is already assigned to an overlapping workshop session.'
        );
    }

    public static function participant(string $sessionId, string $engagementParticipantId): self
    {
        return new self(
            type: 'participant_overlap',
            sessionId: $sessionId,
            message: 'Required participant is already assigned to an overlapping workshop session.',
            engagementParticipantId: $engagementParticipantId
        );
    }

    public function key(): string
    {
        return implode(':', [
            $this->type,
            $this->sessionId,
            $this->engagementParticipantId ?? '-',
        ]);
    }
}
