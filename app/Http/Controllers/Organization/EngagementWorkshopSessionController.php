<?php

namespace App\Http\Controllers\Organization;

use App\Actions\Workshops\CreateEngagementWorkshopSession;
use App\Actions\Workshops\UpdateEngagementWorkshopSession;
use App\Actions\Workshops\WorkshopSessionConflictWarning;
use App\Actions\Workshops\WorkshopSessionMutationResult;
use App\Enums\EngagementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workshops\StoreEngagementWorkshopSessionRequest;
use App\Http\Requests\Workshops\UpdateEngagementWorkshopSessionRequest;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EngagementWorkshopSessionController extends Controller
{
    public function __construct(
        private readonly CreateEngagementWorkshopSession $createSession,
        private readonly UpdateEngagementWorkshopSession $updateSession,
    ) {}

    public function index(
        Request $request,
        Organization $organization,
        Engagement $engagement
    ): JsonResponse {
        $this->assertEngagementScope($organization, $engagement);
        $this->authorize('view', $engagement);

        $sessions = EngagementWorkshopSession::query()
            ->where('organization_id', $organization->getKey())
            ->where('engagement_id', $engagement->getKey())
            ->with([
                'metrics:id,session_id,metric_id',
                'participants:id,session_id,engagement_participant_id',
            ])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $sessions
                ->map(fn (EngagementWorkshopSession $session) => $this->presentSession($session))
                ->values(),
        ]);
    }

    public function store(
        StoreEngagementWorkshopSessionRequest $request,
        Organization $organization,
        Engagement $engagement
    ): JsonResponse {
        $this->assertEngagementScope($organization, $engagement);
        $this->authorize('create', [EngagementWorkshopSession::class, $engagement]);

        $result = $this->createSession->handle(
            $engagement,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'data' => $this->presentSession($result->session),
            'warnings' => $this->presentWarnings($result),
        ], 201);
    }

    public function update(
        UpdateEngagementWorkshopSessionRequest $request,
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): JsonResponse {
        $this->assertSessionScope($organization, $engagement, $session);
        $this->assertEngagementMutable($engagement);
        $this->authorize('update', $session);

        $result = $this->updateSession->handle(
            $session,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'data' => $this->presentSession($result->session),
            'warnings' => $this->presentWarnings($result),
        ]);
    }

    private function assertEngagementMutable(Engagement $engagement): void
    {
        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }
    }

    private function assertEngagementScope(Organization $organization, Engagement $engagement): void
    {
        abort_unless(
            (string) $organization->getKey() === (string) $engagement->organization_id,
            404
        );
    }

    private function assertSessionScope(
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): void {
        $this->assertEngagementScope($organization, $engagement);

        abort_unless(
            (string) $session->organization_id === (string) $organization->getKey()
            && (string) $session->engagement_id === (string) $engagement->getKey(),
            404
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSession(EngagementWorkshopSession $session): array
    {
        $status = $session->status?->value ?? (string) $session->status;

        return [
            'id' => (string) $session->getKey(),
            'organization_id' => (string) $session->organization_id,
            'engagement_id' => (string) $session->engagement_id,
            'facilitator_membership_id' => $session->facilitator_membership_id
                ? (string) $session->facilitator_membership_id
                : null,
            'starts_at' => optional($session->starts_at)?->toIso8601String(),
            'ends_at' => optional($session->ends_at)?->toIso8601String(),
            'timezone' => $session->timezone,
            'status' => $status,
            'notes' => $session->notes,
            'metric_ids' => $session->metrics
                ->map(fn ($metricLink) => (string) $metricLink->metric_id)
                ->values()
                ->all(),
            'participant_ids' => $session->participants
                ->map(fn ($participantLink) => (string) $participantLink->engagement_participant_id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function presentWarnings(WorkshopSessionMutationResult $result): array
    {
        return collect($result->warnings)
            ->map(fn (WorkshopSessionConflictWarning $warning) => [
                'type' => $warning->type,
                'session_id' => $warning->sessionId,
                'message' => $warning->message,
                'engagement_participant_id' => $warning->engagementParticipantId,
            ])
            ->values()
            ->all();
    }
}
