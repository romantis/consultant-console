<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'user_id' => User::factory(),
            'answers_json' => ['notes' => $this->faker->sentence()],
            'submitted_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['submitted_at' => now()]);
    }
}
