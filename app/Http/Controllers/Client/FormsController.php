<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FormAssignment;
use App\Support\Impersonation;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FormsController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $isStaff = Staff::isStaff($user);

        if ($isStaff && ! Impersonation::currentOrganizationId($request)) {
            return redirect()->route('dashboard');
        }

        $assignments = FormAssignment::query()
            ->where('user_id', $user->id)
            ->where('responsibility', 'Contributor')
            ->whereHas('form.organization', fn ($query) => $query->where('is_internal', false))
            ->with([
                'form:id,title,organization_id,engagement_id,metric_id',
                'form.organization:id,name',
                'participant.metric:id,name,engagement_id',
                'participant.metric.engagement:id,title,organization_id',
                'submission' => fn ($query) => $query
                    ->select(['id', 'form_id', 'user_id', 'submitted_at'])
                    ->where('user_id', $user->id),
            ])
            ->orderByRaw('COALESCE(due_at, created_at) ASC')
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

            $metric = $assignment->participant?->metric;
            $engagement = $metric?->engagement;

            return [
                'id' => (string) $assignment->id,
                'form' => [
                    'id' => (string) $assignment->form_id,
                    'title' => $assignment->form?->title,
                    'organization' => $assignment->form?->organization?->only(['id', 'name']),
                ],
                'metric' => $metric ? [
                    'id' => (string) $metric->id,
                    'name' => $metric->name,
                ] : null,
                'engagement' => $engagement ? [
                    'id' => (string) $engagement->id,
                    'title' => $engagement->title,
                ] : null,
                'status' => $status,
                'due_at' => optional($dueAt)->toIso8601String(),
                'submitted_at' => optional($submittedAt)->toIso8601String(),
                'is_due_soon' => $isDueSoon,
                'is_overdue' => $isOverdue,
            ];
        })->values();

        $openAssignments = $assignmentSummaries->filter(fn ($assignment) => $assignment['status'] !== 'Submitted');
        $overdueCount = $assignmentSummaries->where('is_overdue', true)->count();
        $dueSoonCount = $assignmentSummaries->where('is_due_soon', true)->count();

        $nextDueAt = $openAssignments
            ->reject(fn ($assignment) => empty($assignment['due_at']))
            ->sortBy('due_at')
            ->first()['due_at'] ?? null;

        $summary = [
            'total' => $assignmentSummaries->count(),
            'open' => $openAssignments->count(),
            'due_soon' => $dueSoonCount,
            'overdue' => $overdueCount,
            'next_due_at' => $nextDueAt,
        ];

        return Inertia::render('Client/Forms', [
            'summary' => $summary,
            'assignments' => $assignmentSummaries->all(),
        ]);
    }
}
