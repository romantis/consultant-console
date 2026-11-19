<?php

namespace App\Http\Controllers\Organization;

use App\Actions\HandbookWorkbench\BuildHandbookWorkbenchPayload;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EngagementHandbookWorkbenchController extends Controller
{
    public function __construct(
        private readonly BuildHandbookWorkbenchPayload $payloadBuilder
    ) {}

    public function __invoke(Request $request, Organization $organization, Engagement $engagement): Response
    {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $payload = $this->payloadBuilder->handle($organization, $engagement);

        return Inertia::render('Engagements/HandbookWorkbench', $payload);
    }
}
