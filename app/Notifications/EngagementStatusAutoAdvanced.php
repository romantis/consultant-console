<?php

namespace App\Notifications;

use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EngagementStatusAutoAdvanced extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Engagement $engagement,
        public string $fromStatus,
        public string $toStatus,
        public ?Organization $organization = null,
        public ?int $formsTotal = null
    ) {
        $this->afterCommit();
        $this->engagement->setRelation('organization', $this->engagement->organization);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->organization ?? $this->engagement->organization;
        $organizationName = $organization?->name ?? config('app.name');
        $engagementTitle = $this->engagement->title ?? __('Engagement');

        $fromStatus = $this->formatStatusLabel($this->fromStatus);
        $toStatus = $this->formatStatusLabel($this->toStatus);

        $url = route('org.engagements.show', [
            'organization' => $organization?->getKey() ?? $this->engagement->organization_id,
            'engagement' => $this->engagement->getKey(),
        ]);

        return (new MailMessage)
            ->subject(__('Engagement moved to :status', ['status' => $toStatus]))
            ->markdown('mail.engagement_status_auto_advanced', [
                'organizationName' => $organizationName,
                'engagementTitle' => $engagementTitle,
                'fromStatus' => $fromStatus,
                'toStatus' => $toStatus,
                'formsTotal' => $this->formsTotal,
                'url' => $url,
            ]);
    }

    private function formatStatusLabel(string $value): string
    {
        $label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value);
        $label = preg_replace('/_+/', ' ', (string) $label);
        $label = preg_replace('/\s+/', ' ', (string) $label);
        $label = trim((string) $label);

        return ucwords($label);
    }
}
