<?php

namespace App\Http\Controllers;

use App\Actions\Engagements\UpdateEngagementStatus;
use App\Enums\EngagementStatus;
use App\Http\Requests\EngagementOverrideRequest;
use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class EngagementStatusController extends Controller
{
    public function __invoke(
        EngagementOverrideRequest $request,
        Organization $organization,
        Engagement $engagement,
        UpdateEngagementStatus $updater
    ): RedirectResponse {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $this->authorize('update', $engagement);

        $updater->handle(
            $engagement,
            $request->requestedStatus(),
            $request->user(),
            $request->wantsOverride(),
            $request->overrideReason(),
        );

        return back()->with('success', __('Engagement status updated.'));
    }
}
