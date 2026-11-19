<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricParticipantFactory extends Factory
{
    protected $model = MetricParticipant::class;

    public function definition(): array
    {
        return [
            'metric_id' => Metric::factory(),
            'engagement_participant_id' => null,
            'user_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'responsibility' => $this->faker->randomElement(['contributor', 'approver', 'observer']),
            'role_tags_json' => [],
        ];
    }

    public function contributor(): self
    {
        return $this->state(fn () => ['responsibility' => 'contributor']);
    }

    public function approver(): self
    {
        return $this->state(fn () => ['responsibility' => 'approver']);
    }

    public function observer(): self
    {
        return $this->state(fn () => ['responsibility' => 'observer']);
    }
}
