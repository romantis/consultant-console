<?php

namespace App\Support\Metrics\Evaluation;

use App\Ai\Agents\MetricAlignmentDraftAgent;
use App\Support\Metrics\Drafting\MetricAlignmentDraftPromptBuilder;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use JsonException;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

final class MetricAlignmentEvaluationRunner
{
    public function __construct(
        private readonly MetricAlignmentEvaluationDataset $dataset,
        private readonly MetricAlignmentDraftValidator $validator,
        private readonly MetricAlignmentDraftPromptBuilder $promptBuilder,
    ) {}

    /**
     * @param  list<string>  $models
     * @return array<string, mixed>
     */
    public function run(
        array $models,
        int $repetitions,
        float $budgetUsd,
        bool $execute,
        string $outputDirectory,
        int $delayMilliseconds = 0,
        array $caseIds = [],
    ): array {
        $cases = $this->dataset->load((string) config('metric-alignment-evaluation.dataset'));
        $cases = $this->selectCases($cases, $caseIds);
        $estimate = $this->estimateMaximumCost($cases, $models, $repetitions);
        $profile = $this->evaluationProfile();
        $promptHashes = [];

        foreach ($cases as $case) {
            $promptHashes[$case->id] = hash('sha256', $this->promptFor($case));
        }

        if ($estimate > $budgetUsd) {
            throw new RuntimeException(sprintf(
                'Maximum estimated cost $%.4f exceeds the $%.2f budget.',
                $estimate,
                $budgetUsd,
            ));
        }

        $report = [
            'dataset_version' => '1.0.0',
            'prompt_version' => config('metric-alignment-drafting.prompt_version'),
            'prompt_sha256_by_case' => $promptHashes,
            'output_schema_version' => '1.0',
            'evaluation_profile' => $profile,
            'evaluation_profile_sha256' => $this->jsonHash($profile),
            'provider' => $profile['provider'],
            'models' => $models,
            'case_ids' => array_map(
                fn (MetricAlignmentEvaluationCase $case): string => $case->id,
                $cases,
            ),
            'case_count' => count($cases),
            'repetitions' => $repetitions,
            'budget_usd' => $budgetUsd,
            'maximum_estimated_cost_usd' => round($estimate, 6),
            'cost_method' => 'Laravel AI SDK token usage multiplied by OpenRouter Models API pricing snapshot from 2026-06-08.',
            'executed' => $execute,
            'runs' => [],
        ];

        if (! $execute) {
            return $report;
        }

        if (blank(config('ai.providers.openrouter.key'))) {
            throw new RuntimeException('OPENROUTER_API_KEY is required for --execute.');
        }

        File::ensureDirectoryExists($outputDirectory);

        foreach ($models as $model) {
            foreach ($cases as $case) {
                foreach (range(1, $repetitions) as $repetition) {
                    $report['runs'][] = $this->executeRun($case, $model, $repetition);
                    $this->writeReport($outputDirectory, $report);

                    if ($delayMilliseconds > 0) {
                        usleep($delayMilliseconds * 1000);
                    }
                }
            }
        }

        return $report;
    }

    /**
     * @param  list<MetricAlignmentEvaluationCase>  $cases
     * @param  list<string>  $caseIds
     * @return list<MetricAlignmentEvaluationCase>
     */
    private function selectCases(array $cases, array $caseIds): array
    {
        if ($caseIds === []) {
            return $cases;
        }

        $selected = array_values(array_filter(
            $cases,
            fn (MetricAlignmentEvaluationCase $case): bool => in_array($case->id, $caseIds, true),
        ));
        $selectedIds = array_map(
            fn (MetricAlignmentEvaluationCase $case): string => $case->id,
            $selected,
        );
        $unknownIds = array_values(array_diff($caseIds, $selectedIds));

        if ($unknownIds !== []) {
            throw new RuntimeException('Unknown evaluation case: '.implode(', ', $unknownIds));
        }

        return $selected;
    }

    /**
     * @param  list<MetricAlignmentEvaluationCase>  $cases
     * @param  list<string>  $models
     */
    public function estimateMaximumCost(array $cases, array $models, int $repetitions): float
    {
        $schemaCharacters = strlen(File::get((string) config('metric-alignment-evaluation.output_schema')));
        $instructionAllowance = 2500;
        $maxCompletionTokens = (int) config('metric-alignment-evaluation.profile.max_tokens', 2500);
        $cost = 0.0;

        foreach ($models as $model) {
            $pricing = $this->pricingFor($model);

            foreach ($cases as $case) {
                $inputCharacters = strlen($this->promptFor($case));
                $estimatedPromptTokens = (int) ceil(($inputCharacters + $schemaCharacters + $instructionAllowance) / 4);

                $cost += $repetitions * (
                    ($estimatedPromptTokens * (float) $pricing['prompt_price'])
                    + ($maxCompletionTokens * (float) $pricing['completion_price'])
                );
            }
        }

        return $cost;
    }

