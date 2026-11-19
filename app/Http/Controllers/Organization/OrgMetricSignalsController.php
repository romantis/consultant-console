<?php

namespace App\Http\Controllers\Organization;

use App\Actions\OrgMetrics\UpdateOrgMetricSignals;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrgMetricSignalsRequest;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Http\RedirectResponse;

class OrgMetricSignalsController extends Controller
{
    public function __invoke(
        UpdateOrgMetricSignalsRequest $request,
        Organization $organization,
        OrgMetric $orgMetric,
        UpdateOrgMetricSignals $updater
    ): RedirectResponse {
        abort_unless($orgMetric->organization_id === $organization->id, 404);

        $updater->handle(
            $orgMetric,
            $request->consultantSignalPayload(),
            $request->user()
        );

        return back()->with('success', __('Official metric signals updated.'));
    }
}
