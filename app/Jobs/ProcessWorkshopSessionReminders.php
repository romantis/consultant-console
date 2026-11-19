<?php

namespace App\Jobs;

use App\Actions\Workshops\TransitionEngagementWorkshopSession;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\EngagementWorkshopSession;
use App\Models\User;
use App\Notifications\WorkshopSessionReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ProcessWorkshopSessionReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const WINDOW_T_MINUS_24H = 'T-24h';

    public const WINDOW_T_MINUS_1H = 'T-1h';

    private const START_LATE_GRACE_MINUTES = 15;

    public function __construct(
        private readonly ?string $organizationId = null,
        private readonly ?string $engagementId = null
    ) {
        $this->onQueue(config('queue.queues.reminders', 'reminders'));
    }

    public function handle(): void
    {
        $this->markMissedSessions();

        foreach ($this->windowOffsetsInMinutes() as $window => $offsetInMinutes) {
            $windowStart = now()->addMinutes($offsetInMinutes);
            $windowEnd = $windowStart->copy()->addMinutes(5);

            EngagementWorkshopSession::query()
                ->where('status', EngagementWorkshopSessionStatus::SCHEDULED->value)
                ->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->whereHas('engagement', function ($query): void {
                    $query->where('status', '!=', EngagementStatus::FINALIZED->value);
                })
                ->when(
                    $this->organizationId,
                    fn ($query, $organizationId) => $query->where('organization_id', $organizationId)
                )
                ->when(
                    $this->engagementId,
                    fn ($query, $engagementId) => $query->where('engagement_id', $engagementId)
                )
                ->with([
                    'engagement',
                    'facilitatorMembership.user:id,name,email',
                    'participants.engagementParticipant.membership.user:id,name,email',
                ])
                ->orderBy('id')
                ->chunkById(100, function ($sessions) use ($window) {
                    foreach ($sessions as $session) {
                        $this->dispatchReminder($session, $window);
                    }
                }, column: 'id');
        }
    }

    private function markMissedSessions(): void
    {
        $cutoff = now()->subMinutes(self::START_LATE_GRACE_MINUTES);
        $transitionSession = app(TransitionEngagementWorkshopSession::class);

        EngagementWorkshopSession::query()
            ->where('status', EngagementWorkshopSessionStatus::SCHEDULED->value)
            ->where('starts_at', '<', $cutoff)
            ->whereHas('engagement', function ($query): void {
                $query->where('status', '!=', EngagementStatus::FINALIZED->value);
            })
            ->when(
                $this->organizationId,
                fn ($query, $organizationId) => $query->where('organization_id', $organizationId)
            )
            ->when(
                $this->engagementId,
                fn ($query, $engagementId) => $query->where('engagement_id', $engagementId)
            )
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use ($transitionSession): void {
                foreach ($sessions as $session) {
                    try {
                        $transitionSession->handle(
                            $session,
                            EngagementWorkshopSessionStatus::MISSED
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            }, column: 'id');
    }

    private function dispatchReminder(EngagementWorkshopSession $session, string $window): void
    {
        $recipients = $this->resolveRecipients($session);

        foreach ($recipients as $recipient) {
            if (! $this->claimReminderDelivery($session, $recipient, $window)) {
                continue;
            }

            try {
                $recipient->notify(new WorkshopSessionReminderNotification($session, $window));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(EngagementWorkshopSession $session): Collection
    {
        $facilitator = $session->facilitatorMembership?->user;

        $participants = $session->participants
            ->map(fn ($participant) => $participant->engagementParticipant?->membership?->user)
            ->filter(fn (?User $user) => $user !== null);

        return collect([$facilitator])
            ->merge($participants)
            ->filter(fn (?User $user) => $user !== null && filled($user->email))
            ->unique(fn (User $user) => (string) $user->getKey())
            ->values();
    }

    private function claimReminderDelivery(
        EngagementWorkshopSession $session,
        User $recipient,
        string $window
    ): bool {
        $cacheKey = implode(':', [
            'workshop-session-reminder',
            (string) $session->getKey(),
            $window,
            (string) $recipient->getKey(),
        ]);

        $expiresAt = $session->starts_at
            ? $session->starts_at->copy()->addDay()
            : now()->addDay();

        return Cache::add($cacheKey, true, $expiresAt);
    }

    /**
     * @return array<string, int>
     */
    private function windowOffsetsInMinutes(): array
    {
        return [
            self::WINDOW_T_MINUS_24H => 24 * 60,
            self::WINDOW_T_MINUS_1H => 60,
        ];
    }
}
