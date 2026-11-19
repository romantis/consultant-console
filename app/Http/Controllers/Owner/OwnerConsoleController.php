<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\Owner\OwnerDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerConsoleController extends Controller
{
    public function __construct(private readonly OwnerDashboardService $dashboardService) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $payload = $this->dashboardService->organizations($search, $status);

        $consoleOrganizations = array_map(
            static function (array $organization): array {
                return [
                    'id' => $organization['id'],
                    'name' => $organization['name'],
                    'slug' => $organization['slug'],
                    'statusBadges' => array_map(
                        static fn (array $badge): array => [
                            'label' => $badge['label'],
                            'variant' => $badge['variant'],
                            'count' => $badge['count'] ?? null,
                        ],
                        $organization['status_badges'] ?? [],
                    ),
                    'engagementsCount' => (int) $organization['engagements_count'],
                    'activeEngagementsCount' => (int) $organization['active_engagements_count'],
                    'draftEngagementsCount' => (int) $organization['draft_engagements_count'],
                    'completedEngagementsCount' => (int) $organization['completed_engagements_count'],
                    'metricsCount' => (int) $organization['metrics_count'],
                    'membershipsCount' => (int) $organization['memberships_count'],
                    'pendingMembershipsCount' => (int) $organization['pending_memberships_count'],
                ];
            },
            $payload['organizations'],
        );

        $statusOptions = array_map(
            static fn (array $option): array => [
                'value' => $option['value'],
                'label' => $option['label'],
                'count' => (int) $option['count'],
            ],
            $payload['status_options'],
        );

        return Inertia::render('Owner/Console', [
            'heading' => 'Owner console',
            'consoleOrganizations' => $consoleOrganizations,
            'filters' => $payload['filters'],
            'statusOptions' => $statusOptions,
            'summary' => [
                'organizations' => (int) ($payload['summary']['organizations'] ?? 0),
                'activeEngagements' => (int) ($payload['summary']['active_engagements'] ?? 0),
                'metrics' => (int) ($payload['summary']['metrics'] ?? 0),
                'pendingInvites' => (int) ($payload['summary']['pending_invites'] ?? 0),
            ],
        ]);
    }
}
