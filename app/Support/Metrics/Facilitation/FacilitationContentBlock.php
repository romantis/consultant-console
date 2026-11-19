<?php

namespace App\Support\Metrics\Facilitation;

/**
 * A consultant-editable prose block for the facilitation plan.
 *
 * Run-of-show purposes and expected-output descriptions are never source-backed
 * facts: they are system-suggested sequencing/framing, derived deterministically
 * from the brief, until a consultant edits them and they become
 * consultant-authored.
 */
final readonly class FacilitationContentBlock
{
    public const PROVENANCE_SYSTEM_SUGGESTED = 'system_suggested';

    public const PROVENANCE_CONSULTANT_AUTHORED = 'consultant_authored';

    /**
     * @param  list<string>  $sourceItemIds
     * @param  list<string>  $citationIds
     */
    public function __construct(
        public string $text,
        public string $provenance,
        public array $sourceItemIds = [],
        public array $citationIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            text: (string) ($payload['text'] ?? ''),
            provenance: self::normalizeProvenance($payload['provenance'] ?? null),
            sourceItemIds: self::stringList($payload['source_item_ids'] ?? []),
            citationIds: self::stringList($payload['citation_ids'] ?? []),
        );
    }

    public static function systemSuggested(string $text): self
    {
        return new self($text, self::PROVENANCE_SYSTEM_SUGGESTED);
    }

    /**
     * @return array{text: string, provenance: string, source_item_ids: list<string>, citation_ids: list<string>}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'provenance' => $this->provenance,
            'source_item_ids' => $this->sourceItemIds,
            'citation_ids' => $this->citationIds,
        ];
    }

    private static function normalizeProvenance(mixed $provenance): string
    {
        return $provenance === self::PROVENANCE_CONSULTANT_AUTHORED
            ? self::PROVENANCE_CONSULTANT_AUTHORED
            : self::PROVENANCE_SYSTEM_SUGGESTED;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? $item : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
