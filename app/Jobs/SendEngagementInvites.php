<?php

namespace App\Jobs;

use App\Actions\Audit\LogEngagementEvent;
use App\Models\Engagement;
use App\Models\PendingMembership;
use App\Models\User;
use App\Notifications\PendingMembershipInvited;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendEngagementInvites implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param  array<int, string>  $pendingMembershipIds */
    public function __construct(
        public string $organizationId,
        public string $engagementId,
        public array $pendingMembershipIds,
        public ?string $actorId = null
    ) {
        $this->pendingMembershipIds = array_values(array_unique(array_map('strval', $pendingMembershipIds)));
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(LogEngagementEvent $logEngagementEvent): void
    {
        Telemetry::replace($this->organizationId, $this->engagementId);

        if (empty($this->pendingMembershipIds)) {
            return;
        }

        /** @var Engagement|null $engagement */
        $engagement = Engagement::query()
            ->with('organization')
            ->find($this->engagementId);

        if (! $engagement) {
            return;
        }

        /** @var User|null $actor */
        $actor = $this->actorId
            ? User::query()->find($this->actorId)
            : null;

        $pendingMemberships = PendingMembership::query()
            ->whereIn('id', $this->pendingMembershipIds)
            ->get()
            ->filter(fn (PendingMembership $pending) => $pending->engagement_id === $engagement->id);

        if ($pendingMemberships->isEmpty()) {
            return;
        }

        foreach ($pendingMemberships as $pending) {
            if (! $pending->email) {
                continue;
            }

            Notification::route('mail', $pending->email)
                ->notify(new PendingMembershipInvited($pending));

            $logEngagementEvent->handle(
                $engagement,
                'engagement.invitation.dispatched',
                [
                    'pending_membership_id' => (string) $pending->getKey(),
                    'pending_email' => $pending->email,
                    'invited_by_user_id' => $pending->invited_by,
                    'membership_role' => $pending->role?->value ?? (string) $pending->role,
                ],
                $actor,
                $pending
            );
        }
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId,
                'engagement_id' => $this->engagementId,
            ],
            [
                'job:send-engagement-invites',
                'pending:'.count($this->pendingMembershipIds),
            ]
        );
    }
}
