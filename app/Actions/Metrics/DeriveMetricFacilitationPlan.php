<?php

namespace App\Actions\Metrics;

use App\Support\Metrics\Facilitation\ExpectedOutput;
use App\Support\Metrics\Facilitation\FacilitationBriefDocument;
use App\Support\Metrics\Facilitation\FacilitationContentBlock;
use App\Support\Metrics\Facilitation\RunOfShowItem;
use Illuminate\Validation\ValidationException;

/**
 * Derives an initial, system-suggested run of show and expected-output checklist
 * from the decisions in the deterministic brief projection.
 *
 * The output is a preparation proposal, not a recorded workshop outcome. The
 * sequencing is deterministic so the same brief always yields the same plan
 * before any consultant edits.
 */
final class DeriveMetricFacilitationPlan
{
    /**
     * Mirrors MetricAlignmentDraftOutputValidator::RESOLUTION_CATEGORY_BY_KIND
     * so fallback agenda linkage uses the same topic-ID category the canonical
     * brief produces for each focus kind.
     */
    private const AGENDA_CATEGORY_BY_KIND = [
        'must_resolve' => 'material_conflict',
        'missing_evidence' => 'missing_evidence',
        'needs_confirmation' => 'decision_needed',
    ];

    /**
     * @param  array<string, mixed>  $generatedDraft
     * @param  array{schema_version: string, prompt_version: string, profile_version: string, draft_fingerprint: string}|null  $draftVersions
     * @param  list<array<string, mixed>>  $agendaItems
     * @param  list<array<string, mixed>>  $rawReadinessGaps
     */
    public function handle(
        array $generatedDraft,
        string $briefFingerprint,
        ?array $draftVersions,
        array $agendaItems,
        array $rawReadinessGaps,
    ): FacilitationBriefDocument {
        // Merge duplicate resolution_focus items by field_key so no evidence is
        // silently dropped when the draft repeats a field.
        $rawFocus = is_array($generatedDraft['resolution_focus'] ?? null)
            ? $generatedDraft['resolution_focus']
            : [];
        $uniqueFocus = [];
        $indexByFieldKey = [];
        foreach ($rawFocus as $rf) {
            if (! is_array($rf)) {
                continue;
            }
            $fieldKey = (string) ($rf['field_key'] ?? '');
            if ($fieldKey === '') {
                continue;
            }
            if (isset($indexByFieldKey[$fieldKey])) {
                $uniqueFocus[$indexByFieldKey[$fieldKey]] = $this->mergeFocusItems(
                    $uniqueFocus[$indexByFieldKey[$fieldKey]],
                    $rf,
                );

                continue;
            }
            $indexByFieldKey[$fieldKey] = count($uniqueFocus);
            $uniqueFocus[] = $rf;
        }
        $generatedDraft['resolution_focus'] = $uniqueFocus;

        $agendaTopicIds = array_values(array_map(
            static fn (array $item): string => (string) ($item['id'] ?? ''),
            $agendaItems,
        ));

        // The generated draft is a capped top-5 scan brief (spec 033's 30-second
        // design), so for dense metrics it omits material conflicts the workshop
        // still needs to lock. Append a deterministic decision for every canonical
        // agenda topic the draft did not cover, sourced from the canonical brief,
        // so the facilitation plan is complete rather than limited to that cap.
        $coveredAgendaIds = [];
        $representedFieldKeys = [];
        foreach ($uniqueFocus as $rf) {
            $representedFieldKeys[(string) ($rf['field_key'] ?? '')] = true;
            foreach ($rf['source_item_ids'] ?? [] as $sourceItemId) {
                $coveredAgendaIds[(string) $sourceItemId] = true;
            }
        }

        foreach ($agendaItems as $item) {
            $itemId = (string) ($item['id'] ?? '');
            $fieldKey = (string) ($item['field_key'] ?? '');

            if ($itemId === '' || $fieldKey === ''
                || isset($coveredAgendaIds[$itemId])
                || isset($representedFieldKeys[$fieldKey])
            ) {
                continue;
            }
            $representedFieldKeys[$fieldKey] = true;

            // Use the clean field label for the decision title so synthetic
            // decisions read like the draft's ("Discount treatment"), not the
            // canonical agenda statement ("Stakeholders disagree on ...").
            $label = (string) ($item['field_label'] ?? '');
            $decisionTitle = $label !== '' ? $label : (string) ($item['title'] ?? '');
            $rationale = trim((string) ($item['rationale'] ?? ''));

            $generatedDraft['resolution_focus'][] = [
                'field_key' => $fieldKey,
                'kind' => (string) ($item['kind'] ?? 'must_resolve'),
                'title' => mb_substr($decisionTitle, 0, 180),
                'synthesis' => mb_substr(
                    $rationale !== ''
                        ? $rationale
                        : "Stakeholders diverge on {$decisionTitle}; lock the official rule for this field.",
                    0,
                    1200,
                ),
                'source_item_ids' => [$itemId],
                'citation_ids' => is_array($item['citation_ids'] ?? null)
                    ? array_values($item['citation_ids'])
                    : [],
                'facilitator_questions' => [],
                'candidate_resolution_paths' => [],
            ];
        }

        $thesisSourceItemIds = [];
        foreach ($generatedDraft['resolution_focus'] ?? [] as $rf) {
            if (! empty($rf['source_item_ids'])) {
                $thesisSourceItemIds = array_merge($thesisSourceItemIds, $rf['source_item_ids']);
            }
        }
        $thesisSourceItemIds = array_values(array_unique($thesisSourceItemIds));

        $summaryText = trim((string) ($generatedDraft['summary']['text'] ?? ''));
        $thesisCitationIds = $generatedDraft['summary']['citation_ids'] ?? [];

        // With no generated-draft summary the plan is built deterministically, so
        // synthesize the workshop thesis from the canonical agenda instead.
        if ($summaryText === '') {
            [$summaryText, $thesisCitationIds] = $this->deterministicThesis(
                $agendaItems,
                (string) ($generatedDraft['metric_name'] ?? ''),
            );
        }

        $thesisProvenance = (empty($thesisSourceItemIds) || empty($thesisCitationIds)) ? 'system_suggested' : 'source_backed';

        $workshopThesis = [
            'text' => $summaryText,
            'provenance' => $thesisProvenance,
            'source_item_ids' => $thesisSourceItemIds,
            'citation_ids' => $thesisCitationIds,
        ];

        $decisionsToLock = [];
        $coveredTopicIds = [];
        foreach ($generatedDraft['resolution_focus'] ?? [] as $rf) {
            $fieldKey = $rf['field_key'] ?? '';
            if ($fieldKey === '') {
                continue;
            }

            $priority = match ($rf['kind'] ?? '') {
                'must_resolve' => 'high',
                'missing_evidence' => 'medium',
                default => 'low',
            };

            $sourceItemIds = is_array($rf['source_item_ids'] ?? null) ? array_values($rf['source_item_ids']) : [];

            // The schema requires non-empty agenda linkage, so fall back to a
            // synthetic ID there only; the content block keeps the real
            // (possibly empty) source IDs so provenance stays truthful.
            $agendaLinkIds = $sourceItemIds !== []
                ? $sourceItemIds
                : [$this->fallbackAgendaTopicId($fieldKey, (string) ($rf['kind'] ?? ''), $agendaTopicIds)];

            $coveredTopicIds = array_merge($coveredTopicIds, $agendaLinkIds);

            $citationIds = $rf['citation_ids'] ?? [];
            $decisionPromptProvenance = ($sourceItemIds === [] || empty($citationIds)) ? 'system_suggested' : 'source_backed';

            $decisionsToLock[] = [
                'id' => "decision:{$fieldKey}",
                'title' => $rf['title'] ?? '',
                'priority' => $priority,
                'agenda_topic_ids' => $agendaLinkIds,
                'field_keys' => [$fieldKey],
                'decision_prompt' => [
                    'text' => $rf['synthesis'] ?? '',
                    'provenance' => $decisionPromptProvenance,
                    'source_item_ids' => $sourceItemIds,
                    'citation_ids' => $citationIds,
                ],
            ];
        }
        $coveredTopicIds = array_values(array_unique(array_intersect($coveredTopicIds, $agendaTopicIds)));

        $omittedTopicIds = array_values(array_diff($agendaTopicIds, $coveredTopicIds));
        $omittedTopics = [];
        foreach ($omittedTopicIds as $topicId) {
            $omittedTopics[] = [
                'agenda_topic_id' => $topicId,
                'reason' => [
                    'text' => 'Omitted from the generated draft brief.',
                    'provenance' => 'system_suggested',
                    'source_item_ids' => [],
                    'citation_ids' => [],
                ],
            ];
        }

        $coverage = [
            'agenda_topic_ids' => $agendaTopicIds,
            'covered_topic_ids' => $coveredTopicIds,
            'omitted_topics' => $omittedTopics,
        ];

        $readinessGaps = [];
        foreach ($rawReadinessGaps as $gap) {
            $kind = $gap['kind'] ?? 'missing_stakeholder_perspective';
            $description = [
                'text' => $gap['description'] ?? '',
                'provenance' => 'source_backed',
                'source_item_ids' => $gap['source_item_ids'] ?? [],
                'citation_ids' => $gap['citation_ids'] ?? [],
            ];
            $readinessGaps[] = [
                'id' => $gap['id'] ?? '',
                'scope' => 'workshop_readiness',
                'kind' => $kind,
                'status' => 'open',
                'related_decision_ids' => [],
                'description' => $description,
            ];
        }

        // Recommended framing stays empty in the deterministic derivation.
        // candidate_resolution_paths are neutral restatements of the stakeholder
        // resolution options (see MetricAlignmentDraftAgent), not facilitation
        // strategy, so they must not be relabeled as recommended framing (F001).
        // The options remain available to the consultant as source positions on
        // each decision; a genuine facilitation-strategy source may populate this
        // field in a later iteration.
        $recommendedFraming = [];

        // Carry the template workshop prompt per field so field-bound run-of-show
        // segments read like facilitator actions rather than generic lock labels.
        // Sourced from the deterministic brief projection; absent for synthetic
        // decisions, which keep the Lock/Confirm fallback.
        $workshopPromptsByField = [];
        foreach ($generatedDraft['resolution_focus'] ?? [] as $rf) {
            $fieldKey = (string) ($rf['field_key'] ?? '');
            $prompt = trim((string) ($rf['workshop_prompt'] ?? ''));
            if ($fieldKey !== '' && $prompt !== '') {
                $workshopPromptsByField[$fieldKey] = $prompt;
            }
        }

        $orderedDecisions = $this->orderDecisionsByPriority($decisionsToLock);

        return new FacilitationBriefDocument(
            schemaVersion: '2.0',
            status: 'draft',
            metricId: (string) ($generatedDraft['metric_id'] ?? ''),
            metricName: (string) ($generatedDraft['metric_name'] ?? ''),
            sourceSnapshot: [
                'brief_fingerprint' => $briefFingerprint,
                'generated_draft' => $draftVersions,
            ],
            coverage: $coverage,
            workshopThesis: $workshopThesis,
            decisionsToLock: $decisionsToLock,
            readinessGaps: $readinessGaps,
            recommendedFraming: $recommendedFraming,
            runOfShow: $this->buildRunOfShow($orderedDecisions, $workshopPromptsByField),
            expectedOutputs: $this->buildExpectedOutputs($orderedDecisions),
        );
    }

