<?php

namespace App\Http\Controllers;

use App\Actions\StaffOperatorAssignments\RevokeActiveStaffOperatorAssignmentsAction;
use App\Enums\MembershipRole;
use App\Events\Engagements\EngagementInvitationRevoked;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Organizations\MembershipAuditLogger;
use App\Services\Organizations\MembershipRoleManager;
use App\Support\AuthorizedMembershipRoles;
use App\Support\AuthorizesOrganizationRoles;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationMemberController extends Controller
{
    use AuthorizesOrganizationRoles;

    public function __construct(
        private MembershipRoleManager $roleManager,
        private MembershipAuditLogger $membershipAuditLogger,
        private RevokeActiveStaffOperatorAssignmentsAction $revokeActiveStaffOperatorAssignmentsAction,
    ) {}

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->authorizeOrganizationRole($actor, $organization, MembershipRole::ADMIN);

        $allowedRoleValues = AuthorizedMembershipRoles::toValues(
            AuthorizedMembershipRoles::forUser($actor, $organization),
        );

        $data = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'email' => [
                Rule::requiredIf(fn () => empty($request->input('user_id'))),
                'nullable',
                'string',
                'max:255',
                'email:rfc',
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in($allowedRoleValues)],
        ], [
            'role.in' => 'You are not allowed to assign this role.',
        ]);

        $role = MembershipRole::from($data['role']);

        if ($role === MembershipRole::OWNER && ! $this->userHasOrganizationRoleAtLeast($actor, $organization, MembershipRole::OWNER)) {
            return back()->withErrors(['role' => 'Only organization owners can assign the owner role.']);
        }

        try {
            return DB::transaction(function () use ($data, $role, $organization, $request, $actor) {
                $email = isset($data['email']) ? mb_strtolower($data['email']) : null;
                $user = null;

                if (! empty($data['user_id'])) {
                    $user = User::find($data['user_id']);
                } elseif ($email) {
                    $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
                }

                if ($email) {
                    $emailAlreadyMember = $organization->memberships()
                        ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
                        ->exists();

                    if ($emailAlreadyMember) {
                        return back()->withErrors(['email' => 'This user is already a member of the organization.']);
                    }
                }

                if ($user) {
                    if (! $organization->is_internal && Staff::isInternalStaff($user)) {
                        return back()->withErrors([
                            'email' => 'Internal staff must be assigned through staff operator assignments.',
                        ]);
                    }

                    $alreadyMember = $organization->memberships()
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($alreadyMember) {
                        return back()->withErrors(['email' => 'This user is already a member of the organization.']);
                    }

                    $membership = $organization->memberships()->create([
                        'user_id' => $user->id,
                        'role' => $role->value,
                    ]);

                    $this->roleManager->syncRole(
                        $membership,
                        $role,
                        $actor,
                        force: $role === MembershipRole::OWNER,
                    );
                    $this->membershipAuditLogger->membershipCreated($membership, $actor);

                    $pendingMemberships = $organization->pendingMemberships()
                        ->whereRaw('LOWER(email) = ?', [$email ?? mb_strtolower($user->email)])
                        ->get();

                    foreach ($pendingMemberships as $pendingMembership) {
                        $participantIds = EngagementParticipant::query()
                            ->where('pending_membership_id', $pendingMembership->id)
                            ->pluck('id')
                            ->all();

                        if (! empty($participantIds)) {
                            event(new EngagementInvitationRevoked($pendingMembership, $participantIds));
                        }

                        $pendingMembership->delete();
                    }

                    return back()->with('success', 'Member added.');
                }

                if (! $email) {
                    return back()->withErrors(['email' => 'An email address is required when inviting a new member.']);
                }

                $duplicateInvite = $organization->pendingMemberships()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->exists();

                if ($duplicateInvite) {
                    return back()->withErrors(['email' => 'An invite has already been created for this email address.']);
                }

                $organization->pendingMemberships()->create([
                    'email' => $email,
                    'name' => $data['name'] ?? null,
                    'role' => $role->value,
                    'invited_by' => $request->user()->id,
                ]);

                return back()->with('success', 'Invite recorded.');
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function update(Request $request, Organization $organization, string $membershipId): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->authorizeOrganizationRole($actor, $organization, MembershipRole::ADMIN);

        $allowedRoleValues = AuthorizedMembershipRoles::toValues(
            AuthorizedMembershipRoles::forUser($actor, $organization),
        );

        $membership = $this->resolveOrganizationMembership(
            $organization,
            $membershipId,
            $this->extractMembershipUserFilter($request)
        );

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoleValues)],
        ], [
            'role.in' => 'You are not allowed to assign this role.',
        ]);

        $newRole = MembershipRole::from($data['role']);

        try {
            return DB::transaction(function () use (
                $membership,
                $newRole,
                $actor,
                $organization
            ): RedirectResponse {
                $currentRole = $membership->role instanceof MembershipRole
                    ? $membership->role
                    : MembershipRole::from($membership->role);

                $this->roleManager->syncRole($membership, $newRole, $actor);

                $this->revokeActiveStaffOperatorAssignmentsAction->revokeForMembershipChange(
                    $organization,
                    $membership,
                    $actor,
                    $currentRole,
                    $newRole,
                );

                return back()->with('success', 'Member updated.');
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(Request $request, Organization $organization, string $membershipId): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->authorizeOrganizationRole($actor, $organization, MembershipRole::ADMIN);

        $membership = $this->resolveOrganizationMembership(
            $organization,
            $membershipId,
            $this->extractMembershipUserFilter($request)
        );

        try {
            return DB::transaction(function () use ($organization, $membership, $actor, $request): RedirectResponse {
                if ($membership->role === MembershipRole::OWNER) {
                    $ownerCount = $organization->memberships()
                        ->where('role', MembershipRole::OWNER->value)
                        ->count();

                    if ($ownerCount <= 1) {
                        return back()->withErrors(['member' => 'The organization requires at least one owner.']);
                    }
                }

                if ($membership->user_id === $request->user()->id) {
                    $ownerCount = $organization->memberships()
                        ->where('role', MembershipRole::OWNER->value)
                        ->count();

                    if ($ownerCount <= 1) {
                        return back()->withErrors(['member' => 'The organization requires at least one owner.']);
                    }
                }

                $this->revokeActiveStaffOperatorAssignmentsAction->revokeForMembershipChange(
                    $organization,
                    $membership,
                    $actor,
                    $membership->role instanceof MembershipRole
                        ? $membership->role
                        : MembershipRole::from($membership->role),
                );

                $this->membershipAuditLogger->membershipDeleted($membership, $actor);

                $membership->delete();

                return back()->with('success', 'Member removed.');
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    private function resolveOrganizationMembership(Organization $organization, string $membershipId, ?string $userId = null): Membership
    {
        return $organization->memberships()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereKey($membershipId)
            ->firstOrFail();
    }

    private function extractMembershipUserFilter(Request $request): ?string
    {
        $userId = $request->input('user_id');

        return is_string($userId) && $userId !== '' ? $userId : null;
    }

    public function updatePending(Request $request, Organization $organization, PendingMembership $pendingMembership): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->authorizeOrganizationRole($actor, $organization, MembershipRole::ADMIN);

        $allowedRoleValues = AuthorizedMembershipRoles::toValues(
            AuthorizedMembershipRoles::forUser($actor, $organization),
        );

        if ($pendingMembership->organization_id !== $organization->id) {
            abort(404);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoleValues)],
            'name' => ['nullable', 'string', 'max:120'],
        ], [
            'role.in' => 'You are not allowed to assign this role.',
        ]);

        $newRole = MembershipRole::from($data['role']);

        if ($newRole === MembershipRole::OWNER && ! $this->userHasOrganizationRoleAtLeast($actor, $organization, MembershipRole::OWNER)) {
            return back()->withErrors(['role' => 'Only organization owners can assign the owner role.']);
        }

        $pendingMembership->update([
            'role' => $newRole->value,
            'name' => $data['name'] ?? $pendingMembership->name,
        ]);

        return back()->with('success', 'Invite updated.');
    }

    public function destroyPending(Request $request, Organization $organization, PendingMembership $pendingMembership): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->authorizeOrganizationRole($actor, $organization, MembershipRole::ADMIN);

        if ($pendingMembership->organization_id !== $organization->id) {
            abort(404);
        }

        $participantIds = EngagementParticipant::query()
            ->where('pending_membership_id', $pendingMembership->id)
            ->pluck('id')
            ->all();

        if (! empty($participantIds)) {
            event(new EngagementInvitationRevoked($pendingMembership, $participantIds));
        }

        $pendingMembership->delete();

        return back()->with('success', 'Invite removed.');
    }
}
