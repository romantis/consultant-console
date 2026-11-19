<?php

namespace App\Http\Controllers;

use App\Actions\Metrics\ApproveSignOff;
use App\Enums\EngagementStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MetricSignOffController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
        ApproveSignOff $approveSignOff
    ): RedirectResponse {
        if ($metric->organization_id !== $organization->id || $metric->engagement_id !== $engagement->id) {
            abort(404);
        }

        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $this->authorize('signOff', $metric);

        $user = $request->user();

        $approveSignOff->handle(
            $metric,
            [
                'decision' => 'approve',
                'comment' => null,
            ],
            $user
        );

        return back()->with('success', 'Metric signed off.');
    }
}
