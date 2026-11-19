<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\PendingMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementParticipantFactory extends Factory
{
    protected $model = EngagementParticipant::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
            'membership_id' => Membership::factory()->member(),
            'pending_membership_id' => null,
            'membership_role' => MembershipRole::MEMBER,
            'stakeholder_role' => StakeholderRole::MEMBER,
            'active' => true,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'participant_type' => EngagementParticipant::TYPE_PENDING,
            'membership_id' => null,
            'pending_membership_id' => PendingMembership::factory()->viewer(),
            'stakeholder_role' => StakeholderRole::VIEWER,
            'membership_role' => MembershipRole::VIEWER,
            'active' => true,
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'membership_id' => Membership::factory()->owner(),
            'membership_role' => MembershipRole::OWNER,
            'stakeholder_role' => StakeholderRole::ADMIN,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'membership_id' => Membership::factory()->admin(),
            'membership_role' => MembershipRole::ADMIN,
            'stakeholder_role' => StakeholderRole::ADMIN,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn () => [
            'membership_id' => Membership::factory()->member(),
            'membership_role' => MembershipRole::MEMBER,
            'stakeholder_role' => StakeholderRole::MEMBER,
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn () => [
            'membership_id' => Membership::factory()->viewer(),
            'membership_role' => MembershipRole::VIEWER,
            'stakeholder_role' => StakeholderRole::VIEWER,
        ]);
    }
}
