<?php

namespace Database\Factories;

use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgMetricParticipant>
 */
class OrgMetricParticipantFactory extends Factory
{
    protected $model = OrgMetricParticipant::class;

    public function definition(): array
    {
        return [
            'org_metric_id' => OrgMetric::factory(),
            'user_id' => User::factory(),
            'responsibility' => $this->faker->randomElement(['owner', 'approver']),
            'subject_type' => null,
            'subject_id' => null,
        ];
    }
}
