<?php

namespace App\Http\Controllers\Organization;

use App\Actions\OrgMetrics\UpdateOrgMetricAlias;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrgMetricAliasRequest;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Http\RedirectResponse;

class OrgMetricAliasController extends Controller
{
    public function __invoke(
        UpdateOrgMetricAliasRequest $request,
        Organization $organization,
        OrgMetric $orgMetric,
        UpdateOrgMetricAlias $updater
    ): RedirectResponse {
        abort_if(! config('metric-harmony.features.org_metric_overrides', false), 404);

        abort_unless($orgMetric->organization_id === $organization->id, 404);

        $data = $request->validated();

        $updater->handle($orgMetric, $data['alias'] ?? null, $request->user());

        return redirect()
            ->route('org.org-metrics.show', [$organization, $orgMetric])
            ->with('success', __('Org metric alias updated.'));
    }
}