    /**
     * @return array<string, mixed>
     */
    private function executeRun(
        MetricAlignmentEvaluationCase $case,
        string $model,
        int $repetition,
    ): array {
        $startedAt = hrtime(true);
        $prompt = $this->promptFor($case);

        try {
            $response = (new MetricAlignmentDraftAgent('metric-alignment-evaluation.profile'))->prompt(
                prompt: $prompt,
                provider: (string) config('metric-alignment-evaluation.profile.provider', 'openrouter'),
                model: $model,
                timeout: (int) config('metric-alignment-evaluation.profile.timeout_seconds', 90),
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new RuntimeException('Provider did not return structured output.');
            }

            $draft = $response->toArray();
            $validation = $this->validator->validate($case, $draft);
            $usage = $response->usage->toArray();
            $estimatedCost = $this->usageCost($model, $usage);

            return [
                'case_id' => $case->id,
                'model' => $model,
                'returned_model' => $response->meta->model,
                'repetition' => $repetition,
                'prompt_sha256' => hash('sha256', $prompt),
                'status' => $validation->passed() ? 'passed' : 'hard_gate_failed',
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'usage' => $usage,
                'estimated_cost_usd' => round($estimatedCost, 8),
                'output_json_bytes' => $this->outputJsonBytes($draft),
                'output_prose_characters' => $this->outputProseCharacters($draft),
                'validation_errors' => $validation->errors,
                'output' => $draft,
            ];
        } catch (Throwable $exception) {
            return [
                'case_id' => $case->id,
                'model' => $model,
                'returned_model' => null,
                'repetition' => $repetition,
                'prompt_sha256' => hash('sha256', $prompt),
                'status' => 'provider_error',
                'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
                'usage' => null,
                'estimated_cost_usd' => null,
                'output_json_bytes' => null,
                'output_prose_characters' => null,
                'validation_errors' => [$exception->getMessage()],
                'output' => null,
            ];
        }
    }

    private function promptFor(MetricAlignmentEvaluationCase $case): string
    {
        return $this->promptBuilder->build($case->input);
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function usageCost(string $model, array $usage): float
    {
        $pricing = $this->pricingFor($model);

        return ((int) $usage['prompt_tokens'] * (float) $pricing['prompt_price'])
            + ((int) $usage['completion_tokens'] * (float) $pricing['completion_price']);
    }

    /**
     * @return array{prompt_price: float|int, completion_price: float|int}
     */
    private function pricingFor(string $model): array
    {
        $models = config('metric-alignment-evaluation.models', []);
        $pricing = is_array($models) ? ($models[$model] ?? null) : null;

        if (! is_array($pricing)
            || ! isset($pricing['prompt_price'], $pricing['completion_price'])) {
            throw new RuntimeException("Unknown evaluation model: {$model}");
        }

        return $pricing;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function outputJsonBytes(array $draft): int
    {
        try {
            return strlen(json_encode(
                $draft,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not measure generated draft JSON.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function outputProseCharacters(array $draft): int
    {
        $proseFields = ['text', 'synthesis', 'reason', 'description', 'note'];
        $characters = 0;

        foreach ($draft as $key => $value) {
            if (is_string($value) && in_array($key, $proseFields, true)) {
                $characters += mb_strlen($value);
            }

            if (is_array($value)) {
                $characters += $this->outputProseCharacters($value);
            }
        }

        return $characters;
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluationProfile(): array
    {
        $profile = config('metric-alignment-evaluation.profile', []);

        if (! is_array($profile)) {
            throw new RuntimeException('Metric alignment evaluation profile must be an array.');
        }

        $agent = new MetricAlignmentDraftAgent('metric-alignment-evaluation.profile');
        $wireSchema = (new ObjectSchema(
            $agent->schema(new JsonSchemaTypeFactory),
        ))->toArray();
        $canonicalSchema = File::get((string) config('metric-alignment-evaluation.output_schema'));

        return [
            ...$profile,
            'prompt_sha256' => hash('sha256', (string) $agent->instructions()),
            'wire_schema_sha256' => $this->jsonHash($wireSchema),
            'canonical_output_schema_sha256' => hash('sha256', $canonicalSchema),
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function jsonHash(array $value): string
    {
        try {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not hash evaluation profile JSON.', previous: $exception);
        }

        return hash('sha256', $json);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function writeReport(string $outputDirectory, array $report): void
    {
        File::put(
            $outputDirectory.'/results.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}
