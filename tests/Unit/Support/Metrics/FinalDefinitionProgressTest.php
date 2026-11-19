<?php

use App\Models\FinalDefinition;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Support\Metrics\FinalDefinitionProgress;
use App\Support\Metrics\FinalDefinitionRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('builds requirements from field roles and marks missing keys', function () {
    $requirements = FinalDefinitionRequirements::fromPayload(
        [
            ['key' => 'value', 'label' => 'Metric Value', 'required' => true, 'exclude_from_consensus' => false],
            ['key' => 'context', 'label' => 'Context', 'required' => false, 'exclude_from_consensus' => false],
        ],
        [
            ['field' => 'fallback', 'label' => 'Fallback'],
        ],
        [
            'value' => ['value' => ' '],
            'context' => ['value' => 'extra'],
        ],
        true
    );

    expect($requirements['required_fields'])->toBe([
        ['key' => 'value', 'label' => 'Metric Value'],
    ]);
    expect($requirements['missing_keys'])->toBe(['value']);
    expect($requirements['owner_eligible'])->toBeTrue();
});

it('falls back to matrix rows when no field roles are required', function () {
    $requirements = FinalDefinitionRequirements::fromPayload(
        [],
        [
            ['field' => 'velocity', 'label' => 'Velocity'],
        ],
        [],
        false
    );

    expect($requirements['required_fields'])->toBe([
        ['key' => 'velocity', 'label' => 'Velocity'],
    ]);
    expect($requirements['missing_keys'])->toBe(['velocity']);
    expect($requirements['owner_eligible'])->toBeFalse();
});

it('derives progress state from requirements and sign-off', function () {
    $ready = FinalDefinitionProgress::fromRequirements([
        'required_fields' => [
            ['key' => 'value', 'label' => 'Metric Value'],
        ],
        'missing_keys' => [],
        'owner_eligible' => true,
    ], false);

    expect($ready)->toBe([
        'state' => 'ready',
        'missing_count' => 0,
    ]);

    $missing = FinalDefinitionProgress::fromRequirements([
        'required_fields' => [
            ['key' => 'value', 'label' => 'Metric Value'],
        ],
        'missing_keys' => ['value'],
        'owner_eligible' => false,
    ], false);

    expect($missing)->toBe([
        'state' => 'missing',
        'missing_count' => 2,
    ]);

    $signedOff = FinalDefinitionProgress::fromRequirements([
        'required_fields' => [
            ['key' => 'value', 'label' => 'Metric Value'],
        ],
        'missing_keys' => ['value'],
        'owner_eligible' => false,
    ], true);

    expect($signedOff)->toBe([
        'state' => 'signed_off',
    ]);
});

it('builds requirements for a metric using field roles and owner eligibility', function () {
    $metric = Metric::factory()->create();
    $participant = MetricParticipant::factory()->for($metric)->create();

    $finalDefinition = FinalDefinition::factory()
        ->for($metric)
        ->create([
            'definition_json' => [
                'value' => ['label' => 'Metric Value', 'value' => '123'],
            ],
            'owner_participant_id' => $participant->id,
        ]);

    $requirements = FinalDefinitionRequirements::forMetric($metric, $finalDefinition);

    expect($requirements['owner_eligible'])->toBeTrue();
    expect($requirements['missing_keys'])->toBeEmpty();
    expect(collect($requirements['required_fields'])->pluck('key')->all())->toBe(['value']);
});
