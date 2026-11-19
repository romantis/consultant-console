<?php

use App\Models\Discrepancy;
use App\Support\Metrics\AlignmentSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can be instantiated with a null description', function () {
    $signal = new AlignmentSignal(
        id: '123',
        severity: 'medium',
        description: null,
        status: 'open'
    );

    expect($signal->description)->toBeNull();
    expect($signal->toArray())->toBe([
        'id' => '123',
        'severity' => 'medium',
        'description' => null,
        'status' => 'open',
    ]);
});

it('maps a discrepancy with a null description correctly', function () {
    $discrepancy = Discrepancy::factory()->create([
        'severity' => 'low',
        'description' => null,
        'status' => 'resolved',
    ]);

    $signal = AlignmentSignal::fromDiscrepancy($discrepancy);

    expect($signal->id)->toBe((string) $discrepancy->id)
        ->and($signal->severity)->toBe('low')
        ->and($signal->description)->toBeNull()
        ->and($signal->status)->toBe('resolved');

    expect($signal->toArray())->toBe([
        'id' => (string) $discrepancy->id,
        'severity' => 'low',
        'description' => null,
        'status' => 'resolved',
    ]);
});
