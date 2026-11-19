<?php

namespace App\Actions\Workshops;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\EngagementWorkshopSession;
use App\Models\Membership;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateEngagementWorkshopSession
{
    public function __construct(
        private readonly ValidateWorkshopSessionCoverage $validateCoverage,
        private readonly DetectWorkshopSessionConflicts $detectConflicts,
        private readonly DispatchWorkshopSessionNotifications $dispatchNotifications,
        private readonly LogEngagementEvent $logEngagementEvent,
    ) {}

    /**
     * @param  array{
     *     starts_at: string,
     *     ends_at: string,
     *     timezone: string,
     *     metric_ids: array<int, string>,
     *     participant_ids: array<int, string>,
     *     notes?: string|null,
     *     facilitator_membership_id?: string|null
     * }  $attributes
     */
    public function handle(
        EngagementWorkshopSession $session,
        array $attributes,
        ?User $actor = null
    ): WorkshopSessionMutationResult {
        $status = $session->status instanceof EngagementWorkshopSessionStatus
            ? $session->status
            : EngagementWorkshopSessionStatus::from((string) $session->status);

        if (in_array($status, [
            EngagementWorkshopSessionStatus::COMPLETED,
            EngagementWorkshopSessionStatus::CANCELED,
            EngagementWorkshopSessionStatus::MISSED,
        ], true)) {
            throw ValidationException::withMessages([
                'session' => __(
                    'Completed, canceled, or missed workshop sessions cannot be edited.'
                ),
            ]);
        }

        if ($status === EngagementWorkshopSessionStatus::IN_PROGRESS) {
            $this->assertInProgressEditability($session, $attributes);
        }

        $session->loadMissing('participants');

        $previousSchedule = [
            'starts_at' => optional($session->starts_at)?->toIso8601String(),
            'ends_at' => optional($session->ends_at)?->toIso8601String(),
            'timezone' => $session->timezone ? (string) $session->timezone : null,
        ];
        $previousParticipantIds = $session->participants
            ->map(fn ($participant) => (string) $participant->engagement_participant_id)
            ->unique()
            ->values()
            ->all();

        $metricIds = $this->normalizeIds($attributes['metric_ids']);
        $participantIds = $this->normalizeIds($attributes['participant_ids']);
        $requiredParticipantIds = $this->validateCoverage->handle(
            $session->engagement,
            $metricIds,
            $participantIds
        );

        $incomingStartsAt = Carbon::parse((string) $attributes['starts_at']);
        $incomingEndsAt = Carbon::parse((string) $attributes['ends_at']);
        $requestedTimezone = trim((string) $attributes['timezone']);
        $startsAt = $incomingStartsAt->copy()->setTimezone(config('app.timezone'));
        $endsAt = $incomingEndsAt->copy()->setTimezone(config('app.timezone'));
        $preserveScheduleValues = $this->isEquivalentScheduleSelection(
            $session,
            $incomingStartsAt,
            $incomingEndsAt,
            $requestedTimezone
        );
        $timezoneToPersist = $requestedTimezone;

        if ($preserveScheduleValues) {
            $startsAt = $session->starts_at?->copy() ?? $startsAt;
            $endsAt = $session->ends_at?->copy() ?? $endsAt;
            $timezoneToPersist = $session->timezone
                ? trim((string) $session->timezone)
                : $requestedTimezone;
        }

        $facilitatorMembershipId = $this->resolveFacilitatorMembershipId(
            $session,
            $attributes['facilitator_membership_id'] ?? null
        );

        $warnings = $this->detectConflicts->handle(
            engagement: $session->engagement,
            startsAt: $startsAt,
            endsAt: $endsAt,
            facilitatorMembershipId: $facilitatorMembershipId,
            requiredParticipantIds: $requiredParticipantIds,
            excludeSessionId: (string) $session->getKey()
        );

        $updated = DB::transaction(function () use (
            $session,
            $startsAt,
            $endsAt,
            $attributes,
            $timezoneToPersist,
            $facilitatorMembershipId,
            $metricIds,
            $participantIds
        ): EngagementWorkshopSession {
            $session->forceFill([
                'facilitator_membership_id' => $facilitatorMembershipId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $timezoneToPersist,
                'notes' => $attributes['notes'] ?? null,
            ])->save();

            $session->metrics()->delete();
            $session->participants()->delete();

            $session->metrics()->createMany(
                collect($metricIds)
                    ->map(fn (string $metricId) => ['metric_id' => $metricId])
                    ->all()
            );

            $session->participants()->createMany(
                collect($participantIds)
                    ->map(fn (string $participantId) => ['engagement_participant_id' => $participantId])
                    ->all()
            );

            return $session;
        });

        $this->logEngagementEvent->handle(
            $updated->engagement,
            'engagement.workshop_session.updated',
            [
                'session_id' => (string) $updated->getKey(),
                'status' => $status->value,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'timezone' => $updated->timezone,
                'facilitator_membership_id' => $facilitatorMembershipId,
                'metric_ids' => $metricIds,
                'participant_ids' => $participantIds,
                'warnings' => $this->serializeWarnings($warnings),
            ],
            $actor,
            $updated
        );

        try {
            $dispatchSession = $updated->fresh([
                'facilitatorMembership.user',
                'participants.engagementParticipant.membership.user',
            ]);

            $this->dispatchNotifications->dispatchUpdated(
                $dispatchSession,
                $previousSchedule,
                $previousParticipantIds
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return new WorkshopSessionMutationResult(
            session: $updated->fresh(['metrics', 'participants']),
            warnings: $warnings
        );
    }

    /**
     * @param  array{
     *     starts_at?: string,
     *     ends_at?: string,
     *     timezone?: string,
     *     metric_ids?: array<int, string>,
     *     participant_ids?: array<int, string>,
     *     facilitator_membership_id?: string|null
     * }  $attributes
     */
    private function assertInProgressEditability(
        EngagementWorkshopSession $session,
        array $attributes
    ): void {
        $errors = [];

        if (array_key_exists('starts_at', $attributes)) {
            $incomingStartsAt = Carbon::parse((string) $attributes['starts_at'])->toIso8601String();
            $currentStartsAt = optional($session->starts_at)?->toIso8601String();

            if ($incomingStartsAt !== $currentStartsAt) {
                $errors['starts_at'] = __('In-progress workshop sessions cannot change schedule or scope.');
            }
        }

        if (array_key_exists('ends_at', $attributes)) {
            $incomingEndsAt = Carbon::parse((string) $attributes['ends_at'])->toIso8601String();
            $currentEndsAt = optional($session->ends_at)?->toIso8601String();

            if ($incomingEndsAt !== $currentEndsAt) {
                $errors['ends_at'] = __('In-progress workshop sessions cannot change schedule or scope.');
            }
        }

        if (
            array_key_exists('timezone', $attributes)
            && trim((string) $attributes['timezone']) !== (string) $session->timezone
        ) {
            $errors['timezone'] = __('In-progress workshop sessions cannot change schedule or scope.');
        }

        if (array_key_exists('facilitator_membership_id', $attributes)) {
            $incomingFacilitator = $attributes['facilitator_membership_id']
                ? (string) $attributes['facilitator_membership_id']
                : null;
            $currentFacilitator = $session->facilitator_membership_id
                ? (string) $session->facilitator_membership_id
                : null;

            if ($incomingFacilitator !== $currentFacilitator) {
                $errors['facilitator_membership_id'] = __(
                    'In-progress workshop sessions cannot change schedule or scope.'
                );
            }
        }

        if (array_key_exists('metric_ids', $attributes)) {
            $incomingMetricIds = collect($this->normalizeIds($attributes['metric_ids']))
                ->sort()
                ->values()
                ->all();
            $currentMetricIds = $session->metrics()
                ->pluck('metric_id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            if ($incomingMetricIds !== $currentMetricIds) {
                $errors['metric_ids'] = __('In-progress workshop sessions cannot change schedule or scope.');
            }
        }

        if (array_key_exists('participant_ids', $attributes)) {
            $incomingParticipantIds = collect($this->normalizeIds($attributes['participant_ids']))
                ->sort()
                ->values()
                ->all();
            $currentParticipantIds = $session->participants()
                ->pluck('engagement_participant_id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all();

            if ($incomingParticipantIds !== $currentParticipantIds) {
                $errors['participant_ids'] = __('In-progress workshop sessions cannot change schedule or scope.');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isEquivalentScheduleSelection(
        EngagementWorkshopSession $session,
        Carbon $incomingStartsAt,
        Carbon $incomingEndsAt,
        string $incomingTimezone
    ): bool {
        if (! $session->starts_at || ! $session->ends_at) {
            return false;
        }

        $currentStartsAtMinute = $session->starts_at->copy()->utc()->format('Y-m-d\TH:i');
        $currentEndsAtMinute = $session->ends_at->copy()->utc()->format('Y-m-d\TH:i');
        $incomingStartsAtMinute = $incomingStartsAt->copy()->utc()->format('Y-m-d\TH:i');
        $incomingEndsAtMinute = $incomingEndsAt->copy()->utc()->format('Y-m-d\TH:i');
        $currentTimezone = $this->normalizeTimezoneName(
            $session->timezone ? (string) $session->timezone : null
        );
        $incomingTimezoneName = $this->normalizeTimezoneName($incomingTimezone);
        $timezoneUnchanged = $currentTimezone === null
            || $incomingTimezoneName === null
            || $currentTimezone === $incomingTimezoneName;

        return $currentStartsAtMinute === $incomingStartsAtMinute
            && $currentEndsAtMinute === $incomingEndsAtMinute
            && $timezoneUnchanged;
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
            return (new \DateTimeZone($timezone))->getName();
        } catch (Throwable) {
            return $timezone;
        }
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveFacilitatorMembershipId(
        EngagementWorkshopSession $session,
        ?string $facilitatorMembershipId
    ): ?string {
        if (! $facilitatorMembershipId) {
            return $session->facilitator_membership_id
                ? (string) $session->facilitator_membership_id
                : null;
        }

        $membership = Membership::query()
            ->with('user:id')
            ->where('organization_id', $session->organization_id)
            ->whereKey($facilitatorMembershipId)
            ->first();

        if (! $membership || ! $membership->user || ! Staff::isInternalStaff($membership->user)) {
            throw ValidationException::withMessages([
                'facilitator_membership_id' => __(
                    'Facilitator must be a staff membership in this organization.'
                ),
            ]);
        }

        return (string) $membership->getKey();
    }

    /**
     * @param  array<int, WorkshopSessionConflictWarning>  $warnings
     * @return array<int, array<string, string|null>>
     */
    private function serializeWarnings(array $warnings): array
    {
        return collect($warnings)
            ->map(fn (WorkshopSessionConflictWarning $warning) => [
                'type' => $warning->type,
                'session_id' => $warning->sessionId,
                'message' => $warning->message,
                'engagement_participant_id' => $warning->engagementParticipantId,
            ])
            ->values()
            ->all();
    }
}
