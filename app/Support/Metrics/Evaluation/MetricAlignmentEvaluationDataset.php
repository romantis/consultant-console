<?php

namespace App\Support\Metrics\Evaluation;

use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

final class MetricAlignmentEvaluationDataset
{
    /**
     * @return list<MetricAlignmentEvaluationCase>
     */
    public function load(string $path): array
    {
        if (! File::isFile($path)) {
            throw new RuntimeException("Evaluation dataset not found: {$path}");
        }

        try {
            /** @var array{cases?: list<array<string, mixed>>} $dataset */
            $dataset = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Evaluation dataset is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! isset($dataset['cases']) || $dataset['cases'] === []) {
            throw new RuntimeException('Evaluation dataset contains no cases.');
        }

        return array_map(
            fn (array $case): MetricAlignmentEvaluationCase => new MetricAlignmentEvaluationCase(
                id: $this->requiredString($case, 'id'),
                purpose: $this->requiredString($case, 'purpose'),
                input: $this->requiredArray($case, 'input'),
                expectations: $this->requiredArray($case, 'expectations'),
            ),
            $dataset['cases'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Evaluation case is missing a valid {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requiredArray(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            throw new RuntimeException("Evaluation case is missing a valid {$key}.");
        }

        return $value;
    }
}
