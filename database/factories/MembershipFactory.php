<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => MembershipRole::MEMBER,
            'affiliation' => $this->faker->optional()->words(2, true),
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
