<?php

namespace App\Support\Metrics\Evaluation;

final readonly class MetricAlignmentEvaluationCase
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $expectations
     */
    public function __construct(
        public string $id,
        public string $purpose,
        public array $input,
        public array $expectations,
    ) {}
}
