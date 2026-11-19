<?php

namespace Database\Factories;

use App\Enums\EngagementWorkshopSessionStatus;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EngagementWorkshopSession>
 */
class EngagementWorkshopSessionFactory extends Factory
{
    protected $model = EngagementWorkshopSession::class;

    public function definition(): array
    {
        $organization = Organization::factory();

        return [
            'organization_id' => $organization,
            'engagement_id' => Engagement::factory()->for($organization),
            'facilitator_membership_id' => Membership::factory()->admin()->for($organization),
            'starts_at' => now()->addDay()->startOfHour(),
            'ends_at' => now()->addDay()->startOfHour()->addHour(),
            'timezone' => 'UTC',
            'status' => EngagementWorkshopSessionStatus::SCHEDULED,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EngagementWorkshopSession $session) {
            if ($session->engagement && ! $session->organization_id) {
                $session->organization_id = $session->engagement->organization_id;
            }
        })->afterCreating(function (EngagementWorkshopSession $session) {
            if (
                $session->engagement
                && $session->organization_id !== $session->engagement->organization_id
            ) {
                $session->organization_id = $session->engagement->organization_id;
                $session->save();
            }

            if (
                $session->facilitatorMembership
                && $session->facilitatorMembership->organization_id !== $session->organization_id
            ) {
                $facilitator = Membership::factory()->admin()->create([
                    'organization_id' => $session->organization_id,
                ]);

                $session->facilitator_membership_id = $facilitator->getKey();
                $session->save();
            }
        });
    }
}
