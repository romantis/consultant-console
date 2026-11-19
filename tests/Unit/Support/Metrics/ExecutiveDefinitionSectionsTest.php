<?php

use App\Support\Metrics\ExecutiveDefinitionSections;

it('builds executive sections from owner and role metadata only', function () {
    $definition = [
        'active_trigger_signal' => [
            'label' => 'Active user qualification criteria',
            'value' => 'Complete a key feature action',
        ],
        'active_trigger_notes' => [
            'label' => 'Active trigger rule details',
            'value' => 'Action count must be at least one in the window.',
            'companion_of' => 'active_trigger_signal',
        ],
        'deduplication_policy' => [
            'label' => 'Multi-account deduplication policy',
            'value' => 'Merge accounts for the same person',
        ],
        'active_pain_points' => [
            'label' => 'Active user definition divergence points',
            'value' => 'Marketing still counts paid seats as active.',
        ],
    ];

    $fieldRoles = [
        [
            'key' => 'active_trigger_signal',
            'label' => 'Active user qualification criteria',
            'role' => 'definition',
            'exclude_from_consensus' => false,
            'executive_section' => 'official_definition',
        ],
        [
            'key' => 'active_trigger_notes',
            'label' => 'Active trigger rule details',
            'role' => 'context',
            'exclude_from_consensus' => false,
            'companion_of' => 'active_trigger_signal',
            'executive_section' => 'official_definition',
        ],
        [
            'key' => 'deduplication_policy',
            'label' => 'Multi-account deduplication policy',
            'role' => 'governance',
            'exclude_from_consensus' => false,
            'executive_section' => 'measurement_logic_rules',
        ],
        [
            'key' => 'active_pain_points',
            'label' => 'Active user definition divergence points',
            'role' => 'diagnostic',
            'exclude_from_consensus' => false,
            'executive_section' => 'controls_caveats',
        ],
    ];

    $sections = ExecutiveDefinitionSections::build(
        $definition,
        $fieldRoles,
    );

    expect(array_column($sections, 'key'))->toEqual([
        'official_definition',
        'measurement_logic_rules',
        'controls_caveats',
    ]);

    $sectionsByKey = collect($sections)->keyBy('key');

    $definitionItems = collect($sectionsByKey['official_definition']['items'])->keyBy('key');
    expect($definitionItems)->toHaveKeys(['active_trigger_signal'])
        ->and($definitionItems['active_trigger_signal']['companions'])->toHaveCount(1)
        ->and($definitionItems['active_trigger_signal']['companions'][0]['key'])->toBe('active_trigger_notes');

    $logicItems = collect($sectionsByKey['measurement_logic_rules']['items'])->keyBy('key');
    expect($logicItems)->toHaveKeys(['deduplication_policy']);

    $caveatsItems = collect($sectionsByKey['controls_caveats']['items'])->keyBy('key');
    expect($caveatsItems)->toHaveKey('active_pain_points');
});

it('falls back to role and key heuristics when executive sections are invalid or missing', function () {
    $definition = [
        'summary' => [
            'label' => 'Summary',
            'value' => 'Single source of truth for CAC.',
        ],
        'matching_window' => [
            'label' => 'Cost-to-result matching window',
            'value' => '30-day lookback',
        ],
        'divergence_notes' => [
            'label' => 'Known divergence notes',
            'value' => 'Finance excludes partner spend while marketing includes it.',
        ],
    ];

    $fieldRoles = [
        [
            'key' => 'matching_window',
            'label' => 'Cost-to-result matching window',
            'role' => 'governance',
            'exclude_from_consensus' => false,
            'executive_section' => 'invalid_group',
        ],
    ];

    $sections = ExecutiveDefinitionSections::build($definition, $fieldRoles, null);
    $sectionsByKey = collect($sections)->keyBy('key');

    expect(array_column($sections, 'key'))->toEqual([
        'official_definition',
        'measurement_logic_rules',
        'controls_caveats',
    ]);

    expect(collect($sectionsByKey['official_definition']['items'])->pluck('key')->all())
        ->toContain('summary')
        ->and(collect($sectionsByKey['measurement_logic_rules']['items'])->pluck('key')->all())
        ->toContain('matching_window')
        ->and(collect($sectionsByKey['controls_caveats']['items'])->pluck('key')->all())
        ->toContain('divergence_notes');
});
