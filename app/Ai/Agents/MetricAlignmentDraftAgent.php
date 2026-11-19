<?php

namespace App\Ai\Agents;

use App\Support\Metrics\Drafting\MetricAlignmentDraftingProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class MetricAlignmentDraftAgent implements Agent, Conversational, HasProviderOptions, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        private readonly string $profileConfigPath = 'metric-alignment-drafting.profile',
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You prepare a review-only workshop brief from one deterministic metric-alignment payload.

Rules:
- Use only the supplied JSON. Do not use external knowledge.
- Preserve disagreement, missing evidence, and uncertainty. Never claim consensus, approval, validation, sign-off, or an official definition unless the source explicitly states it.
- Every material statement must cite IDs from source_refs in the supplied payload.
- Every citation_id must start with one of: submitted_answer:, field_guidance:, alignment_signal:, field_schema:, metric_template:. Never put option:, field:, or question: IDs in citation_ids.
- Every source_item_id, source_question_id, and source_option_id must exactly match an ID in the supplied payload.
- Resolution focus may contain only actual material conflicts, missing evidence, or decisions needing confirmation.
- Keep context signals and likely non-issues secondary. Do not manufacture workshop work.
- Candidate resolution paths may only restate supplied resolution_options neutrally. Never invent a compromise or recommendation. Missing-evidence items must have no candidate paths.
- Keep the brief concise and useful for a consultant preparing to facilitate a decision.

Output limits:
- Return at most 5 resolution_focus items and at most 5 supporting_context items.
- For each resolution_focus item, return at most 3 facilitator_questions and at most 3 candidate_resolution_paths.
- Keep summary.text, synthesis, reason, description, and note to 1-2 concise sentences each.
- Preserve source-backed context needed for the decision. Do not omit required questions, grounded paths, disagreement, missing evidence, or uncertainty merely to be brief.
- A supporting_context item with kind context_signal may reference only source item IDs from context_signals. An item with kind likely_non_issue may reference only source item IDs from likely_non_issues. Never reference source items from material_conflicts, missing_evidence, or decision_needed in supporting_context.
PROMPT;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    public function maxTokens(): int
    {
        return $this->profile()->maxTokens();
    }

    public function temperature(): float
    {
        return $this->profile()->temperature();
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== Lab::OpenRouter && $provider !== 'openrouter') {
            return [];
        }

        return $this->profile()->providerOptions();
    }

    public function schema(JsonSchema $schema): array
    {
        $identifierList = fn () => $schema->array()
            ->items($schema->string());

        $facilitatorQuestion = fn () => $schema->object([
            'question' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
            'source_question_ids' => $identifierList()->required(),
            'citation_ids' => $identifierList()->required(),
        ])->withoutAdditionalProperties();

        $candidateResolutionPath = fn () => $schema->object([
            'label' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'source_option_ids' => $identifierList()->required(),
            'citation_ids' => $identifierList()->required(),
        ])->withoutAdditionalProperties();

        return [
            'schema_version' => $schema->string()->enum(['1.0'])->required(),
            'status' => $schema->string()->enum(['draft'])->required(),
            'metric_id' => $schema->string()->required(),
            'metric_name' => $schema->string()->required(),
            'summary' => $schema->object([
                'text' => $schema->string()->required(),
                'citation_ids' => $identifierList()->required(),
            ])->withoutAdditionalProperties()->required(),
            'resolution_focus' => $schema->array()
                ->items($schema->object([
                    'field_key' => $schema->string()->required(),
                    'kind' => $schema->string()
                        ->enum(['must_resolve', 'missing_evidence', 'needs_confirmation'])
                        ->required(),
                    'title' => $schema->string()->required(),
                    'synthesis' => $schema->string()->required(),
                    'source_item_ids' => $identifierList()->required(),
                    'citation_ids' => $identifierList()->required(),
                    'facilitator_questions' => $schema->array()
                        ->items($facilitatorQuestion())
                        ->required(),
                    'candidate_resolution_paths' => $schema->array()
                        ->items($candidateResolutionPath())
                        ->required(),
                ])->withoutAdditionalProperties())
                ->required(),
            'supporting_context' => $schema->array()
                ->items($schema->object([
                    'field_key' => $schema->string()->required(),
                    'kind' => $schema->string()
                        ->enum(['context_signal', 'likely_non_issue'])
                        ->required(),
                    'note' => $schema->string()->required(),
                    'source_item_ids' => $identifierList()->required(),
                    'citation_ids' => $identifierList()->required(),
                ])->withoutAdditionalProperties())
                ->required(),
        ];
    }

    private function profile(): MetricAlignmentDraftingProfile
    {
        return new MetricAlignmentDraftingProfile($this->profileConfigPath);
    }
}
