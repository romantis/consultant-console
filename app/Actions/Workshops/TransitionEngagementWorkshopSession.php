<?php

namespace App\Actions\Workshops;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\EngagementWorkshopSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransitionEngagementWorkshopSession
{
    private const START_EARLY_GRACE_MINUTES = 10;

    private const START_LATE_GRACE_MINUTES = 15;

    public function __construct(
        private readonly DispatchWorkshopSessionNotifications $dispatchNotifications,
        private readonly LogEngagementEvent $logEngagementEvent,
    ) {}

    public function handle(
        EngagementWorkshopSession $session,
        EngagementWorkshopSessionStatus $targetStatus,
        ?string $notes = null,
        ?User $actor = null,
        bool $forceStart = false
    ): EngagementWorkshopSession {
        $currentStatus = $session->status instanceof EngagementWorkshopSessionStatus
            ? $session->status
            : EngagementWorkshopSessionStatus::from((string) $session->status);

        if (! $this->isAllowedTransition($currentStatus, $targetStatus)) {
            throw ValidationException::withMessages([
                'status' => __('This workshop session transition is not allowed.'),
            ]);
        }

        $forceStartApplied = false;

        if ($targetStatus === EngagementWorkshopSessionStatus::IN_PROGRESS) {
            $forceStartApplied = $this->assertCanStartWithinWindow(
                $session,
                $currentStatus,
                $actor,
                $forceStart
            );
        }

        $attributes = [
            'status' => $targetStatus,
        ];

        if ($targetStatus === EngagementWorkshopSessionStatus::CANCELED) {
            $attributes['notes'] = $notes ?: $session->notes;
        }

        $session->forceFill($attributes)->save();

        $this->logEngagementEvent->handle(
            $session->engagement,
            $this->auditEventName($targetStatus, $forceStartApplied),
            [
                'session_id' => (string) $session->getKey(),
                'previous_status' => $currentStatus->value,
                'status' => $targetStatus->value,
                'notes' => $notes,
                'force_start' => $forceStartApplied ? true : null,
                'force_started_at' => $forceStartApplied
                    ? now()->toIso8601String()
                    : null,
            ],
            $actor,
            $session
        );

        if ($targetStatus === EngagementWorkshopSessionStatus::CANCELED) {
            try {
                $this->dispatchNotifications->dispatchCanceled($session);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $session->fresh();
    }

    private function assertCanStartWithinWindow(
        EngagementWorkshopSession $session,
        EngagementWorkshopSessionStatus $currentStatus,
        ?User $actor,
        bool $forceStart
    ): bool {
        if ($currentStatus !== EngagementWorkshopSessionStatus::SCHEDULED) {
            return false;
        }

        if (! $session->starts_at) {
            throw ValidationException::withMessages([
                'status' => __('This workshop session does not have a valid start time.'),
            ]);
        }

        if ($forceStart && $this->isLocalForceStartEnabled()) {
            return true;
        }

        $startWindowOpensAt = $session->starts_at
            ->copy()
            ->subMinutes(self::START_EARLY_GRACE_MINUTES);
        $startWindowClosesAt = $session->starts_at
            ->copy()
            ->addMinutes(self::START_LATE_GRACE_MINUTES);
        $now = now();

        if ($now->greaterThan($startWindowClosesAt)) {
            $this->markSessionAsMissed($session, $currentStatus, $actor);

            throw ValidationException::withMessages([
                'status' => __(
                    'This workshop session missed its start window. Schedule a new session.'
                ),
            ]);
        }

        if ($now->lessThan($startWindowOpensAt)) {
            throw ValidationException::withMessages([
                'status' => __(
                    'This workshop session can be started between :opens and :closes.',
                    [
                        'opens' => $startWindowOpensAt->toIso8601String(),
                        'closes' => $startWindowClosesAt->toIso8601String(),
                    ]
                ),
            ]);
        }

        return false;
    }

    private function markSessionAsMissed(
        EngagementWorkshopSession $session,
        EngagementWorkshopSessionStatus $previousStatus,
        ?User $actor = null
    ): void {
        if ($session->status === EngagementWorkshopSessionStatus::MISSED) {
            return;
        }

        $session->forceFill([
            'status' => EngagementWorkshopSessionStatus::MISSED,
        ])->save();

        $this->logEngagementEvent->handle(
            $session->engagement,
            $this->auditEventName(EngagementWorkshopSessionStatus::MISSED),
            [
                'session_id' => (string) $session->getKey(),
                'previous_status' => $previousStatus->value,
                'status' => EngagementWorkshopSessionStatus::MISSED->value,
                'reason' => 'start_window_elapsed',
            ],
            $actor,
            $session
        );
    }

    private function isAllowedTransition(
        EngagementWorkshopSessionStatus $currentStatus,
        EngagementWorkshopSessionStatus $targetStatus
    ): bool {
        return match ($currentStatus) {
            EngagementWorkshopSessionStatus::SCHEDULED => in_array($targetStatus, [
                EngagementWorkshopSessionStatus::IN_PROGRESS,
                EngagementWorkshopSessionStatus::CANCELED,
                EngagementWorkshopSessionStatus::MISSED,
            ], true),
            EngagementWorkshopSessionStatus::IN_PROGRESS => in_array($targetStatus, [
                EngagementWorkshopSessionStatus::COMPLETED,
                EngagementWorkshopSessionStatus::CANCELED,
            ], true),
            EngagementWorkshopSessionStatus::COMPLETED,
            EngagementWorkshopSessionStatus::CANCELED,
            EngagementWorkshopSessionStatus::MISSED => false,
        };
    }

    private function isLocalForceStartEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('metric-harmony.workshops.force_start_enabled');
    }

    private function auditEventName(
        EngagementWorkshopSessionStatus $status,
        bool $forceStarted = false
    ): string {
        if ($status === EngagementWorkshopSessionStatus::IN_PROGRESS && $forceStarted) {
            return 'engagement.workshop_session.force_started';
        }

        return match ($status) {
            EngagementWorkshopSessionStatus::IN_PROGRESS => 'engagement.workshop_session.started',
            EngagementWorkshopSessionStatus::COMPLETED => 'engagement.workshop_session.completed',
            EngagementWorkshopSessionStatus::CANCELED => 'engagement.workshop_session.canceled',
            EngagementWorkshopSessionStatus::MISSED => 'engagement.workshop_session.missed',
            default => 'engagement.workshop_session.updated',
        };
    }
}