    /**
     * Synthesizes a deterministic workshop thesis from the canonical agenda when
     * no generated-draft summary is available.
     *
     * @param  list<array<string, mixed>>  $agendaItems
     * @return array{0: string, 1: list<string>}
     */
    private function deterministicThesis(array $agendaItems, string $metricName): array
    {
        $labels = [];
        $citationIds = [];
        $counts = ['must_resolve' => 0, 'needs_confirmation' => 0, 'missing_evidence' => 0];
        foreach ($agendaItems as $item) {
            $label = (string) ($item['field_label'] ?? '');
            if ($label === '') {
                $label = (string) ($item['title'] ?? '');
            }
            if ($label !== '') {
                $labels[] = $label;
            }
            $kind = (string) ($item['kind'] ?? '');
            if (isset($counts[$kind])) {
                $counts[$kind]++;
            }
            foreach ($item['citation_ids'] ?? [] as $citationId) {
                $citationIds[(string) $citationId] = true;
            }
        }

        $metric = $metricName !== '' ? $metricName : 'this metric';

        if ($labels === []) {
            $text = "No contested aspects of {$metric} were detected; confirm the definition with stakeholders.";

            return [mb_substr($text, 0, 1200), array_keys($citationIds)];
        }

        // Distinguish conflicts to reconcile, decisions to confirm, and evidence
        // gaps to close, so a metric with only missing answers is never described
        // as "contested".
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

        $shown = implode('; ', array_slice($labels, 0, 3));
        $more = count($labels) > 3 ? ', and '.(count($labels) - 3).' more' : '';
        $text = "Stakeholders must {$this->joinThesisClauses($clauses)} of {$metric}: {$shown}{$more}.";

        return [mb_substr($text, 0, 1200), array_keys($citationIds)];
    }

