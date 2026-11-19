<?php

namespace App\Http\Controllers;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use App\Services\Organizations\MembershipAuditLogger;
use App\Services\Organizations\MembershipRoleManager;
use App\Support\AuthorizedMembershipRoles;
use App\Support\HandlesOrganizationMembershipChanges;
use App\Support\Impersonation;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    use HandlesOrganizationMembershipChanges;

    public function __construct(
        private MembershipRoleManager $roleManager,
        private MembershipAuditLogger $membershipAuditLogger,
    ) {}

    public function setActiveOrganization(Request $request): Response
    {
        $data = $request->validate([
            'organization_id' => ['required', 'string'],
        ]);

        $organizationId = (string) $data['organization_id'];
        $user = $request->user();

        abort_unless($user, 403);

        $organization = Organization::query()
            ->select('id', 'is_internal')
            ->find($organizationId);

        $staffWorkspace = Staff::internalOrganizationFor($user);
        $isStaffWorkspace = $staffWorkspace && (string) $staffWorkspace->id === $organizationId;

        $hasAccess = $isStaffWorkspace
            || (
                $organization
                && ! $organization->is_internal
                && (
                    Staff::isStaffOwner($user)
                    || Staff::isStaffAdmin($user)
                    || Staff::canOperateAssignedDelivery($user, $organizationId)
                    || $user->organizations()
                        ->where('organizations.id', $organizationId)
                        ->exists()
                )
            );

        if (! $hasAccess) {
            abort(403);
        }

        Impersonation::queueActiveOrganizationCookie($organizationId);

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (Staff::canImpersonate($user)) {
            $orgs = Organization::query()
                ->select('id', 'name', 'slug')
                ->where('is_internal', false)
                ->orderBy('name')
                ->get();
        } elseif (Staff::isStaffOperator($user)) {
            $assignedIds = StaffOperatorAssignment::query()
                ->active()
                ->forUser($user)
                ->pluck('organization_id');

            $orgs = Organization::query()
                ->select('id', 'name', 'slug')
                ->where('is_internal', false)
                ->where(function ($query) use ($user, $assignedIds) {
                    $query->whereIn('id', $assignedIds)
                        ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
                })
                ->orderBy('name')
                ->get();
        } else {
            $orgs = $user->organizations()
                ->where('organizations.is_internal', false)
                ->select('organizations.id', 'name', 'slug')
                ->orderBy('name')
                ->get();
        }

        $activeOrganizationId = $request->attributes->get('active_organization_id');
        $isImpersonating = (bool) Impersonation::currentOrganizationId($request);

        if (! $isImpersonating) {
            if ($orgs->isEmpty()) {
                Cookie::queue(Cookie::forget(Impersonation::ACTIVE_ORGANIZATION_COOKIE));
                $activeOrganizationId = null;
            } else {
                if (! $activeOrganizationId || ! $orgs->contains(fn ($org) => (string) $org->id === (string) $activeOrganizationId)) {
                    $activeOrganizationId = (string) $orgs->first()->id;
                }

                Impersonation::queueActiveOrganizationCookie((string) $activeOrganizationId);
            }
        }

        $organizations = $orgs->map(fn ($organization) => [
            'id' => (string) $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'can_manage' => Gate::forUser($user)->allows('update', $organization),
        ])->values();

        $staffWorkspace = Staff::internalOrganizationFor($user);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
            'activeOrganizationId' => $activeOrganizationId,
            'staffWorkspace' => $staffWorkspace ? [
                'id' => (string) $staffWorkspace->id,
                'name' => $staffWorkspace->name,
                'slug' => $staffWorkspace->slug,
            ] : null,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Organization::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('Organizations/Create', [
            'roles' => AuthorizedMembershipRoles::toOptions(
                AuthorizedMembershipRoles::forClientOrganizationCreation($actor),
            ),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Organization::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $allowedRoleValues = AuthorizedMembershipRoles::toValues(
            AuthorizedMembershipRoles::forClientOrganizationCreation($actor),
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:organizations,slug'],
            'members' => ['nullable', 'array'],
            'members.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'members.*.name' => ['nullable', 'string', 'max:120'],
            'members.*.affiliation' => ['nullable', 'string', 'max:160'],
            'members.*.role' => ['required_with:members.*.email', 'string', Rule::in($allowedRoleValues)],
            'memberships' => ['nullable', 'array'],
            'memberships.create' => ['nullable', 'array'],
            'memberships.create.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'memberships.create.*.name' => ['nullable', 'string', 'max:120'],
            'memberships.create.*.affiliation' => ['nullable', 'string', 'max:160'],
            'memberships.create.*.role' => ['required_with:memberships.create.*.email', 'string', Rule::in($allowedRoleValues)],
        ], [
            'members.*.role.in' => 'You are not allowed to assign this role.',
            'memberships.create.*.role.in' => 'You are not allowed to assign this role.',
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name']);

        $org = DB::transaction(function () use ($actor, $data, $slug) {
            $org = Organization::create([
                'name' => $data['name'],
                'slug' => $slug,
                'settings_json' => [
                    'locale' => 'en',
                    'currency' => 'USD',
                    'timezone' => 'UTC',
                ],
            ]);

            $membersPayload = $data['memberships']['create'] ?? $data['members'] ?? [];
            $normalized = $this->normalizeNewMemberPayload($membersPayload);

            $this->guardDuplicateMembers($org, $normalized);
            $this->createNewMemberships($org, $normalized, $actor);

            return $org;
        });

        return redirect()->route('org.show', $org);
    }

    public function show(Organization $organization)
    {
        $this->authorize('view', $organization);

        $user = request()->user();
        $canManageOperatorAssignments = $user instanceof User
            && ! $organization->is_internal
            && Staff::canOperateDelivery($user);

        Impersonation::queueActiveOrganizationCookie((string) $organization->id);

        $templates = MetricTemplate::query()
            ->orderBy('name')
            ->get(['key', 'name', 'description']);

        $memberships = $organization->memberships()
            ->with(['user:id,name,email'])
            ->get();

        $members = $memberships
            ->map(fn ($membership) => [
                'id' => (string) $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role instanceof MembershipRole ? $membership->role->value : $membership->role,
            ])
            ->values();

        $now = Carbon::now();

        $openFormsCount = Form::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'Open')
            ->count();

        $assignmentBaseQuery = FormAssignment::query()
            ->whereHas('form', fn ($query) => $query->where('organization_id', $organization->id));

        $unsubmittedAssignmentsQuery = (clone $assignmentBaseQuery)
            ->whereDoesntHave('submission', function ($query) {
                $query
                    ->whereColumn('form_submissions.user_id', 'form_assignment.user_id')
                    ->whereNotNull('submitted_at');
            });

        $openAssignmentsCount = (clone $unsubmittedAssignmentsQuery)->count();

        $dueSoonAssignmentsCount = (clone $unsubmittedAssignmentsQuery)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$now, $now->copy()->addHours(48)])
            ->count();

        $overdueAssignmentsCount = (clone $unsubmittedAssignmentsQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        $recentActivity = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => (string) $log->id,
                'action' => $log->action,
                'created_at' => optional($log->created_at)->toIso8601String(),
                'user' => $log->user ? [
                    'id' => (string) $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'subject' => [
                    'type' => $log->subject_type,
                    'id' => $log->subject_id,
                ],
                'summary' => $log->properties['summary'] ?? null,
            ])
            ->values();

        $insights = [
            'engagements' => $organization->engagements()->count(),
            'open_forms' => $openFormsCount,
            'assignments' => [
                'open' => $openAssignmentsCount,
                'due_soon' => $dueSoonAssignmentsCount,
                'overdue' => $overdueAssignmentsCount,
            ],
            'members' => $memberships->count(),
            'templates' => $templates->count(),
        ];

        $operatorAssignments = [];
        $operatorCandidates = [];

        if ($canManageOperatorAssignments) {
            $activeAssignments = StaffOperatorAssignment::query()
                ->active()
                ->forOrganization($organization)
                ->with([
                    'user:id,name,email',
                    'grantedByUser:id,name',
                ])
                ->orderByDesc('granted_at')
                ->get();

            $assignedUserIds = $activeAssignments
                ->pluck('user_id')
                ->map(fn ($userId) => (string) $userId)
                ->all();

            $operatorAssignments = $activeAssignments
                ->map(fn (StaffOperatorAssignment $assignment) => [
                    'id' => (string) $assignment->id,
                    'granted_at' => optional($assignment->granted_at)->toIso8601String(),
                    'reason' => $assignment->reason,
                    'notes' => $assignment->notes,
                    'user' => $assignment->user ? [
                        'id' => (string) $assignment->user->id,
                        'name' => $assignment->user->name,
                        'email' => $assignment->user->email,
                    ] : null,
                    'granted_by' => $assignment->grantedByUser ? [
                        'id' => (string) $assignment->grantedByUser->id,
                        'name' => $assignment->grantedByUser->name,
                    ] : null,
                ])
                ->values()
                ->all();

            $internalOrganization = Staff::canonicalInternalOrganization();

            if ($internalOrganization) {
                $operatorCandidates = Membership::query()
                    ->where('organization_id', $internalOrganization->id)
                    ->where('role', MembershipRole::MEMBER->value)
                    ->with('user:id,name,email')
                    ->get()
                    ->filter(function (Membership $membership) use ($assignedUserIds) {
                        $memberUser = $membership->user;

                        return $memberUser
                            && ! in_array((string) $memberUser->id, $assignedUserIds, true);
                    })
                    ->map(fn (Membership $membership) => [
                        'id' => (string) $membership->user_id,
                        'name' => $membership->user?->name,
                        'email' => $membership->user?->email,
                        'affiliation' => $membership->affiliation,
                    ])
                    ->sortBy([
                        ['name', 'asc'],
                        ['email', 'asc'],
                    ])
                    ->values()
                    ->all();
            }
        }

        return Inertia::render('Organizations/Show', [
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'is_internal' => (bool) $organization->is_internal,
            ],
            'templates' => $templates,
            'members' => $members,
            'activity' => $recentActivity,
            'insights' => $insights,
            'can_create' => Gate::allows('update', $organization), // staff/admin
            'can_update' => Gate::allows('update', $organization),
            'can_manage_operator_assignments' => $canManageOperatorAssignments,
            'operator_assignments' => $operatorAssignments,
            'operator_candidates' => $operatorCandidates,
            'activeOrganizationId' => (string) $organization->id,
        ]);
    }
}
