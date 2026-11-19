<?php

namespace App\Notifications;

use App\Models\Engagement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EngagementKickoffAssignments extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{title: string, url: string}>  $formLinks
     */
    public function __construct(
        public Engagement $engagement,
        public array $formLinks
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $engagementTitle = $this->engagement->title ?: __('Engagement');
        $formsCount = count($this->formLinks);

        $message = (new MailMessage)
            ->subject(__('Kickoff: forms ready for :engagement', ['engagement' => $engagementTitle]))
            ->line(__('The engagement ":engagement" has kicked off and is now awaiting forms.', [
                'engagement' => $engagementTitle,
            ]))
            ->line($formsCount === 1
                ? __('You have 1 form assignment to complete:')
                : __('You have :count form assignments to complete:', ['count' => $formsCount]));

        foreach ($this->formLinks as $formLink) {
            $title = trim((string) ($formLink['title'] ?? '')) ?: __('Open form');
            $url = (string) ($formLink['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $message->line($title.': '.$url);
        }

        return $message
            ->action(__('Open Forms Workspace'), route('client.forms.index'))
            ->line(__('Please submit your assignments as soon as possible.'));
    }
}
