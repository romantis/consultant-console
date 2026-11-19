<?php

namespace App\Support\Metrics\Facilitation;

/**
 * One ordered, time-boxed facilitation segment in the run of show.
 */
final readonly class RunOfShowItem
{
    /**
     * @param  list<string>  $decisionIds
     */
    public function __construct(
        public string $id,
        public int $position,
        public int $durationMinutes,
        public string $title,
        public array $decisionIds,
        public FacilitationContentBlock $purpose,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload, int $position): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            position: $position,
            durationMinutes: self::clampDuration($payload['duration_minutes'] ?? 0),
            title: trim((string) ($payload['title'] ?? '')),
            decisionIds: self::stringList($payload['decision_ids'] ?? []),
            purpose: FacilitationContentBlock::fromArray(
                is_array($payload['purpose'] ?? null) ? $payload['purpose'] : [],
            ),
        );
    }

    /**
     * @return array{id: string, position: int, duration_minutes: int, title: string, decision_ids: list<string>, purpose: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'duration_minutes' => $this->durationMinutes,
            'title' => $this->title,
            'decision_ids' => $this->decisionIds,
            'purpose' => $this->purpose->toArray(),
        ];
    }

    private static function clampDuration(mixed $value): int
    {
        $minutes = (int) $value;

        return max(1, min(180, $minutes));
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
