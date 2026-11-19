<?php

namespace App\Support\Metrics\Drafting;

use JsonException;
use RuntimeException;

final readonly class MetricAlignmentDraftPromptBuilder
{
    /**
     * @param  array<string, mixed>  $brief
     */
    public function build(array $brief): string
    {
        $bindings = MetricAlignmentDraftSourceIndex::fromBrief($brief)->allowedBindings();

        try {
            $inputJson = json_encode(
                $brief,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $bindingsJson = json_encode(
                $bindings,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Could not encode the deterministic metric alignment brief for drafting.',
                previous: $exception,
            );
        }

        $promptVersion = (string) config('metric-alignment-drafting.prompt_version', '1.0.0');

        return <<<PROMPT
Generate one review-only Generated Draft Brief from this deterministic metric alignment brief.

Prompt contract version: {$promptVersion}

Prioritize material conflicts first, then missing evidence, then decisions needing confirmation.

Allowed ID bindings:
{$bindingsJson}

Deterministic metric alignment brief JSON:
{$inputJson}
PROMPT;
    }
}
