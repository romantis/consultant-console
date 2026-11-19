<?php

namespace Database\Factories;

use App\Models\CanonicalMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CanonicalMetric>
 */
class CanonicalMetricFactory extends Factory
{
    protected $model = CanonicalMetric::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);
        $key = Str::slug($name);

        if ($key === '') {
            $key = 'metric-'.Str::lower(Str::random(6));
        }

        return [
            'key' => $key,
            'name' => $name,
            'description' => $this->faker->sentence(8),
            'is_active' => true,
        ];
    }
}
