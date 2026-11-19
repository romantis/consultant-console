<?php

namespace App\Actions\Engagements;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Models\User;
use App\Notifications\EngagementKickoffAssignments;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateEngagementStatus
{
    private const BLOCKED_REASON_MISSING_VALID_SESSION = 'missing_valid_session';

    private const BLOCKED_REASON_MISSING_FINALIZED_METRICS = 'missing_finalized_metrics';

    public function __construct(
        private EnsureOrgMetricExclusivity $ensureOrgMetricExclusivity,
        private LogEngagementEvent $logEngagementEvent,
    ) {}

    public function handle(
        Engagement $engagement,
        EngagementStatus $status,
        ?User $actor = null,
        bool $overrideBlockedTransition = false,
        ?string $overrideReason = null
    ): Engagement {
        $currentStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        $normalizedOverrideReason = $this->normalizeOverrideReason($overrideReason);

        if ($overrideBlockedTransition && $normalizedOverrideReason === null) {
            throw ValidationException::withMessages([
                'override_reason' => __('Provide an override reason before applying an override.'),
            ]);
        }

        if ($currentStatus === $status) {
            return $engagement;
        }

        if (! $currentStatus || ! in_array($status, $currentStatus->allowedTransitions(), true)) {
            throw ValidationException::withMessages([
                'status' => __('This engagement status transition is not allowed.'),
            ]);
        }

        $isKickoffTransition = $currentStatus === EngagementStatus::DRAFT
            && $status === EngagementStatus::AWAITING_FORMS;

        if (
            $status === EngagementStatus::FINALIZED
            && $currentStatus === EngagementStatus::IN_WORKSHOP
        ) {
            $this->assertAllMetricsFinalized(
                $engagement,
                $currentStatus,
                $status,
                $actor,
                $overrideBlockedTransition,
                $normalizedOverrideReason
            );
        }

        if (
            $currentStatus === EngagementStatus::IN_REVIEW
            && $status === EngagementStatus::IN_WORKSHOP
        ) {
            $this->assertValidWorkshopSessionGate(
                $engagement,
                $currentStatus,
                $status,
                $actor,
                $overrideBlockedTransition,
                $normalizedOverrideReason
            );
        }

        if ($isKickoffTransition) {
            $engagement->loadMissing('organization', 'metrics.orgMetric.canonicalMetric');

            $selections = $engagement->metrics->map(function (Metric $metric) {
                $canonical = $metric->orgMetric?->canonicalMetric;

                return [
                    'org_metric_id' => $metric->org_metric_id,
                    'canonical_name' => $canonical?->name ?? $metric->orgMetric?->name ?? $metric->name,
                    'canonical_key' => $canonical?->key ?? $metric->orgMetric?->key ?? $metric->key,
                ];
            });

            $this->ensureOrgMetricExclusivity->handle(
                $engagement->organization,
                $selections,
                $engagement,
                'status'
            );
        }

        $payload = ['status' => $status];

        if ($status === EngagementStatus::FINALIZED && ! $engagement->finalized_at) {
            $payload['finalized_at'] = now();
        }

        $engagement->forceFill($payload)->save();

        if ($isKickoffTransition) {
            $this->notifyKickoffAssignments($engagement);
        }

        return $engagement;
    }

    private function notifyKickoffAssignments(Engagement $engagement): void
    {
        $eligibleStatuses = array_map(
            static fn (FormAssignmentStatus $status) => $status->value,
            [
                FormAssignmentStatus::OPEN,
                FormAssignmentStatus::IN_PROGRESS,
                FormAssignmentStatus::REOPENED,
            ]
        );

        $assignments = FormAssignment::query()
            ->whereHas('form', fn ($query) => $query->where('engagement_id', $engagement->id))
            ->whereHas('user.memberships', fn ($query) => $query->where('organization_id', $engagement->organization_id))
            ->where(function ($query) use ($eligibleStatuses) {
                $query->whereNull('status');

                if ($eligibleStatuses !== []) {
                    $query->orWhereIn('status', $eligibleStatuses);
                }
            })
            ->whereRaw('LOWER(COALESCE(responsibility, ?)) = ?', ['', 'contributor'])
            ->with(['user:id,name,email', 'form:id,title'])
            ->orderBy('user_id')
            ->orderBy('form_id')
            ->get()
            ->groupBy('user_id');

        foreach ($assignments as $userAssignments) {
            $recipient = $userAssignments->first()?->user;
            $email = (string) ($recipient?->email ?? '');

            if (! $recipient || $email === '') {
                continue;
            }

            $formLinks = $userAssignments
                ->map(fn (FormAssignment $assignment): array => [
                    'title' => (string) ($assignment->form?->title ?? __('Open form')),
                    'url' => route('forms.show', ['form' => $assignment->form_id]),
                ])
                ->unique('url')
                ->values()
                ->all();

            if ($formLinks === []) {
                continue;
            }

            try {
                $recipient->notify(new EngagementKickoffAssignments($engagement, $formLinks));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function assertAllMetricsFinalized(
        Engagement $engagement,
        EngagementStatus $fromStatus,
        EngagementStatus $toStatus,
        ?User $actor = null,
        bool $overrideBlockedTransition = false,
        ?string $overrideReason = null
    ): void {
        $metricsTotal = $engagement->metrics()->count();

        $finalizedTotal = $engagement->metrics()
            ->whereIn('status', [
                MetricStatus::FINALIZED->value,
                MetricStatus::DEPRECATED->value,
            ])
            ->count();

        if ($metricsTotal !== 0 && $finalizedTotal >= $metricsTotal) {
            return;
        }

        if ($overrideBlockedTransition && $overrideReason !== null) {
            $this->logEngagementEvent->logStatusOverrideApplied(
                $engagement,
                $fromStatus,
                $toStatus,
                self::BLOCKED_REASON_MISSING_FINALIZED_METRICS,
                $overrideReason,
                $actor
            );

            return;
        }

        throw ValidationException::withMessages([
            'status' => __('Finalize or deprecate all metrics before closing the engagement.'),
        ]);
    }

    private function assertValidWorkshopSessionGate(
        Engagement $engagement,
        EngagementStatus $fromStatus,
        EngagementStatus $toStatus,
        ?User $actor = null,
        bool $overrideBlockedTransition = false,
        ?string $overrideReason = null
    ): void {
        $hasValidWorkshopSession = $engagement->workshopSessions()
            ->whereIn('status', [
                EngagementWorkshopSessionStatus::SCHEDULED->value,
                EngagementWorkshopSessionStatus::IN_PROGRESS->value,
                EngagementWorkshopSessionStatus::COMPLETED->value,
            ])
            ->exists();

        if ($hasValidWorkshopSession) {
            return;
        }

        if ($overrideBlockedTransition && $overrideReason !== null) {
            $this->logEngagementEvent->logStatusOverrideApplied(
                $engagement,
                $fromStatus,
                $toStatus,
                self::BLOCKED_REASON_MISSING_VALID_SESSION,
                $overrideReason,
                $actor
            );

            return;
        }

        $this->logEngagementEvent->handle(
            $engagement,
            'engagement.status.transition_blocked',
            [
                'from_status' => $fromStatus->value,
                'to_status' => $toStatus->value,
                'reason' => self::BLOCKED_REASON_MISSING_VALID_SESSION,
            ],
            $actor,
            $engagement
        );

        throw ValidationException::withMessages([
            'status' => __(
                'Schedule, start, or complete at least one workshop session before moving to In Workshop.'
            ),
        ]);
    }

    private function normalizeOverrideReason(?string $overrideReason): ?string
    {
        $normalized = trim((string) $overrideReason);

        return $normalized === '' ? null : $normalized;
    }
}
