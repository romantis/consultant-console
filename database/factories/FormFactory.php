<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\Form;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'engagement_id' => Engagement::factory(),
            'metric_id' => function (array $attributes): string {
                $engagementId = $attributes['engagement_id'] ?? null;
                $organizationId = $attributes['organization_id'] ?? null;

                $engagement = is_string($engagementId) ? Engagement::query()->find($engagementId) : null;

                if ($engagement instanceof Engagement) {
                    return (string) Metric::factory()->create([
                        'organization_id' => $engagement->organization_id,
                        'engagement_id' => $engagement->id,
                    ])->id;
                }

                $organizationId = is_string($organizationId) ? $organizationId : null;

                return (string) Metric::factory()->create([
                    'organization_id' => $organizationId,
                    'engagement_id' => $engagementId,
                ])->id;
            },
            'template_key' => 'mrr',
            'title' => 'MRR — '.$this->faker->monthName().' '.$this->faker->year(),
            'schema_json' => [
                'fields' => [
                    ['name' => 'total_mrr', 'type' => 'number', 'label' => 'Total MRR', 'required' => true],
                    ['name' => 'notes', 'type' => 'textarea', 'label' => 'Notes'],
                ],
            ],
            'status' => 'Open',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Form $form) {
            if ($form->metric) {
                if ($form->engagement_id !== $form->metric->engagement_id) {
                    $form->engagement()->associate($form->metric->engagement);
                }

                if ($form->organization_id !== $form->metric->organization_id) {
                    $form->organization()->associate($form->metric->organization);
                }
            } elseif ($form->engagement) {
                $form->organization()->associate($form->engagement->organization);
            } elseif (! $form->organization_id) {
                $form->organization()->associate(Organization::factory()->create());
            }
        })->afterCreating(function (Form $form) {
            if ($form->metric) {
                if ($form->engagement_id !== $form->metric->engagement_id) {
                    $form->engagement()->associate($form->metric->engagement);
                }

                if ($form->organization_id !== $form->metric->organization_id) {
                    $form->organization()->associate($form->metric->organization);
                }

                $form->save();
            } elseif ($form->engagement && $form->organization_id !== $form->engagement->organization_id) {
                $form->organization()->associate($form->engagement->organization);
                $form->save();
            } elseif (! $form->organization_id) {
                $organization = Organization::factory()->create();
                $form->organization()->associate($organization);
                $form->save();
            }
        });
    }
}
