<?php

namespace App\Http\Controllers\Organization;

use App\Actions\OrgMetrics\BuildOrgMetricsIndex;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgMetricIndexController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        BuildOrgMetricsIndex $builder
    ): Response {
        $user = $request->user();

        abort_unless($user && Staff::isInternalStaff($user), 403);

        $this->authorize('view', $organization);

        $payload = $builder->handle($organization);

        return Inertia::render('OrgMetrics/Index', array_merge($payload, [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ],
        ]));
    }
}
