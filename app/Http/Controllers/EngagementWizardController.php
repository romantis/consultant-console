<?php

namespace App\Http\Controllers;

use App\Actions\Engagements\LaunchEngagement;
use App\Http\Requests\LaunchEngagementRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Http\JsonResponse;

class EngagementWizardController extends Controller
{
    public function __construct(private LaunchEngagement $launchEngagement) {}

    public function store(LaunchEngagementRequest $request): JsonResponse
    {
        $data = $request->validated();

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $organization = Organization::query()->findOrFail($data['organization_id']);
        abort_unless(Staff::canOperateAssignedDelivery($actor, $organization), 403);

        $engagement = $this->launchEngagement->handle($data, $actor);

        return response()->json([
            'id' => (string) $engagement->id,
            'organization_id' => (string) $engagement->organization_id,
            'status' => $engagement->status->value,
            'name' => $engagement->title,
            'checklist' => $engagement->onboarding_checklist_json ?? [],
            'created_at' => optional($engagement->created_at)->toISOString(),
        ], 201);
    }
}
