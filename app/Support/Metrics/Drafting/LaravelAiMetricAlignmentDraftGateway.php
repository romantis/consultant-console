<?php

namespace App\Support\Metrics\Drafting;

use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Support\Result;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final readonly class LaravelAiMetricAlignmentDraftGateway implements MetricAlignmentDraftGateway
{
    public function __construct(
        private MetricAlignmentDraftingProfile $profile,
    ) {}

    /**
     * @return Result<MetricAlignmentDraftResponse, MetricAlignmentDraftFailure>
     */
    public function generate(string $prompt): Result
    {
        $startedAt = hrtime(true);

        try {
            $response = (new MetricAlignmentDraftAgent($this->profile->configPath()))->prompt(
                prompt: $prompt,
                provider: $this->profile->provider(),
                model: $this->profile->model(),
                timeout: $this->profile->timeoutSeconds(),
            );

            if (! $response instanceof StructuredAgentResponse) {
                return Result::err(MetricAlignmentDraftFailure::providerReturnedUnstructured(
                    profileVersion: $this->profile->version(),
                    provider: $this->profile->provider(),
                    requestedModel: $this->profile->model(),
                ));
            }

            return Result::ok(new MetricAlignmentDraftResponse(
                profileVersion: $this->profile->version(),
                provider: $this->profile->provider(),
                requestedModel: $this->profile->model(),
                returnedModel: $response->meta->model,
                elapsedMs: $this->elapsedMilliseconds($startedAt),
                draft: $this->sanitizeCitations($this->normalizeCardinality($response->toArray())),
                usage: $response->usage->toArray(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return Result::err(MetricAlignmentDraftFailure::providerError(
                message: $exception->getMessage(),
                profileVersion: $this->profile->version(),
                provider: $this->profile->provider(),
                requestedModel: $this->profile->model(),
            ));
        }
    }

    /**
     * Recursively traverses draft structure and strips any invalid/hallucinated
     * citation_ids that do not match valid source ref patterns.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function sanitizeCitations(array $draft): array
    {
        $sanitizeArray = function (array $arr) use (&$sanitizeArray) {
            foreach ($arr as $key => $value) {
                if ($key === 'citation_ids' && is_array($value)) {
                    $arr[$key] = array_values(array_filter($value, function ($id) {
                        if (! is_string($id)) {
                            return false;
                        }

                        return str_starts_with($id, 'submitted_answer:')
                            || str_starts_with($id, 'field_guidance:')
                            || str_starts_with($id, 'alignment_signal:')
                            || str_starts_with($id, 'field_schema:')
                            || str_starts_with($id, 'metric_template:');
                    }));
                } elseif (is_array($value)) {
                    $arr[$key] = $sanitizeArray($value);
                }
            }

            return $arr;
        };

        return $sanitizeArray($draft);
    }

    /**
     * Gemini can reject deeply nested structured-output schemas that include
     * maxItems, so canonical cardinality is enforced at the provider boundary.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function normalizeCardinality(array $draft): array
    {
        $resolutionFocus = $draft['resolution_focus'] ?? null;

        if (is_array($resolutionFocus) && array_is_list($resolutionFocus)) {
            $draft['resolution_focus'] = array_map(
                function (mixed $item): mixed {
                    if (! is_array($item)) {
                        return $item;
                    }

                    foreach (['facilitator_questions', 'candidate_resolution_paths'] as $key) {
                        $items = $item[$key] ?? null;

                        if (is_array($items) && array_is_list($items)) {
                            $item[$key] = array_slice($items, 0, 3);
                        }
                    }

                    return $item;
                },
                array_slice($resolutionFocus, 0, 5),
            );
        }

        $supportingContext = $draft['supporting_context'] ?? null;

        if (is_array($supportingContext) && array_is_list($supportingContext)) {
            $draft['supporting_context'] = array_slice($supportingContext, 0, 5);
        }

        return $draft;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
