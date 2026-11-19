<?php

namespace App\Support\Metrics\Facilitation;

/**
 * One planned artifact the workshop should produce. A preparation checklist
 * item, never a recorded workshop outcome.
 */
final readonly class ExpectedOutput
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_PREPARED = 'prepared';

    /**
     * @param  list<string>  $decisionIds
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $status,
        public array $decisionIds,
        public FacilitationContentBlock $description,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            label: trim((string) ($payload['label'] ?? '')),
            status: self::normalizeStatus($payload['status'] ?? null),
            decisionIds: self::stringList($payload['decision_ids'] ?? []),
            description: FacilitationContentBlock::fromArray(
                is_array($payload['description'] ?? null) ? $payload['description'] : [],
            ),
        );
    }

    /**
     * @return array{id: string, label: string, status: string, decision_ids: list<string>, description: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'decision_ids' => $this->decisionIds,
            'description' => $this->description->toArray(),
        ];
    }

    private static function normalizeStatus(mixed $status): string
    {
        return $status === self::STATUS_PREPARED
            ? self::STATUS_PREPARED
            : self::STATUS_PLANNED;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value),
            static fn (string $item): bool => $item !== '',
        )));
    }
}
