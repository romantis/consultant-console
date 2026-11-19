<?php

namespace App\Support\Metrics\Drafting;

final readonly class MetricAlignmentDraftValidationResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public array $errors) {}

    public function passed(): bool
    {
        return $this->errors === [];
    }
}
