<?php

use App\Actions\Metrics\DeriveMetricFacilitationPlan;
use Opis\JsonSchema\Validator;

/**
 * Dense CAC/CPL acceptance evaluation for the Consultant Facilitation Brief
 * (Task 3C-06). The reference document is a semantic exemplar, not golden prose:
 * these gates score schema validity and the acceptance semantics, not wording.
 */
function facilitationFixture(): array
{
    $path = base_path('specs/034-optymize-prompt/evaluation/consultant-facilitation-cac-cpl-v1.json');

    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

function facilitationAllText(array $node): array
{
    $texts = [];

    array_walk_recursive($node, function ($value, $key) use (&$texts): void {
        if ($key === 'text' && is_string($value)) {
            $texts[] = $value;
        }
    });

    return $texts;
}

it('validates the reference document against the schema 2.0 contract', function () {
    $fixture = facilitationFixture();
    $schemaPath = base_path('specs/034-optymize-prompt/contracts/consultant-facilitation-brief.schema.json');

    $schema = json_decode(file_get_contents($schemaPath), flags: JSON_THROW_ON_ERROR);
    $document = json_decode(json_encode($fixture['reference_document'], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

    $result = (new Validator)->validate($document, $schema);

    expect($result->isValid())->toBeTrue();
});

it('covers every canonical agenda topic without silent omissions', function () {
    $fixture = facilitationFixture();
    $coverage = $fixture['reference_document']['coverage'];
    $expected = $fixture['expectations']['agenda_coverage'];

    expect($coverage['agenda_topic_ids'])->toHaveCount($expected['topic_count']);
    expect($coverage['covered_topic_ids'])
        ->toEqualCanonicalizing($expected['required_covered_topic_ids']);
    expect($coverage['omitted_topics'])->toBe([]);

    // No agenda topic is dropped without an explicit omission entry.
    $accountedFor = array_merge(
        $coverage['covered_topic_ids'],
        array_column($coverage['omitted_topics'], 'agenda_topic_id'),
    );
    expect(array_diff($coverage['agenda_topic_ids'], $accountedFor))->toBe([]);
});

it('locks every required decision dimension across the dense conflicts', function () {
    $fixture = facilitationFixture();
    $decisions = $fixture['reference_document']['decisions_to_lock'];

    $fieldKeys = collect($decisions)->pluck('field_keys')->flatten()->unique()->values()->all();

    foreach ([
        'costs_included',
        'overhead_handling',
        'lead_definition',
        'customer_definition',
        'cost_timing',
        'deduplication_method',
        'cac_pain_points',
        'cac_example',
    ] as $requiredFieldKey) {
        expect($fieldKeys)->toContain($requiredFieldKey);
    }

    expect($fixture['expectations']['required_decision_dimensions'])->toHaveCount(6);
});

it('keeps readiness gaps separate from canonical missing evidence', function () {
    $fixture = facilitationFixture();
    $gaps = $fixture['reference_document']['readiness_gaps'];

    expect($fixture['source_case']['canonical_missing_evidence_count'])->toBe(0);
    expect($gaps)->toHaveCount(3);

    foreach ($gaps as $gap) {
        expect($gap['scope'])->toBe('workshop_readiness');
    }

    $kinds = array_column($gaps, 'kind');
    expect($kinds)->toContain('missing_stakeholder_perspective');
    expect(array_count_values($kinds)['missing_system_owner'] ?? 0)->toBe(2);
});

it('keeps recommendations proposed and distinct from sourced facts and decisions', function () {
    $fixture = facilitationFixture();
    $document = $fixture['reference_document'];

    foreach ($document['recommended_framing'] as $framing) {
        expect($framing['review_status'])->toBe('proposed');
        // A recommendation is never presented as an established fact.
        expect($framing['recommendation']['provenance'])->toBe('system_suggested');
    }

    // Both required provenance states are exercised, and no block masquerades as
    // a recorded decision.
    $provenances = [];
    array_walk_recursive($document, function ($value, $key) use (&$provenances): void {
        if ($key === 'provenance' && is_string($value)) {
            $provenances[] = $value;
        }
    });

    expect($provenances)->toContain('source_backed');
    expect($provenances)->toContain('system_suggested');
    expect($provenances)->not->toContain('recorded_decision');
    expect($document['status'])->toBe('draft');
});

it('contains none of the forbidden consensus or approval claims', function () {
    $fixture = facilitationFixture();
    $texts = facilitationAllText($fixture['reference_document']);
    $haystack = implode("\n", $texts);

    foreach ($fixture['expectations']['forbidden_claims'] as $forbidden) {
        expect($haystack)->not->toContain($forbidden);
    }
});

it('references only defined decisions from the run of show and expected outputs', function () {
    $fixture = facilitationFixture();
    $document = $fixture['reference_document'];

    $decisionIds = array_column($document['decisions_to_lock'], 'id');

    $referenced = collect($document['run_of_show'])
        ->concat($document['expected_outputs'])
        ->pluck('decision_ids')
        ->flatten()
        ->unique()
        ->all();

    expect(array_diff($referenced, $decisionIds))->toBe([]);
});

it('derives and serializes a facilitation brief that validates against the contract schema', function () {
    $fixture = facilitationFixture();
    $refDoc = $fixture['reference_document'];

    $generatedDraft = [
        'schema_version' => '1.0',
        'status' => 'draft',
        'metric_id' => $refDoc['metric_id'],
        'metric_name' => $refDoc['metric_name'],
        'summary' => [
            'text' => $refDoc['workshop_thesis']['text'],
            'citation_ids' => $refDoc['workshop_thesis']['citation_ids'],
        ],
        'resolution_focus' => array_map(function ($d) {
            return [
                'field_key' => $d['field_keys'][0] ?? 'overhead_handling',
                'kind' => match ($d['priority'] ?? 'high') {
                    'high' => 'must_resolve',
                    'medium' => 'missing_evidence',
                    default => 'needs_confirmation',
                },
                'title' => $d['title'],
                'synthesis' => $d['decision_prompt']['text'],
                'source_item_ids' => $d['agenda_topic_ids'],
                'citation_ids' => $d['decision_prompt']['citation_ids'],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ];
        }, $refDoc['decisions_to_lock']),
        'supporting_context' => [],
    ];

    foreach ($refDoc['recommended_framing'] ?? [] as $framing) {
        $relatedDecisionId = $framing['related_decision_ids'][0] ?? null;
        if ($relatedDecisionId) {
            $fieldKey = str_replace('decision:', '', $relatedDecisionId);
            foreach ($generatedDraft['resolution_focus'] as &$rf) {
                if ($rf['field_key'] === $fieldKey) {
                    $rf['candidate_resolution_paths'][] = [
                        'label' => explode(': ', $framing['recommendation']['text'], 2)[0] ?? '',
                        'description' => explode(': ', $framing['recommendation']['text'], 2)[1] ?? '',
                        'source_option_ids' => $framing['recommendation']['source_item_ids'] ?? [],
                        'citation_ids' => $framing['recommendation']['citation_ids'] ?? [],
                    ];
                }
            }
            unset($rf);
        }
    }

    $action = app(DeriveMetricFacilitationPlan::class);
    $fingerprint = $refDoc['source_snapshot']['brief_fingerprint'];
    $draftVersions = $refDoc['source_snapshot']['generated_draft'];

    $rawReadinessGaps = array_map(function ($gap) {
        return [
            'id' => $gap['id'],
            'kind' => $gap['kind'],
            'description' => $gap['description']['text'],
            'source_item_ids' => $gap['description']['source_item_ids'] ?? [],
            'citation_ids' => $gap['description']['citation_ids'] ?? [],
        ];
    }, $refDoc['readiness_gaps']);

    $agendaItems = array_map(fn (string $id): array => [
        'id' => $id,
        'field_key' => '',
        'kind' => 'must_resolve',
        'title' => '',
        'rationale' => '',
        'citation_ids' => [],
    ], $refDoc['coverage']['agenda_topic_ids']);

    $document = $action->handle(
        $generatedDraft,
        $fingerprint,
        $draftVersions,
        $agendaItems,
        $rawReadinessGaps
    );

    $schemaPath = base_path('specs/034-optymize-prompt/contracts/consultant-facilitation-brief.schema.json');
    $schema = json_decode(file_get_contents($schemaPath), flags: JSON_THROW_ON_ERROR);

    $serialized = json_decode(json_encode($document->toArray(), JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);

    $result = (new Validator)->validate($serialized, $schema);

    expect($result->isValid())->toBeTrue();
});
