<?php

namespace App\Http\Controllers;

use App\Actions\Audit\LogMetricEvent;
use App\Actions\Metrics\ComputeDiscrepancies;
use App\Models\Discrepancy;
use App\Models\Metric;
use App\Models\User;
use App\Support\Discrepancies\DiscrepancyPresenter;
use App\Support\Metrics\FieldRoleSchema;
use App\Support\Metrics\RecomputeStatusPresenter;
use App\Support\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DiscrepancyController extends Controller
{
    public function index(Request $request, Metric $metric): JsonResponse
    {
        $this->authorize('view', $metric);

        $statusFilter = Str::lower((string) $request->string('status'));
        $perPage = (int) min(100, max(1, $request->integer('per_page', 25)));

        $listQuery = $metric->discrepancies()->orderByDesc('detected_at');

        if ($statusFilter === 'open') {
            $listQuery->where(function ($query) {
                $query->whereNull('resolved_at')->orWhere('status', 'Open');
            });
        } elseif ($statusFilter === 'resolved') {
            $listQuery->where(function ($query) {
                $query->whereNotNull('resolved_at')->orWhere('status', 'Resolved');
            });
        }

        $fieldInput = $request->input('field', []);
        $fields = collect(is_array($fieldInput) ? $fieldInput : [$fieldInput])
            ->filter(fn ($value) => filled($value))
            ->values();

        if ($fields->isNotEmpty()) {
            $listQuery->whereIn('field_key', $fields->all());
        }

        $resolutionFields = FieldRoleSchema::resolutionFieldsForMetric($metric);

        $paginator = $listQuery->paginate($perPage)->withQueryString();
        $collection = $paginator->getCollection()->map(
            fn (Discrepancy $discrepancy) => DiscrepancyPresenter::present($discrepancy, $resolutionFields)
        );

        $countsQuery = Discrepancy::query()->where('metric_id', $metric->getKey());
        $openCount = (clone $countsQuery)
            ->where(function ($query) {
                $query->whereNull('resolved_at')->orWhere('status', 'Open');
            })
            ->count();
        $resolvedCount = (clone $countsQuery)
            ->where(function ($query) {
                $query->whereNotNull('resolved_at')->orWhere('status', 'Resolved');
            })
            ->count();

        $severityBreakdown = (clone $countsQuery)
            ->select('severity')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('severity')
            ->pluck('aggregate', 'severity')
            ->map(fn ($value) => (int) $value)
            ->toArray();

        $user = $request->user();
        $canRecompute = $user ? Gate::forUser($user)->allows('review', $metric) : false;

        $canResolve = false;
        if ($user) {
            if (Staff::isStaffOwner($user) || Staff::isStaffAdmin($user)) {
                $canResolve = true;
            } elseif (Staff::canOperateAssignedDelivery($user, (string) $metric->organization_id)) {
                $canResolve = true;
            }
        }

        return response()->json([
            'data' => $collection->values()->all(),
            'meta' => [
                'counts' => [
                    'open' => $openCount,
                    'resolved' => $resolvedCount,
                    'total' => (clone $countsQuery)->count(),
                ],
                'severity' => $severityBreakdown,
                'filters' => [
                    'status' => in_array($statusFilter, ['open', 'resolved'], true) ? $statusFilter : 'all',
                    'field' => $fields->all(),
                ],
                'permissions' => [
                    'resolve' => $canResolve,
                    'recompute' => $canRecompute,
                ],
                'recompute' => RecomputeStatusPresenter::present($metric),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function recompute(Request $request, Metric $metric): JsonResponse
    {
        $this->authorize('review', $metric);

        /** @var User|null $actor */
        $actor = $request->user();

        $status = $metric->recompute_status ?: 'idle';
        $alreadyQueued = in_array($status, ['queued', 'running'], true);

        $queuedAt = $metric->recompute_queued_at;
        $force = $request->boolean('force');
        $changedAt = $metric->definitions_changed_at;
        $finishedAt = $metric->recompute_finished_at;
        $hasPendingChanges = ! $changedAt
            || ! $finishedAt
            || $changedAt->greaterThan($finishedAt);

        if (! $hasPendingChanges && ! $alreadyQueued && ! $force) {
            return response()->json([
                'status' => 'idle',
                'metric_id' => (string) $metric->getKey(),
                'queued_at' => optional($queuedAt)->toIso8601String(),
                'finished_at' => optional($finishedAt)->toIso8601String(),
                'changed_at' => optional($changedAt)->toIso8601String(),
                'skipped' => true,
                'reason' => 'up_to_date',
            ]);
        }

        if (! $alreadyQueued) {
            $queuedAt = now();

            $metric->forceFill([
                'recompute_status' => 'queued',
                'recompute_queued_at' => $queuedAt,
            ])->save();

            ComputeDiscrepancies::dispatch(
                (string) $metric->getKey(),
                $actor?->getKey()
            )->afterCommit();

            $status = 'queued';
        }

        return response()->json([
            'status' => $status,
            'metric_id' => (string) $metric->getKey(),
            'queued_at' => optional($queuedAt)->toIso8601String(),
            'finished_at' => optional($metric->recompute_finished_at)->toIso8601String(),
            'changed_at' => optional($metric->definitions_changed_at)->toIso8601String(),
        ], 202);
    }

    public function update(
        Request $request,
        Metric $metric,
        Discrepancy $discrepancy,
        LogMetricEvent $logMetricEvent
    ): JsonResponse {
        if ($discrepancy->metric_id !== $metric->getKey()) {
            abort(404);
        }

        $this->authorize('resolve', $discrepancy);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['Open', 'open', 'Resolved', 'resolved'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = $this->normalizeStatus($validated['status']);
        $notesProvided = $request->exists('notes');
        $notes = $notesProvided ? (string) ($validated['notes'] ?? '') : null;
        $notes = $notesProvided ? trim($notes) : null;
        $now = now();

        $attributes = [
            'status' => $status,
            'resolved_at' => $status === 'Resolved'
                ? ($discrepancy->resolved_at ?? $now)
                : null,
        ];

        if ($notesProvided) {
            $snapshot = $discrepancy->values_snapshot ?? [];
            if ($notes === null || $notes === '') {
                unset($snapshot['resolution']);
            } else {
                $snapshot['resolution'] = array_filter([
                    'notes' => $notes,
                    'actor' => $request->user()?->only(['id', 'name']),
                    'updated_at' => $now->toIso8601String(),
                ]);
            }

            $attributes['values_snapshot'] = $snapshot;
        }

        DB::transaction(function () use ($metric, $discrepancy, $attributes, $status, $notesProvided, $notes, $logMetricEvent, $request) {
            // Lock the parent metric row first to prevent race conditions
            $lockedMetric = Metric::where('id', $metric->id)->lockForUpdate()->first();

            $discrepancy->forceFill($attributes)->save();

            $lockedMetric->incrementBriefSourceRevision();

            $logMetricEvent->handle(
                $metric,
                $status === 'Resolved'
                    ? 'metric.discrepancy.resolved'
                    : 'metric.discrepancy.reopened',
                array_filter([
                    'discrepancy_id' => (string) $discrepancy->getKey(),
                    'field_key' => $discrepancy->field_key,
                    'notes' => $notesProvided ? ($notes ?: null) : null,
                    'status' => $status,
                    'resolved_at' => optional($discrepancy->resolved_at)->toIso8601String(),
                ], fn ($value) => $value !== null),
                $request->user(),
                $discrepancy
            );
        });

        $resolutionFields = FieldRoleSchema::resolutionFieldsForMetric($metric);

        return response()->json([
            'data' => DiscrepancyPresenter::present($discrepancy->refresh(), $resolutionFields),
        ]);
    }

    protected function normalizeStatus(string $status): string
    {
        return Str::lower($status) === 'resolved' ? 'Resolved' : 'Open';
    }
}
