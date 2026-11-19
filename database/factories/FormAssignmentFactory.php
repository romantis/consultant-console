<?php

namespace Database\Factories;

use App\Enums\FormAssignmentStatus;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormAssignmentFactory extends Factory
{
    protected $model = FormAssignment::class;

    public function definition(): array
    {
        return [
            'form_id' => function (array $attributes): string {
                $metricId = $attributes['metric_id'] ?? null;
                $metric = null;

                if (is_string($metricId) && $metricId !== '') {
                    $metric = Metric::query()->find($metricId);
                }

                if (! $metric instanceof Metric) {
                    $metric = Metric::factory()->create();
                }

                $existingFormId = Form::query()
                    ->where('metric_id', $metric->id)
                    ->value('id');

                if (is_string($existingFormId) && $existingFormId !== '') {
                    return $existingFormId;
                }

                return (string) Form::factory()->create([
                    'organization_id' => $metric->organization_id,
                    'engagement_id' => $metric->engagement_id,
                    'metric_id' => $metric->id,
                ])->id;
            },
            'metric_id' => null,
            'metric_participant_id' => null,
            'user_id' => User::factory(),
            'status' => FormAssignmentStatus::OPEN->value,
            'responsibility' => 'Contributor',
            'role_tags_json' => [],
            'due_at' => $this->faker->optional()->dateTimeBetween('+1 day', '+2 weeks'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (FormAssignment $assignment): void {
            if ($assignment->metric_id || ! $assignment->form_id) {
                return;
            }

            $metricId = Form::query()->whereKey($assignment->form_id)->value('metric_id');
            if (! $metricId) {
                return;
            }

            $assignment->forceFill(['metric_id' => $metricId])->save();
        });
    }
}
