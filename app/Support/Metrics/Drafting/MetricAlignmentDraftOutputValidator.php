<?php

namespace App\Support\Metrics\Drafting;

use Illuminate\Support\Facades\File;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

final readonly class MetricAlignmentDraftOutputValidator
{
    private const RESOLUTION_CATEGORY_BY_KIND = [
        'must_resolve' => 'material_conflict',
        'missing_evidence' => 'missing_evidence',
        'needs_confirmation' => 'decision_needed',
    ];

    private const SUPPORTING_CATEGORY_BY_KIND = [
        'context_signal' => 'context_signal',
        'likely_non_issue' => 'likely_non_issue',
    ];

    public function __construct(
        private Validator $schemaValidator,
        private ErrorFormatter $errorFormatter,
    ) {}

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $draft
     */
    public function validate(array $brief, array $draft): MetricAlignmentDraftValidationResult
    {
        $errors = $this->validateCanonicalSchema($draft);
        $summary = $brief['summary'] ?? [];
        $sourceIndex = MetricAlignmentDraftSourceIndex::fromBrief($brief);

        if (($draft['schema_version'] ?? null) !== '1.0' || ($draft['status'] ?? null) !== 'draft') {
            $errors[] = 'Schema version or draft status is invalid.';
        }

        if (($draft['metric_id'] ?? null) !== ($summary['metric_id'] ?? null)) {
            $errors[] = 'Metric ID does not match the input.';
        }

        if (($draft['metric_name'] ?? null) !== ($summary['metric_name'] ?? null)) {
            $errors[] = 'Metric name does not match the input.';
        }

        $resolutionFocus = $this->listValue($draft, 'resolution_focus', $errors);
        $supportingContext = $this->listValue($draft, 'supporting_context', $errors);

        $this->validateBindings($sourceIndex, $draft, $errors);
        $this->validateResolutionFocus($sourceIndex, $resolutionFocus, $errors);
        $this->validateSupportingContext($sourceIndex, $supportingContext, $errors);

        return new MetricAlignmentDraftValidationResult(array_values(array_unique($errors)));
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<string>
     */
    private function validateCanonicalSchema(array $draft): array
    {
        $schemaPath = (string) config('metric-alignment-drafting.output_schema');

        try {
            $schema = json_decode(File::get($schemaPath), flags: JSON_THROW_ON_ERROR);
            $data = json_decode(json_encode($draft, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Metric alignment output schema or draft contains invalid JSON: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $result = $this->schemaValidator->validate($data, $schema);

        if ($result->isValid()) {
            return [];
        }

        $error = $result->error();

        if ($error === null) {
            return ['Canonical output schema validation failed.'];
        }

        $formattedErrors = $this->errorFormatter->formatKeyed($error);
        $errors = [];

        foreach ($formattedErrors as $path => $messages) {
            foreach ($messages as $message) {
                $location = $path === '' ? '/' : $path;
                $errors[] = "Canonical output schema violation at {$location}: {$message}";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private function listValue(array $draft, string $key, array &$errors): array
    {
        $value = $draft[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            $errors[] = "{$key} must be a list.";

            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $errors
     */
    private function validateBindings(
        MetricAlignmentDraftSourceIndex $sourceIndex,
        array $draft,
        array &$errors,
    ): void {
        foreach ($sourceIndex->allowedBindings() as $key => $validIds) {
            foreach ($this->collectValuesForKey($draft, $key) as $usedId) {
                if (! in_array($usedId, $validIds, true)) {
                    $errors[] = "Unknown {$key} value: {$usedId}.";
                }
            }

            foreach ($this->collectListsForKey($draft, $key) as $ids) {
                if (count($ids) !== count(array_unique($ids))) {
                    $errors[] = "{$key} values must be unique within each list.";
                }
            }
        }

        foreach ($this->collectValuesForKey($draft, 'field_key') as $fieldKey) {
            if (! in_array($fieldKey, $sourceIndex->fieldKeys, true)) {
                $errors[] = "Unknown field_key value: {$fieldKey}.";
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resolutionFocus
     * @param  list<string>  $errors
     */
    private function validateResolutionFocus(
        MetricAlignmentDraftSourceIndex $sourceIndex,
        array $resolutionFocus,
        array &$errors,
    ): void {
        foreach ($resolutionFocus as $item) {
            $fieldKey = $item['field_key'] ?? null;
            $kind = $item['kind'] ?? null;

            if (! is_string($fieldKey) || ! is_string($kind)) {
                continue;
            }

            foreach ($this->stringList($item['source_item_ids'] ?? null) as $sourceItemId) {
                $binding = $sourceIndex->sourceItems[$sourceItemId] ?? null;

                if ($binding === null) {
                    continue;
                }

                if ($binding['field_key'] !== $fieldKey) {
                    $errors[] = "Source item {$sourceItemId} does not belong to field {$fieldKey}.";
                }

                $expectedCategory = self::RESOLUTION_CATEGORY_BY_KIND[$kind] ?? null;

                if ($expectedCategory !== null && $binding['category'] !== $expectedCategory) {
                    $errors[] = "Source item {$sourceItemId} does not support resolution kind {$kind}.";
                }
            }

            foreach ($this->listItems($item['facilitator_questions'] ?? null) as $question) {
                foreach ($this->stringList($question['source_question_ids'] ?? null) as $questionId) {
                    $boundFieldKey = $sourceIndex->sourceQuestions[$questionId] ?? null;

                    if ($boundFieldKey !== null && $boundFieldKey !== $fieldKey) {
                        $errors[] = "Source question {$questionId} does not belong to field {$fieldKey}.";
                    }
                }
            }

            $paths = $item['candidate_resolution_paths'] ?? null;

            if (! is_array($paths) || ! array_is_list($paths)) {
                $errors[] = 'candidate_resolution_paths must be a list.';

                continue;
            }

            if ($kind === 'missing_evidence' && $paths !== []) {
                $errors[] = 'Missing-evidence focus must not contain candidate resolution paths.';
            }

            foreach ($this->listItems($paths) as $path) {
                foreach ($this->stringList($path['source_option_ids'] ?? null) as $optionId) {
                    $boundFieldKey = $sourceIndex->sourceOptions[$optionId] ?? null;

                    if ($boundFieldKey !== null && $boundFieldKey !== $fieldKey) {
                        $errors[] = "Source option {$optionId} does not belong to field {$fieldKey}.";
                    }
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $supportingContext
     * @param  list<string>  $errors
     */
    private function validateSupportingContext(
        MetricAlignmentDraftSourceIndex $sourceIndex,
        array $supportingContext,
        array &$errors,
    ): void {
        foreach ($supportingContext as $item) {
            $fieldKey = $item['field_key'] ?? null;
            $kind = $item['kind'] ?? null;

            if (! is_string($fieldKey) || ! is_string($kind)) {
                continue;
            }

            foreach ($this->stringList($item['source_item_ids'] ?? null) as $sourceItemId) {
                $binding = $sourceIndex->sourceItems[$sourceItemId] ?? null;

                if ($binding === null) {
                    continue;
                }

                if ($binding['field_key'] !== $fieldKey) {
                    $errors[] = "Source item {$sourceItemId} does not belong to field {$fieldKey}.";
                }

                $expectedCategory = self::SUPPORTING_CATEGORY_BY_KIND[$kind] ?? null;

                if ($expectedCategory !== null && $binding['category'] !== $expectedCategory) {
                    $errors[] = "Source item {$sourceItemId} does not support context kind {$kind}.";
                }
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listItems(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function collectValuesForKey(array $data, string $targetKey): array
    {
        $values = [];

        foreach ($data as $key => $value) {
            if ($key === $targetKey && is_array($value)) {
                $values = [...$values, ...$this->stringList($value)];
            } elseif ($key === $targetKey && is_string($value)) {
                $values[] = $value;
            }

            if (is_array($value)) {
                $values = [...$values, ...$this->collectValuesForKey($value, $targetKey)];
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<list<string>>
     */
    private function collectListsForKey(array $data, string $targetKey): array
    {
        $lists = [];

        foreach ($data as $key => $value) {
            if ($key === $targetKey && is_array($value)) {
                $lists[] = $this->stringList($value);
            }

            if (is_array($value)) {
                $lists = [...$lists, ...$this->collectListsForKey($value, $targetKey)];
            }
        }

        return $lists;
    }
}
