<?php

namespace App\Jobs;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EngagementStatusAutoAdvanced;
use App\Support\Observability\Telemetry;
use App\Support\Staff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyEngagementStatusAutoAdvance implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $organizationId,
        public string $engagementId,
        public string $fromStatus,
        public string $toStatus,
        public ?int $formsTotal = null
    ) {
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(LogEngagementEvent $logEngagementEvent): void
    {
        Telemetry::replace($this->organizationId, $this->engagementId);

        $engagement = Engagement::query()
            ->with('organization')
            ->find($this->engagementId);

        if (! $engagement) {
            return;
        }

        $recipients = $this->resolveRecipients($engagement);

        if ($recipients->isEmpty()) {
            $logEngagementEvent->handle(
                $engagement,
                'engagement.status.auto_advance.notifications.skipped',
                [
                    'from_status' => $this->fromStatus,
                    'to_status' => $this->toStatus,
                    'reason' => 'missing-recipients',
                ]
            );

            return;
        }

        Notification::send(
            $recipients,
            new EngagementStatusAutoAdvanced(
                $engagement,
                $this->fromStatus,
                $this->toStatus,
                $engagement->organization,
                $this->formsTotal
            )
        );

        $logEngagementEvent->handle(
            $engagement,
            'engagement.status.auto_advance.notified',
            [
                'from_status' => $this->fromStatus,
                'to_status' => $this->toStatus,
                'recipient_user_ids' => $recipients
                    ->map(fn (User $user) => (string) $user->getKey())
                    ->values()
                    ->all(),
            ]
        );
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(Engagement $engagement): Collection
    {
        $staffParticipants = EngagementParticipant::query()
            ->with('membership.user')
            ->where('engagement_id', $engagement->getKey())
            ->where('participant_type', EngagementParticipant::TYPE_MEMBERSHIP)
            ->where('active', true)
            ->whereHas('membership', function ($query): void {
                $query->whereIn('role', [
                    MembershipRole::OWNER->value,
                    MembershipRole::ADMIN->value,
                ]);
            })
            ->get()
            ->map(fn (EngagementParticipant $participant) => $participant->membership?->user)
            ->filter(fn (?User $user) => $user !== null && ! empty($user->email))
            ->filter(fn (User $user) => Staff::isStaff($user))
            ->unique(fn (User $user) => $user->getKey())
            ->values();

        if ($staffParticipants->isNotEmpty()) {
            return $staffParticipants;
        }

        $orgStaffRecipients = $this->resolveOrganizationStaffRecipients($engagement);

        if ($orgStaffRecipients->isNotEmpty()) {
            return $orgStaffRecipients;
        }

        return $this->resolveStaffRecipients();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveOrganizationStaffRecipients(Engagement $engagement): Collection
    {
        return Membership::query()
            ->with('user')
            ->where('organization_id', $engagement->organization_id)
            ->whereIn('role', [
                MembershipRole::OWNER->value,
                MembershipRole::ADMIN->value,
            ])
            ->get()
            ->map(fn (Membership $membership) => $membership->user)
            ->filter(fn (?User $user) => $user !== null && ! empty($user->email))
            ->filter(fn (User $user) => Staff::isStaff($user))
            ->unique(fn (User $user) => $user->getKey())
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveStaffRecipients(): Collection
    {
        $internalOrganization = Organization::query()
            ->select('id')
            ->where('is_internal', true)
            ->first();

        if (! $internalOrganization) {
            return collect();
        }

        return Membership::query()
            ->with('user')
            ->where('organization_id', $internalOrganization->id)
            ->whereIn('role', [
                MembershipRole::OWNER->value,
                MembershipRole::ADMIN->value,
            ])
            ->get()
            ->map(fn (Membership $membership) => $membership->user)
            ->filter(fn (?User $user) => $user !== null && ! empty($user->email))
            ->unique(fn (User $user) => $user->getKey())
            ->values();
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId,
                'engagement_id' => $this->engagementId,
            ],
            ['job:notify-engagement-status-auto-advance']
        );
    }
}
