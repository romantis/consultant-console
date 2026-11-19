<?php

namespace App\Support\Metrics\Facilitation;

/**
 * The consultant-editable facilitation plan persisted for a metric: the
 * run of show and the expected-output checklist, plus the source snapshot they
 * were derived from.
 */
final readonly class FacilitationBriefDocument
{
    /**
     * @param  array{brief_fingerprint: string, generated_draft: array{schema_version: string, prompt_version: string, profile_version: string, draft_fingerprint: string}|null}  $sourceSnapshot
     * @param  array{agenda_topic_ids: list<string>, covered_topic_ids: list<string>, omitted_topics: list<array<string, mixed>>}  $coverage
     * @param  array<string, mixed>  $workshopThesis
     * @param  list<array<string, mixed>>  $decisionsToLock
     * @param  list<array<string, mixed>>  $readinessGaps
     * @param  list<array<string, mixed>>  $recommendedFraming
     * @param  list<RunOfShowItem>  $runOfShow
     * @param  list<ExpectedOutput>  $expectedOutputs
     */
    public function __construct(
        public string $schemaVersion,
        public string $status,
        public string $metricId,
        public string $metricName,
        public array $sourceSnapshot,
        public array $coverage,
        public array $workshopThesis,
        public array $decisionsToLock,
        public array $readinessGaps,
        public array $recommendedFraming,
        public array $runOfShow,
        public array $expectedOutputs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $runOfShow = is_array($payload['run_of_show'] ?? null) ? $payload['run_of_show'] : [];
        $expectedOutputs = is_array($payload['expected_outputs'] ?? null) ? $payload['expected_outputs'] : [];

        return new self(
            schemaVersion: (string) ($payload['schema_version'] ?? '2.0'),
            status: (string) ($payload['status'] ?? 'draft'),
            metricId: (string) ($payload['metric_id'] ?? ''),
            metricName: (string) ($payload['metric_name'] ?? ''),
            sourceSnapshot: self::normalizeSourceSnapshot($payload['source_snapshot'] ?? []),
            coverage: self::normalizeCoverage($payload['coverage'] ?? []),
            workshopThesis: self::normalizeContentBlock($payload['workshop_thesis'] ?? []),
            decisionsToLock: self::normalizeDecisionsToLock($payload['decisions_to_lock'] ?? []),
            readinessGaps: self::normalizeReadinessGaps($payload['readiness_gaps'] ?? []),
            recommendedFraming: self::normalizeRecommendedFraming($payload['recommended_framing'] ?? []),
            runOfShow: array_values(array_map(
                static fn (mixed $item, int $index): RunOfShowItem => RunOfShowItem::fromArray(
                    is_array($item) ? $item : [],
                    $index + 1,
                ),
                $runOfShow,
                array_keys($runOfShow),
            )),
            expectedOutputs: array_values(array_map(
                static fn (mixed $item): ExpectedOutput => ExpectedOutput::fromArray(
                    is_array($item) ? $item : [],
                ),
                $expectedOutputs,
            )),
        );
    }

    /**
     * @return array{
     *     schema_version: string,
     *     status: string,
     *     metric_id: string,
     *     metric_name: string,
     *     source_snapshot: array<string, mixed>,
     *     coverage: array{agenda_topic_ids: list<string>, covered_topic_ids: list<string>, omitted_topics: list<array<string, mixed>>},
     *     workshop_thesis: array<string, mixed>,
     *     decisions_to_lock: list<array<string, mixed>>,
     *     readiness_gaps: list<array<string, mixed>>,
     *     recommended_framing: list<array<string, mixed>>,
     *     run_of_show: list<array<string, mixed>>,
     *     expected_outputs: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'status' => $this->status,
            'metric_id' => $this->metricId,
            'metric_name' => $this->metricName,
            'source_snapshot' => $this->sourceSnapshot,
            'coverage' => $this->coverage,
            'workshop_thesis' => $this->workshopThesis,
            'decisions_to_lock' => $this->decisionsToLock,
            'readiness_gaps' => $this->readinessGaps,
            'recommended_framing' => $this->recommendedFraming,
            'run_of_show' => array_map(
                static fn (RunOfShowItem $item): array => $item->toArray(),
                $this->runOfShow,
            ),
            'expected_outputs' => array_map(
                static fn (ExpectedOutput $output): array => $output->toArray(),
                $this->expectedOutputs,
            ),
        ];
    }

    /**
     * @return array{brief_fingerprint: string, generated_draft: array{schema_version: string, prompt_version: string, profile_version: string, draft_fingerprint: string}|null}
     */
    private static function normalizeSourceSnapshot(mixed $snapshot): array
    {
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $generatedDraft = $snapshot['generated_draft'] ?? null;

        return [
            'brief_fingerprint' => (string) ($snapshot['brief_fingerprint'] ?? ''),
            'generated_draft' => is_array($generatedDraft)
                ? [
                    'schema_version' => (string) ($generatedDraft['schema_version'] ?? ''),
                    'prompt_version' => (string) ($generatedDraft['prompt_version'] ?? ''),
                    'profile_version' => (string) ($generatedDraft['profile_version'] ?? ''),
                    'draft_fingerprint' => (string) ($generatedDraft['draft_fingerprint'] ?? ''),
                ]
                : null,
        ];
    }

    private static function normalizeContentBlock(mixed $block): array
    {
        $block = is_array($block) ? $block : [];

        return [
            'text' => (string) ($block['text'] ?? ''),
            'provenance' => (string) ($block['provenance'] ?? 'source_backed'),
            'source_item_ids' => self::stringList($block['source_item_ids'] ?? []),
            'citation_ids' => self::stringList($block['citation_ids'] ?? []),
        ];
    }

    private static function normalizeCoverage(mixed $coverage): array
    {
        $coverage = is_array($coverage) ? $coverage : [];
        $omitted = is_array($coverage['omitted_topics'] ?? null) ? $coverage['omitted_topics'] : [];

        $omittedTopics = array_values(array_map(
            static fn (mixed $topic): array => [
                'agenda_topic_id' => (string) (($isArr = is_array($topic)) ? ($topic['agenda_topic_id'] ?? '') : ''),
                'reason' => self::normalizeContentBlock($isArr ? ($topic['reason'] ?? []) : []),
            ],
            $omitted
        ));

        return [
            'agenda_topic_ids' => self::stringList($coverage['agenda_topic_ids'] ?? []),
            'covered_topic_ids' => self::stringList($coverage['covered_topic_ids'] ?? []),
            'omitted_topics' => $omittedTopics,
        ];
    }

    private static function normalizeDecisionsToLock(mixed $decisions): array
    {
        if (! is_array($decisions)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $d): array => [
                'id' => (string) (($isArr = is_array($d)) ? ($d['id'] ?? '') : ''),
                'title' => (string) ($isArr ? ($d['title'] ?? '') : ''),
                'priority' => (string) ($isArr ? ($d['priority'] ?? 'low') : 'low'),
                'agenda_topic_ids' => self::stringList($isArr ? ($d['agenda_topic_ids'] ?? []) : []),
                'field_keys' => self::stringList($isArr ? ($d['field_keys'] ?? []) : []),
                'decision_prompt' => self::normalizeContentBlock($isArr ? ($d['decision_prompt'] ?? []) : []),
            ],
            $decisions
        ));
    }

    private static function normalizeReadinessGaps(mixed $gaps): array
    {
        if (! is_array($gaps)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $gap): array => [
                'id' => (string) (($isArr = is_array($gap)) ? ($gap['id'] ?? '') : ''),
                'scope' => 'workshop_readiness',
                'kind' => (string) ($isArr ? ($gap['kind'] ?? 'missing_stakeholder_perspective') : 'missing_stakeholder_perspective'),
                'status' => (string) ($isArr ? ($gap['status'] ?? 'open') : 'open'),
                'related_decision_ids' => self::stringList($isArr ? ($gap['related_decision_ids'] ?? []) : []),
                'description' => self::normalizeContentBlock($isArr ? ($gap['description'] ?? []) : []),
            ],
            $gaps
        ));
    }

    private static function normalizeRecommendedFraming(mixed $framing): array
    {
        if (! is_array($framing)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $f): array => [
                'id' => (string) (($isArr = is_array($f)) ? ($f['id'] ?? '') : ''),
                'review_status' => (string) ($isArr ? ($f['review_status'] ?? 'proposed') : 'proposed'),
                'related_decision_ids' => self::stringList($isArr ? ($f['related_decision_ids'] ?? []) : []),
                'recommendation' => self::normalizeContentBlock($isArr ? ($f['recommendation'] ?? []) : []),
            ],
            $framing
        ));
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
