<?php

namespace Database\Factories;

use App\Models\CanonicalMetric;
use App\Models\MetricTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricTemplate>
 */
class MetricTemplateFactory extends Factory
{
    protected $model = MetricTemplate::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug();
        $name = $this->faker->sentence(3);

        return [
            'canonical_metric_id' => CanonicalMetric::factory()->state([
                'key' => $key,
                'name' => $name,
            ]),
            'key' => $key,
            'name' => $name,
            'description' => $this->faker->sentence(8),
            'form_schema_json' => [
                'fields' => [
                    [
                        'name' => 'value',
                        'type' => 'number',
                        'label' => 'Metric Value',
                        'required' => true,
                        'classification' => [
                            'role' => 'definition',
                        ],
                    ],
                ],
            ],
        ];
    }
}
