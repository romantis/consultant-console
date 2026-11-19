<?php

namespace App\Jobs;

use App\Models\ReminderSchedule;
use App\Support\Notifications\ReminderDispatcher;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchReminderWindow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $window,
        protected ?string $organizationId = null,
        protected ?string $engagementId = null,
    ) {
        $this->onQueue(config('queue.queues.reminders', 'reminders'));
    }

    public function handle(ReminderDispatcher $dispatcher): void
    {
        $query = ReminderSchedule::query()
            ->pending()
            ->where('window', $this->window)
            ->where('deliver_at', '<=', now())
            ->when($this->organizationId, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when($this->engagementId, fn ($query, $engagementId) => $query->where('engagement_id', $engagementId))
            ->orderBy('deliver_at');

        $query->with([
            'formAssignment.user',
            'formAssignment.form',
            'formAssignment.metric',
            'organization',
            'engagement',
            'metric',
        ])->chunk(50, function ($schedules) use ($dispatcher) {
            foreach ($schedules as $schedule) {
                Telemetry::scoped(
                    $schedule->organization_id ? (string) $schedule->organization_id : null,
                    $schedule->engagement_id ? (string) $schedule->engagement_id : null,
                    $schedule->metric_id ? (string) $schedule->metric_id : null,
                    fn () => $dispatcher->dispatch($schedule)
                );
            }
        });
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId,
                'engagement_id' => $this->engagementId,
            ],
            [
                'job:dispatch-reminder-window',
                'window:'.$this->window,
            ]
        );
    }
}
