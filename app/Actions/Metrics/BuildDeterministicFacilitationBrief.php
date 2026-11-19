<?php

namespace App\Actions\Metrics;

/**
 * Projects the canonical Metric Alignment Brief into the existing
 * `GeneratedMetricAlignmentDraft` shape — deterministically, with no AI.
 *
 * This lets the consultant-facing brief surfaces (thesis, decisions, stakeholder
 * options, supporting context) render through the same components the AI draft
 * used, while the generated draft is removed. Decisions come from the canonical
 * agenda (one per material conflict, missing-evidence, or decision-to-confirm),
 * stakeholder options from `resolution_options`, facilitator questions from
 * `recommended_questions`, and supporting context from the canonical signals.
 */
final class BuildDeterministicFacilitationBrief
{
    private const KIND_BY_BUCKET = [
        'material_conflicts' => 'must_resolve',
        'decision_needed' => 'needs_confirmation',
        'missing_evidence' => 'missing_evidence',
    ];

    /**
     * @param  array<string, mixed>  $brief  the canonical metric_alignment_brief payload
     * @return array<string, mixed>
     */
    public function build(array $brief, string $metricId, string $metricName): array
    {
        $questionsByField = $this->groupByField($brief['recommended_questions'] ?? []);
        $optionsByField = $this->groupByField($brief['resolution_options'] ?? []);

        $resolutionFocus = [];
        $thesisItems = [];
        $thesisCitationIds = [];

        foreach (self::KIND_BY_BUCKET as $bucket => $kind) {
            foreach ($brief[$bucket] ?? [] as $item) {
                $fieldKey = (string) ($item['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $citationIds = $this->refIds($item['source_refs'] ?? []);
                $label = (string) ($item['field_label'] ?? $item['title'] ?? $fieldKey);
                $thesisItems[] = ['label' => $label, 'kind' => $kind];
                $thesisCitationIds = array_merge($thesisCitationIds, $citationIds);

                $facilitatorQuestions = [];
                foreach ($questionsByField[$fieldKey] ?? [] as $question) {
                    $facilitatorQuestions[] = [
                        'question' => (string) ($question['question'] ?? ''),
                        'reason' => (string) ($question['reason'] ?? ''),
                        'source_question_ids' => [(string) ($question['id'] ?? '')],
                        'citation_ids' => $this->refIds($question['source_refs'] ?? []),
                    ];
                }

                // Mirror the drafting rule: missing-evidence items carry no
                // resolution paths (there is no submitted value to restate).
                $candidatePaths = [];
                if ($kind !== 'missing_evidence') {
                    foreach ($optionsByField[$fieldKey] ?? [] as $option) {
                        $candidatePaths[] = [
                            'label' => (string) ($option['label'] ?? ''),
                            'description' => (string) ($option['description'] ?? ''),
                            'source_option_ids' => [(string) ($option['id'] ?? '')],
                            'citation_ids' => $this->refIds($option['source_refs'] ?? []),
                        ];
                    }
                }

                $resolutionFocus[] = [
                    'field_key' => $fieldKey,
                    'kind' => $kind,
                    'title' => $label,
                    'synthesis' => (string) ($item['rationale'] ?? ''),
                    'source_item_ids' => [(string) ($item['id'] ?? '')],
                    'citation_ids' => $citationIds,
                    'facilitator_questions' => $facilitatorQuestions,
                    'candidate_resolution_paths' => $candidatePaths,
                    // Transient field carried through to DeriveMetricFacilitationPlan
                    // so a field-bound run-of-show segment can be titled from the
                    // template workshop prompt. Not part of the generated-draft
                    // contract and never persisted on the facilitation document.
                    'workshop_prompt' => $this->workshopPromptFromRefs($item['source_refs'] ?? []),
                ];
            }
        }

        $supportingContext = [];
        foreach (['context_signals' => 'context_signal', 'likely_non_issues' => 'likely_non_issue'] as $bucket => $kind) {
            foreach ($brief[$bucket] ?? [] as $item) {
                $supportingContext[] = [
                    'field_key' => (string) ($item['field_key'] ?? ''),
                    'kind' => $kind,
                    'note' => (string) ($item['rationale'] ?? $item['title'] ?? ''),
                    'source_item_ids' => [(string) ($item['id'] ?? '')],
                    'citation_ids' => $this->refIds($item['source_refs'] ?? []),
                ];
            }
        }

        return [
            'schema_version' => '1.0',
            'status' => 'draft',
            'metric_id' => $metricId,
            'metric_name' => $metricName,
            'summary' => [
                'text' => $this->thesis($thesisItems, $metricName),
                'citation_ids' => array_values(array_unique($thesisCitationIds)),
            ],
            'resolution_focus' => $resolutionFocus,
            'supporting_context' => $supportingContext,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByField(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $fieldKey = (string) ($item['field_key'] ?? '');
            if ($fieldKey !== '') {
                $grouped[$fieldKey][] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Extracts the verbatim template workshop prompt for a field from its source
     * references. The `workshop_prompt` field-guidance ref is only present when
     * the template actually defines a prompt, so its excerpt is an unambiguous
     * prompt (never the generic fallback). Returns an empty string otherwise.
     *
     * @param  list<array<string, mixed>>  $refs
     */
    private function workshopPromptFromRefs(array $refs): string
    {
        foreach ($refs as $ref) {
            if (($ref['meta']['guidance_kind'] ?? null) === 'workshop_prompt') {
                return trim((string) ($ref['excerpt'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $refs
     * @return list<string>
     */
    private function refIds(array $refs): array
    {
        return array_values(array_filter(array_map(
            static fn (array $ref): string => (string) ($ref['id'] ?? ''),
            $refs,
        ), static fn (string $id): bool => $id !== ''));
    }

    /**
     * Mirrors DeriveMetricFacilitationPlan::deterministicThesis so the brief and
     * the run of show share one phrasing. The clauses distinguish conflicts to
     * reconcile, decisions to confirm, and evidence gaps to close, so a metric
     * with only missing answers is never described as "contested".
     *
     * @param  list<array{label: string, kind: string}>  $items
     */
    private function thesis(array $items, string $metricName): string
    {
        $metric = $metricName !== '' ? $metricName : 'this metric';

        if ($items === []) {
            return "No contested aspects of {$metric} were detected; confirm the definition with stakeholders.";
        }

        $counts = ['must_resolve' => 0, 'needs_confirmation' => 0, 'missing_evidence' => 0];
        foreach ($items as $item) {
            $kind = $item['kind'] ?? '';
            if (isset($counts[$kind])) {
                $counts[$kind]++;
            }
        }

        $clauses = [];
        if ($counts['must_resolve'] > 0) {
            $n = $counts['must_resolve'];
            $clauses[] = "reconcile {$n} contested ".($n === 1 ? 'aspect' : 'aspects');
        }
        if ($counts['needs_confirmation'] > 0) {
            $n = $counts['needs_confirmation'];
            $clauses[] = "confirm {$n} ".($n === 1 ? 'decision' : 'decisions');
        }
        if ($counts['missing_evidence'] > 0) {
            $n = $counts['missing_evidence'];
            $clauses[] = "close {$n} evidence ".($n === 1 ? 'gap' : 'gaps');
        }

        $labels = array_map(static fn (array $item): string => $item['label'], $items);
        $shown = implode('; ', array_slice($labels, 0, 3));
        $more = count($labels) > 3 ? ', and '.(count($labels) - 3).' more' : '';

        return "Stakeholders must {$this->joinClauses($clauses)} of {$metric}: {$shown}{$more}.";
    }

    /**
     * Joins thesis clauses with serial-comma "and" so a single phrasing reads
     * naturally for one, two, or three clause groups.
     *
     * @param  list<string>  $clauses
     */
    private function joinClauses(array $clauses): string
    {
        if (count($clauses) === 1) {
            return $clauses[0];
        }

        if (count($clauses) === 2) {
            return "{$clauses[0]} and {$clauses[1]}";
        }

        $last = array_pop($clauses);

        return implode(', ', $clauses).', and '.$last;
    }
}
