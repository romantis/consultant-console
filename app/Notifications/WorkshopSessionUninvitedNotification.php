<?php

namespace App\Notifications;

use App\Models\EngagementWorkshopSession;
use App\Support\Workshops\WorkshopSessionScheduleLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class WorkshopSessionUninvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EngagementWorkshopSession $session)
    {
        $this->afterCommit();
        $this->session->loadMissing(['organization', 'engagement']);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $engagementTitle = (string) ($this->session->engagement?->title ?: __('Engagement'));
        $timezone = $this->session->timezone ?: 'UTC';
        $startsAt = Carbon::parse((string) $this->session->starts_at)->timezone($timezone);
        $endsAt = Carbon::parse((string) $this->session->ends_at)->timezone($timezone);
        $metricNames = $this->resolveMetricNames();

        return (new MailMessage)
            ->subject(__('Workshop invite removed: :engagement', ['engagement' => $engagementTitle]))
            ->line(__('Your workshop invitation has been canceled.'))
            ->line(__('Originally scheduled: :schedule', [
                'schedule' => WorkshopSessionScheduleLabel::format(
                    $startsAt,
                    $endsAt,
                    $timezone,
                ),
            ]))
            ->lineIf(
                filled($this->session->notes),
                __('Session notes: :notes', ['notes' => (string) $this->session->notes])
            )
            ->lineIf(
                count($metricNames) > 0,
                __('Affected metrics: :metrics', ['metrics' => implode(', ', $metricNames)])
            )
            ->line(__('No action is required.'));
    }

    /**
     * @return array<int, string>
     */
    private function resolveMetricNames(): array
    {
        $this->session->loadMissing('metrics.metric:id,name');

        return $this->session->metrics
            ->map(fn ($metricLink) => trim((string) ($metricLink->metric?->name ?? 'Metric')))
            ->map(fn (string $name) => $name !== '' ? $name : 'Metric')
            ->unique()
            ->values()
            ->all();
    }
}
