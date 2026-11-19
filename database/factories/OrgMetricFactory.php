<?php

namespace Database\Factories;

use App\Models\CanonicalMetric;
use App\Models\Organization;
use App\Models\OrgMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrgMetric>
 */
class OrgMetricFactory extends Factory
{
    protected $model = OrgMetric::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);
        $key = Str::slug($name);

        if ($key === '') {
            $key = 'metric-'.Str::lower(Str::random(6));
        }

        return [
            'organization_id' => Organization::factory(),
            'canonical_metric_id' => CanonicalMetric::factory()->state([
                'key' => $key,
                'name' => $name,
            ]),
            'key' => $key,
            'slug' => Str::slug($name),
            'name' => $name,
            'alias' => null,
        ];
    }
}
