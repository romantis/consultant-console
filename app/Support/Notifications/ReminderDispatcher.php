<?php

namespace App\Support\Notifications;

use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\ReminderSchedule;
use App\Support\Notifications\Providers\EmailProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReminderDispatcher
{
    public function __construct(
        private readonly EmailProvider $email,
    ) {}

    /**
     * Dispatch a reminder schedule to the appropriate channel(s).
     */
    public function dispatch(ReminderSchedule $schedule): void
    {
        $schedule->refresh();

        $lock = Cache::lock($this->dispatchLockKey($schedule), 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if ($schedule->status !== ReminderSchedule::STATUS_PENDING) {
                return;
            }

            if ($schedule->deliver_at && $schedule->deliver_at->isFuture()) {
                return;
            }

            $schedule->loadMissing([
                'formAssignment.user',
                'formAssignment.form.organization',
                'formAssignment.form.engagement',
                'formAssignment.metric',
                'formAssignment.metric.engagement',
                'organization.memberships.user',
                'engagement',
                'metric',
            ]);

            $assignment = $schedule->formAssignment;

            if (! $assignment) {
                $this->markFailed($schedule, 'missing-assignment');
                $this->rememberDispatch($schedule);

                return;
            }

            if ($this->assignmentCompleted($assignment)) {
                $this->markCancelled($schedule, 'assignment-completed');
                $this->rememberDispatch($schedule);

                return;
            }

            if (! $this->isContributorAssignment($assignment)) {
                $this->markCancelled($schedule, 'assignment-not-contributor');
                $this->rememberDispatch($schedule);

                return;
            }

            if (! $this->assigneeBelongsToScheduleOrganization($schedule, $assignment)) {
                $this->markCancelled($schedule, 'assignment-user-outside-organization');
                $this->rememberDispatch($schedule);

                return;
            }

            [$delivered, $reason] = $this->deliverEmailOnly($schedule);

            if ($delivered) {
                $this->markSent($schedule);
                $this->rememberDispatch($schedule);

                return;
            }

            if ($reason === 'assignment-completed') {
                $this->markCancelled($schedule, $reason);
                $this->rememberDispatch($schedule);

                return;
            }

            $this->markFailed($schedule, $reason ?? 'delivery-failed');
            $this->rememberDispatch($schedule);
        } finally {
            $lock->release();
        }
    }

    /**
     * Deliver via email channel.
     *
     * @return array{0:bool,1:?string}
     */
    protected function deliverEmailOnly(ReminderSchedule $schedule): array
    {
        $recipients = $this->resolveEmailRecipients($schedule);

        return $this->email->deliver($schedule, $recipients);
    }

    protected function assignmentCompleted(FormAssignment $assignment): bool
    {
        return FormSubmission::query()
            ->where('form_id', $assignment->form_id)
            ->where('user_id', $assignment->user_id)
            ->whereNotNull('submitted_at')
            ->exists();
    }

    protected function isContributorAssignment(FormAssignment $assignment): bool
    {
        return mb_strtolower((string) $assignment->responsibility) === 'contributor';
    }

    protected function assigneeBelongsToScheduleOrganization(ReminderSchedule $schedule, FormAssignment $assignment): bool
    {
        if (! $schedule->organization_id || ! $assignment->user_id) {
            return false;
        }

        return Membership::query()
            ->where('organization_id', $schedule->organization_id)
            ->where('user_id', $assignment->user_id)
            ->exists();
    }

    /**
     * @return array<int,string>
     */
    protected function resolveEmailRecipients(ReminderSchedule $schedule): array
    {
        $assignment = $schedule->formAssignment;

        if (! $assignment || ! $this->isContributorAssignment($assignment)) {
            return [];
        }

        $email = $assignment->user?->email;

        return $email ? [$email] : [];
    }

    protected function markSent(ReminderSchedule $schedule): void
    {
        $schedule->forceFill([
            'status' => ReminderSchedule::STATUS_SENT,
            'delivered_at' => now(),
        ])->save();
    }

    protected function markFailed(ReminderSchedule $schedule, string $reason): void
    {
        $schedule->forceFill([
            'status' => ReminderSchedule::STATUS_FAILED,
            'delivered_at' => now(),
        ])->save();

        Log::notice('Reminder schedule delivery failed', [
            'schedule_id' => $schedule->id,
            'reason' => $reason,
        ]);
    }

    protected function markCancelled(ReminderSchedule $schedule, string $reason): void
    {
        $schedule->forceFill([
            'status' => ReminderSchedule::STATUS_CANCELLED,
            'delivered_at' => now(),
        ])->save();

        Log::info('Reminder schedule cancelled', [
            'schedule_id' => $schedule->id,
            'reason' => $reason,
        ]);
    }

    protected function rememberDispatch(ReminderSchedule $schedule): void
    {
        Cache::put(
            $this->idempotencyCacheKey($schedule),
            now()->timestamp,
            now()->addHours(48)
        );
    }

    protected function dispatchLockKey(ReminderSchedule $schedule): string
    {
        return $this->idempotencyCacheKey($schedule).':lock';
    }

    protected function idempotencyCacheKey(ReminderSchedule $schedule): string
    {
        return sprintf('notifications:assignment:%s:%s', $schedule->form_assignment_id, $schedule->window);
    }
}
