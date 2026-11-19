<?php

namespace App\Support\Metrics\Evaluation;

use App\Support\Metrics\Drafting\MetricAlignmentDraftOutputValidator;
use App\Support\Metrics\Drafting\MetricAlignmentDraftValidationResult;

final readonly class MetricAlignmentDraftValidator
{
    public function __construct(
        private MetricAlignmentDraftOutputValidator $outputValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     */
    public function validate(
        MetricAlignmentEvaluationCase $case,
        array $draft,
    ): MetricAlignmentDraftValidationResult {
        $errors = $this->outputValidator->validate($case->input, $draft)->errors;
        $resolutionFocus = $this->listValue($draft, 'resolution_focus');
        $supportingContext = $this->listValue($draft, 'supporting_context');

        $this->validateExpectedFocus($case, $resolutionFocus, $errors);
        $this->validateRequiredCitations($case, $draft, $errors);
        $this->validateCandidatePathPolicy($case, $resolutionFocus, $errors);
        $this->validateSupportingContext($case, $supportingContext, $errors);

        return new MetricAlignmentDraftValidationResult(array_values(array_unique($errors)));
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return list<array<string, mixed>>
     */
    private function listValue(array $draft, string $key): array
    {
        $value = $draft[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $resolutionFocus
     * @param  list<string>  $errors
     */
    private function validateExpectedFocus(
        MetricAlignmentEvaluationCase $case,
        array $resolutionFocus,
        array &$errors,
    ): void {
        $actualKeys = [];

        foreach ($resolutionFocus as $item) {
            $fieldKey = $item['field_key'] ?? null;
            $kind = $item['kind'] ?? null;

            if (is_string($fieldKey) && is_string($kind)) {
                $actualKeys[] = "{$fieldKey}:{$kind}";
            }
        }

        foreach ($case->expectations['expected_resolution_focus'] ?? [] as $expected) {
            if (! is_array($expected)) {
                continue;
            }

            $expectedKey = ($expected['field_key'] ?? '').':'.($expected['kind'] ?? '');

            if (! in_array($expectedKey, $actualKeys, true)) {
                $errors[] = "Expected resolution focus is missing: {$expectedKey}.";
            }
        }

        foreach ($case->expectations['forbidden_resolution_focus_field_keys'] ?? [] as $fieldKey) {
            if (is_string($fieldKey) && in_array($fieldKey, array_column($resolutionFocus, 'field_key'), true)) {
                $errors[] = "Forbidden field was promoted to resolution focus: {$fieldKey}.";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $errors
     */
    private function validateRequiredCitations(
        MetricAlignmentEvaluationCase $case,
        array $draft,
        array &$errors,
    ): void {
        $usedCitationIds = $this->collectValuesForKey($draft, 'citation_ids');

        foreach ($case->expectations['required_citation_sets'] ?? [] as $citationSet) {
            if (! is_array($citationSet)) {
                continue;
            }

            foreach ($citationSet['all_of'] ?? [] as $citationId) {
                if (is_string($citationId) && ! in_array($citationId, $usedCitationIds, true)) {
                    $errors[] = "Required citation is missing: {$citationId}.";
                }
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $resolutionFocus
     * @param  list<string>  $errors
     */
    private function validateCandidatePathPolicy(
        MetricAlignmentEvaluationCase $case,
        array $resolutionFocus,
        array &$errors,
    ): void {
        if (($case->expectations['candidate_path_policy'] ?? null) !== 'none') {
            return;
        }

        foreach ($resolutionFocus as $item) {
            if (($item['candidate_resolution_paths'] ?? []) !== []) {
                $errors[] = 'Candidate resolution paths are forbidden for this case.';
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $supportingContext
     * @param  list<string>  $errors
     */
    private function validateSupportingContext(
        MetricAlignmentEvaluationCase $case,
        array $supportingContext,
        array &$errors,
    ): void {
        $actualKeys = array_column($supportingContext, 'field_key');

        foreach ($case->expectations['required_supporting_context'] ?? [] as $expected) {
            $fieldKey = is_array($expected) ? ($expected['field_key'] ?? null) : $expected;

            if (is_string($fieldKey) && ! in_array($fieldKey, $actualKeys, true)) {
                $errors[] = "Required supporting context is missing: {$fieldKey}.";
            }
        }
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
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $values[] = $item;
                    }
                }
            }

            if (is_array($value)) {
                $values = [...$values, ...$this->collectValuesForKey($value, $targetKey)];
            }
        }

        return array_values(array_unique($values));
    }
}
