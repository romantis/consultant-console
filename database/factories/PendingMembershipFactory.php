<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PendingMembership>
 */
class PendingMembershipFactory extends Factory
{
    protected $model = PendingMembership::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'engagement_id' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'role' => MembershipRole::MEMBER,
            'invited_by' => User::factory(),
            'affiliation' => Str::title($this->faker->words(2, true)),
        ];
    }

    public function role(MembershipRole $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function owner(): static
    {
        return $this->role(MembershipRole::OWNER);
    }

    public function admin(): static
    {
        return $this->role(MembershipRole::ADMIN);
    }

    public function member(): static
    {
        return $this->role(MembershipRole::MEMBER);
    }

    public function viewer(): static
    {
        return $this->role(MembershipRole::VIEWER);
    }
}
