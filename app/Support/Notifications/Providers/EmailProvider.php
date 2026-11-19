<?php

namespace App\Support\Notifications\Providers;

use App\Mail\FormDueReminderMail;
use App\Models\FormAssignment;
use App\Models\NotificationAttempt;
use App\Models\ReminderSchedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailProvider
{
    /**
     * @param  array<int,string>  $recipients
     * @return array{0:bool,1:?string}
     */
    public function deliver(ReminderSchedule $schedule, array $recipients): array
    {
        $assignment = $schedule->formAssignment;

        if (! $assignment instanceof FormAssignment) {
            $this->recordAttempt($schedule, NotificationAttempt::STATUS_FAILED, [
                'reason' => 'missing-assignment',
            ]);

            return [false, 'missing-assignment'];
        }

        $recipients = array_values(array_filter($recipients));

        if (empty($recipients)) {
            $this->recordAttempt($schedule, NotificationAttempt::STATUS_SKIPPED, [
                'reason' => 'missing-recipient',
            ]);

            return [false, 'missing-recipient'];
        }

        $primary = array_shift($recipients);
        $contextRecipients = array_values(array_filter(array_merge([$primary], $recipients)));

        try {
            $mailer = Mail::to($primary);

            if (! empty($recipients)) {
                $mailer->cc($recipients);
            }

            $mailer->send(new FormDueReminderMail($assignment));

            $this->recordAttempt($schedule, NotificationAttempt::STATUS_SENT, [
                'recipients' => $contextRecipients,
            ]);

            return [true, null];
        } catch (Throwable $exception) {
            Log::error('Email reminder delivery failed', [
                'schedule_id' => $schedule->id,
                'message' => $exception->getMessage(),
            ]);

            $this->recordAttempt($schedule, NotificationAttempt::STATUS_FAILED, [
                'recipients' => $contextRecipients,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [false, 'email-failed'];
        }
    }

    /**
     * @param  array<string,mixed>|null  $context
     */
    protected function recordAttempt(ReminderSchedule $schedule, string $status, ?array $context = null): void
    {
        NotificationAttempt::create([
            'organization_id' => $schedule->organization_id,
            'engagement_id' => $schedule->engagement_id,
            'metric_id' => $schedule->metric_id,
            'form_assignment_id' => $schedule->form_assignment_id,
            'notification_id' => $schedule->id,
            'channel' => ReminderSchedule::CHANNEL_EMAIL,
            'provider_response' => $context,
            'status' => $status,
            'attempted_at' => now(),
        ]);
    }
}
