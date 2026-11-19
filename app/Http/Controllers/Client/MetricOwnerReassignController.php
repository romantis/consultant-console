<?php

namespace App\Http\Controllers\Client;

use App\Actions\Metrics\ReassignMetricOwner;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\ImpersonationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetricOwnerReassignController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        Metric $metric,
        ReassignMetricOwner $reassignMetricOwner
    ): JsonResponse {
        abort_if(! config('metric-harmony.features.org_metric_overrides', false), 404);

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

        if (! $context->allowsAtLeast(MembershipRole::ADMIN)) {
            abort(403);
        }

        $validated = $request->validate([
            'owner_participant_id' => [
                'required',
                'string',
                Rule::exists('metric_participants', 'id')
                    ->where(fn ($query) => $query->where('metric_id', $metric->id)),
            ],
        ]);

        return response()->json(
            $reassignMetricOwner->handle($metric, $validated['owner_participant_id'], $user)
        );
    }
}
