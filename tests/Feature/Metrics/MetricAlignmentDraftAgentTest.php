<?php

use App\Ai\Agents\MetricAlignmentDraftAgent;

it('contains output limits and concision rules in instructions', function () {
    $instructions = (string) app(MetricAlignmentDraftAgent::class)->instructions();

    expect($instructions)
        ->toContain('Output limits:')
        ->toContain('Return at most 5 resolution_focus items and at most 5 supporting_context items.')
        ->toContain('For each resolution_focus item, return at most 3 facilitator_questions and at most 3 candidate_resolution_paths.')
        ->toContain('Keep summary.text, synthesis, reason, description, and note to 1-2 concise sentences each.')
        ->toContain('Preserve source-backed context needed for the decision. Do not omit required questions, grounded paths, disagreement, missing evidence, or uncertainty merely to be brief.')
        ->toContain('Keep the brief concise and useful for a consultant preparing to facilitate a decision.')
        ->toContain('A supporting_context item with kind context_signal may reference only source item IDs from context_signals.')
        ->toContain('An item with kind likely_non_issue may reference only source item IDs from likely_non_issues.')
        ->toContain('Never reference source items from material_conflicts, missing_evidence, or decision_needed in supporting_context.');
});
