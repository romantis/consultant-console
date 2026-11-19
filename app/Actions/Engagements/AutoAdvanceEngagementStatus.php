<?php

namespace App\Actions\Engagements;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementStatus;
use App\Jobs\NotifyEngagementStatusAutoAdvance;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;

class AutoAdvanceEngagementStatus
{
    public function __construct(
        private readonly UpdateEngagementStatus $updateEngagementStatus,
        private readonly LogEngagementEvent $logEngagementEvent
    ) {}

    public function handle(Engagement $engagement, ?Form $triggerForm = null): bool
    {
        $engagement->refresh();

        if ($engagement->status !== EngagementStatus::AWAITING_FORMS) {
            return false;
        }

        if (! in_array(EngagementStatus::IN_REVIEW, $engagement->status->allowedTransitions(), true)) {
            return false;
        }

        if ($triggerForm && (string) $triggerForm->engagement_id !== (string) $engagement->getKey()) {
            return false;
        }

        $readiness = $this->resolveMetricWorkshopReadiness($engagement);
        if ($readiness['ready_metrics'] === 0) {
            return false;
        }

        $fromStatus = $engagement->status;

        $this->updateEngagementStatus->handle($engagement, EngagementStatus::IN_REVIEW);

        $this->logEngagementEvent->handle(
            $engagement,
            'engagement.status.auto_advanced',
            [
                'from_status' => $fromStatus->value,
                'to_status' => EngagementStatus::IN_REVIEW->value,
                'trigger' => 'metric_workshop_ready',
                'metrics_total' => $readiness['metrics_total'],
                'metrics_ready' => $readiness['ready_metrics'],
                'trigger_form_id' => $triggerForm?->getKey(),
            ],
            null,
            $triggerForm
        );

        NotifyEngagementStatusAutoAdvance::dispatch(
            (string) $engagement->organization_id,
            (string) $engagement->getKey(),
            $fromStatus->value,
            EngagementStatus::IN_REVIEW->value,
            $readiness['ready_metrics']
        )->afterCommit();

        return true;
    }

    /**
     * @return array{ready_metrics: int, metrics_total: int}
     */
    private function resolveMetricWorkshopReadiness(Engagement $engagement): array
    {
        $metricsTotal = $engagement->metrics()->count();
        $forms = $engagement->forms()->get(['id', 'metric_id']);

        if ($forms->isEmpty()) {
            return [
                'ready_metrics' => 0,
                'metrics_total' => $metricsTotal,
            ];
        }

        $rows = FormAssignment::query()
            ->selectRaw(
                'form_assignment.form_id, COUNT(*) as assignment_count, SUM(CASE WHEN form_submissions.submitted_at IS NOT NULL THEN 1 ELSE 0 END) as submitted_count'
            )
            ->leftJoin('form_submissions', function ($join): void {
                $join->on('form_submissions.form_id', '=', 'form_assignment.form_id')
                    ->on('form_submissions.user_id', '=', 'form_assignment.user_id');
            })
            ->whereIn('form_assignment.form_id', $forms->pluck('id')->all())
            ->where(function ($query): void {
                $query->whereNull('form_assignment.responsibility')
                    ->orWhere('form_assignment.responsibility', 'Contributor');
            })
            ->groupBy('form_assignment.form_id')
            ->get()
            ->keyBy(fn ($row) => (string) $row->form_id);

        $readyMetrics = $forms
            ->groupBy(fn (Form $form) => $form->metric_id ? (string) $form->metric_id : 'form:'.$form->id)
            ->filter(function ($metricForms) use ($rows): bool {
                return $metricForms->every(function (Form $form) use ($rows): bool {
                    $stats = $rows->get((string) $form->id);
                    $assignmentCount = (int) ($stats->assignment_count ?? 0);
                    $submittedCount = (int) ($stats->submitted_count ?? 0);

                    return $assignmentCount > 0 && $submittedCount >= $assignmentCount;
                });
            })
            ->count();

        return [
            'ready_metrics' => $readyMetrics,
            'metrics_total' => $metricsTotal,
        ];
    }
}
