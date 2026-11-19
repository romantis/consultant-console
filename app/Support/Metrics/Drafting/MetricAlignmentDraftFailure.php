<?php

namespace App\Support\Metrics\Drafting;

final readonly class MetricAlignmentDraftFailure
{
    /**
     * @param  list<string>  $validationErrors
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $profileVersion,
        public string $provider,
        public string $requestedModel,
        public array $validationErrors = [],
    ) {}

    public static function providerReturnedUnstructured(
        string $profileVersion,
        string $provider,
        string $requestedModel,
    ): self {
        return new self(
            code: 'provider_unstructured_response',
            message: 'Provider did not return structured output.',
            profileVersion: $profileVersion,
            provider: $provider,
            requestedModel: $requestedModel,
        );
    }

    public static function providerError(
        string $message,
        string $profileVersion,
        string $provider,
        string $requestedModel,
    ): self {
        return new self(
            code: 'provider_error',
            message: $message,
            profileVersion: $profileVersion,
            provider: $provider,
            requestedModel: $requestedModel,
        );
    }

    /**
     * @param  list<string>  $validationErrors
     */
    public static function invalidOutput(
        MetricAlignmentDraftResponse $response,
        array $validationErrors,
    ): self {
        return new self(
            code: 'invalid_draft_output',
            message: 'Provider returned a draft that failed source-bound output validation.',
            profileVersion: $response->profileVersion,
            provider: $response->provider,
            requestedModel: $response->requestedModel,
            validationErrors: $validationErrors,
        );
    }
}
