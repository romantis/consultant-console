<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\HandbookExport;
use App\Models\Organization;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HandbookExportIndexController extends Controller
{
    public function __invoke(Request $request, Organization $organization): Response
    {
        $user = $request->user();
        $publicLinksEnabled = (bool) config('metric-harmony.features.public_handbook_links', false);

        abort_unless($user && Staff::isInternalStaff($user), 403);

        $this->authorize('view', $organization);

        $exports = HandbookExport::query()
            ->where('organization_id', $organization->id)
            ->with(['publishedBy:id,name'])
            ->orderByDesc('queued_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (HandbookExport $export) use ($publicLinksEnabled) {
                $queuedAt = $export->queued_at;
                $versionLabel = $queuedAt ? ('v'.$queuedAt->format('Ymd-His')) : 'v-';
                $publicUrl = $publicLinksEnabled && $export->isPublished() && $export->public_slug
                    ? route('handbook-exports.public', $export->public_slug)
                    : null;

                return [
                    'id' => (string) $export->id,
                    'status' => $export->status,
                    'version_label' => $versionLabel,
                    'queued_at' => optional($export->queued_at)->toIso8601String(),
                    'started_at' => optional($export->started_at)->toIso8601String(),
                    'completed_at' => optional($export->completed_at)->toIso8601String(),
                    'failed_at' => optional($export->failed_at)->toIso8601String(),
                    'failure_reason' => $export->failure_reason,
                    'is_ready' => $export->isReady(),
                    'is_published' => $export->isPublished(),
                    'published_at' => optional($export->published_at)->toIso8601String(),
                    'published_by' => $export->publishedBy ? [
                        'id' => (string) $export->publishedBy->id,
                        'name' => $export->publishedBy->name,
                    ] : null,
                    'public_url' => $publicUrl,
                    'can_publish' => Gate::allows('publish', $export),
                    'can_unpublish' => Gate::allows('unpublish', $export),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Organizations/HandbookExports', [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ],
            'exports' => $exports,
        ]);
    }
}
