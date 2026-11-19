<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffOperatorAssignment>
 */
class StaffOperatorAssignmentFactory extends Factory
{
    protected $model = StaffOperatorAssignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory()->state(['is_internal' => false]),
            'granted_by_user_id' => User::factory(),
            'revoked_by_user_id' => null,
            'granted_at' => now(),
            'revoked_at' => null,
            'reason' => null,
            'notes' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_by_user_id' => User::factory(),
            'revoked_at' => now(),
        ]);
    }
}
