<?php

use App\Support\Metrics\Drafting\BriefFingerprint;

it('generates a deterministic sha256 fingerprint for a brief', function () {
    $brief = [
        'summary' => [
            'metric_id' => '123',
            'metric_name' => 'Gross Churn',
        ],
        'stakeholder_positions' => [],
    ];

    $hash1 = BriefFingerprint::calculate($brief);
    $hash2 = BriefFingerprint::calculate($brief);

    expect($hash1)->toBe($hash2)
        ->and(strlen($hash1))->toBe(64)
        ->and(preg_match('/^[a-f0-9]{64}$/', $hash1))->toBe(1);
});

it('moves the facilitation fingerprint on readiness gaps but ignores the rest of the snapshot', function () {
    $brief = [
        'summary' => ['metric_id' => '123', 'metric_name' => 'Gross Churn'],
        'material_conflicts' => [],
    ];

    $noGaps = BriefFingerprint::forFacilitationPlan($brief, [
        'required_departments' => ['Finance'],
        'represented_departments' => ['Finance'],
        'gaps' => [],
    ]);
    $withGap = BriefFingerprint::forFacilitationPlan($brief, [
        'required_departments' => ['Finance', 'Sales'],
        'represented_departments' => ['Finance'],
        'gaps' => [['id' => 'readiness:required_department:sales', 'kind' => 'missing_stakeholder_perspective']],
    ]);
    // Same brief and gaps, but a department that is now represented only changes
    // the rest of the snapshot — the plan persists only gaps, so it must not flip.
    $extraRepresented = BriefFingerprint::forFacilitationPlan($brief, [
        'required_departments' => ['Finance'],
        'represented_departments' => ['Finance', 'Marketing'],
        'gaps' => [],
    ]);

    expect($noGaps)->not->toBe($withGap)
        ->and($noGaps)->toBe($extraRepresented)
        ->and($noGaps)->not->toBe(BriefFingerprint::calculate($brief));
});

it('throws a json exception for invalid json structures', function () {
    // Pass a value that causes JSON encoding failure (like resource or infinite float)
    $invalidBrief = [
        'invalid_value' => INF,
    ];

    BriefFingerprint::calculate($invalidBrief);
})->throws(JsonException::class);
