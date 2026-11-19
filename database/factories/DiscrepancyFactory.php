<?php

namespace Database\Factories;

use App\Models\Discrepancy;
use App\Models\Metric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discrepancy>
 */
class DiscrepancyFactory extends Factory
{
    protected $model = Discrepancy::class;

    public function definition(): array
    {
        return [
            'metric_id' => Metric::factory(),
            'engagement_id' => fn (array $attributes) => Metric::find($attributes['metric_id'])->engagement_id,
            'field_key' => 'value',
            'rule_type' => 'number',
            'severity' => 'medium',
            'status' => 'Open',
            'description' => $this->faker->sentence(),
            'values_snapshot' => [
                'definitions' => [
                    ['definition_id' => (string) str()->ulid(), 'value' => 100, 'normalized' => 100],
                    ['definition_id' => (string) str()->ulid(), 'value' => 120, 'normalized' => 120],
                ],
            ],
            'detected_at' => now(),
            'resolved_at' => null,
        ];
    }
}
