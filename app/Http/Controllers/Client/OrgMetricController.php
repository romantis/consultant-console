<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\BuildOrgMetricDetail;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Support\ImpersonationGuard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgMetricController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        OrgMetric $orgMetric,
        BuildOrgMetricDetail $builder
    ): Response {
        if ($engagement->organization_id !== $organization->id || $orgMetric->organization_id !== $organization->id) {
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

        $engagementMetric = Metric::query()
            ->where('organization_id', $organization->id)
            ->where('engagement_id', $engagement->id)
            ->where('org_metric_id', $orgMetric->id)
            ->first();

        $detail = $builder->handle($orgMetric, $engagementMetric);

        return Inertia::render('Client/Metric', [
            'organization' => $organization->only(['id', 'name']),
            'engagement' => $engagement->only(['id', 'title']),
            'detail' => $detail,
        ]);
    }
}
