<?php

namespace App\Jobs;

use App\Actions\Audit\LogOrgMetricEvent;
use App\Models\Metric;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use App\Notifications\OrgMetricSignOffApproved;
use App\Notifications\OrgMetricSignOffChangesRequested;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyOrgMetricSignOff implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const EVENT_APPROVED = 'approved';

    public const EVENT_CHANGES_REQUESTED = 'changes_requested';

    public function __construct(
        public string $orgMetricId,
        public string $metricId,
        public string $organizationId,
        public string $engagementId,
        public string $event,
        public ?string $actorId = null,
        public ?string $message = null,
        public ?array $recipientUserIds = null
    ) {
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(LogOrgMetricEvent $logOrgMetricEvent): void
    {
        Telemetry::replace($this->organizationId, $this->engagementId, $this->metricId);

        $orgMetric = OrgMetric::query()
            ->with(['organization', 'canonicalMetric'])
            ->find($this->orgMetricId);

        $metric = Metric::query()
            ->with(['organization', 'engagement'])
            ->find($this->metricId);

        if (! $orgMetric || ! $metric) {
            return;
        }

        $actor = $this->resolveActor();
        $recipients = $this->resolveRecipients($orgMetric, $this->event, $actor);

        if ($recipients->isEmpty()) {
            $logOrgMetricEvent->handle(
                $orgMetric,
                'org-metric.sign-off.notifications.skipped',
                [
                    'event' => $this->event,
                    'reason' => 'missing-recipients',
                ],
                $actor,
                $orgMetric
            );

            return;
        }

        $notification = match ($this->event) {
            self::EVENT_APPROVED => new OrgMetricSignOffApproved(
                $orgMetric,
                $metric,
                $orgMetric->organization ?? $metric->organization,
                $this->message,
                $actor?->name
            ),
            self::EVENT_CHANGES_REQUESTED => new OrgMetricSignOffChangesRequested(
                $orgMetric,
                $metric,
                $orgMetric->organization ?? $metric->organization,
                $this->message,
                $actor?->name
            ),
            default => null,
        };

        if (! $notification) {
            return;
        }

        Notification::send($recipients, $notification);

        $logOrgMetricEvent->handle(
            $orgMetric,
            'org-metric.sign-off.notified',
            [
                'event' => $this->event,
                'recipient_user_ids' => $recipients
                    ->map(fn (User $user) => (string) $user->getKey())
                    ->values()
                    ->all(),
            ],
            $actor,
            $orgMetric
        );
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(OrgMetric $orgMetric, string $event, ?User $actor = null): Collection
    {
        $responsibilities = match ($event) {
            default => ['owner'],
        };

        $recipientIds = collect($this->recipientUserIds ?? [])
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->unique()
            ->values();

        $query = OrgMetricParticipant::query()
            ->with('user')
            ->where('org_metric_id', $orgMetric->getKey())
            ->whereIn('responsibility', $responsibilities)
            ->whereNotNull('user_id');

        $recipients = $query->get()
            ->map(fn (OrgMetricParticipant $participant) => $participant->user)
            ->filter(fn (?User $user) => $user !== null && ! empty($user->email))
            ->unique(fn (User $user) => $user->getKey())
            ->values();

        if (! $actor) {
            return $recipients;
        }

        return $recipients
            ->reject(fn (User $user) => (string) $user->getKey() === (string) $actor->getKey())
            ->values();
    }

    protected function resolveActor(): ?User
    {
        if (! $this->actorId) {
            return null;
        }

        return User::query()->find($this->actorId);
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId,
                'engagement_id' => $this->engagementId,
                'metric_id' => $this->metricId,
            ],
            ['job:notify-org-metric-sign-off']
        );
    }
}
