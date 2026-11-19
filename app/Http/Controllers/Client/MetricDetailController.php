<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\BuildOrgMetricDetail;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\ImpersonationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricDetailController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
        BuildOrgMetricDetail $builder
    ): JsonResponse {
        if ($metric->organization_id !== $organization->id || $metric->engagement_id !== $engagement->id) {
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

        $metric->loadMissing('orgMetric');

        $orgMetric = $metric->orgMetric;

        if (! $orgMetric || $orgMetric->organization_id !== $organization->id) {
            abort(404);
        }

        return response()->json($builder->handle($orgMetric, $metric));
    }
}
