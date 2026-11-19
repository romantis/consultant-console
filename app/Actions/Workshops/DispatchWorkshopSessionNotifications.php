<?php

namespace App\Actions\Workshops;

use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\User;
use App\Notifications\WorkshopSessionCanceledNotification;
use App\Notifications\WorkshopSessionInviteNotification;
use App\Notifications\WorkshopSessionUninvitedNotification;
use App\Notifications\WorkshopSessionUpdatedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class DispatchWorkshopSessionNotifications
{
    public function dispatchCreated(EngagementWorkshopSession $session): void
    {
        $session->loadMissing([
            'facilitatorMembership.user:id,name,email',
            'participants.engagementParticipant.membership.user:id,name,email',
        ]);

        $this->notifyRecipients(
            $this->resolveCurrentRecipients($session),
            fn () => new WorkshopSessionInviteNotification($session),
        );
    }

    /**
     * @param  array{starts_at: string|null, ends_at: string|null, timezone: string|null}  $previousSchedule
     * @param  array<int, string>  $previousParticipantIds
     */
    public function dispatchUpdated(
        EngagementWorkshopSession $session,
        array $previousSchedule,
        array $previousParticipantIds
    ): void {
        $session->loadMissing([
            'facilitatorMembership.user:id,name,email',
            'participants.engagementParticipant.membership.user:id,name,email',
        ]);
        $hasScheduleChange = $this->hasScheduleChange($session, $previousSchedule);

        $currentParticipantIds = $session->participants
            ->map(fn ($participant) => (string) $participant->engagement_participant_id)
            ->unique()
            ->values();
        $previousParticipantIdsCollection = collect($previousParticipantIds)
            ->map(fn ($participantId) => (string) $participantId)
            ->unique()
            ->values();

        $addedParticipantIds = $currentParticipantIds
            ->diff($previousParticipantIdsCollection)
            ->values();
        $removedParticipantIds = $previousParticipantIdsCollection
            ->diff($currentParticipantIds)
            ->values();
        $removedParticipantRecipients = $this->resolveParticipantRecipientsByIds(
            $session,
            $removedParticipantIds
        );
        $currentRecipients = $this->resolveCurrentRecipients($session);

        if ($hasScheduleChange) {
            $this->notifyRecipients(
                $currentRecipients,
                fn () => new WorkshopSessionUpdatedNotification($session),
            );

            $this->notifyRecipients(
                $removedParticipantRecipients,
                fn () => new WorkshopSessionUninvitedNotification($session),
            );

            return;
        }

        if ($addedParticipantIds->isNotEmpty()) {
            $newParticipantRecipients = $session->participants
                ->filter(fn ($participant) => $addedParticipantIds->contains((string) $participant->engagement_participant_id))
                ->map(fn ($participant) => $participant->engagementParticipant?->membership?->user)
                ->filter(fn (?User $user) => $user instanceof User && filled($user->email))
                ->unique(fn (User $user) => (string) $user->getKey())
                ->values();

            $this->notifyRecipients(
                $newParticipantRecipients,
                fn () => new WorkshopSessionInviteNotification($session),
            );
        }

        $this->notifyRecipients(
            $removedParticipantRecipients,
            fn () => new WorkshopSessionUninvitedNotification($session),
        );
    }

    public function dispatchCanceled(EngagementWorkshopSession $session): void
    {
        $session->loadMissing([
            'facilitatorMembership.user:id,name,email',
            'participants.engagementParticipant.membership.user:id,name,email',
        ]);

        $this->notifyRecipients(
            $this->resolveCurrentRecipients($session),
            fn () => new WorkshopSessionCanceledNotification($session),
        );
    }

    /**
     * @param  array{starts_at: string|null, ends_at: string|null, timezone: string|null}  $previousSchedule
     */
    private function hasScheduleChange(
        EngagementWorkshopSession $session,
        array $previousSchedule
    ): bool {
        $currentStartsAt = $session->starts_at
            ? $session->starts_at->copy()->utc()->format('Y-m-d\TH:i')
            : null;
        $currentEndsAt = $session->ends_at
            ? $session->ends_at->copy()->utc()->format('Y-m-d\TH:i')
            : null;
        $previousStartsAt = $this->normalizeIsoTimestampToMinute(
            $previousSchedule['starts_at'] ?? null
        );
        $previousEndsAt = $this->normalizeIsoTimestampToMinute(
            $previousSchedule['ends_at'] ?? null
        );
        $currentTimezone = $this->normalizeTimezoneName(
            $session->timezone ? (string) $session->timezone : null
        );
        $previousTimezone = $this->normalizeTimezoneName(
            is_string($previousSchedule['timezone'] ?? null)
                ? (string) $previousSchedule['timezone']
                : null
        );

        $timezoneChanged = $currentTimezone !== null
            && $previousTimezone !== null
            && $currentTimezone !== $previousTimezone;
        $startsAtChanged = $currentStartsAt !== $previousStartsAt;
        $endsAtChanged = $currentEndsAt !== $previousEndsAt;

        return $startsAtChanged || $endsAtChanged || $timezoneChanged;
    }

    private function normalizeIsoTimestampToMinute(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->format('Y-m-d\TH:i');
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeTimezoneName(?string $timezone): ?string
    {
        if (! is_string($timezone)) {
            return null;
        }

        $timezone = trim($timezone);

        if ($timezone === '') {
            return null;
        }

        try {
            return new \DateTimeZone($timezone)->getName();
        } catch (Throwable) {
            return $timezone;
        }
    }

    /**
     * @param  Collection<int, string>  $participantIds
     * @return Collection<int, User>
     */
    private function resolveParticipantRecipientsByIds(
        EngagementWorkshopSession $session,
        Collection $participantIds
    ): Collection {
        if ($participantIds->isEmpty()) {
            return collect();
        }

        return EngagementParticipant::query()
            ->where('engagement_id', $session->engagement_id)
            ->whereIn('id', $participantIds->all())
            ->with('membership.user:id,name,email')
            ->get()
            ->map(fn (EngagementParticipant $participant) => $participant->membership?->user)
            ->filter(fn (?User $user) => $user instanceof User && filled($user->email))
            ->unique(fn (User $user) => (string) $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveCurrentRecipients(EngagementWorkshopSession $session): Collection
    {
        $facilitator = $session->facilitatorMembership?->user;

        $participants = $session->participants
            ->map(fn ($participant) => $participant->engagementParticipant?->membership?->user)
            ->filter(fn (?User $user) => $user instanceof User);

        return collect([$facilitator])
            ->merge($participants)
            ->filter(fn (?User $user) => $user instanceof User && filled($user->email))
            ->unique(fn (User $user) => (string) $user->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  \Closure(): object  $notificationFactory
     */
    private function notifyRecipients(Collection $recipients, \Closure $notificationFactory): void
    {
        foreach ($recipients as $recipient) {
            try {
                $recipient->notify($notificationFactory());
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
