<?php

namespace App\Http\Controllers\Organization;

use App\Actions\Engagements\SyncMetricParticipants;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMetricParticipantsRequest;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class EngagementMetricParticipantsController extends Controller
{
    public function __invoke(
        UpdateMetricParticipantsRequest $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
        SyncMetricParticipants $syncMetricParticipants
    ): RedirectResponse {
        abort_unless($metric->organization_id === $organization->id, 404);
        abort_unless($metric->engagement_id === $engagement->id, 404);

        $data = $request->validated();

        $syncMetricParticipants->handle(
            $engagement,
            $metric,
            $data['participants'] ?? []
        );

        return back()->with('success', __('Metric participants updated.'));
    }
}