    /**
     * Joins thesis clauses with serial-comma "and" so a single phrasing reads
     * naturally for one, two, or three clause groups.
     *
     * @param  list<string>  $clauses
     */
    private function joinThesisClauses(array $clauses): string
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

    /**
     * Resolves the agenda topic ID used for linkage when a focus item carries
     * no source IDs. The ID is derived from the focus kind and must exist in the
     * canonical agenda.
     *
     * @param  list<string>  $agendaTopicIds
     */
    private function fallbackAgendaTopicId(string $fieldKey, string $kind, array $agendaTopicIds): string
    {
        $category = self::AGENDA_CATEGORY_BY_KIND[$kind] ?? 'material_conflict';
        $kindDerivedId = "field:{$fieldKey}:{$category}";

        if (in_array($kindDerivedId, $agendaTopicIds, true)) {
            return $kindDerivedId;
        }

        throw ValidationException::withMessages([
            'generated_draft.resolution_focus' => [
                "Resolution focus for field {$fieldKey} and kind {$kind} has no matching canonical agenda topic.",
            ],
        ]);
    }

    /**
     * Combines two resolution_focus items for the same field_key. Conflicting
     * kinds are rejected because their evidence and resolution-path rules are
     * not interchangeable.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     * @return array<string, mixed>
     */
    private function mergeFocusItems(array $first, array $second): array
    {
        $firstKind = (string) ($first['kind'] ?? '');
        $secondKind = (string) ($second['kind'] ?? '');

        if ($firstKind !== $secondKind) {
            $fieldKey = (string) ($first['field_key'] ?? $second['field_key'] ?? '');

            throw ValidationException::withMessages([
                'generated_draft.resolution_focus' => [
                    "Resolution focus contains conflicting kinds for field {$fieldKey}: {$firstKind} and {$secondKind}.",
                ],
            ]);
        }

        foreach (['source_item_ids', 'citation_ids'] as $listKey) {
            $first[$listKey] = array_values(array_unique(array_merge(
                is_array($first[$listKey] ?? null) ? $first[$listKey] : [],
                is_array($second[$listKey] ?? null) ? $second[$listKey] : [],
            )));
        }

        foreach (['candidate_resolution_paths', 'facilitator_questions'] as $listKey) {
            $first[$listKey] = array_merge(
                is_array($first[$listKey] ?? null) ? $first[$listKey] : [],
                is_array($second[$listKey] ?? null) ? $second[$listKey] : [],
            );
        }

        $firstSynthesis = trim((string) ($first['synthesis'] ?? ''));
        $secondSynthesis = trim((string) ($second['synthesis'] ?? ''));

        if ($secondSynthesis !== '' && $secondSynthesis !== $firstSynthesis) {
            $merged = $firstSynthesis === '' ? $secondSynthesis : "{$firstSynthesis} {$secondSynthesis}";
            $first['synthesis'] = mb_substr($merged, 0, 1200);
        }

        return $first;
    }

