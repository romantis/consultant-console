<?php

namespace App\Notifications;

use App\Models\EngagementWorkshopSession;
use App\Support\Workshops\WorkshopSessionMetricLinks;
use App\Support\Workshops\WorkshopSessionScheduleLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class WorkshopSessionInviteNotification extends Notification implements ShouldQueue
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
        $metricLinks = WorkshopSessionMetricLinks::build($this->session);
        $message = (new MailMessage)
            ->subject(__('Workshop invite: :engagement', ['engagement' => $engagementTitle]))
            ->line(__('You have been invited to a workshop session.'))
            ->line(__('When: :schedule', [
                'schedule' => WorkshopSessionScheduleLabel::format(
                    $startsAt,
                    $endsAt,
                    $timezone,
                ),
            ]))
            ->lineIf(
                filled($this->session->notes),
                __('Notes: :notes', ['notes' => (string) $this->session->notes])
            );

        if (count($metricLinks) > 0) {
            $message->line(__('Workshop metric links:'));

            foreach ($metricLinks as $metricLink) {
                $message->line(WorkshopSessionMetricLinks::toMarkdownListItem($metricLink));
            }

            $message->action(__('Open Workshop'), $metricLinks[0]['url']);
        }

        return $message;
    }
}
