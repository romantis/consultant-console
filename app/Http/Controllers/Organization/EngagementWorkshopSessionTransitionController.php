<?php

namespace App\Http\Controllers\Organization;

use App\Actions\Workshops\TransitionEngagementWorkshopSession;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workshops\TransitionEngagementWorkshopSessionRequest;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EngagementWorkshopSessionTransitionController extends Controller
{
    public function __construct(
        private readonly TransitionEngagementWorkshopSession $transitionSession,
    ) {}

    public function start(
        TransitionEngagementWorkshopSessionRequest $request,
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): JsonResponse {
        $this->assertSessionScope($organization, $engagement, $session);
        $this->assertEngagementMutable($engagement);
        $this->authorize('start', $session);

        return response()->json([
            'data' => $this->presentSession(
                $this->transitionSession->handle(
                    $session,
                    EngagementWorkshopSessionStatus::IN_PROGRESS,
                    actor: $request->user(),
                    forceStart: $request->boolean('force_start')
                )
            ),
        ]);
    }

    public function complete(
        TransitionEngagementWorkshopSessionRequest $request,
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): JsonResponse {
        $this->assertSessionScope($organization, $engagement, $session);
        $this->assertEngagementMutable($engagement);
        $this->authorize('complete', $session);

        return response()->json([
            'data' => $this->presentSession(
                $this->transitionSession->handle(
                    $session,
                    EngagementWorkshopSessionStatus::COMPLETED,
                    actor: $request->user()
                )
            ),
        ]);
    }

    public function cancel(
        TransitionEngagementWorkshopSessionRequest $request,
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): JsonResponse {
        $this->assertSessionScope($organization, $engagement, $session);
        $this->assertEngagementMutable($engagement);
        $this->authorize('cancel', $session);

        return response()->json([
            'data' => $this->presentSession(
                $this->transitionSession->handle(
                    $session,
                    EngagementWorkshopSessionStatus::CANCELED,
                    notes: $request->validated('notes'),
                    actor: $request->user()
                )
            ),
        ]);
    }

    private function assertSessionScope(
        Organization $organization,
        Engagement $engagement,
        EngagementWorkshopSession $session
    ): void {
        abort_unless(
            (string) $engagement->organization_id === (string) $organization->getKey()
            && (string) $session->organization_id === (string) $organization->getKey()
            && (string) $session->engagement_id === (string) $engagement->getKey(),
            404
        );
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

    /**
     * @return array<string, mixed>
     */
    private function presentSession(EngagementWorkshopSession $session): array
    {
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
            'status' => $session->status?->value ?? (string) $session->status,
            'notes' => $session->notes,
        ];
    }
}
