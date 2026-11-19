<?php

namespace App\Actions\Workshops;

use App\Actions\Audit\LogEngagementEvent;
use App\Actions\Engagements\UpdateEngagementStatus;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\EngagementWorkshopSessionMetric;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\User;
use App\Support\ImpersonationGuard;
use App\Support\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateEngagementWorkshopSession
{
    public function __construct(
        private readonly ValidateWorkshopSessionCoverage $validateCoverage,
        private readonly DetectWorkshopSessionConflicts $detectConflicts,
        private readonly DispatchWorkshopSessionNotifications $dispatchNotifications,
        private readonly LogEngagementEvent $logEngagementEvent,
        private readonly UpdateEngagementStatus $updateEngagementStatus,
    ) {}

    /**
     * @param  array{
     *     starts_at: string,
     *     ends_at: string,
     *     timezone: string,
     *     metric_ids: array<int, string>,
     *     participant_ids: array<int, string>,
     *     notes?: string|null,
     *     facilitator_membership_id?: string|null,
     *     status?: string
     * }  $attributes
     */
    public function handle(
        Engagement $engagement,
        array $attributes,
        ?User $actor = null
    ): WorkshopSessionMutationResult {
        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $metricIds = $this->normalizeIds($attributes['metric_ids']);
        $participantIds = $this->normalizeIds($attributes['participant_ids']);
        $requiredParticipantIds = $this->validateCoverage->handle(
            $engagement,
            $metricIds,
            $participantIds
        );

        $status = isset($attributes['status'])
            ? EngagementWorkshopSessionStatus::from((string) $attributes['status'])
            : EngagementWorkshopSessionStatus::SCHEDULED;

        if ($status !== EngagementWorkshopSessionStatus::SCHEDULED) {
            throw ValidationException::withMessages([
                'status' => __('New workshop sessions must be created in Scheduled status.'),
            ]);
        }

        $this->assertNoActiveMetricSessions($engagement, $metricIds);

        $startsAt = Carbon::parse((string) $attributes['starts_at'])
            ->setTimezone(config('app.timezone'));
        $endsAt = Carbon::parse((string) $attributes['ends_at'])
            ->setTimezone(config('app.timezone'));
        $facilitatorMembershipId = $this->resolveFacilitatorMembershipId(
            $engagement,
            $attributes['facilitator_membership_id'] ?? null,
            $actor
        );

        $warnings = $this->detectConflicts->handle(
            engagement: $engagement,
            startsAt: $startsAt,
            endsAt: $endsAt,
            facilitatorMembershipId: $facilitatorMembershipId,
            requiredParticipantIds: $requiredParticipantIds
        );

        $session = DB::transaction(function () use (
            $engagement,
            $engagementStatus,
            $startsAt,
            $endsAt,
            $attributes,
            $status,
            $facilitatorMembershipId,
            $metricIds,
            $participantIds,
            $actor
        ): EngagementWorkshopSession {
            $session = EngagementWorkshopSession::query()->create([
                'organization_id' => $engagement->organization_id,
                'engagement_id' => $engagement->getKey(),
                'facilitator_membership_id' => $facilitatorMembershipId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => trim((string) $attributes['timezone']),
                'status' => $status,
                'notes' => $attributes['notes'] ?? null,
            ]);

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

            if ($engagementStatus === EngagementStatus::IN_REVIEW) {
                $this->updateEngagementStatus->handle(
                    $engagement,
                    EngagementStatus::IN_WORKSHOP,
                    $actor
                );
            }

            return $session;
        });

        $this->logEngagementEvent->handle(
            $engagement,
            'engagement.workshop_session.created',
            [
                'session_id' => (string) $session->getKey(),
                'status' => $status->value,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'timezone' => $session->timezone,
                'facilitator_membership_id' => $facilitatorMembershipId,
                'metric_ids' => $metricIds,
                'participant_ids' => $participantIds,
                'warnings' => $this->serializeWarnings($warnings),
            ],
            $actor,
            $session
        );

        try {
            $this->dispatchNotifications->dispatchCreated($session);
        } catch (Throwable $exception) {
            report($exception);
        }

        return new WorkshopSessionMutationResult(
            session: $session->fresh(['metrics', 'participants']),
            warnings: $warnings
        );
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
        Engagement $engagement,
        ?string $facilitatorMembershipId,
        ?User $actor
    ): ?string {
        if ($facilitatorMembershipId) {
            $membership = Membership::query()
                ->with('user:id')
                ->where('organization_id', $engagement->organization_id)
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

        if (! $actor) {
            return null;
        }

        return ImpersonationGuard::resolve($actor, $engagement->organization_id)
            ->membership()
            ?->getKey();
    }

    /**
     * @param  array<int, string>  $metricIds
     */
    private function assertNoActiveMetricSessions(Engagement $engagement, array $metricIds): void
    {
        $activeMetricIds = EngagementWorkshopSessionMetric::query()
            ->whereIn('metric_id', $metricIds)
            ->whereHas('session', function ($query) use ($engagement): void {
                $query
                    ->where('engagement_id', $engagement->getKey())
                    ->whereIn('status', [
                        EngagementWorkshopSessionStatus::SCHEDULED->value,
                        EngagementWorkshopSessionStatus::IN_PROGRESS->value,
                    ]);
            })
            ->pluck('metric_id')
            ->map(fn ($metricId) => (string) $metricId)
            ->unique()
            ->values();

        if ($activeMetricIds->isEmpty()) {
            return;
        }

        $metricNames = Metric::query()
            ->where('engagement_id', $engagement->getKey())
            ->whereIn('id', $activeMetricIds->all())
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        $metricList = $metricNames->isNotEmpty()
            ? $metricNames->implode(', ')
            : __('selected metrics');

        throw ValidationException::withMessages([
            'metric_ids' => __(
                'Selected metrics already have a scheduled or in-progress workshop session: :metrics.',
                ['metrics' => $metricList]
            ),
        ]);
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
