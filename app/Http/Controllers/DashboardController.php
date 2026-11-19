<?php

namespace App\Http\Controllers;

use App\Enums\MetricStatus;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! Staff::isStaff($user)) {
            return redirect()->route('home');
        }

        $assignments = FormAssignment::query()
            ->where('user_id', $user->id)
            ->whereHas('participant', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'form:id,title,organization_id',
                'form.organization:id,name',
                'participant.metric:id,name,status',
                'submission' => fn ($query) => $query
                    ->select(['id', 'form_id', 'user_id', 'submitted_at'])
                    ->where('user_id', $user->id),
            ])
            ->orderByDesc('due_at')
            ->get();

        $now = Carbon::now();

        $assignmentSummaries = $assignments->map(function (FormAssignment $assignment) use ($now) {
            $submission = $assignment->submission;
            $dueAt = $assignment->due_at;
            $submittedAt = $submission?->submitted_at;

            $isSubmitted = $submittedAt !== null;
            $isOverdue = ! $isSubmitted && $dueAt && $dueAt->isPast();
            $isDueSoon = ! $isSubmitted && $dueAt && ! $isOverdue && $now->diffInHours($dueAt, false) <= 48;

            $status = $isSubmitted ? 'Submitted' : ($isOverdue ? 'Overdue' : 'In Progress');

            return [
                'id' => $assignment->id,
                'form' => [
                    'id' => $assignment->form_id,
                    'title' => $assignment->form?->title,
                    'organization' => $assignment->form?->organization?->only(['id', 'name']),
                ],
                'status' => $status,
                'due_at' => optional($dueAt)->toIso8601String(),
                'submitted_at' => optional($submittedAt)->toIso8601String(),
                'is_due_soon' => $isDueSoon,
                'is_overdue' => $isOverdue,
            ];
        })->values();

        $submittedCount = $assignmentSummaries->where('status', 'Submitted')->count();
        $overdueCount = $assignmentSummaries->where('is_overdue', true)->count();

        $tiles = [
            'assigned' => $assignmentSummaries->count(),
            'in_progress' => max(0, $assignmentSummaries->count() - $submittedCount - $overdueCount),
            'due_soon' => $assignmentSummaries->where('is_due_soon', true)->count(),
            'overdue' => $overdueCount,
            'submitted' => $submittedCount,
        ];

        $organizationIds = $user->memberships()->pluck('organization_id');

        $metricCountsQuery = Metric::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status');

        if (Staff::isStaff($user)) {
            if ($organizationIds->isNotEmpty()) {
                $metricCountsQuery->whereIn('organization_id', $organizationIds);
            }
        } else {
            $metricCountsQuery->whereHas('participants', fn ($query) => $query->where('user_id', $user->id));
        }

        $metricCounts = $metricCountsQuery->pluck('aggregate', 'status');

        $metricsByStatus = collect(MetricStatus::cases())
            ->mapWithKeys(fn (MetricStatus $status) => [
                $status->value => (int) ($metricCounts[$status->value] ?? 0),
            ])
            ->all();

        $metricsSummary = [
            'total' => array_sum($metricsByStatus),
            'by_status' => $metricsByStatus,
        ];

        return Inertia::render('Dashboard', [
            'tiles' => $tiles,
            'assignments' => $assignmentSummaries->all(),
            'metrics' => $metricsSummary,
        ]);
    }
}
