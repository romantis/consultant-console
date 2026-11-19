<?php

use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces unique metric keys per engagement', function () {
    $engagement = Engagement::factory()->create();

    Metric::factory()->for($engagement)->create(['key' => 'mrr']);

    expect(fn () => Metric::factory()->for($engagement)->create(['key' => 'mrr']))
        ->toThrow(QueryException::class);
});

it('enforces a single final definition per metric', function () {
    $metric = Metric::factory()->create();
    $user = User::factory()->create();

    FinalDefinition::create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'status' => 'Draft',
        'definition_json' => ['foo' => ['label' => 'Foo', 'value' => null]],
    ]);

    expect(fn () => FinalDefinition::create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'status' => 'Draft',
        'definition_json' => ['bar' => ['label' => 'Bar', 'value' => null]],
    ]))->toThrow(QueryException::class);
});
