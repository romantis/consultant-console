<?php

namespace App\Actions\Workshops;

use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Models\MetricParticipant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ValidateWorkshopSessionCoverage
{
    /**
     * @param  array<int, string>  $metricIds
     * @param  array<int, string>  $participantIds
     * @return array<int, string>
     */
    public function handle(Engagement $engagement, array $metricIds, array $participantIds): array
    {
        $normalizedMetricIds = collect($metricIds)
            ->map(fn (string $metricId) => trim($metricId))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedMetricIds->isEmpty()) {
            throw ValidationException::withMessages([
                'metric_ids' => __('Select at least one metric for the workshop session.'),
            ]);
        }

        $normalizedParticipantIds = collect($participantIds)
            ->map(fn (string $participantId) => trim($participantId))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedParticipantIds->isEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => __('Select at least one participant for the workshop session.'),
            ]);
        }

        $metrics = Metric::query()
            ->with(['finalDefinition:id,metric_id,owner_participant_id'])
            ->where('engagement_id', $engagement->getKey())
            ->whereIn('id', $normalizedMetricIds->all())
            ->get(['id', 'name']);

        if ($metrics->count() !== $normalizedMetricIds->count()) {
            throw ValidationException::withMessages([
                'metric_ids' => __('One or more selected metrics are outside this engagement scope.'),
            ]);
        }

        $this->assertMetricsFormsSubmitted($metrics);

        $approverCoverage = MetricParticipant::query()
            ->whereIn('metric_id', $normalizedMetricIds->all())
            ->whereIn('engagement_participant_id', $normalizedParticipantIds->all())
            ->whereIn('responsibility', ['approver', 'owner'])
            ->pluck('metric_id')
            ->map(fn ($metricId) => (string) $metricId)
            ->unique();

        $requiredParticipantIds = MetricParticipant::query()
            ->whereIn('metric_id', $normalizedMetricIds->all())
            ->whereIn('engagement_participant_id', $normalizedParticipantIds->all())
            ->whereIn('responsibility', ['approver', 'owner'])
            ->pluck('engagement_participant_id')
            ->map(fn ($participantId) => (string) $participantId)
            ->unique();

        $uncoveredMetrics = $this->detectUncoveredMetrics(
            $metrics,
            $approverCoverage,
            $normalizedParticipantIds
        );

        if ($uncoveredMetrics->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => __(
                    'Each selected metric must include at least one approver or owner participant.'
                ),
            ]);
        }

        $ownerParticipantIds = $metrics
            ->map(fn (Metric $metric) => $metric->finalDefinition?->owner_participant_id)
            ->filter()
            ->map(fn ($participantId) => (string) $participantId)
            ->filter(fn (string $participantId) => $normalizedParticipantIds->contains($participantId))
            ->values();

        return $requiredParticipantIds
            ->merge($ownerParticipantIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Metric>  $metrics
     */
    private function assertMetricsFormsSubmitted(Collection $metrics): void
    {
        if ($metrics->isEmpty()) {
            return;
        }

        $metricIds = $metrics
            ->map(fn (Metric $metric) => (string) $metric->getKey())
            ->values()
            ->all();

        $forms = Form::query()
            ->whereIn('metric_id', $metricIds)
            ->get(['id', 'metric_id']);

        $metricIdsWithForms = $forms
            ->map(fn (Form $form) => (string) $form->metric_id)
            ->unique()
            ->values();

        $incompleteMetricIds = collect($metricIds)
            ->diff($metricIdsWithForms)
            ->values();

        if ($forms->isNotEmpty()) {
            $submissionStatsByFormId = FormAssignment::query()
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
                        ->orWhereRaw('LOWER(form_assignment.responsibility) = ?', ['contributor']);
                })
                ->groupBy('form_assignment.form_id')
                ->get()
                ->keyBy(fn ($row) => (string) $row->form_id);

            $formIncompleteMetricIds = $forms
                ->filter(function (Form $form) use ($submissionStatsByFormId): bool {
                    /** @var object|null $stats */
                    $stats = $submissionStatsByFormId->get((string) $form->getKey());
                    $assignmentCount = (int) ($stats->assignment_count ?? 0);
                    $submittedCount = (int) ($stats->submitted_count ?? 0);

                    return $assignmentCount === 0 || $submittedCount < $assignmentCount;
                })
                ->map(fn (Form $form) => (string) $form->metric_id)
                ->unique()
                ->values();

            $incompleteMetricIds = $incompleteMetricIds
                ->merge($formIncompleteMetricIds)
                ->unique()
                ->values();
        }

        if ($incompleteMetricIds->isEmpty()) {
            return;
        }

        $pendingMetricNames = $metrics
            ->filter(fn (Metric $metric) => $incompleteMetricIds->contains((string) $metric->getKey()))
            ->map(fn (Metric $metric) => trim((string) $metric->name))
            ->filter()
            ->values();

        $pendingList = $pendingMetricNames->isNotEmpty()
            ? $pendingMetricNames->implode(', ')
            : __('selected metrics');

        throw ValidationException::withMessages([
            'metric_ids' => __(
                'Workshops can be scheduled only after all contributor forms are submitted for: :metrics.',
                ['metrics' => $pendingList]
            ),
        ]);
    }

    /**
     * @param  Collection<int, Metric>  $metrics
     * @param  Collection<int, string>  $coveredMetricIds
     * @param  Collection<int, string>  $selectedParticipantIds
     * @return Collection<int, string>
     */
    private function detectUncoveredMetrics(
        Collection $metrics,
        Collection $coveredMetricIds,
        Collection $selectedParticipantIds
    ): Collection {
        return $metrics
            ->filter(function (Metric $metric) use ($coveredMetricIds, $selectedParticipantIds) {
                $metricId = (string) $metric->getKey();

                if ($coveredMetricIds->contains($metricId)) {
                    return false;
                }

                $ownerParticipantId = $metric->finalDefinition?->owner_participant_id;

                if (! $ownerParticipantId) {
                    return true;
                }

                return ! $selectedParticipantIds->contains((string) $ownerParticipantId);
            })
            ->map(fn (Metric $metric) => (string) $metric->getKey())
            ->values();
    }
}
