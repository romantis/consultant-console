<?php

namespace Database\Factories;

use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Metric>
 */
class MetricFactory extends Factory
{
    protected $model = Metric::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->sentence(3);
        $key = Str::slug($name);

        if (! $key) {
            $key = 'metric-'.Str::lower(Str::random(6));
        }

        return [
            'engagement_id' => Engagement::factory(),
            'organization_id' => null,
            'metric_template_id' => MetricTemplate::factory(),
            'key' => $key,
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->word()),
            'status' => MetricStatus::AWAITING_FORMS,
            'recompute_status' => 'idle',
            'recompute_queued_at' => null,
            'recompute_finished_at' => null,
            'definitions_changed_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Metric $metric) {
            if ($metric->engagement) {
                $metric->organization()->associate($metric->engagement->organization);
            } elseif (! $metric->organization_id) {
                $metric->organization()->associate(Organization::factory()->create());
            }
        })->afterCreating(function (Metric $metric) {
            if ($metric->engagement && $metric->organization_id !== $metric->engagement->organization_id) {
                $metric->organization()->associate($metric->engagement->organization);
                $metric->save();
            } elseif (! $metric->organization_id) {
                $organization = Organization::factory()->create();
                $metric->organization()->associate($organization);
                $metric->save();
            }
        });
    }

    public function inReview(): self
    {
        return $this->state(fn () => ['status' => MetricStatus::IN_REVIEW]);
    }
}
