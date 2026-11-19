<?php

namespace App\Support\Metrics\Drafting;

final readonly class MetricAlignmentDraftResponse
{
    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public string $profileVersion,
        public string $provider,
        public string $requestedModel,
        public string $returnedModel,
        public int $elapsedMs,
        public array $draft,
        public array $usage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile_version' => $this->profileVersion,
            'provider' => $this->provider,
            'requested_model' => $this->requestedModel,
            'returned_model' => $this->returnedModel,
            'elapsed_ms' => $this->elapsedMs,
            'usage' => $this->usage,
            'draft' => $this->draft,
        ];
    }
}
