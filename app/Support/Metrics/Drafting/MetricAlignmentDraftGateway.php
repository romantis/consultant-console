<?php

namespace App\Support\Metrics\Drafting;

use App\Support\Result;

interface MetricAlignmentDraftGateway
{
    /**
     * @return Result<MetricAlignmentDraftResponse, MetricAlignmentDraftFailure>
     */
    public function generate(string $prompt): Result;
}
