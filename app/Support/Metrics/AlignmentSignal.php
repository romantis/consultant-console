<?php

namespace App\Support\Metrics;

use App\Models\Discrepancy;

final readonly class AlignmentSignal
{
    public function __construct(
        public string $id,
        public string $severity,
        public ?string $description,
        public string $status,
    ) {}

    public static function fromDiscrepancy(Discrepancy $discrepancy): self
    {
        return new self(
            id: (string) $discrepancy->id,
            severity: $discrepancy->severity,
            description: $discrepancy->description,
            status: $discrepancy->status,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
