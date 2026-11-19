<?php

namespace App\Http\Controllers\Organization;

use App\Actions\OrgMetrics\UpdateOrgMetricDefaultTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrgMetricDefaultTemplateRequest;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Http\RedirectResponse;

class OrgMetricDefaultTemplateController extends Controller
{
    public function __invoke(
        UpdateOrgMetricDefaultTemplateRequest $request,
        Organization $organization,
        OrgMetric $orgMetric,
        UpdateOrgMetricDefaultTemplate $updater
    ): RedirectResponse {
        abort_if(! config('metric-harmony.features.org_metric_overrides', false), 404);

        abort_unless($orgMetric->organization_id === $organization->id, 404);

        $data = $request->validated();

        $updater->handle(
            $orgMetric,
            $data['default_metric_template_id'] ?? null,
            $request->user()
        );

        return redirect()
            ->route('org.org-metrics.show', [$organization, $orgMetric])
            ->with('success', __('Org metric default template updated.'));
    }
}
