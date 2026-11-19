<?php

namespace App\Actions\Metrics;

use App\Support\Metrics\Drafting\MetricAlignmentDraftFailure;
use App\Support\Metrics\Drafting\MetricAlignmentDraftGateway;
use App\Support\Metrics\Drafting\MetricAlignmentDraftOutputValidator;
use App\Support\Metrics\Drafting\MetricAlignmentDraftPromptBuilder;
use App\Support\Metrics\Drafting\MetricAlignmentDraftResponse;
use App\Support\Result;

final readonly class GenerateMetricAlignmentDraft
{
    public function __construct(
        private MetricAlignmentDraftPromptBuilder $promptBuilder,
        private MetricAlignmentDraftGateway $gateway,
        private MetricAlignmentDraftOutputValidator $outputValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $brief
     * @return Result<MetricAlignmentDraftResponse, MetricAlignmentDraftFailure>
     */
    public function handle(array $brief): Result
    {
        $result = $this->gateway->generate(
            $this->promptBuilder->build($brief),
        );

        if ($result->isErr()) {
            return $result;
        }

        /** @var MetricAlignmentDraftResponse $response */
        $response = $result->value();
        $validation = $this->outputValidator->validate($brief, $response->draft);

        if (! $validation->passed()) {
            return Result::err(MetricAlignmentDraftFailure::invalidOutput(
                response: $response,
                validationErrors: $validation->errors,
            ));
        }

        return Result::ok($response);
    }
}
