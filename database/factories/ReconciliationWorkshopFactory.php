<?php

namespace Database\Factories;

use App\Models\EngagementWorkshopSession;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\ReconciliationWorkshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationWorkshop>
 */
class ReconciliationWorkshopFactory extends Factory
{
    protected $model = ReconciliationWorkshop::class;

    public function definition(): array
    {
        $scheduledAt = $this->faker->dateTimeBetween('-2 days', '+5 days');

        return [
            'metric_id' => Metric::factory(),
            'engagement_id' => null,
            'session_id' => null,
            'facilitator_id' => Membership::factory()->admin(),
            'scheduled_at' => $scheduledAt,
            'participants' => [
                ['name' => $this->faker->name(), 'role' => 'Consultant'],
                ['name' => $this->faker->name(), 'role' => 'Department Lead'],
            ],
            'decision_snapshot' => [
                'consensus' => 'Align on revenue recognition window',
                'follow_ups' => ['Finance to provide revised numbers'],
            ],
            'notes' => $this->faker->optional()->paragraph(),
            'transcript_url' => $this->faker->optional()->url(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ReconciliationWorkshop $workshop) {
            if (! $workshop->engagement_id && $workshop->metric?->engagement_id) {
                $workshop->engagement_id = $workshop->metric->engagement_id;
            }

            if (! $workshop->session_id && $workshop->engagement_id) {
                $session = EngagementWorkshopSession::factory()->create([
                    'organization_id' => $workshop->metric?->organization_id,
                    'engagement_id' => $workshop->engagement_id,
                ]);

                $workshop->session_id = $session->getKey();
            }

            if ($workshop->isDirty(['engagement_id', 'session_id'])) {
                $workshop->save();
            }
        });
    }
}
