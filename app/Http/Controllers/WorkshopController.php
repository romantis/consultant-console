<?php

namespace App\Http\Controllers;

use App\Actions\Workshops\RecordWorkshop;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\ReconciliationWorkshop;
use App\Support\ImpersonationGuard;
use App\Support\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class WorkshopController extends Controller
{
    public function __construct(
        private readonly RecordWorkshop $recordWorkshop
    ) {}

    public function store(Request $request, Metric $metric): JsonResponse
    {
        $this->authorize('review', $metric);

        $validated = $request->validate([
            'session_id' => [
                'required',
                'string',
                Rule::exists('engagement_workshop_sessions', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $metric->organization_id)
                        ->where('engagement_id', $metric->engagement_id)),
            ],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'facilitator_id' => [
                'required',
                'string',
                Rule::exists('memberships', 'id')
                    ->where(fn ($query) => $query->where('organization_id', $metric->organization_id)),
            ],
            'participants' => ['nullable', 'array', 'max:20'],
            'participants.*' => [
                'string',
                Rule::exists('engagement_participants', 'id')
                    ->where(fn ($query) => $query->where('engagement_id', $metric->engagement_id)),
            ],
            'notes' => ['nullable', 'string', 'max:20000'],
            'decisions' => ['nullable', 'array'],
            'transcript_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $scheduledAt = Carbon::parse($validated['scheduled_at']);

        /** @var Membership $facilitator */
        $facilitator = Membership::query()
            ->with('user:id,name')
            ->where('organization_id', $metric->organization_id)
            ->findOrFail($validated['facilitator_id']);

        $participantIds = collect($validated['participants'] ?? [])
            ->filter()
            ->values();

        $participantSnapshot = $this->buildParticipantSnapshot($metric, $participantIds);

        $workshop = $this->recordWorkshop->handle(
            metric: $metric,
            attributes: [
                'session_id' => (string) $validated['session_id'],
                'facilitator_id' => $facilitator->getKey(),
                'scheduled_at' => $scheduledAt,
                'participants' => $participantSnapshot,
                'decisions' => $validated['decisions'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'transcript_url' => $validated['transcript_url'] ?? null,
            ],
            actor: $request->user()
        );

        return response()->json([
            'data' => [
                'id' => (string) $workshop->getKey(),
                'metric_id' => (string) $metric->getKey(),
                'session_id' => (string) $workshop->session_id,
                'scheduled_at' => optional($workshop->scheduled_at)->toIso8601String(),
                'facilitator' => $this->presentFacilitator($facilitator),
                'participants' => $participantSnapshot,
                'notes' => $workshop->notes,
                'decisions' => $workshop->decision_snapshot ?? [],
                'transcript_url' => $workshop->transcript_url,
            ],
        ], 201);
    }

    public function start(Request $request, Metric $metric): JsonResponse
    {
        $this->authorize('view', $metric);

        $user = $request->user();
        abort_unless($user && Staff::isInternalStaff($user), 403);

        $context = ImpersonationGuard::resolve($user, $metric->organization_id, $request);
        $facilitator = $context->membership();

        $participantIds = MetricParticipant::query()
            ->where('metric_id', $metric->id)
            ->whereNotNull('engagement_participant_id')
            ->pluck('engagement_participant_id')
            ->values();

        $participantSnapshot = $this->buildParticipantSnapshot($metric, $participantIds);

        $workshop = $this->recordWorkshop->handle(
            metric: $metric,
            attributes: [
                'facilitator_id' => $facilitator?->getKey(),
                'scheduled_at' => now(),
                'participants' => $participantSnapshot,
            ],
            actor: $user
        );

        return response()->json([
            'data' => [
                'id' => (string) $workshop->getKey(),
                'metric_id' => (string) $metric->getKey(),
                'scheduled_at' => optional($workshop->scheduled_at)->toIso8601String(),
                'facilitator' => $facilitator ? $this->presentFacilitator($facilitator) : null,
                'participants' => $participantSnapshot,
                'notes' => $workshop->notes,
                'decisions' => $workshop->decision_snapshot ?? [],
                'transcript_url' => $workshop->transcript_url,
            ],
        ], 201);
    }

    public function update(Request $request, Metric $metric, ReconciliationWorkshop $workshop): JsonResponse
    {
        $this->authorize('view', $metric);

        if (
            (string) $workshop->metric_id !== (string) $metric->id
            || (string) $workshop->engagement_id !== (string) $metric->engagement_id
        ) {
            abort(404);
        }

        $validated = $request->validate([
            'participants' => ['nullable', 'array', 'max:20'],
            'participants.*' => [
                'string',
                Rule::exists('engagement_participants', 'id')
                    ->where(fn ($query) => $query->where('engagement_id', $metric->engagement_id)),
            ],
            'notes' => ['nullable', 'string', 'max:20000'],
            'decision_snapshot' => ['nullable', 'array'],
            'decisions' => ['nullable', 'array'],
            'transcript_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $updates = [];
        $participantSnapshot = $workshop->participants ?? [];

        if (array_key_exists('participants', $validated)) {
            $participantSnapshot = $this->buildParticipantSnapshot(
                $metric,
                collect($validated['participants'] ?? [])
            );
            $updates['participants'] = $participantSnapshot;
        }

        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        if (array_key_exists('transcript_url', $validated)) {
            $updates['transcript_url'] = $validated['transcript_url'];
        }

        if (
            array_key_exists('decision_snapshot', $validated)
            || array_key_exists('decisions', $validated)
        ) {
            $updates['decision_snapshot'] = array_key_exists('decision_snapshot', $validated)
                ? $validated['decision_snapshot']
                : ($validated['decisions'] ?? null);
        }

        if ($updates) {
            $workshop->fill($updates)->save();
        }

        $workshop->loadMissing(['facilitator.user:id,name']);
        $facilitator = $workshop->facilitator;

        return response()->json([
            'data' => [
                'id' => (string) $workshop->getKey(),
                'metric_id' => (string) $metric->getKey(),
                'scheduled_at' => optional($workshop->scheduled_at)->toIso8601String(),
                'facilitator' => $facilitator ? $this->presentFacilitator($facilitator) : null,
                'participants' => $participantSnapshot,
                'notes' => $workshop->notes,
                'decisions' => $workshop->decision_snapshot ?? [],
                'transcript_url' => $workshop->transcript_url,
            ],
        ]);
    }

    protected function presentParticipant(EngagementParticipant $participant): array
    {
        $membershipRole = $participant->membership_role;
        $stakeholderRole = $participant->stakeholder_role;

        return array_filter([
            'id' => (string) $participant->id,
            'type' => $participant->participant_type,
            'name' => $participant->membership?->user?->name
                ?? $participant->pendingMembership?->name
                ?? $participant->pendingMembership?->email,
            'role' => $stakeholderRole ? ($stakeholderRole->value ?? (string) $stakeholderRole) : null,
            'membership_role' => $membershipRole ? ($membershipRole->value ?? (string) $membershipRole) : null,
        ], fn ($value) => $value !== null);
    }

    protected function presentFacilitator(Membership $facilitator): array
    {
        $role = $facilitator->role;

        return array_filter([
            'id' => (string) $facilitator->id,
            'name' => $facilitator->user?->name,
            'role' => $role ? ($role->value ?? (string) $role) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  iterable<int, string|null>  $participantIds
     * @return array<int, array<string, mixed>>
     */
    protected function buildParticipantSnapshot(Metric $metric, iterable $participantIds): array
    {
        $ids = collect($participantIds)
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $participantRecords = EngagementParticipant::query()
            ->with(['membership.user:id,name', 'pendingMembership'])
            ->where('engagement_id', $metric->engagement_id)
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy(fn ($participant) => (string) $participant->id);

        return $ids
            ->map(fn ($id) => $participantRecords->get((string) $id))
            ->filter()
            ->map(fn (EngagementParticipant $participant) => $this->presentParticipant($participant))
            ->values()
            ->all();
    }
}
