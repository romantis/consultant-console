<?php

namespace App\Http\Middleware;

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\HandbookExport;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\ImpersonationGuard;
use App\Support\Staff;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = Auth::user();
        $staffRole = $user ? Staff::staffRole($user) : null;
        $isInternalStaff = $user ? Staff::isInternalStaff($user) : false;
        $canOperateDelivery = $user ? Staff::canOperateDelivery($user) : false;
        $hasOperatorAssignments = $user ? Staff::hasOperatorAssignments($user, $staffRole) : false;
        $canGovernInternal = $user ? Staff::canGovernInternal($user) : false;
        $canImpersonate = $user ? Staff::canImpersonate($user) : false;
        $staffWorkspace = $user ? Staff::internalOrganizationFor($user) : null;
        $isStaff = (bool) $staffWorkspace;
        $canUseClientMembershipContext = $user ? ! $isInternalStaff : false;
        $activeOrganizationId = $request->attributes->get('active_organization_id');
        $impersonatedOrganizationId = $request->attributes->get('impersonated_organization_id');
        $impersonatedRole = $request->attributes->get('impersonated_role');
        $impersonationJustification = $request->attributes->get('impersonation_justification');
        $impersonationStartedAt = $request->attributes->get('impersonation_started_at');
        $activeOrganization = null;

        if (! $impersonatedOrganizationId) {
            $impersonatedOrganizationId = Impersonation::currentOrganizationId($request);
        }

        if (! $impersonatedRole) {
            $impersonatedRole = Impersonation::currentRole($request)?->value;
        }

        if (! $impersonationJustification) {
            $impersonationJustification = Impersonation::justification($request);
        }

        if (! $impersonationStartedAt) {
            $impersonationStartedAt = Impersonation::startedAt($request)?->toISOString();
        }

        $impersonatedRole = is_string($impersonatedRole) ? $impersonatedRole : null;
        $impersonationJustification = is_string($impersonationJustification) ? $impersonationJustification : null;
        $impersonationStartedAt = is_string($impersonationStartedAt) ? $impersonationStartedAt : null;
        $routeOrganization = $request->route('organization');

        if (! $impersonatedOrganizationId && $routeOrganization instanceof Organization) {
            $activeOrganizationId = (string) $routeOrganization->id;
        } elseif (! $impersonatedOrganizationId && (is_string($routeOrganization) || is_numeric($routeOrganization))) {
            $activeOrganizationId = (string) $routeOrganization;
        }

        if ($user && $canUseClientMembershipContext && ! $activeOrganizationId) {
            $preferredMembership = $user->memberships()
                ->whereHas('organization', fn ($query) => $query->where('is_internal', false))
                ->with('organization:id,name,slug')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if ($preferredMembership && $preferredMembership->organization) {
                $activeOrganization = $preferredMembership->organization;
                $activeOrganizationId = (string) $preferredMembership->organization->id;
            }
        }

        $engagements = [];
        $activeEngagementId = null;

        $routeEngagement = $request->route('engagement');
        $routeEngagementId = null;

        if ($routeEngagement instanceof Engagement) {
            $routeEngagementId = (string) $routeEngagement->id;

            if (! $activeOrganizationId) {
                $activeOrganizationId = (string) $routeEngagement->organization_id;
            }
        } elseif (is_string($routeEngagement) || is_numeric($routeEngagement)) {
            $routeEngagementId = (string) $routeEngagement;
        }

        if ($user && $activeOrganizationId) {
            if (! $activeOrganization) {
                if ($impersonatedOrganizationId) {
                    $activeOrganization = Organization::query()
                        ->where('id', $activeOrganizationId)
                        ->first();
                } elseif ($isInternalStaff) {
                    $activeOrganization = Organization::query()
                        ->where('id', $activeOrganizationId)
                        ->where('is_internal', false)
                        ->first();

                    if ($activeOrganization && ! $this->canUseStaffActiveOrganization($user, $activeOrganization)) {
                        $activeOrganization = null;
                    }
                } elseif ($canUseClientMembershipContext) {
                    $activeOrganization = $user->organizations()
                        ->where('organizations.id', $activeOrganizationId)
                        ->where('organizations.is_internal', false)
                        ->first();
                }
            }

            if ($activeOrganization) {
                $engagements = $activeOrganization->engagements()
                    ->orderByDesc('starts_on')
                    ->orderByDesc('created_at')
                    ->get(['id', 'title', 'status', 'review_cadence', 'next_review_on'])
                    ->map(function ($engagement) {
                        $status = $engagement->status;

                        if ($status instanceof EngagementStatus) {
                            $status = $status->value;
                        }

                        return [
                            'id' => (string) $engagement->id,
                            'title' => $engagement->title,
                            'status' => $status,
                            'review_cadence' => $engagement->review_cadence,
                            'next_review_on' => optional($engagement->next_review_on)->toDateString(),
                        ];
                    })
                    ->values()
                    ->all();

                $engagementCookie = $this->engagementCookieName((string) $activeOrganizationId);
                $activeEngagementId = $request->cookie($engagementCookie);

                if (empty($engagements)) {
                    Cookie::queue(Cookie::forget($engagementCookie));
                }

                $engagementCollection = collect($engagements);

                if ($activeEngagementId && ! $engagementCollection->contains(fn ($item) => $item['id'] === (string) $activeEngagementId)) {
                    $activeEngagementId = null;
                }

                if ($routeEngagementId && $engagementCollection->contains(fn ($item) => $item['id'] === (string) $routeEngagementId)) {
                    $activeEngagementId = (string) $routeEngagementId;
                }

                if (! $activeEngagementId && ! empty($engagements)) {
                    $activeEngagementId = $engagements[0]['id'];
                }

                if ($activeEngagementId) {
                    Cookie::queue(cookie($engagementCookie, (string) $activeEngagementId, 60 * 24 * 30));
                }
            } else {
                $invalidActiveOrganizationId = (string) $activeOrganizationId;
                Cookie::queue(Cookie::forget($this->engagementCookieName($invalidActiveOrganizationId)));

                if (! $impersonatedOrganizationId) {
                    Cookie::queue(Cookie::forget(Impersonation::ACTIVE_ORGANIZATION_COOKIE));
                }

                $activeOrganizationId = null;
            }
        }

        $clientNavigation = null;
        $impersonatingClientOrg = $isInternalStaff
            && $impersonatedOrganizationId
            && $activeOrganization
            && (string) $impersonatedOrganizationId === (string) $activeOrganization->id;

        if ($user && ($canUseClientMembershipContext || $impersonatingClientOrg)) {
            $clientNavigation = $this->buildClientNavigation(
                $request,
                $activeOrganization,
                $user,
                $engagements,
                $activeEngagementId,
                $impersonatingClientOrg,
            );
        }

        $handbookExports = [];

        if ($clientNavigation && $activeOrganization) {
            $handbookExports = $this->resolveHandbookExports($activeOrganization);
        }

        $impersonationPayload = $this->buildImpersonationPayload(
            $impersonatedOrganizationId,
            $impersonatedRole,
            $impersonationStartedAt,
            $impersonationJustification,
        );

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? [
                    'id' => Auth::id(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_staff' => $isStaff,
                    'is_internal_staff' => $isInternalStaff,
                    'staff_role' => $staffRole?->value,
                    'can_operate_delivery' => $canOperateDelivery,
                    'has_operator_assignments' => $hasOperatorAssignments,
                    'can_impersonate' => $canImpersonate,
                    'can_govern_internal' => $canGovernInternal,
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'impersonation_error' => session('impersonation_error'),
            ],
            'flags' => [
                'allow_public_registration' => config('console.allow_public_registration'),
                'workshop_discrepancies' => config('metric-harmony.features.workshop_discrepancies'),
                'public_handbook_links' => config('metric-harmony.features.public_handbook_links'),
                'change_proposals' => config('metric-harmony.features.change_proposals'),
                'org_metric_overrides' => config('metric-harmony.features.org_metric_overrides'),
                'workshop_force_start_enabled' => app()->environment('local')
                    && config('metric-harmony.workshops.force_start_enabled'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'organizations' => $user ? $this->mapSidebarOrganizations($user, $isInternalStaff) : [],
            'activeOrganizationId' => $activeOrganizationId,
            'engagements' => $engagements,
            'activeEngagementId' => $activeEngagementId,
            'staffWorkspace' => $staffWorkspace
                ? [
                    'id' => (string) $staffWorkspace->id,
                    'name' => $staffWorkspace->name,
                    'slug' => $staffWorkspace->slug,
                ]
                : null,
            'impersonation' => $impersonationPayload,
            'ownerOrganizations' => $staffWorkspace
                ? $this->mapOwnerOrganizations()
                : null,
            'clientNavigation' => $clientNavigation,
            'handbookExports' => $handbookExports,
        ]);
    }

    /**
     * @param  array<int, array{id: string, title: string, status?: string|null}>  $engagements
     */
    private function buildClientNavigation(
        Request $request,
        ?Organization $organization,
        User $user,
        array $engagements,
        ?string $activeEngagementId,
        bool $allowImpersonatedMembership = false
    ): ?array {
        if (! $organization) {
            return null;
        }

        $context = ImpersonationGuard::resolve($user, $organization, $request);
        $membership = $context->membership();
        $membershipRole = $context->role();

        if (! $membership && (! $allowImpersonatedMembership || ! $context->impersonating())) {
            return null;
        }

        $membershipPayload = [
            'id' => $membership ? (string) $membership->id : sprintf('impersonation:%s', $organization->id),
            'role' => $membershipRole?->value ?? null,
            'canViewHandbook' => true,
        ];

        $links = [
            'forms' => null,
            'metrics' => null,
            'handbook' => $allowImpersonatedMembership
                ? $this->safeRoute('client.handbook.index')
                : $this->safeRoute('home'),
            'support' => null,
        ];

        $handbookMetrics = $this->buildHandbookMetrics($organization, $activeEngagementId);

        $engagementLinks = array_map(
            fn (array $engagement): array => [
                'id' => (string) $engagement['id'],
                'title' => $engagement['title'],
                'status' => $engagement['status'] ?? null,
                'href' => $this->safeRoute('org.engagements.show', [$organization, $engagement['id']]),
                'reviewCadence' => $engagement['review_cadence'] ?? null,
                'nextReviewOn' => $engagement['next_review_on'] ?? null,
            ],
            $engagements,
        );

        return [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
            ],
            'membership' => $membershipPayload,
            'engagements' => array_values($engagementLinks),
            'links' => $links,
            'handbookMetrics' => $handbookMetrics,
            'activeEngagementId' => $activeEngagementId,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, href: string|null}>
     */
    private function buildHandbookMetrics(Organization $organization, ?string $activeEngagementId): array
    {
        $activeEngagementStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::activePhases()
        );

        $orgMetrics = OrgMetric::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query) use ($activeEngagementStatuses) {
                $query->whereHas('metrics', function ($metricQuery) use ($activeEngagementStatuses) {
                    $metricQuery->whereHas('engagement', function ($engagementQuery) use ($activeEngagementStatuses) {
                        $engagementQuery->whereIn('status', $activeEngagementStatuses);
                    });
                })->orWhereHas('finalDefinitions', function ($definitionQuery) {
                    $definitionQuery->whereNotNull('signed_off_at');
                });
            })
            ->with(['canonicalMetric:id,name'])
            ->get(['id', 'organization_id', 'canonical_metric_id', 'name', 'alias']);

        if ($orgMetrics->isEmpty()) {
            return [];
        }

        return $orgMetrics
            ->map(function (OrgMetric $orgMetric) use ($organization, $activeEngagementId) {
                $displayName = $orgMetric->alias
                    ?: ($orgMetric->canonicalMetric?->name ?? $orgMetric->name ?? 'Metric');
                $href = $activeEngagementId
                    ? $this->safeRoute('client.handbook.metrics.page', [$organization, $activeEngagementId, $orgMetric])
                    : null;

                return [
                    'id' => (string) $orgMetric->getKey(),
                    'name' => $displayName,
                    'href' => $href,
                ];
            })
            ->sortBy(fn (array $item) => strtolower((string) ($item['name'] ?? '')))
            ->values()
            ->all();
    }

    private function safeRoute(string $name, array $parameters = []): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    private function engagementCookieName(string $organizationId): string
    {
        return 'active_engagement_id_'.$organizationId;
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string, owned: bool}>
     */
    private function mapOrganizations(User $user): array
    {
        return $user
            ->organizations()
            ->where('organizations.is_internal', false)
            ->select('organizations.id', 'name', 'slug')
            ->orderBy('name')
            ->get()
            ->map(function ($organization) {
                $role = $organization->pivot?->role;

                if ($role && ! $role instanceof MembershipRole) {
                    $role = MembershipRole::tryFrom((string) $role);
                }

                return [
                    'id' => (string) $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'owned' => true,
                    'role' => $role?->value,
                    'can_manage' => $role?->isManager() ?? false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string, owned?: bool, role?: string|null, can_manage?: bool, can_operate_delivery?: bool}>
     */
    private function mapSidebarOrganizations(User $user, bool $isInternalStaff): array
    {
        $organizations = array_values($this->mapOrganizations($user));

        if (! $isInternalStaff) {
            return array_map(
                static function (array $organization): array {
                    if (($organization['can_manage'] ?? false) !== true) {
                        unset($organization['can_manage']);
                    }

                    unset($organization['role']);

                    return $organization;
                },
                $organizations,
            );
        }

        $ownedIds = array_map(
            static fn (array $organization): string => $organization['id'],
            $organizations,
        );

        $organizationLookup = collect($organizations)->keyBy('id');

        $staffOrgs = $this->mapStaffVisibleOrganizations($user);
        $sidebarOrganizationModels = Organization::query()
            ->select('id', 'is_internal')
            ->whereIn('id', array_map(
                static fn (array $organization): string => (string) $organization['id'],
                $staffOrgs,
            ))
            ->get()
            ->keyBy(fn (Organization $organization) => (string) $organization->id);

        $consultantOrganizations = array_map(
            function (array $organization) use ($user, $ownedIds, $organizationLookup, $sidebarOrganizationModels): array {
                $organization['owned'] = in_array($organization['id'], $ownedIds, true);
                $organization['can_operate_delivery'] = Staff::canOperateAssignedDelivery($user, $organization['id']);
                $organizationModel = $sidebarOrganizationModels->get((string) $organization['id']);

                $details = $organizationLookup->get($organization['id']);

                if (is_array($details)) {
                    $organization['role'] = $details['role'] ?? null;
                    $organization['can_manage'] = $organizationModel
                        ? Gate::forUser($user)->allows('update', $organizationModel)
                        : false;
                } else {
                    $organization['role'] = null;
                    $organization['can_manage'] = $organizationModel
                        ? Gate::forUser($user)->allows('update', $organizationModel)
                        : false;
                }

                return $organization;
            },
            $staffOrgs,
        );

        if (Staff::canImpersonate($user)) {
            array_unshift($consultantOrganizations, [
                'id' => 'impersonation-off',
                'name' => 'Impersonation off',
                'slug' => 'impersonation-off',
                'owned' => false,
                'role' => null,
                'can_manage' => false,
                'can_operate_delivery' => false,
            ]);
        }

        return array_values($consultantOrganizations);
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string}>
     */
    private function mapStaffVisibleOrganizations(User $user): array
    {
        if (Staff::canImpersonate($user)) {
            return $this->mapOwnerOrganizations();
        }

        if (Staff::isStaffOperator($user)) {
            $assignedIds = StaffOperatorAssignment::query()
                ->active()
                ->forUser($user)
                ->pluck('organization_id');

            return Organization::query()
                ->select('id', 'name', 'slug')
                ->where('is_internal', false)
                ->where(function ($query) use ($user, $assignedIds) {
                    $query->whereIn('id', $assignedIds)
                        ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
                })
                ->orderBy('name')
                ->get()
                ->map(fn (Organization $organization) => [
                    'id' => (string) $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ])
                ->values()
                ->all();
        }

        return [];
    }

    private function canUseStaffActiveOrganization(User $user, Organization $organization): bool
    {
        if ($organization->is_internal) {
            return false;
        }

        if (Staff::canOperateDelivery($user)) {
            return true;
        }

        if (! Staff::isStaffOperator($user)) {
            return false;
        }

        return Staff::canOperateAssignedDelivery($user, $organization)
            || $user->organizations()
                ->where('organizations.id', $organization->id)
                ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildImpersonationPayload(
        ?string $organizationId,
        ?string $role = null,
        ?string $startedAt = null,
        ?string $justification = null
    ): ?array {
        if (! $organizationId) {
            return null;
        }

        $organization = Organization::query()
            ->select('id', 'name', 'slug')
            ->find($organizationId);

        if (! $organization) {
            return null;
        }

        $payload = [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
        ];

        $payload['role'] = $role;

        if ($startedAt) {
            $payload['started_at'] = $startedAt;
        }

        if ($justification) {
            $payload['justification'] = $justification;
        }

        return $payload;
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string}>
     */
    private function mapOwnerOrganizations(): array
    {
        return Organization::query()
            ->select('id', 'name', 'slug')
            ->where('is_internal', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveHandbookExports(Organization $organization): array
    {
        return HandbookExport::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('queued_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (HandbookExport $export) {
                $queuedAt = $export->queued_at;
                $versionLabel = $queuedAt ? ('v'.$queuedAt->format('Ymd-His')) : 'v—';

                return [
                    'id' => (string) $export->getKey(),
                    'status' => $export->status,
                    'version_label' => $versionLabel,
                    'queued_at' => optional($export->queued_at)->toIso8601String(),
                    'started_at' => optional($export->started_at)->toIso8601String(),
                    'completed_at' => optional($export->completed_at)->toIso8601String(),
                    'failed_at' => optional($export->failed_at)->toIso8601String(),
                    'failure_reason' => $export->failure_reason,
                    'is_ready' => $export->isReady(),
                ];
            })
            ->values()
            ->all();
    }
}
