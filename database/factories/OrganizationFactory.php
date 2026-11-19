<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(4)),
            'settings_json' => [
                'locale' => 'en',
                'currency' => 'USD',
                'timezone' => 'UTC',
            ],
        ];
    }
}
