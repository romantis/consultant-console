<?php

namespace App\Http\Controllers\Organization;

use App\Actions\OrgMetrics\BuildOrgMetricDetail;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Support\Staff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgMetricShowController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        OrgMetric $orgMetric,
        BuildOrgMetricDetail $builder
    ): Response {
        $user = $request->user();

        abort_unless($user && Staff::isInternalStaff($user), 403);

        $this->authorize('view', $organization);

        abort_unless($orgMetric->organization_id === $organization->id, 404);

        $payload = $builder->handle($orgMetric);

        return Inertia::render('OrgMetrics/Show', array_merge($payload, [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ],
            'can' => [
                'update' => $user->can('update', $orgMetric),
                'manage_participants' => $user->can('manageParticipants', $orgMetric),
            ],
        ]));
    }
}
