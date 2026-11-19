<?php

namespace App\Http\Controllers;

use App\Actions\StaffOperatorAssignments\RevokeActiveStaffOperatorAssignmentsAction;
use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Organizations\MembershipAuditLogger;
use App\Services\Organizations\MembershipRoleManager;
use App\Support\AuthorizedMembershipRoles;
use App\Support\HandlesOrganizationMembershipChanges;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizationSettingsController extends Controller
{
    use HandlesOrganizationMembershipChanges;

    public function __construct(
        private MembershipRoleManager $roleManager,
        private MembershipAuditLogger $membershipAuditLogger,
        private RevokeActiveStaffOperatorAssignmentsAction $revokeActiveStaffOperatorAssignmentsAction,
    ) {}

    public function edit(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $organization->load([
            'memberships.user:id,name,email',
            'pendingMemberships',
        ]);

        $actor = $request->user();
        $roleOptions = [];

        if ($actor instanceof User) {
            $roleOptions = AuthorizedMembershipRoles::toOptions(
                AuthorizedMembershipRoles::forUser($actor, $organization),
            );
        }

        $inviteTtlMinutes = (int) Config::get('invitations.pending.accept_ttl_minutes', 60 * 24 * 7);

        return Inertia::render('Organizations/Settings', [
            'organization' => $organization->only(['id', 'name', 'slug', 'settings_json']),
            'members' => $organization->memberships
                ->map(fn ($membership) => [
                    'id' => (string) $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role instanceof MembershipRole ? $membership->role->value : $membership->role,
                    'affiliation' => $membership->affiliation,
                ])->values()->all(),
            'pending_members' => $organization->pendingMemberships
                ->map(function ($pending) use ($inviteTtlMinutes) {
                    $sentAt = $pending->created_at instanceof Carbon ? $pending->created_at->copy() : null;

                    return [
                        'id' => (string) $pending->id,
                        'email' => $pending->email,
                        'name' => $pending->name,
                        'role' => $pending->role instanceof MembershipRole ? $pending->role->value : $pending->role,
                        'affiliation' => $pending->affiliation,
                        'engagement_id' => $pending->engagement_id ? (string) $pending->engagement_id : null,
                        'sent_at' => $sentAt?->toIso8601String(),
                        'expires_at' => $sentAt?->copy()->addMinutes($inviteTtlMinutes)->toIso8601String(),
                    ];
                })->values()->all(),
            'roles' => array_values($roleOptions),
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $allowedRoleValues = AuthorizedMembershipRoles::toValues(
            AuthorizedMembershipRoles::forUser($actor, $organization),
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'settings_json.locale' => ['required', 'string', 'max:8'],
            'settings_json.currency' => ['required', 'string', 'max:8'],
            'settings_json.timezone' => ['nullable', 'string', 'max:120', 'timezone:all'],
            'memberships' => ['nullable', 'array'],
            'memberships.create' => ['nullable', 'array'],
            'memberships.create.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'memberships.create.*.name' => ['nullable', 'string', 'max:120'],
            'memberships.create.*.affiliation' => ['nullable', 'string', 'max:160'],
            'memberships.create.*.role' => ['required_with:memberships.create.*.email', 'string', Rule::in($allowedRoleValues)],
            'memberships.update' => ['nullable', 'array'],
            'memberships.update.*.membership_id' => ['required', 'string', Rule::exists('memberships', 'id')->where('organization_id', $organization->id)],
            'memberships.update.*.role' => ['sometimes', 'string', Rule::in($allowedRoleValues)],
            'memberships.update.*.affiliation' => ['nullable', 'string', 'max:160'],
            'memberships.delete' => ['nullable', 'array'],
            'memberships.delete.*' => ['string', Rule::exists('memberships', 'id')->where('organization_id', $organization->id)],
            'memberships.pending_update' => ['nullable', 'array'],
            'memberships.pending_update.*.pending_id' => ['required', 'string', Rule::exists('pending_memberships', 'id')->where('organization_id', $organization->id)],
            'memberships.pending_update.*.role' => ['required', 'string', Rule::in($allowedRoleValues)],
            'memberships.pending_update.*.name' => ['nullable', 'string', 'max:120'],
            'memberships.pending_update.*.affiliation' => ['nullable', 'string', 'max:160'],
            'memberships.pending_delete' => ['nullable', 'array'],
            'memberships.pending_delete.*' => ['string', Rule::exists('pending_memberships', 'id')->where('organization_id', $organization->id)],
        ], [
            'memberships.create.*.role.in' => 'You are not allowed to assign this role.',
            'memberships.update.*.role.in' => 'You are not allowed to assign this role.',
            'memberships.pending_update.*.role.in' => 'You are not allowed to assign this role.',
        ]);

        $membershipsPayload = $data['memberships'] ?? [];
        $createPayload = $this->normalizeNewMemberPayload($membershipsPayload['create'] ?? []);
        $memberDeletes = array_values($membershipsPayload['delete'] ?? []);
        $pendingDeletes = array_values($membershipsPayload['pending_delete'] ?? []);

        $this->guardDuplicateMembers($organization, $createPayload, $memberDeletes, $pendingDeletes);

        DB::transaction(function () use ($organization, $data, $membershipsPayload, $createPayload, $actor, $memberDeletes, $pendingDeletes) {
            $organization->update([
                'name' => $data['name'],
                'settings_json' => $data['settings_json'],
            ]);

            $this->applyMemberUpdates($organization, $membershipsPayload['update'] ?? [], $actor);
            $this->applyMemberDeletions($organization, $memberDeletes, $actor);
            $this->applyPendingUpdates($organization, $membershipsPayload['pending_update'] ?? []);
            $this->applyPendingDeletions($organization, $pendingDeletes);
            $this->createNewMemberships($organization, $createPayload, $actor);
        });

        return back()->with('success', 'Saved.');
    }

    /**
     * @param  array<int, array{membership_id: string, role: string, affiliation?: string|null}>  $updates
     */
    private function applyMemberUpdates(Organization $organization, array $updates, User $actor): void
    {
        if (empty($updates)) {
            return;
        }

        $memberships = $organization->memberships()
            ->whereIn('id', collect($updates)->pluck('membership_id')->all())
            ->get()
            ->keyBy(fn (Membership $membership) => (string) $membership->id);

        foreach ($updates as $index => $update) {
            $membership = $memberships[(string) $update['membership_id']] ?? null;

            if (! $membership) {
                throw ValidationException::withMessages([
                    "memberships.update.{$index}.membership_id" => 'The selected member is invalid.',
                ]);
            }

            if (array_key_exists('role', $update)) {
                $currentRole = $membership->role instanceof MembershipRole
                    ? $membership->role
                    : MembershipRole::from($membership->role);
                $newRole = MembershipRole::from($update['role']);

                $this->roleManager->syncRole($membership, $newRole, $actor);

                $this->revokeActiveStaffOperatorAssignmentsAction->revokeForMembershipChange(
                    $organization,
                    $membership,
                    $actor,
                    $currentRole,
                    $newRole,
                );
            }

            if (array_key_exists('affiliation', $update)) {
                $affiliation = is_string($update['affiliation']) && trim($update['affiliation']) !== ''
                    ? trim($update['affiliation'])
                    : null;

                if ($membership->affiliation !== $affiliation) {
                    $membership->update(['affiliation' => $affiliation]);
                }
            }
        }
    }

    private function applyMemberDeletions(Organization $organization, array $ids, User $actor): void
    {
        if (empty($ids)) {
            return;
        }

        $memberships = $organization->memberships()
            ->with('user:id')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Membership $membership) => (string) $membership->id);

        $remainingOwnersAfterRemovals = $organization->memberships()
            ->where('role', MembershipRole::OWNER->value)
            ->whereNotIn('id', $ids)
            ->count();

        foreach ($ids as $index => $id) {
            $membership = $memberships[(string) $id] ?? null;

            if (! $membership) {
                continue;
            }

            if ($membership->role === MembershipRole::OWNER && $remainingOwnersAfterRemovals === 0) {
                throw ValidationException::withMessages([
                    "memberships.delete.{$index}" => 'The organization requires at least one owner.',
                ]);
            }

            if ($membership->user_id === $actor->id && $remainingOwnersAfterRemovals === 0) {
                throw ValidationException::withMessages([
                    "memberships.delete.{$index}" => 'The organization requires at least one owner.',
                ]);
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
        }
    }

    /**
     * @param  array<int, array{pending_id: string, role: string, name?: string|null, affiliation?: string|null}>  $updates
     */
    private function applyPendingUpdates(Organization $organization, array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $pendingMemberships = $organization->pendingMemberships()
            ->whereIn('id', collect($updates)->pluck('pending_id')->all())
            ->get()
            ->keyBy(fn (PendingMembership $pending) => (string) $pending->id);

        foreach ($updates as $index => $update) {
            $pending = $pendingMemberships[(string) $update['pending_id']] ?? null;

            if (! $pending) {
                throw ValidationException::withMessages([
                    "memberships.pending_update.{$index}.pending_id" => 'The selected pending invite is invalid.',
                ]);
            }

            $name = array_key_exists('name', $update)
                ? (is_string($update['name']) && trim($update['name']) !== '' ? trim($update['name']) : null)
                : $pending->name;
            $affiliation = array_key_exists('affiliation', $update)
                ? (is_string($update['affiliation']) && trim($update['affiliation']) !== '' ? trim($update['affiliation']) : null)
                : $pending->affiliation;

            $pending->update([
                'role' => MembershipRole::from($update['role'])->value,
                'name' => $name,
                'affiliation' => $affiliation,
            ]);
        }
    }

    private function applyPendingDeletions(Organization $organization, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $organization->pendingMemberships()
            ->whereIn('id', $ids)
            ->delete();
    }
}
