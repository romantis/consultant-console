<?php

namespace App\Jobs;

use App\Actions\Audit\LogChangeProposalEvent;
use App\Models\ChangeProposal;
use App\Models\MetricParticipant;
use App\Models\User;
use App\Notifications\ChangeProposalReviewRequested;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyChangeProposalApprovers implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $proposalId,
        public string $organizationId,
        public string $engagementId,
        public string $metricId,
        public ?string $actorId = null
    ) {
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(LogChangeProposalEvent $logChangeProposalEvent): void
    {
        Telemetry::replace($this->organizationId, $this->engagementId, $this->metricId);

        /** @var ChangeProposal|null $proposal */
        $proposal = ChangeProposal::query()
            ->with([
                'organization',
                'metric.organization',
                'initiator.user',
            ])
            ->find($this->proposalId);

        if (! $proposal || ! $proposal->metric) {
            return;
        }

        $approvers = $this->resolveApprovers($proposal);
        $actor = $this->resolveActor();

        if ($approvers->isEmpty()) {
            $logChangeProposalEvent->handle(
                $proposal,
                'metric.change-proposal.notifications.skipped',
                ['reason' => 'missing-approvers'],
                $actor
            );

            return;
        }

        Notification::send(
            $approvers,
            new ChangeProposalReviewRequested(
                $proposal,
                $proposal->metric,
                $proposal->organization ?? $proposal->metric->organization,
                $proposal->initiator?->user?->name
            )
        );

        $logChangeProposalEvent->handle(
            $proposal,
            'metric.change-proposal.notified',
            [
                'approver_user_ids' => $approvers
                    ->map(fn (User $user) => (string) $user->getKey())
                    ->values()
                    ->all(),
            ],
            $actor
        );
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveApprovers(ChangeProposal $proposal): Collection
    {
        $approverIds = collect($proposal->approver_ids ?? [])
            ->map(static fn ($value) => (string) $value)
            ->filter()
            ->unique()
            ->values();

        if ($approverIds->isEmpty()) {
            return collect();
        }

        return MetricParticipant::query()
            ->with('user')
            ->whereIn('id', $approverIds->all())
            ->get()
            ->map(fn (MetricParticipant $participant) => $participant->user)
            ->filter(fn (?User $user) => $user !== null && ! empty($user->email))
            ->unique(fn (User $user) => $user->getKey())
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
            ['job:notify-change-proposal-approvers']
        );
    }
}
