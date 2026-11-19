<?php

namespace App\Actions\Metrics;

use App\Actions\Workshops\BuildWorkshopPayload;
use App\Models\Metric;
use App\Support\Metrics\AlignmentSignal;
use App\Support\Metrics\FieldOptionLabelResolver;
use App\Support\Metrics\FieldRoleSchema;

class BuildMetricAlignmentBrief
{
    /**
     * Build the alignment brief completely, loading necessary relationships.
     */
    public function build(Metric $metric): array
    {
        $metric->load([
            'definitions' => function ($query) {
                $query->where('status', 'Submitted')->with('user');
            },
            'template',
            'discrepancies',
        ]);

        $rolesPayload = $this->resolveFieldRolesAndUnclassified($metric);

        return $this->handle($metric, $rolesPayload['field_roles']);
    }

    /**
     * Resolve field roles and unclassified fields for the metric.
     *
     * @return array{field_roles: array<int, array<string, mixed>>, unclassified: array<int, string>}
     */
    public function resolveFieldRolesAndUnclassified(Metric $metric): array
    {
        $fieldRolePayload = FieldRoleSchema::forMetric($metric);
        $discrepanciesByField = $metric->discrepancies->keyBy('field_key');
        $fieldRoles = collect($fieldRolePayload['field_roles'])
            ->map(function ($field) use ($metric, $discrepanciesByField) {
                $fieldKey = $field['key'];
                $discrepancy = $discrepanciesByField->get($fieldKey);
                $values = [];

                foreach ($metric->definitions->where('status', 'Submitted') as $definition) {
                    $normalizedField = $definition->normalizedField($fieldKey);

                    if ($normalizedField === null) {
                        continue;
                    }

                    $values[] = [
                        'definition_id' => (string) $definition->id,
                        'raw' => $normalizedField['raw'] ?? null,
                        'normalized' => $normalizedField['normalized'] ?? null,
                        'other_text' => $normalizedField['other_text'] ?? null,
                        'other_text_normalized' => $normalizedField['other_text_normalized'] ?? null,
                        'user' => $definition->user?->only(['id', 'name']),
                        'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                    ];
                }

                return [
                    'key' => $fieldKey,
                    'label' => $field['label'],
                    'role' => $field['role'],
                    'required' => $field['required'] ?? false,
                    'exclude_from_consensus' => $field['exclude_from_consensus'] ?? false,
                    'companion_of' => $field['companion_of'] ?? null,
                    'cue_context_scope' => $field['cue_context_scope'] ?? null,
                    'executive_section' => $field['executive_section'] ?? null,
                    'value' => $field['value'] ?? null,
                    'unclassified' => $field['unclassified'] ?? false,
                    'workshop_prompt' => $field['workshop_prompt'] ?? null,
                    'workshop_guidance' => $field['workshop_guidance'] ?? null,
                    'alignment_signal' => $discrepancy
                        ? AlignmentSignal::fromDiscrepancy($discrepancy)->toArray()
                        : null,
                    'values' => $values,
                ];
            })
            ->values()
            ->all();

        return [
            'field_roles' => $fieldRoles,
            'unclassified' => $fieldRolePayload['unclassified'],
        ];
    }

    /**
     * Build the metric alignment brief.
     */
    public function handle(Metric $metric, array $fieldRoles): array
    {
        $optionLabelMap = FieldOptionLabelResolver::forMetric($metric);
        $affiliationByDefinitionId = $this->affiliationByDefinitionId($metric, $fieldRoles);

        $stakeholderPositions = [];
        $materialConflicts = [];
        $decisionNeeded = [];
        $missingEvidence = [];
        $contextSignals = [];
        $likelyNonIssues = [];
        $recommendedQuestions = [];
        $resolutionOptions = [];
        $categoriesByKey = [];

        foreach ($fieldRoles as $field) {
            $fieldKey = $field['key'];
            $fieldLabel = $field['label'];
            $role = $field['role'] ?? 'unknown';

            // Build stakeholder positions for this field
            $fieldPositions = $this->buildStakeholderPositions(
                $field,
                $optionLabelMap,
                $affiliationByDefinitionId,
            );
            $stakeholderPositions = array_merge($stakeholderPositions, $fieldPositions);

            // Determine classification
            $category = $this->classifyField($field);
            $categoriesByKey[$fieldKey] = $category;
            if (! $category) {
                continue;
            }

            // Build source references
            $sourceRefs = $this->sourceRefsForField($field, $fieldPositions);

            // Create attention item
            $attentionItem = [
                'id' => "field:{$fieldKey}:{$category}",
                'category' => $category,
                'field_key' => $fieldKey,
                'field_label' => $fieldLabel,
                'field_role' => $role,
                'severity' => $field['alignment_signal']['severity'] ?? ($category === 'material_conflict' ? 'medium' : 'low'),
                'title' => $this->titleForCategory($category, $fieldLabel),
                'rationale' => $this->rationaleForCategory($category, $field),
                'stakeholder_positions' => $fieldPositions,
                'source_refs' => $sourceRefs,
            ];

            // Distribute attention item to correct array
            switch ($category) {
                case 'material_conflict':
                    $materialConflicts[] = $attentionItem;
                    break;
                case 'decision_needed':
                    $decisionNeeded[] = $attentionItem;
                    break;
                case 'missing_evidence':
                    $missingEvidence[] = $attentionItem;
                    break;
                case 'context_signal':
                    $contextSignals[] = $attentionItem;
                    break;
                case 'likely_non_issue':
                    $likelyNonIssues[] = $attentionItem;
                    break;
            }

            // Build recommended question if needed
            if (in_array($category, ['material_conflict', 'decision_needed', 'missing_evidence'], true)) {
                $recommendedQuestions[] = [
                    'id' => "question:{$fieldKey}",
                    'field_key' => $fieldKey,
                    'question' => is_string($field['workshop_prompt'] ?? null) && trim($field['workshop_prompt']) !== ''
                        ? trim($field['workshop_prompt'])
                        : "Agree on the official {$fieldLabel}.",
                    'reason' => $this->questionReasonForCategory($category),
                    'priority' => $this->questionPriorityForCategory($category),
                    'source_refs' => $sourceRefs,
                ];
            }

            // Build resolution options if in conflict or decision_needed
            if (in_array($category, ['material_conflict', 'decision_needed'], true)) {
                // Resolution option for each unique stakeholder position
                $seenValues = [];
                foreach ($fieldPositions as $pos) {
                    $normVal = $pos['normalized_value'];
                    $serialized = is_array($normVal) ? json_encode($normVal) : (string) $normVal;
                    if (in_array($serialized, $seenValues, true)) {
                        continue;
                    }
                    $seenValues[] = $serialized;

                    $resolutionOptions[] = [
                        'id' => "option:{$fieldKey}:use-{$pos['definition_id']}",
                        'field_key' => $fieldKey,
                        'label' => 'Use '.($pos['stakeholder']['name'] ?? 'Stakeholder').' position',
                        'description' => "Adopt the submitted value '".$pos['display_value']."' as the official definition.",
                        'source_refs' => $pos['source_refs'],
                    ];
                }
            }
        }

        // Calculate source counts
        $submittedAnswersCount = $metric->definitions->where('status', 'Submitted')->count();
        $alignmentSignalsCount = collect($fieldRoles)->filter(fn ($f) => ! empty($f['alignment_signal']))->count();
        $guidedFieldsCount = collect($fieldRoles)->filter(fn ($f) => ! empty($f['workshop_prompt']) || ! empty($f['workshop_guidance']))->count();
        $missingEvidenceItemsCount = count($missingEvidence);

        $companionAnswers = $this->buildCompanionAnswerGroups(
            $fieldRoles,
            $optionLabelMap,
            $affiliationByDefinitionId,
        );
        $cueContexts = $this->buildCueContexts(
            $fieldRoles,
            $optionLabelMap,
            $affiliationByDefinitionId,
            $companionAnswers,
            $categoriesByKey,
        );

        return [
            'summary' => [
                'metric_id' => (string) $metric->id,
                'metric_name' => $metric->name,
                'status' => $metric->status instanceof \UnitEnum ? $metric->status->value : (string) $metric->status,
                'source_counts' => [
                    'submitted_answers' => $submittedAnswersCount,
                    'alignment_signals' => $alignmentSignalsCount,
                    'guided_fields' => $guidedFieldsCount,
                    'missing_evidence_items' => $missingEvidenceItemsCount,
                ],
            ],
            'stakeholder_positions' => $stakeholderPositions,
            'companion_answers' => $companionAnswers,
            'cue_contexts' => $cueContexts,
            'workshop_cues' => [],
            'material_conflicts' => $materialConflicts,
            'decision_needed' => $decisionNeeded,
            'missing_evidence' => $missingEvidence,
            'context_signals' => $contextSignals,
            'likely_non_issues' => $likelyNonIssues,
            'recommended_questions' => $recommendedQuestions,
            'resolution_options' => $resolutionOptions,
            'draft_alignment_summary' => null,
        ];
    }

    private function buildStakeholderPositions(
        array $field,
        array $optionLabelMap,
        array $affiliationByDefinitionId = [],
    ): array {
        $fieldKey = $field['key'];
        $fieldLabel = $field['label'];
        $positions = [];

        foreach ($field['values'] ?? [] as $val) {
            $displayValue = $this->buildDisplayValue($fieldKey, $val, $optionLabelMap);

            // Build the submitted answer reference for this specific position
            $ref = [
                'id' => "submitted_answer:{$val['definition_id']}:{$fieldKey}",
                'type' => 'submitted_answer',
                'label' => ($val['user']['name'] ?? 'Stakeholder').' answer',
                'field_key' => $fieldKey,
                'source_id' => $val['definition_id'],
                'excerpt' => $displayValue,
                'meta' => [
                    'submitted_at' => $val['submitted_at'] ?? null,
                ],
            ];

            $positions[] = [
                'field_key' => $fieldKey,
                'field_label' => $fieldLabel,
                'stakeholder' => [
                    'id' => $val['user']['id'] ?? null,
                    'name' => $val['user']['name'] ?? 'Stakeholder',
                    'affiliation' => $affiliationByDefinitionId[$val['definition_id']] ?? null,
                ],
                'definition_id' => $val['definition_id'],
                'raw_value' => $val['raw'] ?? null,
                'normalized_value' => $val['normalized'] ?? null,
                'display_value' => $displayValue,
                'submitted_at' => $val['submitted_at'] ?? null,
                'source_refs' => [$ref],
            ];
        }

        return $positions;
    }

    /**
     * Group companion submitted answers under the primary field they explain.
     *
     * Companion relationships come from the explicit classification.companion_of
     * metadata surfaced on each field role (see FieldRoleSchema), never from
     * field-name suffix inference. Raw submitted answers are reused verbatim,
     * including their existing submitted_answer source refs, so this is an
     * additional grouped view over evidence the brief already exposes — it never
     * rewrites the source answers. Blank companion answers are omitted.
     *
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @param  array<string, array<string, string>>  $optionLabelMap
     * @param  array<string, string>  $affiliationByDefinitionId
     * @return array<int, array{primary_field_key: string, companion_field_key: string, companion_field_label: string, companion_field_role: string, answers: array<int, array{definition_id: mixed, stakeholder: array{id: mixed, name: string}, display_value: string, raw_value: mixed, normalized_value: mixed, submitted_at: ?string, source_refs: array<int, array<string, mixed>>}>}>
     */
    private function buildCompanionAnswerGroups(
        array $fieldRoles,
        array $optionLabelMap,
        array $affiliationByDefinitionId = [],
    ): array {
        $existingFieldKeys = [];
        foreach ($fieldRoles as $field) {
            $key = $field['key'] ?? null;
            if (is_string($key) && $key !== '') {
                $existingFieldKeys[$key] = true;
            }
        }

        $groups = [];

        foreach ($fieldRoles as $field) {
            $companionKey = $field['key'] ?? null;
            if (! is_string($companionKey) || $companionKey === '') {
                continue;
            }

            $companionOf = $field['companion_of'] ?? null;
            if (! is_string($companionOf) || $companionOf === '') {
                continue;
            }

            // Only group under a primary field that exists on this metric.
            if (! isset($existingFieldKeys[$companionOf])) {
                continue;
            }

            $answers = [];
            foreach (
                $this->buildStakeholderPositions(
                    $field,
                    $optionLabelMap,
                    $affiliationByDefinitionId,
                ) as $position
            ) {
                if (
                    $this->isBlankValue($position['raw_value'])
                    && $this->isBlankValue($position['normalized_value'])
                ) {
                    continue;
                }

                $answers[] = [
                    'definition_id' => $position['definition_id'],
                    'stakeholder' => $position['stakeholder'],
                    'display_value' => $position['display_value'],
                    'raw_value' => $position['raw_value'],
                    'normalized_value' => $position['normalized_value'],
                    'submitted_at' => $position['submitted_at'],
                    'source_refs' => $position['source_refs'],
                ];
            }

            if ($answers === []) {
                continue;
            }

            $groups[] = [
                'primary_field_key' => $companionOf,
                'companion_field_key' => $companionKey,
                'companion_field_label' => is_string($field['label'] ?? null) ? $field['label'] : $companionKey,
                'companion_field_role' => is_string($field['role'] ?? null) ? $field['role'] : 'unknown',
                'answers' => $answers,
            ];
        }

        return $groups;
    }

    /**
     * Source-backed affiliation by definition.
     *
     * Affiliation is resolved from each respondent's engagement membership — the
     * canonical system of record mirrored by
     * {@see BuildWorkshopPayload::resolveParticipantAffiliation()}
     * — so the `<name> (<team>)` respondent label works on every metric, not only
     * templates that happen to collect a `team_department` field. A submitted
     * `team_department`-style field remains an optional fallback, used only when a
     * respondent has no membership affiliation.
     *
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @return array<string, string>
     */
    private function affiliationByDefinitionId(Metric $metric, array $fieldRoles): array
    {
        $affiliations = $this->membershipAffiliationsByDefinitionId($metric);

        foreach ($this->teamDepartmentAffiliationsByDefinitionId($fieldRoles) as $definitionId => $affiliation) {
            if (! isset($affiliations[$definitionId])) {
                $affiliations[$definitionId] = $affiliation;
            }
        }

        return $affiliations;
    }

    /**
     * Map each definition to its respondent's canonical membership affiliation,
     * joined through the metric participant that links the submitting user to the
     * engagement membership. Relations are eager-loaded once, so there is no N+1
     * over respondents.
     *
     * @return array<string, string>
     */
    private function membershipAffiliationsByDefinitionId(Metric $metric): array
    {
        $metric->loadMissing([
            'definitions',
            'participants.engagementParticipant.membership',
            'participants.engagementParticipant.pendingMembership',
        ]);

        $affiliationByUserId = [];

        foreach ($metric->participants as $participant) {
            $userId = $participant->user_id;
            if ($userId === null) {
                continue;
            }

            $engagementParticipant = $participant->engagementParticipant;
            $affiliation = $this->stringValue(
                $engagementParticipant?->membership?->affiliation
                    ?? $engagementParticipant?->pendingMembership?->affiliation,
            );

            if ($affiliation !== null) {
                $affiliationByUserId[(string) $userId] = $affiliation;
            }
        }

        if ($affiliationByUserId === []) {
            return [];
        }

        $affiliations = [];

        foreach ($metric->definitions as $definition) {
            $userId = $definition->user_id;
            if ($userId === null) {
                continue;
            }

            $affiliation = $affiliationByUserId[(string) $userId] ?? null;
            if ($affiliation !== null) {
                $affiliations[(string) $definition->getKey()] = $affiliation;
            }
        }

        return $affiliations;
    }

    /**
     * Fallback affiliation from a submitted `team_department`-style meta field,
     * keyed by definition. Used only when a respondent has no membership
     * affiliation.
     *
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @return array<string, string>
     */
    private function teamDepartmentAffiliationsByDefinitionId(array $fieldRoles): array
    {
        $teamDepartmentField = collect($fieldRoles)->firstWhere('key', 'team_department');

        if (! is_array($teamDepartmentField)) {
            return [];
        }

        $affiliations = [];

        foreach ($teamDepartmentField['values'] ?? [] as $value) {
            $definitionId = $value['definition_id'] ?? null;
            if (! is_string($definitionId) || $definitionId === '') {
                continue;
            }

            $affiliation = $this->stringValue(
                $value['raw'] ?? $value['normalized'] ?? null,
            );

            if ($affiliation !== null) {
                $affiliations[$definitionId] = $affiliation;
            }
        }

        return $affiliations;
    }

    /**
     * Deterministic, source-backed cue context for each field-bound prep
     * segment. This becomes the future AI input contract: current field answers,
     * companion answers, respondent roles/teams, template guidance, and any
     * explicitly flagged matrix-wide fields with source-backed submitted values.
     *
     * @param  array<int, array<string, mixed>>  $fieldRoles
     * @param  array<string, array<string, string>>  $optionLabelMap
     * @param  array<string, string>  $affiliationByDefinitionId
     * @param  array<int, array<string, mixed>>  $companionAnswers
     * @param  array<string, ?string>  $categoriesByKey
     * @return array<int, array{field_key: string, field_label: string, field_role: string, stakeholder_positions: array<int, array<string, mixed>>, companion_answers: array<int, array<string, mixed>>, workshop_guidance: array{text: string, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}|null, matrix_context: array<int, array{field_key: string, field_label: string, field_role: string, stakeholder_positions: array<int, array<string, mixed>>, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}>, citation_ids: array<int, string>}>
     */
    private function buildCueContexts(
        array $fieldRoles,
        array $optionLabelMap,
        array $affiliationByDefinitionId,
        array $companionAnswers,
        array $categoriesByKey,
    ): array {
        $companionGroupsByPrimary = [];
        foreach ($companionAnswers as $group) {
            $primaryFieldKey = $group['primary_field_key'] ?? null;

            if (is_string($primaryFieldKey) && $primaryFieldKey !== '') {
                $companionGroupsByPrimary[$primaryFieldKey][] = $group;
            }
        }

        $matrixWideFields = array_values(array_filter(
            $fieldRoles,
            fn (array $field): bool => ($field['cue_context_scope'] ?? null) === 'matrix_wide',
        ));

        // The matrix-wide context is identical for every cue context (each cue
        // only excludes its own field), so build each matrix field's positions
        // once here instead of rebuilding them inside the per-field loop below.
        $matrixWideContextByField = $this->buildMatrixWideContext(
            $matrixWideFields,
            $optionLabelMap,
            $affiliationByDefinitionId,
        );

        $contexts = [];

        foreach ($fieldRoles as $field) {
            if (! $this->shouldBuildCueContext($field, $categoriesByKey)) {
                continue;
            }

            $fieldKey = $field['key'] ?? null;

            if (! is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            $stakeholderPositions = $this->sourceBackedPositions(
                $this->buildStakeholderPositions(
                    $field,
                    $optionLabelMap,
                    $affiliationByDefinitionId,
                ),
            );

            $workshopGuidance = $this->cueWorkshopGuidance($field);
            $matrixContext = $this->matrixWideCueContext(
                $matrixWideContextByField,
                $fieldKey,
            );

            $citationIds = [];
            foreach ($stakeholderPositions as $position) {
                $citationIds = array_merge(
                    $citationIds,
                    $this->citationIdsFromSourceRefs($position['source_refs'] ?? []),
                );
            }
            foreach ($companionGroupsByPrimary[$fieldKey] ?? [] as $group) {
                foreach ($group['answers'] ?? [] as $answer) {
                    $citationIds = array_merge(
                        $citationIds,
                        $this->citationIdsFromSourceRefs($answer['source_refs'] ?? []),
                    );
                }
            }
            if ($workshopGuidance !== null) {
                $citationIds = array_merge(
                    $citationIds,
                    $workshopGuidance['citation_ids'],
                );
            }
            foreach ($matrixContext as $contextField) {
                $citationIds = array_merge(
                    $citationIds,
                    $contextField['citation_ids'] ?? [],
                );
            }

            $contexts[] = [
                'field_key' => $fieldKey,
                'field_label' => is_string($field['label'] ?? null) ? $field['label'] : $fieldKey,
                'field_role' => is_string($field['role'] ?? null) ? $field['role'] : 'unknown',
                'stakeholder_positions' => $stakeholderPositions,
                'companion_answers' => $companionGroupsByPrimary[$fieldKey] ?? [],
                'workshop_guidance' => $workshopGuidance,
                'matrix_context' => $matrixContext,
                'citation_ids' => array_values(array_unique($citationIds)),
                'alignment_signal' => $field['alignment_signal'] ?? null,
            ];
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, ?string>  $categoriesByKey
     */
    private function shouldBuildCueContext(array $field, array $categoriesByKey): bool
    {
        $fieldKey = $field['key'] ?? null;
        $category = $categoriesByKey[$fieldKey] ?? null;

        return in_array(
            $category,
            ['material_conflict', 'decision_needed', 'missing_evidence'],
            true,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @return array<int, array<string, mixed>>
     */
    private function sourceBackedPositions(array $positions): array
    {
        return array_values(array_filter(
            $positions,
            fn (array $position): bool => ! (
                $this->isBlankValue($position['raw_value'] ?? null)
                && $this->isBlankValue($position['normalized_value'] ?? null)
            ),
        ));
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{text: string, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}|null
     */
    private function cueWorkshopGuidance(array $field): ?array
    {
        $fieldKey = $field['key'] ?? null;
        $guidance = $this->stringValue($field['workshop_guidance'] ?? null);

        if (! is_string($fieldKey) || $fieldKey === '' || $guidance === null) {
            return null;
        }

        $sourceRef = [
            'id' => "field_guidance:{$fieldKey}:guidance",
            'type' => 'field_guidance',
            'label' => 'Workshop guidance',
            'field_key' => $fieldKey,
            'source_id' => null,
            'excerpt' => $guidance,
            'meta' => [
                'guidance_kind' => 'workshop_guidance',
            ],
        ];

        return [
            'text' => $guidance,
            'source_refs' => [$sourceRef],
            'citation_ids' => [$sourceRef['id']],
        ];
    }

    /**
     * Build each matrix-wide field's source-backed context once, keyed by field
     * key. Fields with no source-backed positions are omitted entirely.
     *
     * @param  array<int, array<string, mixed>>  $matrixWideFields
     * @param  array<string, array<string, string>>  $optionLabelMap
     * @param  array<string, string>  $affiliationByDefinitionId
     * @return array<string, array{field_key: string, field_label: string, field_role: string, stakeholder_positions: array<int, array<string, mixed>>, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}>
     */
    private function buildMatrixWideContext(
        array $matrixWideFields,
        array $optionLabelMap,
        array $affiliationByDefinitionId,
    ): array {
        $contextByField = [];

        foreach ($matrixWideFields as $field) {
            $fieldKey = $field['key'] ?? null;

            if (! is_string($fieldKey) || $fieldKey === '') {
                continue;
            }

            $positions = $this->sourceBackedPositions(
                $this->buildStakeholderPositions(
                    $field,
                    $optionLabelMap,
                    $affiliationByDefinitionId,
                ),
            );

            if ($positions === []) {
                continue;
            }

            $sourceRefs = $this->sourceRefsFromPositions($positions);

            $contextByField[$fieldKey] = [
                'field_key' => $fieldKey,
                'field_label' => is_string($field['label'] ?? null) ? $field['label'] : $fieldKey,
                'field_role' => is_string($field['role'] ?? null) ? $field['role'] : 'unknown',
                'stakeholder_positions' => $positions,
                'source_refs' => $sourceRefs,
                'citation_ids' => $this->citationIdsFromSourceRefs($sourceRefs),
            ];
        }

        return $contextByField;
    }

    /**
     * Select the matrix-wide context for one cue, excluding the cue's own field.
     *
     * @param  array<string, array{field_key: string, field_label: string, field_role: string, stakeholder_positions: array<int, array<string, mixed>>, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}>  $matrixWideContextByField
     * @return array<int, array{field_key: string, field_label: string, field_role: string, stakeholder_positions: array<int, array<string, mixed>>, source_refs: array<int, array<string, mixed>>, citation_ids: array<int, string>}>
     */
    private function matrixWideCueContext(
        array $matrixWideContextByField,
        string $currentFieldKey,
    ): array {
        $context = [];

        foreach ($matrixWideContextByField as $fieldKey => $contextField) {
            if ($fieldKey === $currentFieldKey) {
                continue;
            }

            $context[] = $contextField;
        }

        return $context;
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     * @return array<int, array<string, mixed>>
     */
    private function sourceRefsFromPositions(array $positions): array
    {
        $refs = [];

        foreach ($positions as $position) {
            $refs = array_merge($refs, $position['source_refs'] ?? []);
        }

        return $refs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sourceRefs
     * @return array<int, string>
     */
    private function citationIdsFromSourceRefs(array $sourceRefs): array
    {
        $ids = [];

        foreach ($sourceRefs as $sourceRef) {
            $id = $sourceRef['id'] ?? null;

            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function isBlankValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }

    private function sourceRefsForField(array $field, array $fieldPositions): array
    {
        $fieldKey = $field['key'];
        $refs = [];

        // 1. Submitted answers (from stakeholder positions)
        foreach ($fieldPositions as $pos) {
            $refs = array_merge($refs, $pos['source_refs']);
        }

        // 2. Field guidance - prompt
        if (! empty($field['workshop_prompt'])) {
            $refs[] = [
                'id' => "field_guidance:{$fieldKey}:prompt",
                'type' => 'field_guidance',
                'label' => 'Workshop prompt',
                'field_key' => $fieldKey,
                'source_id' => null,
                'excerpt' => $field['workshop_prompt'],
                'meta' => [
                    'guidance_kind' => 'workshop_prompt',
                ],
            ];
        }

        // 3. Field guidance - guidance
        if (! empty($field['workshop_guidance'])) {
            $refs[] = [
                'id' => "field_guidance:{$fieldKey}:guidance",
                'type' => 'field_guidance',
                'label' => 'Workshop guidance',
                'field_key' => $fieldKey,
                'source_id' => null,
                'excerpt' => $field['workshop_guidance'],
                'meta' => [
                    'guidance_kind' => 'workshop_guidance',
                ],
            ];
        }

        // 3. Detector signal from the alignment signal source
        if (! empty($field['alignment_signal'])) {
            $sig = $field['alignment_signal'];
            $refs[] = [
                'id' => "alignment_signal:{$sig['id']}:{$fieldKey}",
                'type' => 'alignment_signal',
                'label' => 'Detector signal',
                'field_key' => $fieldKey,
                'source_id' => $sig['id'],
                'excerpt' => $sig['description'] ?? 'Field evidence needs facilitator review.',
            ];
        }

        // Fallback reference if we have absolutely nothing
        if (empty($refs)) {
            $refs[] = [
                'id' => "field_schema:{$fieldKey}",
                'type' => 'field_guidance',
                'label' => 'Field schema',
                'field_key' => $fieldKey,
                'source_id' => null,
                'excerpt' => "Field '{$field['label']}' from template.",
            ];
        }

        return $refs;
    }

    private function classifyField(array $field): ?string
    {
        $role = $field['role'] ?? 'unknown';
        $required = (bool) ($field['required'] ?? false);
        $excludeFromConsensus = (bool) ($field['exclude_from_consensus'] ?? false);
        $hasGuidance = ! empty($field['workshop_prompt']) || ! empty($field['workshop_guidance']);
        $submittedCount = count($field['values'] ?? []);
        $hasDivergent = $this->hasDivergentNormalizedValues($field);
        $hasSignal = ! empty($field['alignment_signal']);

        // Excluded fields should never be material_conflict, decision_needed, or missing_evidence
        if ($excludeFromConsensus) {
            if (in_array($role, ['context', 'diagnostic'], true) && ($submittedCount > 0 || $hasGuidance || $hasSignal)) {
                return 'context_signal';
            }

            return 'likely_non_issue';
        }

        // 1. missing_evidence
        if (($required || $hasGuidance) && $submittedCount < 2) {
            return 'missing_evidence';
        }

        // 2. material_conflict
        if (in_array($role, ['definition', 'governance'], true) && ($hasSignal || $hasDivergent)) {
            return 'material_conflict';
        }

        // 3. decision_needed
        if ($hasGuidance) {
            return 'decision_needed';
        }

        // 4. context_signal
        if (in_array($role, ['context', 'diagnostic'], true) && ($submittedCount > 0 || $hasGuidance || $hasSignal)) {
            return 'context_signal';
        }

        // 5. likely_non_issue
        return 'likely_non_issue';
    }

    private function hasDivergentNormalizedValues(array $field): bool
    {
        $values = $field['values'] ?? [];
        if (count($values) < 2) {
            return false;
        }

        $first = $values[0]['normalized'] ?? null;
        for ($i = 1; $i < count($values); $i++) {
            if (! $this->valuesAreEqual($first, $values[$i]['normalized'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function valuesAreEqual($a, $b): bool
    {
        if (is_array($a) && is_array($b)) {
            sort($a);
            sort($b);

            return $a === $b;
        }

        return $a === $b;
    }

    private function resolveDisplayValue(string $fieldKey, mixed $value, array $optionLabelMap): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_array($value)) {
            $mapped = array_map(function ($val) use ($fieldKey, $optionLabelMap) {
                return $this->resolveDisplayValue($fieldKey, $val, $optionLabelMap);
            }, $value);

            return implode(', ', $mapped);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $strVal = (string) $value;
        if (isset($optionLabelMap[$fieldKey][$strVal])) {
            return $optionLabelMap[$fieldKey][$strVal];
        }

        return $strVal;
    }

    private function buildDisplayValue(string $fieldKey, array $val, array $optionLabelMap): string
    {
        $hasOptions = isset($optionLabelMap[$fieldKey]);
        $raw = $val['raw'] ?? null;
        $normalized = $val['normalized'] ?? null;

        if ($hasOptions) {
            // choice-based field: use normalized for option-label mapping / comparison
            $valueToResolve = $normalized ?? $raw;
            $displayValue = $this->resolveDisplayValue($fieldKey, $valueToResolve, $optionLabelMap);

            $otherText = $val['other_text'] ?? null;
            if (is_string($otherText) && $otherText !== '') {
                if ($displayValue !== '' && $displayValue !== '—') {
                    $displayValue .= ' ('.$otherText.')';
                } else {
                    $displayValue = $otherText;
                }
            }

            return $displayValue;
        }

        // free-text field (or boolean / other type): display raw text
        if (is_bool($raw)) {
            return $raw ? 'Yes' : 'No';
        }
        if (is_bool($normalized)) {
            return $normalized ? 'Yes' : 'No';
        }

        if ($raw === null || $raw === '') {
            if ($normalized === null || $normalized === '') {
                return '—';
            }

            return (string) $normalized;
        }

        return (string) $raw;
    }

    private function titleForCategory(string $category, string $fieldLabel): string
    {
        return match ($category) {
            'material_conflict' => "Stakeholders disagree on {$fieldLabel}.",
            'decision_needed' => "Confirm official {$fieldLabel}.",
            'missing_evidence' => "Missing stakeholder answers for {$fieldLabel}.",
            'context_signal' => "Supporting context for {$fieldLabel}.",
            'likely_non_issue' => "No alignment needed for {$fieldLabel}.",
            default => "Review {$fieldLabel}.",
        };
    }

    private function rationaleForCategory(string $category, array $field): string
    {
        return match ($category) {
            'material_conflict' => $this->materialConflictRationale($field),
            'decision_needed' => 'This consensus field has custom workshop guidance that must be confirmed.',
            'missing_evidence' => 'This required or guided field has fewer than two submitted answers to compare.',
            'context_signal' => $this->contextSignalRationale($field),
            'likely_non_issue' => 'This field is either excluded from consensus or has matching positions.',
            default => 'Inspect field details.',
        };
    }

    /**
     * Field-specific rationale for a material conflict so the attention card is
     * informative on its own. Names the field and the concrete divergence (the
     * count of distinct submitted values) without repeating the cue's
     * why_this_cue prose — which already carries the alignment_signal
     * description — or the card's own severity badge.
     *
     * @param  array<string, mixed>  $field
     */
    private function materialConflictRationale(array $field): string
    {
        $fieldLabel = $this->stringValue($field['label'] ?? null) ?? 'this field';
        $distinctCount = $this->distinctNormalizedValueCount($field);

        if ($distinctCount >= 2) {
            return "Stakeholders submitted {$distinctCount} different values for {$fieldLabel}.";
        }

        if (! empty($field['alignment_signal'])) {
            return "A discrepancy signal flags {$fieldLabel} for facilitator review.";
        }

        return "Submitted positions for {$fieldLabel} need facilitator review.";
    }

    /**
     * Count distinct normalized submitted values, mirroring the equality
     * semantics of {@see hasDivergentNormalizedValues()} so multi-select arrays
     * compare by membership rather than order.
     *
     * @param  array<string, mixed>  $field
     */
    private function distinctNormalizedValueCount(array $field): int
    {
        $values = $field['values'] ?? [];
        $distinct = [];

        foreach ($values as $value) {
            $normalized = $value['normalized'] ?? null;

            $alreadySeen = false;
            foreach ($distinct as $seen) {
                if ($this->valuesAreEqual($seen, $normalized)) {
                    $alreadySeen = true;
                    break;
                }
            }

            if (! $alreadySeen) {
                $distinct[] = $normalized;
            }
        }

        return count($distinct);
    }

    /**
     * Resolve a field-specific rationale for a context signal. Falls back to an
     * empty string rather than a generic placeholder so the UI can suppress
     * context items that carry no distinguishing content.
     *
     * @param  array<string, mixed>  $field
     */
    private function contextSignalRationale(array $field): string
    {
        $signalDescription = $field['alignment_signal']['description'] ?? null;
        if (is_string($signalDescription) && trim($signalDescription) !== '') {
            return trim($signalDescription);
        }

        foreach (['workshop_guidance', 'workshop_prompt'] as $guidanceKey) {
            $guidance = $field[$guidanceKey] ?? null;
            if (is_string($guidance) && trim($guidance) !== '') {
                return trim($guidance);
            }
        }

        return '';
    }

    private function questionReasonForCategory(string $category): string
    {
        return match ($category) {
            'material_conflict' => 'Stakeholders submitted different positions.',
            'decision_needed' => 'Field requires workshop confirmation.',
            'missing_evidence' => 'Missing stakeholder answers to compare.',
            default => 'Needs stakeholder alignment.',
        };
    }

    private function questionPriorityForCategory(string $category): string
    {
        return match ($category) {
            'material_conflict' => 'high',
            'decision_needed' => 'medium',
            'missing_evidence' => 'low',
            default => 'medium',
        };
    }
}
