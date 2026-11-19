<?php

namespace App\Notifications;

use App\Models\ChangeProposal;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChangeProposalReviewRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ChangeProposal $proposal,
        public Metric $metric,
        public ?Organization $organization = null,
        public ?string $initiatorName = null
    ) {
        $this->afterCommit();
        $this->proposal->setRelation('metric', $this->metric);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->organization ?? $this->metric->organization;
        $organizationName = $organization?->name ?? config('app.name');
        $metricName = $this->metric->name ?? __('Metric');

        $rationale = Str::of((string) ($this->proposal->rationale ?? ''))
            ->squish()
            ->limit(500)
            ->value();

        $url = route('org.metrics.show', [
            'organization' => $organization?->getKey() ?? $this->metric->organization_id,
            'metric' => $this->metric->getKey(),
        ]);

        return (new MailMessage)
            ->subject(__('Action required: change proposal for :metric', ['metric' => $metricName]))
            ->markdown('mail.change_proposal_review', [
                'organizationName' => $organizationName,
                'metricName' => $metricName,
                'rationale' => $rationale,
                'url' => $url,
                'initiator' => $this->initiatorName,
            ]);
    }
}
