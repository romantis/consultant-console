<?php

namespace App\Actions\Metrics;

/**
 * Builds the deterministic Facilitator Readiness Pack (spec 037, schema 0.1) from
 * the Workshop Prep payloads the controller already assembles: the metric
 * alignment brief, the serialized facilitation brief, the workshop readiness
 * snapshot, and the resolved field roles.
 *
 * The pack is a private, operator-facing preparation briefing. It is a pure,
 * source-backed transform: it never calls an AI provider, never persists, and
 * never makes or recommends a decision. Optional sections stay empty when the
 * sources do not justify them rather than being padded.
 */
final class BuildFacilitatorReadinessPack
{
    private const TIER_BY_BUCKET = [
        'material_conflicts' => 'must_resolve',
        'missing_evidence' => 'missing_evidence',
        'decision_needed' => 'needs_confirmation',
    ];

    private const TIER_ORDER = [
        'must_resolve' => 0,
        'missing_evidence' => 1,
        'needs_confirmation' => 2,
    ];

    /**
     * @param  array<string, mixed>  $brief  metric_alignment_brief payload
     * @param  array<string, mixed>|null  $facilitationBrief  serialized facilitation brief (status, needs_source_review, document) or null
     * @param  array<string, mixed>  $workshopReadiness  workshop_readiness payload (gaps, ...)
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @return array<string, mixed>
     */
    public function handle(
        array $brief,
        ?array $facilitationBrief,
        array $workshopReadiness,
        array $fieldRoles,
        string $metricId,
        string $metricName,
    ): array {
        $fieldRolesByKey = $this->indexBy($fieldRoles, 'key');
        $positionsByField = $this->groupBy($brief['stakeholder_positions'] ?? [], 'field_key');
        $companionsByPrimary = $this->groupBy($brief['companion_answers'] ?? [], 'primary_field_key');
        $cueContextByField = $this->indexBy($brief['cue_contexts'] ?? [], 'field_key');
        $questionByField = $this->indexBy($brief['recommended_questions'] ?? [], 'field_key');

        $document = is_array($facilitationBrief['document'] ?? null)
            ? $facilitationBrief['document']
            : [];
        $runOfShow = is_array($document['run_of_show'] ?? null) ? $document['run_of_show'] : [];
        $runOfShowByField = $this->runOfShowByFieldKey($runOfShow, $document['decisions_to_lock'] ?? []);

        $decisions = $this->buildDecisions(
            $brief,
            $fieldRolesByKey,
            $positionsByField,
            $companionsByPrimary,
            $cueContextByField,
            $questionByField,
            $runOfShowByField,
        );

        $captureChecklist = $this->buildCaptureChecklist($decisions);
        $facilitationRisks = $this->buildFacilitationRisks($decisions, $brief);
        $readiness = $this->buildReadinessOverview($brief, $facilitationBrief, $workshopReadiness, $decisions, $runOfShow);
        $openingPlan = $this->buildOpeningPlan($decisions, $runOfShowByField, $runOfShow);

        [$sourceRefs, $citationIds] = $this->aggregateRefs(
            array_merge(
                ...array_map(fn (array $decision): array => $decision['source_refs'], $decisions),
                ...array_map(fn (array $item): array => $item['source_refs'], $captureChecklist),
                ...array_map(fn (array $risk): array => $risk['source_refs'], $facilitationRisks),
            ),
        );

        return [
            'schema_version' => '0.1',
            'metric_id' => $metricId,
            'metric_name' => $metricName,
            'generated_at' => null,
            'provenance' => 'deterministic',
            'readiness' => $readiness,
            'workshop_thesis' => null,
            'decisions' => $decisions,
            'opening_plan' => $openingPlan,
            'capture_checklist' => $captureChecklist,
            'facilitation_risks' => $facilitationRisks,
            'source_refs' => $sourceRefs,
            'citation_ids' => $citationIds,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldRolesByKey
     * @param  array<string, array<int, array<string, mixed>>>  $positionsByField
     * @param  array<string, array<int, array<string, mixed>>>  $companionsByPrimary
     * @param  array<string, array<string, mixed>>  $cueContextByField
     * @param  array<string, array<string, mixed>>  $questionByField
     * @param  array<string, array{item_id: string, position: int}>  $runOfShowByField
     * @return array<int, array<string, mixed>>
     */
    private function buildDecisions(
        array $brief,
        array $fieldRolesByKey,
        array $positionsByField,
        array $companionsByPrimary,
        array $cueContextByField,
        array $questionByField,
        array $runOfShowByField,
    ): array {
        $raw = [];

        foreach (self::TIER_BY_BUCKET as $bucket => $tier) {
            foreach ($brief[$bucket] ?? [] as $item) {
                $fieldKey = (string) ($item['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $raw[] = [
                    'field_key' => $fieldKey,
                    'tier' => $tier,
                    'item' => $item,
                ];
            }
        }

        // Order by run-of-show position when the field is on the agenda, then by
        // attention tier (must-resolve first), then by original brief order so the
        // sequence is stable.
        usort($raw, function (array $a, array $b) use ($runOfShowByField): int {
            $posA = $runOfShowByField[$a['field_key']]['position'] ?? PHP_INT_MAX;
            $posB = $runOfShowByField[$b['field_key']]['position'] ?? PHP_INT_MAX;

            if ($posA !== $posB) {
                return $posA <=> $posB;
            }

            return self::TIER_ORDER[$a['tier']] <=> self::TIER_ORDER[$b['tier']];
        });

        $matrixFieldKeysByField = [];
        foreach ($raw as $entry) {
            $fieldKey = $entry['field_key'];
            $matrixFieldKeysByField[$fieldKey] = array_values(array_filter(array_map(
                fn (array $field): string => (string) ($field['field_key'] ?? ''),
                $cueContextByField[$fieldKey]['matrix_context'] ?? [],
            )));
        }
        $decisionFieldKeys = array_map(fn (array $entry): string => $entry['field_key'], $raw);

        $decisions = [];
        foreach ($raw as $entry) {
            $fieldKey = $entry['field_key'];
            $tier = $entry['tier'];
            $item = $entry['item'];
            $fieldRole = $fieldRolesByKey[$fieldKey] ?? [];
            $fieldLabel = (string) ($item['field_label'] ?? $fieldRole['label'] ?? $fieldKey);
            $decisionId = "readiness_decision:{$fieldKey}";

            $positions = $this->buildSourcePositions($positionsByField[$fieldKey] ?? []);
            $companionGroups = $this->buildCompanionGroups($companionsByPrimary[$fieldKey] ?? []);
            $matrixContext = $this->buildMatrixContext($cueContextByField[$fieldKey] ?? []);

            $decisionQuestion = $this->buildDecisionQuestion($questionByField[$fieldKey] ?? null, $item, $fieldLabel);
            $whyThisMatters = $this->buildWhyThisMatters($item, $fieldRole);
            $startWith = $this->buildStartWith($fieldRole, $runOfShowByField[$fieldKey] ?? null);
            $watchOuts = $this->buildWatchOuts($decisionId, $tier, $fieldRole, $positions, $matrixContext);
            $captureRequirements = $this->buildCaptureRequirements($decisionId, $tier);
            $relatedDecisionIds = $this->relatedDecisionIds(
                $fieldKey,
                $matrixFieldKeysByField,
                $decisionFieldKeys,
            );

            $decisionRefs = array_merge(
                $decisionQuestion['source_refs'],
                $whyThisMatters['source_refs'] ?? [],
                ...array_map(fn (array $p): array => $p['source_refs'], $positions),
                ...array_map(fn (array $g): array => $g['source_refs'], $companionGroups),
                ...array_map(fn (array $m): array => $m['source_refs'], $matrixContext),
            );
            [$sourceRefs, $citationIds] = $this->aggregateRefs($decisionRefs);

            $decisions[] = [
                'id' => $decisionId,
                'field_key' => $fieldKey,
                'field_label' => $fieldLabel,
                'run_of_show_item_id' => $runOfShowByField[$fieldKey]['item_id'] ?? null,
                'attention_tier' => $tier,
                'decision_question' => $decisionQuestion,
                'why_this_matters' => $whyThisMatters,
                'source_positions' => $positions,
                'companion_groups' => $companionGroups,
                'matrix_context' => $matrixContext,
                'start_with' => $startWith,
                'fallback_question' => null,
                'watch_outs' => $watchOuts,
                'capture_requirements' => $captureRequirements,
                'related_decision_ids' => $relatedDecisionIds,
                'source_refs' => $sourceRefs,
                'citation_ids' => $citationIds,
            ];
        }

        return $decisions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @return array<int, array<string, mixed>>
     */
    private function buildSourcePositions(array $positions): array
    {
        return array_values(array_map(function (array $position): array {
            $refs = is_array($position['source_refs'] ?? null) ? $position['source_refs'] : [];

            return [
                'stakeholder_name' => (string) ($position['stakeholder']['name'] ?? 'Stakeholder'),
                'stakeholder_affiliation' => $this->nullableString($position['stakeholder']['affiliation'] ?? null),
                'value' => (string) ($position['display_value'] ?? ''),
                'source_refs' => $refs,
                'citation_ids' => $this->citationIds($refs),
            ];
        }, $positions));
    }

    /**
     * Group repeated companion labels once, then list the respondent-specific
     * values under that group (contract: do not repeat the companion label inside
     * every respondent card).
     *
     * @param  array<int, array<string, mixed>>  $companionGroups
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanionGroups(array $companionGroups): array
    {
        return array_values(array_map(function (array $group): array {
            $answers = $this->buildSourcePositions(array_map(
                fn (array $answer): array => $answer + ['display_value' => $answer['display_value'] ?? ''],
                is_array($group['answers'] ?? null) ? $group['answers'] : [],
            ));

            $refs = array_merge(...array_map(fn (array $answer): array => $answer['source_refs'], $answers)) ?: [];
            [$sourceRefs, $citationIds] = $this->aggregateRefs($refs);

            return [
                'field_key' => (string) ($group['companion_field_key'] ?? ''),
                'field_label' => (string) ($group['companion_field_label'] ?? ''),
                'answers' => $answers,
                'source_refs' => $sourceRefs,
                'citation_ids' => $citationIds,
            ];
        }, $companionGroups));
    }

    /**
     * @param  array<string, mixed>  $cueContext
     * @return array<int, array<string, mixed>>
     */
    private function buildMatrixContext(array $cueContext): array
    {
        return array_values(array_map(function (array $field): array {
            $refs = is_array($field['source_refs'] ?? null) ? $field['source_refs'] : [];

            return $this->sourceBackedText(
                (string) ($field['field_label'] ?? $field['field_key'] ?? ''),
                $refs,
            );
        }, $cueContext['matrix_context'] ?? []));
    }

    /**
     * @param  array<string, mixed>|null  $question
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildDecisionQuestion(?array $question, array $item, string $fieldLabel): array
    {
        $text = $this->nullableString($question['question'] ?? null);
        $refs = is_array($question['source_refs'] ?? null) ? $question['source_refs'] : [];

        if ($text === null) {
            $text = "Agree on the official {$fieldLabel}.";
            $refs = is_array($item['source_refs'] ?? null) ? $item['source_refs'] : [];
        }

        return $this->sourceBackedText($text, $refs);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $fieldRole
     * @return array<string, mixed>|null
     */
    private function buildWhyThisMatters(array $item, array $fieldRole): ?array
    {
        $signal = $this->nullableString($fieldRole['alignment_signal']['description'] ?? null);
        $rationale = $this->nullableString($item['rationale'] ?? null);
        $text = $signal ?? $rationale;

        if ($text === null) {
            return null;
        }

        $refs = is_array($item['source_refs'] ?? null) ? $item['source_refs'] : [];

        return $this->sourceBackedText($text, $refs);
    }

    /**
     * Derive the facilitation start move from the template workshop prompt first,
     * then the run-of-show segment title (contract rule). Returns null when neither
     * source exists rather than inventing a generic opener.
     *
     * @param  array<string, mixed>  $fieldRole
     * @param  array{item_id: string, position: int, title?: string}|null  $runOfShowItem
     * @return array<string, mixed>|null
     */
    private function buildStartWith(array $fieldRole, ?array $runOfShowItem): ?array
    {
        $prompt = $this->nullableString($fieldRole['workshop_prompt'] ?? null);

        if ($prompt !== null) {
            $fieldKey = (string) ($fieldRole['key'] ?? '');

            return $this->sourceBackedText($prompt, [[
                'id' => "field_guidance:{$fieldKey}:prompt",
                'type' => 'field_guidance',
                'label' => 'Workshop prompt',
                'field_key' => $fieldKey,
                'source_id' => null,
                'excerpt' => $prompt,
                'meta' => ['guidance_kind' => 'workshop_prompt'],
            ]]);
        }

        $title = $this->nullableString($runOfShowItem['title'] ?? null);

        if ($title !== null) {
            return $this->sourceBackedText($title, []);
        }

        return null;
    }

    /**
     * Decision-specific mistakes to avoid, built only from stable process rules and
     * concrete source conflicts — never generic filler.
     *
     * @param  array<string, mixed>  $fieldRole
     * @param  array<int, array<string, mixed>>  $positions
     * @param  array<int, array<string, mixed>>  $matrixContext
     * @return array<int, array<string, mixed>>
     */
    private function buildWatchOuts(
        string $decisionId,
        string $tier,
        array $fieldRole,
        array $positions,
        array $matrixContext,
    ): array {
        $watchOuts = [];

        if ($tier === 'must_resolve' && count($positions) >= 2) {
            $positionRefs = array_merge(...array_map(fn (array $p): array => $p['source_refs'], $positions)) ?: [];

            $watchOuts[] = $this->facilitationRisk(
                "{$decisionId}:watch_out:no_recommendation",
                $decisionId,
                'high',
                'Do not present one stakeholder source position as the recommendation.',
                'Name the official choice through the workshop, not by adopting a single submitted value.',
                $positionRefs,
            );
        }

        if ($tier === 'must_resolve' && ($fieldRole['role'] ?? null) === 'definition') {
            $watchOuts[] = $this->facilitationRisk(
                "{$decisionId}:watch_out:label_operating_view",
                $decisionId,
                'medium',
                'Do not close this decision without labeling the surviving operating view.',
                'Any non-official view that will still be reported needs an explicit label and audience.',
                [],
            );
        }

        if ($matrixContext !== []) {
            $matrixRefs = array_merge(...array_map(fn (array $m): array => $m['source_refs'], $matrixContext)) ?: [];
            $labels = implode(', ', array_map(fn (array $m): string => $m['text'], $matrixContext));

            $watchOuts[] = $this->facilitationRisk(
                "{$decisionId}:watch_out:check_related_context",
                $decisionId,
                'medium',
                'Do not close this decision without checking the related context it affects.',
                "Confirm whether the related context should shape the wording: {$labels}.",
                $matrixRefs,
            );
        }

        return $watchOuts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCaptureRequirements(string $decisionId, string $tier): array
    {
        if ($tier === 'missing_evidence') {
            return [
                $this->captureItem(
                    "capture:{$decisionId}:unresolved_gap",
                    $decisionId,
                    'Unresolved evidence gap marked',
                    'Mark the field as unresolved pending more submitted answers; do not record a decision yet.',
                ),
                $this->captureItem(
                    "capture:{$decisionId}:gather_owner",
                    $decisionId,
                    'Owner assigned to gather the missing input',
                    'Record who will collect the missing stakeholder answers before reconciliation.',
                ),
            ];
        }

        $items = [
            $this->captureItem(
                "capture:{$decisionId}:official_wording",
                $decisionId,
                'Official wording captured',
                'Record the exact agreed definition wording.',
            ),
            $this->captureItem(
                "capture:{$decisionId}:rationale",
                $decisionId,
                'Rationale captured',
                'Record why the agreed wording was chosen.',
            ),
        ];

        if ($tier === 'must_resolve') {
            $items[] = $this->captureItem(
                "capture:{$decisionId}:operating_view",
                $decisionId,
                'Surviving operating view labeled',
                'Label any non-official view that will still be reported.',
            );
        }

        $items[] = $this->captureItem(
            "capture:{$decisionId}:owner",
            $decisionId,
            'Owner or approving role captured',
            'Record who owns or approves this decision.',
        );

        return $items;
    }

    /**
     * @param  array<string, array<int, string>>  $matrixFieldKeysByField
     * @param  array<int, string>  $decisionFieldKeys
     * @return array<int, string>
     */
    private function relatedDecisionIds(
        string $fieldKey,
        array $matrixFieldKeysByField,
        array $decisionFieldKeys,
    ): array {
        $related = [];

        foreach ($decisionFieldKeys as $otherKey) {
            if ($otherKey === $fieldKey) {
                continue;
            }

            // A real dependency exists when one decision's matrix context names the
            // other decision's field — never inferred from field-name similarity.
            $linked = in_array($otherKey, $matrixFieldKeysByField[$fieldKey] ?? [], true)
                || in_array($fieldKey, $matrixFieldKeysByField[$otherKey] ?? [], true);

            if ($linked) {
                $related["readiness_decision:{$otherKey}"] = true;
            }
        }

        return array_keys($related);
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @return array<int, array<string, mixed>>
     */
    private function buildCaptureChecklist(array $decisions): array
    {
        $checklist = [];

        foreach ($decisions as $decision) {
            foreach ($decision['capture_requirements'] as $requirement) {
                $checklist[] = $requirement;
            }
        }

        // A readiness gap is already surfaced once as an entry blocker (see
        // buildBlockerSummary + next_action "Resolve N blocking item(s)"), so it is
        // not re-emitted here as a "Confirm or accept {label}" capture line. The
        // checklist stays scoped to per-decision capture requirements; a gap is
        // resolved before the workshop, not captured during it.
        return $checklist;
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @param  array<string, mixed>  $brief
     * @return array<int, array<string, mixed>>
     */
    private function buildFacilitationRisks(array $decisions, array $brief): array
    {
        $risks = [];

        foreach ($decisions as $decision) {
            foreach ($decision['watch_outs'] as $watchOut) {
                $risks[] = $watchOut;
            }
        }

        if (($brief['missing_evidence'] ?? []) !== []) {
            $risks[] = $this->facilitationRisk(
                'risk:workshop:unresolved_evidence',
                null,
                'medium',
                'Do not present a field with missing evidence as a decided baseline.',
                'Keep fields with fewer than two submitted answers marked unresolved until more input arrives.',
                [],
            );
        }

        return $risks;
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>|null  $facilitationBrief
     * @param  array<string, mixed>  $workshopReadiness
     * @param  array<int, array<string, mixed>>  $decisions
     * @param  array<int, array<string, mixed>>  $runOfShow
     * @return array<string, mixed>
     */
    private function buildReadinessOverview(
        array $brief,
        ?array $facilitationBrief,
        array $workshopReadiness,
        array $decisions,
        array $runOfShow,
    ): array {
        // Material conflicts are the workshop's agenda, not blockers to entry. A
        // blocker is something that prevents preparing or deciding: a field with
        // too little evidence to compare, or a required stakeholder perspective
        // that never submitted.
        //
        // FX02: a lone readiness gap (a required perspective with no response) is
        // a hard entry blocker -> state `blocked`, not `needs_review`. A missing
        // required voice demands a pre-workshop action (confirm a participant or
        // explicitly accept the gap); `blocked` + "Resolve N item(s)" states that
        // imperative, where `needs_review` ("review the run of show") would bury
        // it. Both blocker kinds are therefore counted uniformly below. Revisit if
        // FX05 shows required-department gaps are so common that `blocked` goes
        // flat. See decision-log 2026-07-02.
        $missingCount = count($brief['missing_evidence'] ?? []);
        $gapCount = count($workshopReadiness['gaps'] ?? []);
        $blockerCount = $missingCount + $gapCount;
        $decisionCount = count($decisions);

        $plannedMinutes = array_reduce(
            $runOfShow,
            fn (int $total, array $item): int => $total + (int) ($item['duration_minutes'] ?? 0),
            0,
        );

        $facilitationStatus = $this->nullableString($facilitationBrief['status'] ?? null);
        $hasFacilitationPlan = $facilitationBrief !== null;

        $state = match (true) {
            $decisionCount === 0 && $blockerCount === 0 => 'no_prep_needed',
            $facilitationStatus === 'needs_source_review' => 'needs_review',
            // Any blocker, including a lone readiness gap, blocks entry (FX02).
            $blockerCount > 0 => 'blocked',
            $hasFacilitationPlan && $facilitationStatus === 'ready_for_workshop' => 'ready',
            $hasFacilitationPlan => 'in_progress',
            default => 'needs_review',
        };

        return [
            'state' => $state,
            'decision_count' => $decisionCount,
            'blocker_count' => $blockerCount,
            'planned_minutes' => $plannedMinutes,
            'next_action' => $this->nextAction($state, $blockerCount),
            'blocker_summary' => $this->buildBlockerSummary($brief, $workshopReadiness),
        ];
    }

    private function nextAction(string $state, int $blockerCount): string
    {
        return match ($state) {
            'no_prep_needed' => 'No workshop preparation is needed for this metric yet.',
            'blocked' => "Resolve {$blockerCount} blocking item(s) before the workshop.",
            'ready' => 'Rehearse the opening question, then facilitate the run of show.',
            'in_progress' => 'Finish preparing the run of show before the workshop.',
            default => 'Review the prepared run of show before the workshop.',
        };
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $workshopReadiness
     * @return array<int, array<string, mixed>>
     */
    private function buildBlockerSummary(array $brief, array $workshopReadiness): array
    {
        $summary = [];

        foreach ($brief['missing_evidence'] ?? [] as $item) {
            $text = $this->nullableString($item['title'] ?? null)
                ?? $this->nullableString($item['rationale'] ?? null);

            if ($text === null) {
                continue;
            }

            $refs = is_array($item['source_refs'] ?? null) ? $item['source_refs'] : [];
            $summary[] = $this->sourceBackedText($text, $refs);
        }

        foreach ($workshopReadiness['gaps'] ?? [] as $gap) {
            // Prefer the gap's full description — a self-explanatory sentence that
            // names what is missing and what to do — over its bare label fragment
            // ("Sales perspective"), which never explains the blocker on its own.
            $text = $this->nullableString($gap['description'] ?? null)
                ?? $this->nullableString($gap['label'] ?? null);

            if ($text === null) {
                continue;
            }

            $citationIds = array_values(array_filter(
                is_array($gap['citation_ids'] ?? null) ? $gap['citation_ids'] : [],
                'is_string',
            ));

            $summary[] = [
                'text' => $text,
                'provenance' => 'deterministic',
                'source_refs' => [],
                'citation_ids' => $citationIds,
            ];
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @param  array<string, array{item_id: string, position: int}>  $runOfShowByField
     * @param  array<int, array<string, mixed>>  $runOfShow
     * @return array<string, mixed>|null
     */
    private function buildOpeningPlan(array $decisions, array $runOfShowByField, array $runOfShow): ?array
    {
        if ($decisions === []) {
            return null;
        }

        $first = $decisions[0];
        $openingQuestion = $first['start_with'] ?? $first['decision_question'];

        // Omit the opening plan when its question only restates the first decision.
        // start_with and decision_question commonly derive from the same workshop
        // prompt, so the "open with" block would just echo decision-index row 1. A
        // distinct opener — e.g. a run-of-show segment title — is what earns the block.
        $opener = mb_strtolower(trim((string) ($openingQuestion['text'] ?? '')));
        $firstQuestion = mb_strtolower(trim((string) ($first['decision_question']['text'] ?? '')));
        if ($opener === $firstQuestion) {
            return null;
        }

        $timeboxNote = null;
        $itemId = $first['run_of_show_item_id'] ?? null;
        if ($itemId !== null) {
            foreach ($runOfShow as $item) {
                if ((string) ($item['id'] ?? '') === $itemId) {
                    $minutes = (int) ($item['duration_minutes'] ?? 0);
                    if ($minutes > 0) {
                        $timeboxNote = "{$minutes} min planned for this decision.";
                    }
                    break;
                }
            }
        }

        return [
            'first_decision_id' => $first['id'],
            'opening_question' => $openingQuestion,
            'fallback_question' => null,
            'setup_note' => null,
            'timebox_note' => $timeboxNote,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $runOfShow
     * @param  array<int, array<string, mixed>>  $decisionsToLock
     * @return array<string, array{item_id: string, position: int, title: string}>
     */
    private function runOfShowByFieldKey(array $runOfShow, array $decisionsToLock): array
    {
        $fieldKeysByDecisionId = [];
        foreach ($decisionsToLock as $decision) {
            $id = (string) ($decision['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $fieldKeysByDecisionId[$id] = array_values(array_filter(
                is_array($decision['field_keys'] ?? null) ? $decision['field_keys'] : [],
                'is_string',
            ));
        }

        $byField = [];
        foreach ($runOfShow as $index => $item) {
            $itemId = (string) ($item['id'] ?? '');
            $position = (int) ($item['position'] ?? $index);
            $title = (string) ($item['title'] ?? '');

            foreach ($item['decision_ids'] ?? [] as $decisionId) {
                foreach ($fieldKeysByDecisionId[(string) $decisionId] ?? [] as $fieldKey) {
                    if (! isset($byField[$fieldKey])) {
                        $byField[$fieldKey] = [
                            'item_id' => $itemId,
                            'position' => $position,
                            'title' => $title,
                        ];
                    }
                }
            }
        }

        return $byField;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRefs
     * @return array<string, mixed>
     */
    private function sourceBackedText(string $text, array $sourceRefs): array
    {
        [$refs, $citationIds] = $this->aggregateRefs($sourceRefs);

        return [
            'text' => $text,
            'provenance' => 'deterministic',
            'source_refs' => $refs,
            'citation_ids' => $citationIds,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRefs
     * @return array<string, mixed>
     */
    private function facilitationRisk(
        string $id,
        ?string $decisionId,
        string $severity,
        string $label,
        string $mitigation,
        array $sourceRefs,
    ): array {
        [$refs, $citationIds] = $this->aggregateRefs($sourceRefs);

        return [
            'id' => $id,
            'decision_id' => $decisionId,
            'severity' => $severity,
            'label' => $label,
            'mitigation' => $mitigation,
            'source_refs' => $refs,
            'citation_ids' => $citationIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function captureItem(string $id, string $decisionId, string $label, string $reason): array
    {
        return [
            'id' => $id,
            'decision_id' => $decisionId,
            'label' => $label,
            'reason' => $reason,
            'required' => true,
            'source_refs' => [],
            'citation_ids' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRefs
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function aggregateRefs(array $sourceRefs): array
    {
        $unique = [];
        foreach ($sourceRefs as $ref) {
            if (! is_array($ref)) {
                continue;
            }
            $id = $this->nullableString($ref['id'] ?? null);
            if ($id === null) {
                continue;
            }
            $unique[$id] = $ref;
        }

        return [array_values($unique), array_keys($unique)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRefs
     * @return array<int, string>
     */
    private function citationIds(array $sourceRefs): array
    {
        return $this->aggregateRefs($sourceRefs)[1];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(array $items, string $key): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $value = $this->nullableString($item[$key] ?? null);
            if ($value !== null && ! isset($indexed[$value])) {
                $indexed[$value] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBy(array $items, string $key): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $value = $this->nullableString($item[$key] ?? null);
            if ($value === null) {
                continue;
            }
            $grouped[$value][] = $item;
        }

        return $grouped;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
