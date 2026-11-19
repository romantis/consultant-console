<?php

namespace App\Jobs;

use App\Enums\EngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Models\FormAssignment;
use App\Models\ReminderSchedule;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessAssignmentReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly ?string $window = null,
        private readonly ?string $organizationId = null,
        private readonly ?string $engagementId = null,
    ) {
        $this->onQueue(config('queue.queues.reminders', 'reminders'));
    }

    public function handle(): void
    {
        $offsets = ReminderSchedule::defaultWindowOffsets();

        if ($this->window !== null) {
            if (! array_key_exists($this->window, $offsets)) {
                return;
            }

            $offsets = [$this->window => $offsets[$this->window]];
        }

        $eligibleStatuses = array_map(
            static fn (FormAssignmentStatus $status) => $status->value,
            [
                FormAssignmentStatus::OPEN,
                FormAssignmentStatus::IN_PROGRESS,
                FormAssignmentStatus::REOPENED,
            ]
        );

        $assignmentQuery = $this->eligibleAssignmentQuery($eligibleStatuses);

        $this->normalizePendingScheduleChannels();
        $this->cancelIneligiblePendingSchedules($eligibleStatuses);

        $assignmentQuery
            ->orderBy('id')
            ->chunkById(200, function ($assignments) use ($offsets) {
                foreach ($assignments as $assignment) {
                    $this->processAssignment($assignment, $offsets);
                }
            }, column: 'id');

        foreach (array_keys($offsets) as $window) {
            $hasPending = ReminderSchedule::query()
                ->pending()
                ->where('window', $window)
                ->where('deliver_at', '<=', now())
                ->when($this->organizationId, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
                ->when($this->engagementId, fn ($query, $engagementId) => $query->where('engagement_id', $engagementId))
                ->exists();

            if ($hasPending) {
                DispatchReminderWindow::dispatch(
                    $window,
                    organizationId: $this->organizationId,
                    engagementId: $this->engagementId,
                );
            }
        }
    }

    private function eligibleAssignmentQuery(array $eligibleStatuses): Builder
    {
        return FormAssignment::query()
            ->whereNotNull('due_at')
            ->when($this->organizationId, function ($query, $organizationId) {
                $query->where(function ($query) use ($organizationId) {
                    $query->whereHas('metric', fn ($metricQuery) => $metricQuery->where('organization_id', $organizationId))
                        ->orWhereHas('form', fn ($formQuery) => $formQuery->where('organization_id', $organizationId));
                });
            })
            ->when($this->engagementId, function ($query, $engagementId) {
                $query->where(function ($query) use ($engagementId) {
                    $query->whereHas('metric', fn ($metricQuery) => $metricQuery->where('engagement_id', $engagementId))
                        ->orWhereHas('form', fn ($formQuery) => $formQuery->where('engagement_id', $engagementId));
                });
            })
            ->where(function ($query) use ($eligibleStatuses) {
                $query->whereNull('status');

                if (! empty($eligibleStatuses)) {
                    $query->orWhereIn('status', $eligibleStatuses);
                }
            })
            ->whereRaw('LOWER(COALESCE(responsibility, ?)) = ?', ['', 'contributor'])
            ->where(function ($query) {
                $query->whereHas(
                    'metric.engagement',
                    fn ($engagementQuery) => $engagementQuery->where('status', EngagementStatus::AWAITING_FORMS->value)
                )->orWhereHas(
                    'form.engagement',
                    fn ($engagementQuery) => $engagementQuery->where('status', EngagementStatus::AWAITING_FORMS->value)
                );
            });
    }

    private function cancelIneligiblePendingSchedules(array $eligibleStatuses): void
    {
        ReminderSchedule::query()
            ->pending()
            ->when($this->window, fn ($query, $window) => $query->where('window', $window))
            ->when($this->organizationId, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when($this->engagementId, fn ($query, $engagementId) => $query->where('engagement_id', $engagementId))
            ->whereNotIn(
                'form_assignment_id',
                $this->eligibleAssignmentQuery($eligibleStatuses)->select('id')
            )
            ->update([
                'status' => ReminderSchedule::STATUS_CANCELLED,
                'delivered_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function normalizePendingScheduleChannels(): void
    {
        ReminderSchedule::query()
            ->pending()
            ->when($this->window, fn ($query, $window) => $query->where('window', $window))
            ->when($this->organizationId, fn ($query, $organizationId) => $query->where('organization_id', $organizationId))
            ->when($this->engagementId, fn ($query, $engagementId) => $query->where('engagement_id', $engagementId))
            ->where('channel', '!=', ReminderSchedule::CHANNEL_EMAIL)
            ->update([
                'channel' => ReminderSchedule::CHANNEL_EMAIL,
                'updated_at' => now(),
            ]);
    }

    private function processAssignment(FormAssignment $assignment, array $offsets): void
    {
        $current = now();

        if ($this->assignmentCompleted($assignment)) {
            return;
        }

        $assignment->loadMissing([
            'form.organization',
            'form.engagement',
            'metric.organization',
            'metric.engagement',
        ]);

        $organizationId = $this->resolveOrganizationId($assignment);
        $engagementId = $this->resolveEngagementId($assignment);
        $metricId = $this->resolveMetricId($assignment);

        if (! $organizationId || ! $engagementId || ! $metricId) {
            return;
        }

        Telemetry::scoped($organizationId, $engagementId, $metricId, function () use (
            $assignment,
            $offsets,
            $current,
            $organizationId,
            $engagementId,
            $metricId
        ) {
            foreach ($offsets as $window => $hours) {
                $deliverAt = $this->calculateDeliverAt($assignment, $hours);

                if (! $deliverAt) {
                    continue;
                }

                if ($hours < 0 && $deliverAt->lessThan($current->copy()->subDay())) {
                    continue;
                }

                $channel = $this->determineChannel();

                $schedule = ReminderSchedule::query()
                    ->where('form_assignment_id', $assignment->id)
                    ->where('window', $window)
                    ->first();

                if ($schedule) {
                    if ($schedule->status !== ReminderSchedule::STATUS_PENDING) {
                        continue;
                    }

                    $dirty = [];

                    if ($schedule->deliver_at === null || $schedule->deliver_at->ne($deliverAt)) {
                        $dirty['deliver_at'] = $deliverAt;
                    }

                    if ($schedule->channel !== $channel) {
                        $dirty['channel'] = $channel;
                    }

                    if (! empty($dirty)) {
                        $schedule->forceFill($dirty)->save();
                    }

                    continue;
                }

                ReminderSchedule::create([
                    'organization_id' => $organizationId,
                    'engagement_id' => $engagementId,
                    'metric_id' => $metricId,
                    'form_assignment_id' => $assignment->id,
                    'window' => $window,
                    'channel' => $channel,
                    'deliver_at' => $deliverAt,
                    'status' => ReminderSchedule::STATUS_PENDING,
                ]);
            }
        });
    }

    private function calculateDeliverAt(FormAssignment $assignment, int $offsetHours): ?Carbon
    {
        if (! $assignment->due_at) {
            return null;
        }

        return $assignment->due_at->copy()->addHours($offsetHours);
    }

    private function determineChannel(): string
    {
        return ReminderSchedule::CHANNEL_EMAIL;
    }

    private function resolveOrganizationId(FormAssignment $assignment): ?string
    {
        return $assignment->metric?->organization_id
            ?? $assignment->form?->organization_id
            ?? $assignment->metric?->organization?->id
            ?? $assignment->form?->organization?->id;
    }

    private function resolveEngagementId(FormAssignment $assignment): ?string
    {
        return $assignment->metric?->engagement_id
            ?? $assignment->form?->engagement_id
            ?? $assignment->metric?->engagement?->id
            ?? $assignment->form?->engagement?->id;
    }

    private function resolveMetricId(FormAssignment $assignment): ?string
    {
        return $assignment->metric_id
            ?? $assignment->metric?->id
            ?? $assignment->form?->metric_id;
    }

    private function assignmentCompleted(FormAssignment $assignment): bool
    {
        $status = $assignment->status;

        if ($status instanceof FormAssignmentStatus) {
            return $status === FormAssignmentStatus::COMPLETED;
        }

        if (is_string($status) && $status !== '') {
            return $status === FormAssignmentStatus::COMPLETED->value;
        }

        return false;
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId,
                'engagement_id' => $this->engagementId,
            ],
            [
                'job:process-assignment-reminders',
                $this->window !== null ? 'window:'.$this->window : null,
            ]
        );
    }
}
