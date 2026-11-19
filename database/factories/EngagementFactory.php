<?php

namespace Database\Factories;

use App\Enums\EngagementStatus;
use App\Models\Engagement;
use App\Models\Organization;
use App\Support\EngagementChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => $this->faker->unique()->sentence(3),
            'status' => EngagementStatus::DRAFT->value,
            'starts_on' => $this->faker->optional()->date(),
            'ends_on' => null,
            'repository_driver' => 'notion',
            'repository_reference' => $this->faker->optional()->url(),
            'repository_notes' => $this->faker->optional()->sentence(),
            'stakeholders_json' => [
                [
                    'name' => $this->faker->name(),
                    'email' => $this->faker->safeEmail(),
                    'role' => 'member',
                    'department' => Str::title($this->faker->words(2, true)),
                ],
            ],
            'onboarding_checklist_json' => EngagementChecklist::merge([], [
                'metrics_selected' => true,
                'stakeholders_invited' => true,
                'repository_connected' => true,
            ]),
            'review_cadence' => $this->faker->randomElement(['Monthly', 'Quarterly', 'Semiannual']),
            'next_review_on' => $this->faker->optional()->dateTimeBetween('now', '+2 months')?->format('Y-m-d'),
        ];
    }
}
