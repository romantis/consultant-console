<?php

namespace App\Services\Engagements;

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Support\Collection;

class StakeholderInvitationService
{
    /**
     * @param  Collection<int, array{email: string, name?: string|null, role?: string|null, department?: string|null, affiliation?: string|null}>  $stakeholders
     */
    public function syncForEngagement(Engagement $engagement, Collection $stakeholders, User $actor): Collection
    {
        $organization = $engagement->organization;

        if (! $organization instanceof Organization || $stakeholders->isEmpty()) {
            return collect();
        }

        $normalized = $stakeholders
            ->filter(fn (array $stakeholder) => ! empty($stakeholder['email']))
            ->map(fn (array $stakeholder) => [
                'email' => mb_strtolower($stakeholder['email']),
                'name' => isset($stakeholder['name']) && $stakeholder['name'] !== ''
                    ? trim((string) $stakeholder['name'])
                    : null,
                'role' => $stakeholder['role'] ?? null,
                'affiliation' => $this->normalizeAffiliation($stakeholder),
            ])
            ->unique('email');

        if ($normalized->isEmpty()) {
            return collect();
        }

        $participants = collect();

        $existingMemberEmails = $organization->memberships()
            ->with('user:id,email')
            ->get()
            ->map(fn ($membership) => $membership->user ? mb_strtolower($membership->user->email) : null)
            ->filter()
            ->all();

        $existingPending = $organization->pendingMemberships()
            ->whereIn('email', $normalized->pluck('email')->all())
            ->get()
            ->keyBy(fn (PendingMembership $pending) => mb_strtolower($pending->email));

        foreach ($normalized as $entry) {
            $email = $entry['email'];

            if (in_array($email, $existingMemberEmails, true)) {
                continue;
            }

            /** @var PendingMembership|null $pending */
            $pending = $existingPending->get($email);

            if ($pending) {
                $updates = [
                    'affiliation' => $entry['affiliation'] ?? $pending->affiliation,
                ];

                // Preserve the first engagement that created the invite to avoid hijacking existing pending memberships.
                if ($pending->engagement_id === null) {
                    $updates['engagement_id'] = $engagement->id;
                }

                if (! empty($entry['role'])) {
                    $updates['role'] = $this->mapStakeholderRoleToMembership($entry['role']);
                }

                $pending->forceFill($updates)->save();

                $stakeholderRole = $this->normalizeStakeholderRole($entry['role'] ?? null);

                $participant = EngagementParticipant::updateOrCreate(
                    [
                        'engagement_id' => $engagement->id,
                        'participant_type' => EngagementParticipant::TYPE_PENDING,
                        'pending_membership_id' => $pending->id,
                    ],
                    [
                        'membership_id' => null,
                        'membership_role' => $pending->role?->value,
                        'stakeholder_role' => $stakeholderRole->value,
                        'active' => true,
                    ],
                );

                $participant->setRelation('pendingMembership', $pending);

                $participants->put('invite:'.$email, $participant);

                continue;
            }

            $stakeholderRole = $this->normalizeStakeholderRole($entry['role'] ?? null);

            $pendingMembership = $organization->pendingMemberships()->create([
                'email' => $email,
                'name' => $entry['name'],
                'role' => $this->mapStakeholderRoleToMembership($entry['role'] ?? null)->value,
                'invited_by' => $actor->id,
                'engagement_id' => $engagement->id,
                'affiliation' => $entry['affiliation'] ?? null,
            ]);

            $participant = EngagementParticipant::create([
                'engagement_id' => $engagement->id,
                'participant_type' => EngagementParticipant::TYPE_PENDING,
                'pending_membership_id' => $pendingMembership->id,
                'membership_id' => null,
                'membership_role' => $pendingMembership->role?->value,
                'stakeholder_role' => $stakeholderRole->value,
                'active' => true,
            ]);

            $participant->setRelation('pendingMembership', $pendingMembership);

            $participants->put('invite:'.$email, $participant);
        }

        return $participants;
    }

    private function mapStakeholderRoleToMembership(?string $role): MembershipRole
    {
        $stakeholderRole = $this->normalizeStakeholderRole($role);

        return match ($stakeholderRole) {
            StakeholderRole::ADMIN => MembershipRole::ADMIN,
            StakeholderRole::VIEWER => MembershipRole::VIEWER,
            default => MembershipRole::MEMBER,
        };
    }

    private function normalizeStakeholderRole(?string $role): StakeholderRole
    {
        $normalized = mb_strtolower(trim((string) $role));

        if ($normalized === '') {
            return StakeholderRole::MEMBER;
        }

        return match ($normalized) {
            'admin', 'approver' => StakeholderRole::ADMIN,
            'viewer', 'observer' => StakeholderRole::VIEWER,
            default => StakeholderRole::MEMBER,
        };
    }

    /**
     * Accept both legacy `department` and newer `affiliation` fields.
     *
     * @param  array{affiliation?: string|null, department?: string|null}  $stakeholder
     */
    private function normalizeAffiliation(array $stakeholder): ?string
    {
        $affiliation = is_string($stakeholder['affiliation'] ?? null)
            ? trim($stakeholder['affiliation'])
            : '';

        if ($affiliation !== '') {
            return $affiliation;
        }

        $department = is_string($stakeholder['department'] ?? null)
            ? trim($stakeholder['department'])
            : '';

        return $department !== '' ? $department : null;
    }
}
