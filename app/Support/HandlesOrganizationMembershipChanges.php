<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

trait HandlesOrganizationMembershipChanges
{
    /**
     * @param  array<int, array{email?: string|null, name?: string|null, role?: string|null, affiliation?: string|null}>|null  $entries
     */
    private function normalizeNewMemberPayload(?array $entries): Collection
    {
        return collect($entries ?? [])
            ->map(function ($entry) {
                $email = is_string($entry['email'] ?? null) ? trim($entry['email']) : '';
                $name = is_string($entry['name'] ?? null) ? trim($entry['name']) : '';
                $role = is_string($entry['role'] ?? null)
                    ? $entry['role']
                    : MembershipRole::MEMBER->value;
                $affiliation = is_string($entry['affiliation'] ?? null) ? trim($entry['affiliation']) : '';

                return [
                    'email' => $email,
                    'name' => $name,
                    'role' => $role,
                    'affiliation' => $affiliation,
                ];
            })
            ->filter(fn (array $entry) => $entry['email'] !== '')
            ->map(function (array $entry) {
                $email = mb_strtolower($entry['email']);
                $name = $entry['name'] !== '' ? $entry['name'] : null;
                $affiliation = $entry['affiliation'] !== '' ? $entry['affiliation'] : null;

                return [
                    'email' => $email,
                    'name' => $name,
                    'role' => $entry['role'],
                    'affiliation' => $affiliation,
                ];
            })
            ->unique('email')
            ->values();
    }

    private function guardDuplicateMembers(
        Organization $organization,
        Collection $normalized,
        array $ignoredMembershipIds = [],
        array $ignoredPendingIds = [],
        string $errorKeyBase = 'memberships.create'
    ): void {
        if ($normalized->isEmpty()) {
            return;
        }

        $existingMemberEmails = $organization->memberships()
            ->when(! empty($ignoredMembershipIds), fn ($query) => $query->whereNotIn('id', $ignoredMembershipIds))
            ->with('user:id,email')
            ->get()
            ->map(fn ($membership) => $membership->user ? mb_strtolower($membership->user->email) : null)
            ->filter()
            ->all();

        $existingPendingEmails = $organization->pendingMemberships()
            ->when(! empty($ignoredPendingIds), fn ($query) => $query->whereNotIn('id', $ignoredPendingIds))
            ->get()
            ->map(fn ($pending) => mb_strtolower($pending->email))
            ->all();

        $errors = [];

        foreach ($normalized as $index => $entry) {
            $email = $entry['email'];

            if (in_array($email, $existingMemberEmails, true)) {
                $errors["{$errorKeyBase}.{$index}.email"] = 'A member with this email already exists.';

                continue;
            }

            if (in_array($email, $existingPendingEmails, true)) {
                $errors["{$errorKeyBase}.{$index}.email"] = 'An invite has already been created for this email address.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function createNewMemberships(Organization $organization, Collection $normalized, User $actor): void
    {
        foreach ($normalized as $index => $entry) {
            $user = User::whereRaw('LOWER(email) = ?', [$entry['email']])->first();

            if ($user && ! $organization->is_internal && Staff::isInternalStaff($user)) {
                throw ValidationException::withMessages([
                    "memberships.create.{$index}.email" => 'Internal staff must be assigned through staff operator assignments.',
                ]);
            }

            if ($user && $user->id !== $actor->id) {
                $membership = $organization->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $entry['role'],
                    'affiliation' => $entry['affiliation'] ?? null,
                ]);

                $memberRole = MembershipRole::from($entry['role']);

                $otherOwnerExists = $memberRole === MembershipRole::OWNER
                    && $organization->memberships()
                        ->where('role', MembershipRole::OWNER->value)
                        ->whereKeyNot($membership->id)
                        ->exists();

                if ($memberRole !== MembershipRole::OWNER || $otherOwnerExists) {
                    $this->roleManager->syncRole(
                        $membership,
                        $memberRole,
                        $actor,
                        force: $memberRole === MembershipRole::OWNER,
                    );
                }

                $this->membershipAuditLogger->membershipCreated($membership, $actor);

                continue;
            }

            if (! $user) {
                $organization->pendingMemberships()->create([
                    'email' => $entry['email'],
                    'name' => $entry['name'],
                    'role' => $entry['role'],
                    'invited_by' => $actor->id,
                    'affiliation' => $entry['affiliation'] ?? null,
                ]);
            }
        }
    }
}
