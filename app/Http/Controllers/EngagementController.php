<?php

namespace App\Http\Controllers;

use App\Actions\Engagements\DeleteEngagementDraft;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\PendingMembership;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EngagementController extends Controller
{
    public function create(Organization $organization): RedirectResponse
    {
        $this->authorizeLaunchAccess(request()->user(), $organization);

        return redirect()->route('org.engagements.launch', $organization);
    }

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);
        $canLaunch = $this->canLaunchForOrganization($request->user(), $organization);

        $statusParam = $request->string('status')->trim();
        $statusFilter = $statusParam->isNotEmpty() ? EngagementStatus::tryFromLegacy($statusParam->toString()) : null;

        $startDate = null;
        $endDate = null;

        try {
            $startDate = $request->has('starts_on') ? $request->date('starts_on') : null;
        } catch (\Throwable $e) {
            $startDate = null;
        }

        try {
            $endDate = $request->has('ends_on') ? $request->date('ends_on') : null;
        } catch (\Throwable $e) {
            $endDate = null;
        }

        $engagementsQuery = $organization->engagements()
            ->withCount([
                'forms',
                'forms as completed_forms_count' => fn ($query) => $query->where('status', 'Completed'),
                'forms as in_progress_forms_count' => fn ($query) => $query->where('status', 'InProgress'),
                'forms as open_forms_count' => fn ($query) => $query->where('status', 'Open'),
                'metrics',
                'metrics as metrics_awaiting_forms_count' => fn ($query) => $query->where('status', MetricStatus::AWAITING_FORMS),
                'metrics as metrics_in_review_count' => fn ($query) => $query->where('status', MetricStatus::IN_REVIEW),
                'metrics as metrics_in_workshop_count' => fn ($query) => $query->where('status', MetricStatus::IN_WORKSHOP),
                'metrics as metrics_finalized_count' => fn ($query) => $query->where('status', MetricStatus::FINALIZED),
                'metrics as metrics_deprecated_count' => fn ($query) => $query->where('status', MetricStatus::DEPRECATED),
            ])
            ->when($statusFilter, fn ($query) => $query->where('status', $statusFilter->value))
            ->when($startDate, fn ($query) => $query->whereDate('starts_on', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('ends_on', '<=', $endDate))
            ->orderByDesc('starts_on')
            ->orderByDesc('created_at');

        $engagements = $engagementsQuery->paginate(15)->withQueryString();

        $engagements->getCollection()->transform(function (Engagement $engagement) use ($request) {
            $formsProgress = [
                'open' => (int) $engagement->open_forms_count,
                'in_progress' => (int) $engagement->in_progress_forms_count,
                'completed' => (int) $engagement->completed_forms_count,
            ];
            $formsProgress['total'] = array_sum($formsProgress);
            $formsProgress['completion'] = $formsProgress['total'] > 0
                ? (int) round(($formsProgress['completed'] / $formsProgress['total']) * 100)
                : 0;

            $metricsProgress = [
                'awaiting_forms' => (int) $engagement->metrics_awaiting_forms_count,
                'in_review' => (int) $engagement->metrics_in_review_count,
                'in_workshop' => (int) $engagement->metrics_in_workshop_count,
                'finalized' => (int) $engagement->metrics_finalized_count,
                'deprecated' => (int) $engagement->metrics_deprecated_count,
            ];
            $metricsProgress['total'] = array_sum($metricsProgress);
            $metricsProgress['completion'] = $metricsProgress['total'] > 0
                ? (int) round(($metricsProgress['finalized'] / $metricsProgress['total']) * 100)
                : 0;

            return [
                'id' => (string) $engagement->id,
                'title' => $engagement->title,
                'status' => $engagement->status instanceof EngagementStatus ? $engagement->status->value : $engagement->status,
                'starts_on' => optional($engagement->starts_on)->toDateString(),
                'ends_on' => optional($engagement->ends_on)->toDateString(),
                'forms' => [
                    'total' => (int) $engagement->forms_count,
                    'progress' => $formsProgress,
                ],
                'metrics' => [
                    'total' => (int) $engagement->metrics_count,
                    'progress' => $metricsProgress,
                ],
                'can_delete' => $request->user()?->can('delete', $engagement) ?? false,
            ];
        });

        $availableStatuses = collect(EngagementStatus::cases())
            ->map(fn (EngagementStatus $status) => [
                'value' => $status->value,
                'label' => $status->value,
            ])
            ->values();

        return Inertia::render('Engagements/Index', [
            'organization' => [
                ...$organization->only(['id', 'name', 'is_internal']),
                'can_launch' => $canLaunch,
            ],
            'engagements' => $engagements,
            'filters' => [
                'available' => [
                    'statuses' => $availableStatuses,
                ],
                'active' => [
                    'status' => $statusFilter?->value,
                    'starts_on' => $startDate?->toDateString(),
                    'ends_on' => $endDate?->toDateString(),
                ],
            ],
        ]);
    }

    public function launch(Organization $organization): Response
    {
        $actor = request()->user();
        $this->authorizeLaunchAccess($actor, $organization);

        $canonicalMetrics = CanonicalMetric::query()
            ->where('is_active', true)
            ->with(['templates' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $orgMetrics = OrgMetric::query()
            ->where('organization_id', $organization->id)
            ->get(['id', 'canonical_metric_id', 'default_metric_template_id'])
            ->keyBy(fn (OrgMetric $orgMetric) => (string) $orgMetric->canonical_metric_id);

        $blockedStatuses = array_merge(
            [EngagementStatus::DRAFT->value],
            array_map(static fn (EngagementStatus $status) => $status->value, EngagementStatus::activePhases())
        );

        $activeEngagements = Metric::query()
            ->select([
                'metrics.org_metric_id',
                'engagements.id as engagement_id',
                'engagements.title',
                'engagements.status as engagement_status',
            ])
            ->join('engagements', 'engagements.id', '=', 'metrics.engagement_id')
            ->where('metrics.organization_id', $organization->id)
            ->whereIn('engagements.status', $blockedStatuses)
            ->orderByDesc('engagements.updated_at')
            ->get()
            ->groupBy('org_metric_id')
            ->map(function ($rows) {
                $row = $rows->first();

                return [
                    'id' => (string) $row->engagement_id,
                    'title' => $row->title,
                    'status' => $row->engagement_status,
                ];
            });

        $metrics = $canonicalMetrics
            ->map(function (CanonicalMetric $metric) use ($orgMetrics, $activeEngagements) {
                $orgMetric = $orgMetrics->get((string) $metric->getKey());
                $activeEngagement = $orgMetric ? $activeEngagements->get((string) $orgMetric->getKey()) : null;
                $templateLookup = $metric->templates
                    ->keyBy(fn (MetricTemplate $template) => (string) $template->id);
                $defaultTemplateId = null;

                $orgDefault = $orgMetric?->default_metric_template_id;
                if ($orgDefault && $templateLookup->has((string) $orgDefault)) {
                    $defaultTemplateId = (string) $orgDefault;
                }

                $canonicalDefault = $metric->default_metric_template_id;
                if (! $defaultTemplateId && $canonicalDefault && $templateLookup->has((string) $canonicalDefault)) {
                    $defaultTemplateId = (string) $canonicalDefault;
                }

                if (! $defaultTemplateId) {
                    $defaultTemplateId = (string) ($metric->templates->first()?->id ?? '');
                }

                return [
                    'id' => (string) $metric->getKey(),
                    'key' => $metric->key,
                    'name' => $metric->name,
                    'description' => $metric->description ?? '',
                    'is_active' => (bool) $metric->is_active,
                    'active_engagement' => $activeEngagement,
                    'default_metric_template_id' => $defaultTemplateId !== '' ? $defaultTemplateId : null,
                    'templates' => $metric->templates
                        ->map(function (MetricTemplate $template) {
                            $requiredDepartments = collect(data_get($template->form_schema_json, 'required_departments', []))
                                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                                ->map(fn (string $value) => trim($value))
                                ->values()
                                ->all();

                            return [
                                'id' => (string) $template->id,
                                'key' => $template->key,
                                'name' => $template->name,
                                'description' => $template->description ?? '',
                                'form_schema_id' => (string) $template->id,
                                'default_due_days' => (int) data_get($template->form_schema_json, 'default_due_days', 14),
                                'required_departments' => $requiredDepartments,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $membershipRecords = $organization->memberships()
            ->with('user:id,name,email')
            ->get();

        $members = $membershipRecords
            ->map(function (Membership $membership) {
                $departments = collect([$membership->affiliation])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value) => trim($value))
                    ->values()
                    ->all();

                $role = $membership->role?->value;

                return [
                    'id' => (string) $membership->id,
                    'name' => $membership->user?->name ?? '',
                    'email' => $membership->user?->email ?? '',
                    'role' => $role ? ucfirst($role) : 'Member',
                    'departments' => $departments,
                    'is_staff' => $membership->user ? Staff::isStaff($membership->user) : false,
                ];
            })
            ->values()
            ->all();

        $pendingMembers = $organization->pendingMemberships()
            ->orderBy('email')
            ->get()
            ->map(function (PendingMembership $pending) {
                $departments = collect([$pending->affiliation])
                    ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value) => trim($value))
                    ->values()
                    ->all();

                $role = $pending->role?->value;

                return [
                    'id' => (string) $pending->id,
                    'name' => $pending->name ?? '',
                    'email' => $pending->email,
                    'role' => $role ? ucfirst($role) : 'Member',
                    'departments' => $departments,
                ];
            })
            ->values()
            ->all();

        $stakeholderRoles = [
            ['value' => 'Admin', 'label' => 'Admin (manage engagement)'],
            ['value' => 'Member', 'label' => 'Member (collaborate on delivery)'],
            ['value' => 'Viewer', 'label' => 'Viewer (read only)'],
        ];

        $today = Carbon::now();

        $repositoryFeatureEnabled = (bool) config('metric-harmony.features.repository_drivers', false);
        $repositoryOptions = [];

        if ($repositoryFeatureEnabled) {
            $repositoryOptions = collect(config('metric-harmony.repository_drivers', []))
                ->map(fn (string $label, string $driver) => [
                    'driver' => $driver,
                    'label' => $label,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Engagements/LaunchWizard', [
            'submitUrl' => route('api.engagements.store'),
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'metrics' => $metrics,
            'members' => $members,
            'currentMembershipId' => optional(
                $membershipRecords->firstWhere('user_id', optional(request()->user())->id)
            )?->getKey(),
            'pendingMembers' => $pendingMembers,
            'stakeholderRoles' => $stakeholderRoles,
            'repositoryOptions' => $repositoryOptions,
            'repositoryFeatureEnabled' => $repositoryFeatureEnabled,
            'defaultTimeline' => [
                'start' => '',
                'end' => '',
            ],
            'permissions' => [
                'can_launch' => $this->canLaunchForOrganization($actor, $organization),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        DeleteEngagementDraft $deleteEngagementDraft
    ): RedirectResponse {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $this->authorize('delete', $engagement);

        $deleteEngagementDraft->handle($engagement, $request->user());

        return redirect()
            ->route('org.engagements.index', $organization)
            ->with('success', __('Engagement draft deleted.'));
    }

    private function authorizeLaunchAccess(mixed $actor, Organization $organization): void
    {
        abort_unless($actor instanceof User, 403);
        abort_unless($this->canLaunchForOrganization($actor, $organization), 403);
    }

    private function canLaunchForOrganization(mixed $actor, Organization $organization): bool
    {
        return $actor instanceof User
            && Staff::canOperateAssignedDelivery($actor, $organization);
    }
}