    /**
     * Orders decisions for the run of show by conflict priority (high, then
     * medium, then low), preserving the draft order within each tier so the
     * sequence follows what must be resolved rather than template sections.
     *
     * @param  list<array<string, mixed>>  $decisions
     * @return list<array<string, mixed>>
     */
    private function orderDecisionsByPriority(array $decisions): array
    {
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $ordered = $decisions;
        usort(
            $ordered,
            fn (array $left, array $right): int => ($rank[$left['priority'] ?? 'low'] ?? 3)
                <=> ($rank[$right['priority'] ?? 'low'] ?? 3),
        );

        return $ordered;
    }

    /**
     * Builds a decision-oriented run of show: a framing opener, one timeboxed
     * segment per decision (titled from the decision rather than the template
     * section), and a readiness/next-steps closer.
     *
     * @param  list<array<string, mixed>>  $decisions
     * @param  array<string, string>  $workshopPromptsByField
     * @return list<RunOfShowItem>
     */
    private function buildRunOfShow(array $decisions, array $workshopPromptsByField): array
    {
        $items = [];
        $position = 1;

        $items[] = new RunOfShowItem(
            id: 'segment:framing',
            position: $position++,
            durationMinutes: 10,
            title: 'Frame the metric and alignment goal',
            decisionIds: [],
            purpose: FacilitationContentBlock::systemSuggested(
                'Open the workshop, restate the metric and why it matters, and set expectations for the decisions the group will lock today. For each decision we surface the stakeholder positions, weigh the trade-offs, and lock the official choice.',
            ),
        );

        foreach ($decisions as $decision) {
            $fieldKey = (string) ($decision['field_keys'][0] ?? '');
            $title = (string) ($decision['title'] ?? '');
            $action = $this->decisionActionForPriority((string) ($decision['priority'] ?? 'low'));

            $items[] = new RunOfShowItem(
                id: "segment:lock:{$fieldKey}",
                position: $position++,
                durationMinutes: match ($decision['priority'] ?? 'low') {
                    'high' => 15,
                    'medium' => 12,
                    default => 10,
                },
                title: mb_substr($this->segmentTitle($fieldKey, $action, $title, $workshopPromptsByField), 0, 180),
                decisionIds: [(string) $decision['id']],
                purpose: FacilitationContentBlock::systemSuggested(
                    'Lock the official choice the group commits to.',
                ),
            );
        }

        $items[] = new RunOfShowItem(
            id: 'segment:next-steps',
            position: $position,
            durationMinutes: 10,
            title: 'Confirm readiness and agree next steps',
            decisionIds: [],
            purpose: FacilitationContentBlock::systemSuggested(
                'Review open readiness gaps, assign owners for follow-up, and confirm what each participant takes away from the session.',
            ),
        );

        return $items;
    }

