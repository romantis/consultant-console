<?php

namespace App\Notifications;

use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class OrgMetricSignOffApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrgMetric $orgMetric,
        public Metric $metric,
        public ?Organization $organization = null,
        public ?string $comment = null,
        public ?string $approverName = null
    ) {
        $this->afterCommit();
        $this->orgMetric->setRelation('canonicalMetric', $this->orgMetric->canonicalMetric);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->organization ?? $this->orgMetric->organization ?? $this->metric->organization;
        $organizationName = $organization?->name ?? config('app.name');
        $metricName = $this->displayName();
        $engagementTitle = $this->metric->engagement?->title;

        $comment = Str::of((string) ($this->comment ?? ''))
            ->squish()
            ->limit(500)
            ->value();

        $url = route('client.handbook.metrics.page', [
            'organization' => $organization?->getKey() ?? $this->metric->organization_id,
            'engagement' => $this->metric->engagement_id,
            'orgMetric' => $this->orgMetric->getKey(),
        ]);

        return (new MailMessage)
            ->subject(__('Metric signed off: :metric', ['metric' => $metricName]))
            ->markdown('mail.org_metric_sign_off_approved', [
                'organizationName' => $organizationName,
                'metricName' => $metricName,
                'engagementTitle' => $engagementTitle,
                'comment' => $comment,
                'url' => $url,
                'approver' => $this->approverName,
            ]);
    }

    private function displayName(): string
    {
        $alias = $this->orgMetric->alias;

        if (is_string($alias) && trim($alias) !== '') {
            return $alias;
        }

        return $this->orgMetric->canonicalMetric?->name
            ?? $this->orgMetric->name
            ?? $this->metric->name
            ?? __('Metric');
    }
}
