<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricDefinition>
 */
class MetricDefinitionFactory extends Factory
{
    protected $model = MetricDefinition::class;

    public function definition(): array
    {
        return [
            'metric_id' => Metric::factory(),
            'form_submission_id' => null,
            'user_id' => User::factory(),
            'source_type' => 'form_submission',
            'status' => 'Submitted',
            'definition_json' => ['value' => $this->faker->randomFloat(2, 10, 1000)],
            'normalized_json' => [
                'fields' => [
                    'value' => [
                        'type' => 'number',
                        'raw' => 123,
                        'normalized' => 123.0,
                        'label' => 'Metric Value',
                    ],
                ],
                'meta' => [],
            ],
            'submitted_at' => now(),
        ];
    }
}