    /**
     * Builds concrete expected outputs: one planned artifact naming each
     * decision the workshop should lock, plus a consolidated decision log.
     *
     * @param  list<array<string, mixed>>  $decisions
     * @return list<ExpectedOutput>
     */
    private function buildExpectedOutputs(array $decisions): array
    {
        $outputs = [];

        foreach ($decisions as $decision) {
            $fieldKey = (string) ($decision['field_keys'][0] ?? '');
            $title = (string) ($decision['title'] ?? '');
            $statusVerb = $this->decisionOutputVerbForPriority((string) ($decision['priority'] ?? 'low'));

            $outputs[] = new ExpectedOutput(
                id: "output:{$fieldKey}",
                label: mb_substr("{$statusVerb}: {$title}", 0, 180),
                status: ExpectedOutput::STATUS_PLANNED,
                decisionIds: [(string) $decision['id']],
                description: FacilitationContentBlock::systemSuggested(
                    mb_substr("{$title} — documented official rule, ready for reconciliation.", 0, 1200),
                ),
            );
        }

        $outputs[] = new ExpectedOutput(
            id: 'output:decision-log',
            label: 'Workshop decision log',
            status: ExpectedOutput::STATUS_PLANNED,
            decisionIds: [],
            description: FacilitationContentBlock::systemSuggested(
                'A consolidated record of every decision the workshop locks, plus any deferred items and their owners.',
            ),
        );

        return $outputs;
    }

    /**
     * Resolves a field-bound segment title: the template workshop prompt when one
     * is defined for the field, otherwise the action prefix plus the field label
     * (`Lock: {label}` / `Confirm: {label}`).
     *
     * @param  array<string, string>  $workshopPromptsByField
     */
    private function segmentTitle(string $fieldKey, string $action, string $decisionTitle, array $workshopPromptsByField): string
    {
        $workshopPrompt = trim((string) ($workshopPromptsByField[$fieldKey] ?? ''));

        return $workshopPrompt !== ''
            ? $workshopPrompt
            : "{$action}: {$decisionTitle}";
    }

    private function decisionActionForPriority(string $priority): string
    {
        return $priority === 'low' ? 'Confirm' : 'Lock';
    }

    private function decisionOutputVerbForPriority(string $priority): string
    {
        return $priority === 'low' ? 'Confirmed' : 'Locked';
    }
}
