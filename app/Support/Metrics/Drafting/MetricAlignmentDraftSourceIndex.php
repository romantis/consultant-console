<?php

namespace App\Support\Metrics\Drafting;

final readonly class MetricAlignmentDraftSourceIndex
{
    private const SOURCE_ITEM_COLLECTIONS = [
        'material_conflicts',
        'decision_needed',
        'missing_evidence',
        'context_signals',
        'likely_non_issues',
    ];

    /**
     * @param  list<string>  $citationIds
     * @param  array<string, array{field_key: string, category: string}>  $sourceItems
     * @param  array<string, string>  $sourceQuestions
     * @param  array<string, string>  $sourceOptions
     * @param  list<string>  $fieldKeys
     */
    private function __construct(
        public array $citationIds,
        public array $sourceItems,
        public array $sourceQuestions,
        public array $sourceOptions,
        public array $fieldKeys,
    ) {}

    /**
     * @param  array<string, mixed>  $brief
     */
    public static function fromBrief(array $brief): self
    {
        $sourceItems = [];

        foreach (self::SOURCE_ITEM_COLLECTIONS as $collection) {
            foreach (self::listItems($brief[$collection] ?? null) as $item) {
                $id = $item['id'] ?? null;
                $fieldKey = $item['field_key'] ?? null;
                $category = $item['category'] ?? null;

                if (is_string($id) && is_string($fieldKey) && is_string($category)) {
                    $sourceItems[$id] = [
                        'field_key' => $fieldKey,
                        'category' => $category,
                    ];
                }
            }
        }

        $sourceQuestions = self::fieldBindings($brief['recommended_questions'] ?? null);
        $sourceOptions = self::fieldBindings($brief['resolution_options'] ?? null);
        $fieldKeys = self::collectValuesForKey($brief, 'field_key');

        return new self(
            citationIds: self::collectSourceRefIds($brief),
            sourceItems: $sourceItems,
            sourceQuestions: $sourceQuestions,
            sourceOptions: $sourceOptions,
            fieldKeys: $fieldKeys,
        );
    }

    /**
     * @return array{
     *     citation_ids: list<string>,
     *     source_item_ids: list<string>,
     *     source_question_ids: list<string>,
     *     source_option_ids: list<string>
     * }
     */
    public function allowedBindings(): array
    {
        return [
            'citation_ids' => $this->citationIds,
            'source_item_ids' => array_keys($this->sourceItems),
            'source_question_ids' => array_keys($this->sourceQuestions),
            'source_option_ids' => array_keys($this->sourceOptions),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listItems(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_array'));
    }

    /**
     * @return array<string, string>
     */
    private static function fieldBindings(mixed $value): array
    {
        $bindings = [];

        foreach (self::listItems($value) as $item) {
            $id = $item['id'] ?? null;
            $fieldKey = $item['field_key'] ?? null;

            if (is_string($id) && is_string($fieldKey)) {
                $bindings[$id] = $fieldKey;
            }
        }

        return $bindings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function collectSourceRefIds(array $data): array
    {
        $ids = [];

        foreach ($data as $value) {
            if (! is_array($value)) {
                continue;
            }

            foreach (self::listItems($value['source_refs'] ?? null) as $sourceRef) {
                if (is_string($sourceRef['id'] ?? null)) {
                    $ids[] = $sourceRef['id'];
                }
            }

            $ids = [...$ids, ...self::collectSourceRefIds($value)];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function collectValuesForKey(array $data, string $targetKey): array
    {
        $values = [];

        foreach ($data as $key => $value) {
            if ($key === $targetKey && is_string($value)) {
                $values[] = $value;
            }

            if (is_array($value)) {
                $values = [...$values, ...self::collectValuesForKey($value, $targetKey)];
            }
        }

        return array_values(array_unique($values));
    }
}
