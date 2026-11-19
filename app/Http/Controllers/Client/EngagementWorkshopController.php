<?php

namespace App\Http\Controllers\Client;

use App\Actions\Workshops\BuildWorkshopPayload;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MetricStatus;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\ImpersonationGuard;
use App\Support\Metrics\FinalDefinitionProgress;
use App\Support\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EngagementWorkshopController extends Controller
{
    public function __construct(
        private readonly BuildWorkshopPayload $payloadBuilder
    ) {}

    public function __invoke(Request $request, Organization $organization, Engagement $engagement): Response|JsonResponse
    {
        if ($engagement->organization_id !== $organization->id) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $context = ImpersonationGuard::resolve($user, $organization, $request);

        if (! $context->impersonating() && ! $context->membership()) {
            abort(403);
        }

        $metricId = $request->string('metric')->toString();

        if ($metricId === '') {
            abort(404);
        }

        $metric = Metric::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->findOrFail($metricId);

        $status = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($request->string('probe')->toString() === 'status' && $request->expectsJson()) {
            return response()->json([
                'engagement_status' => $status?->value ?? (string) $engagement->status,
            ]);
        }

        $workshopClosed = $status !== EngagementStatus::IN_WORKSHOP;

        // Client workshop access mirrors handbook visibility (membership/impersonation + form completion gate).
        if (! Staff::isStaff($user)) {
            if (! in_array($status, [EngagementStatus::IN_WORKSHOP, EngagementStatus::FINALIZED], true)) {
                abort(403, 'Workshop access is available once the engagement is in workshop.');
            }

            if ($status === EngagementStatus::IN_WORKSHOP) {
                if (! $this->hasWorkshopSessionForMetric($organization, $engagement, $metric)) {
                    abort(403, 'Workshop session is not scheduled for this metric.');
                }

                if ($this->metricHasOpenContributorAssignments($metric)) {
                    abort(403, 'Waiting on form submissions for this metric.');
                }
            }
        }

        $visibleMetricIds = null;
        if (! Staff::isStaff($user) && $status === EngagementStatus::IN_WORKSHOP) {
            $visibleMetricIds = $this->sessionScopedMetricIds($organization, $engagement);
        }

        $payload = $this->payloadBuilder->handle($metric, $user);
        $workshopMetricsQuery = Metric::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->with([
                'template:id,form_schema_json',
                'definitions:id,metric_id,normalized_json,submitted_at,updated_at,created_at',
                'finalDefinition:id,metric_id,owner_participant_id,signed_off_at,definition_json',
                'finalDefinition.ownerParticipant:id,user_id',
                'finalDefinition.ownerParticipant.user:id,name',
            ]);

        if (is_array($visibleMetricIds)) {
            $workshopMetricsQuery->whereIn('id', $visibleMetricIds);
        }

        $workshopMetrics = $workshopMetricsQuery
            ->orderBy('name')
            ->get()
            ->map(fn (Metric $metricItem) => [
                'id' => (string) $metricItem->getKey(),
                'name' => $metricItem->name,
                'status' => $metricItem->status instanceof MetricStatus
                    ? $metricItem->status->value
                    : (string) $metricItem->status,
                'last_submitted_at' => optional($metricItem->last_submitted_at)->toIso8601String(),
                'final_definition_progress' => FinalDefinitionProgress::forMetric($metricItem, $metricItem->finalDefinition),
            ])
            ->values()
            ->all();

        return Inertia::render('Workshops/MetricWorkshop', [
            'organization' => $organization->only(['id', 'name']),
            'engagement' => [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus
                    ? $engagement->status->value
                    : (string) $engagement->status,
            ],
            'workshop_closed' => $workshopClosed,
            'metric' => $payload['metric'],
            'finalDefinition' => $this->resolveFinalDefinition($payload),
            'officialSignals' => $payload['official_signals'] ?? null,
            'final_definition_requirements' => $payload['final_definition_requirements'] ?? null,
            'can' => [
                'update_final_definition' => (bool) ($payload['permissions']['update_final_definition'] ?? false),
                'sign_off' => (bool) ($payload['permissions']['sign_off'] ?? false),
            ],
            'workshop' => [
                'base_url' => route('client.handbook.workshop', [$organization, $engagement], false),
                'metrics' => $workshopMetrics,
                'recompute' => $payload['recompute'],
                'session_context' => $this->buildSessionContext($organization, $engagement, $metric),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveFinalDefinition(array $payload): array
    {
        $definition = $payload['draft_definition']
            ?? $payload['final_definition']
            ?? null;

        if (is_array($definition)) {
            return $definition;
        }

        return [
            'id' => null,
            'status' => 'Draft',
            'definition_json' => [],
            'notes' => null,
            'owner_participant_id' => null,
            'owner_participant' => null,
            'signed_off_at' => null,
            'signed_off_by' => null,
        ];
    }

    /**
     * @return array{
     *     selected_session_id: string|null,
     *     sessions: array<int, array{
     *         id: string,
     *         status: string,
     *         starts_at: string|null,
     *         ends_at: string|null,
     *         timezone: string|null,
     *         metric_ids: array<int, string>,
     *         participant_ids: array<int, string>
     *     }>
     * }
     */
    private function buildSessionContext(
        Organization $organization,
        Engagement $engagement,
        Metric $metric
    ): array {
        $sessions = EngagementWorkshopSession::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->with([
                'metrics:id,session_id,metric_id',
                'participants:id,session_id,engagement_participant_id',
            ])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (EngagementWorkshopSession $session) => $session->metrics
                ->contains(fn ($metricLink) => (string) $metricLink->metric_id === (string) $metric->id))
            ->values()
            ->map(function (EngagementWorkshopSession $session): array {
                $status = $session->status?->value ?? (string) $session->status;

                return [
                    'id' => (string) $session->getKey(),
                    'status' => $status,
                    'starts_at' => optional($session->starts_at)->toIso8601String(),
                    'ends_at' => optional($session->ends_at)->toIso8601String(),
                    'timezone' => $session->timezone,
                    'metric_ids' => $session->metrics
                        ->map(fn ($metricLink) => (string) $metricLink->metric_id)
                        ->values()
                        ->all(),
                    'participant_ids' => $session->participants
                        ->map(fn ($participantLink) => (string) $participantLink->engagement_participant_id)
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $selected = $sessions
            ->sortBy(function (array $session): array {
                return [
                    $this->sessionPriority((string) ($session['status'] ?? '')),
                    $session['starts_at'] ?? '9999-12-31T23:59:59+00:00',
                ];
            })
            ->first();

        return [
            'selected_session_id' => $selected['id'] ?? null,
            'sessions' => $sessions->all(),
        ];
    }

    private function sessionPriority(string $status): int
    {
        return match (strtolower(trim($status))) {
            'inprogress' => 0,
            'scheduled' => 1,
            'missed' => 2,
            default => 3,
        };
    }

    private function hasWorkshopSessionForMetric(
        Organization $organization,
        Engagement $engagement,
        Metric $metric
    ): bool {
        return EngagementWorkshopSession::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->whereIn('status', [
                EngagementWorkshopSessionStatus::SCHEDULED->value,
                EngagementWorkshopSessionStatus::IN_PROGRESS->value,
                EngagementWorkshopSessionStatus::COMPLETED->value,
            ])
            ->whereHas('metrics', fn ($query) => $query->where('metric_id', $metric->id))
            ->exists();
    }

    private function metricHasOpenContributorAssignments(Metric $metric): bool
    {
        return FormAssignment::query()
            ->where('metric_id', $metric->id)
            ->where(function ($query): void {
                $query->whereNull('responsibility')
                    ->orWhereRaw('LOWER(COALESCE(responsibility, ?)) = ?', ['', 'contributor']);
            })
            ->whereIn('status', [
                FormAssignmentStatus::OPEN->value,
                FormAssignmentStatus::IN_PROGRESS->value,
                FormAssignmentStatus::REOPENED->value,
            ])
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function sessionScopedMetricIds(
        Organization $organization,
        Engagement $engagement
    ): array {
        return EngagementWorkshopSession::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->whereIn('status', [
                EngagementWorkshopSessionStatus::SCHEDULED->value,
                EngagementWorkshopSessionStatus::IN_PROGRESS->value,
                EngagementWorkshopSessionStatus::COMPLETED->value,
            ])
            ->with('metrics:id,session_id,metric_id')
            ->get()
            ->flatMap(fn (EngagementWorkshopSession $session) => $session->metrics->pluck('metric_id'))
            ->map(fn ($metricId) => (string) $metricId)
            ->unique()
            ->values()
            ->all();
    }
}
