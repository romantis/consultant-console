<?php

namespace App\Actions\Metrics;

use App\Actions\Audit\LogMetricEvent;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\User;
use App\Support\Metrics\ValidationGuidanceFields;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ComputeDiscrepancies implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private ?bool $pgvectorAvailable = null;

    public function __construct(
        public string $metricId,
        public ?string $actorId = null
    ) {
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(): void
    {
        /** @var Metric|null $metric */
        $metric = Metric::query()
            ->with([
                'definitions' => fn ($query) => $query->with('user')->orderByDesc('submitted_at'),
            ])
            ->find($this->metricId);

        if (! $metric) {
            return;
        }

        /** @var User|null $actor */
        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        $metric->forceFill([
            'recompute_status' => 'running',
        ])->save();

        try {
            $this->recomputeDiscrepancies($metric, $actor);
        } catch (Throwable $exception) {
            Log::error('Failed to recompute discrepancies', [
                'metric_id' => $this->metricId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $metric->forceFill([
                'recompute_status' => 'idle',
                'recompute_finished_at' => now(),
            ])->save();
        }
    }

    protected function recomputeDiscrepancies(Metric $metric, ?User $actor): void
    {
        DB::transaction(function () use ($metric, $actor) {
            // Lock the parent metric row first to prevent race conditions
            $lockedMetric = Metric::where('id', $metric->id)->lockForUpdate()->first();

            $submittedDefinitions = $lockedMetric->definitions
                ->filter(fn ($definition) => strcasecmp($definition->status, 'Submitted') === 0);

            $lockedMetric->discrepancies()->delete();

            if ($submittedDefinitions->isEmpty()) {
                $lockedMetric->markStatus(MetricStatus::AWAITING_FORMS);

                return;
            }

            $fieldMatrix = $this->buildFieldMatrix($submittedDefinitions);
            $discrepanciesCreated = 0;

            foreach ($fieldMatrix as $fieldKey => $field) {
                $analysis = $this->analyzeField($fieldKey, $field);

                if (! $analysis) {
                    continue;
                }

                $discrepancy = $lockedMetric->discrepancies()->create([
                    'engagement_id' => $lockedMetric->engagement_id,
                    'field_key' => $fieldKey,
                    'rule_type' => $field['type'],
                    'severity' => $analysis['severity'],
                    'status' => 'Open',
                    'description' => $analysis['description'],
                    'values_snapshot' => [
                        'label' => $field['label'],
                        'values' => $field['values'],
                        'analysis' => [
                            'score' => $analysis['score'],
                            'vector_used' => $analysis['vector_used'],
                            'details' => $analysis['details'],
                        ],
                    ],
                    'detected_at' => now(),
                ]);

                $this->logDiscrepancy($lockedMetric, $discrepancy, $analysis, $actor);
                $discrepanciesCreated++;
            }

            $latestSubmission = $submittedDefinitions->pluck('submitted_at')->filter()->max();
            if ($latestSubmission) {
                $lockedMetric->forceFill(['last_submitted_at' => $latestSubmission])->save();
            }

            $lockedMetric->incrementBriefSourceRevision();

            if ($discrepanciesCreated > 0) {
                $lockedMetric->markStatus(MetricStatus::IN_REVIEW);

                return;
            }

            if ($submittedDefinitions->count() > 1) {
                $lockedMetric->markStatus(MetricStatus::IN_WORKSHOP);

                return;
            }

            $lockedMetric->markStatus(MetricStatus::IN_REVIEW);
        });
    }

    /**
     * @param  Collection<int, MetricDefinition>  $definitions
     * @return array<string, array{label: string, type: string, values: array<int, array<string, mixed>>}>
     */
    protected function buildFieldMatrix(Collection $definitions): array
    {
        $matrix = [];

        foreach ($definitions as $definition) {
            foreach ($definition->normalizedFields() as $fieldKey => $field) {
                if (! is_string($fieldKey) || ValidationGuidanceFields::contains($fieldKey)) {
                    continue;
                }

                $matrix[$fieldKey]['label'] = $field['label'] ?? Str::headline($fieldKey);
                $matrix[$fieldKey]['type'] = Str::lower($field['type'] ?? 'text');
                $matrix[$fieldKey]['values'][] = [
                    'definition_id' => $definition->id,
                    'user' => $definition->user?->only(['id', 'name']),
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
                ];
            }
        }

        return $matrix;
    }

    /**
     * @param  array{label: string, type: string, values: array<int, array<string, mixed>>}  $field
     */
    protected function analyzeField(string $fieldKey, array $field): ?array
    {
        $type = $field['type'];
        $values = $field['values'] ?? [];

        if (count($values) <= 1) {
            return null;
        }

        if ($this->isNumericType($type)) {
            return $this->scoreNumericField($field);
        }

        if ($this->isEnumType($type)) {
            return $this->scoreEnumField($field);
        }

        if ($this->isTextType($type)) {
            return $this->scoreTextField($field);
        }

        return null;
    }

    /**
     * @param  array{label: string, type: string, values: array<int, array<string, mixed>>}  $field
     */
    protected function scoreNumericField(array $field): ?array
    {
        $numericValues = collect($field['values'] ?? [])
            ->map(function ($value) {
                $normalized = $value['normalized'] ?? $value['raw'] ?? null;

                return is_numeric($normalized) ? (float) $normalized : null;
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($numericValues->count() <= 1) {
            return null;
        }

        $min = $numericValues->min();
        $max = $numericValues->max();
        $spread = $max - $min;
        $baseline = max(abs($min), abs($max), 1);
        $ratio = $spread / $baseline;

        if ($ratio <= 0.05) {
            return null;
        }

        $score = round(min(100, $ratio * 100), 2);

        return [
            'severity' => $this->severityFromRatio($ratio),
            'score' => $score,
            'vector_used' => false,
            'description' => sprintf(
                'Values vary by %.1f%% (%s → %s) which exceeds the ±5%% tolerance.',
                $ratio * 100,
                $this->formatNumber($min),
                $this->formatNumber($max)
            ),
            'details' => [
                'ratio' => $ratio,
                'spread' => $spread,
                'min' => $min,
                'max' => $max,
            ],
        ];
    }

    /**
     * @param  array{label: string, type: string, values: array<int, array<string, mixed>>}  $field
     */
    protected function scoreEnumField(array $field): ?array
    {
        $values = collect($field['values'] ?? [])
            ->flatMap(function ($value) {
                $normalized = $value['normalized'] ?? $value['raw'] ?? null;

                if (is_array($normalized)) {
                    return collect($normalized)
                        ->filter(fn ($entry) => $entry !== null && $entry !== '')
                        ->all();
                }

                if ($normalized === null || $normalized === '') {
                    return [];
                }

                return [$normalized];
            })
            ->map(fn ($value) => Str::of((string) $value)->lower()->value())
            ->values();

        if ($values->count() <= 1) {
            return null;
        }

        $distribution = $values->countBy();

        if ($distribution->count() <= 1) {
            return null;
        }

        $total = $values->count();
        $topCount = $distribution->max();
        $topShare = $total > 0 ? ($topCount / $total) * 100 : 0.0;
        $score = round((($total - $topCount) / $total) * 100, 2);

        if ($score < 10) {
            return null;
        }

        return [
            'severity' => $this->bucketSeverity($score),
            'score' => $score,
            'vector_used' => false,
            'description' => sprintf(
                'Contributors selected %d different options; the top option covers %d of %d responses (%.0f%%), so there is no clear consensus.',
                $distribution->count(),
                $topCount,
                $total,
                $topShare
            ),
            'details' => [
                'distribution' => $distribution->toArray(),
                'total' => $total,
                'top_share' => round($topShare, 1),
                'options' => $distribution->map(fn ($count, $option) => [
                    'option' => $option,
                    'count' => $count,
                    'percentage' => $total ? round(($count / $total) * 100, 1) : 0,
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @param  array{label: string, type: string, values: array<int, array<string, mixed>>}  $field
     */
    protected function scoreTextField(array $field): ?array
    {
        $texts = collect($field['values'] ?? [])
            ->map(function ($value) {
                $text = $value['normalized'] ?? $value['raw'] ?? null;

                return is_string($text) ? trim($text) : null;
            })
            ->filter(fn ($value) => filled($value))
            ->values();

        if ($texts->count() <= 1) {
            return null;
        }

        [$averageSimilarity, $vectorUsed] = $this->calculateTextSimilarity($texts->all());
        $divergence = 1 - $averageSimilarity;

        if ($divergence <= 0.2) {
            return null;
        }

        $score = round($divergence * 100, 2);

        return [
            'severity' => $this->bucketSeverity($score),
            'score' => $score,
            'vector_used' => $vectorUsed,
            'description' => $vectorUsed
                ? sprintf('Average cosine similarity %.2f indicates divergent narratives.', $averageSimilarity)
                : sprintf('Token overlap %.0f%% indicates divergent narratives.', $averageSimilarity * 100),
            'details' => [
                'average_similarity' => $averageSimilarity,
                'vector_used' => $vectorUsed,
                'pair_count' => $this->pairCount($texts->count()),
            ],
        ];
    }

    protected function isNumericType(string $type): bool
    {
        return in_array($type, ['number', 'numeric', 'integer', 'currency', 'percent'], true);
    }

    protected function isEnumType(string $type): bool
    {
        return in_array($type, ['enum', 'select', 'dropdown', 'radio', 'checkbox', 'checkbox_group'], true);
    }

    protected function isTextType(string $type): bool
    {
        return in_array($type, ['text', 'textarea', 'richtext', 'rich_text', 'markdown'], true);
    }

    protected function severityFromRatio(float $ratio): string
    {
        if ($ratio >= 0.25) {
            return 'high';
        }

        if ($ratio >= 0.1) {
            return 'medium';
        }

        return 'low';
    }

    protected function bucketSeverity(float $score): string
    {
        if ($score >= 70) {
            return 'high';
        }

        if ($score >= 35) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<int, string>  $texts
     * @return array{0: float, 1: bool}
     */
    protected function calculateTextSimilarity(array $texts): array
    {
        $pairs = 0;
        $total = 0.0;
        $vectorUsed = false;

        for ($i = 0; $i < count($texts); $i++) {
            for ($j = $i + 1; $j < count($texts); $j++) {
                $result = $this->textSimilarityPair($texts[$i], $texts[$j]);
                $total += $result['value'];
                $vectorUsed = $vectorUsed || $result['vector_used'];
                $pairs++;
            }
        }

        if ($pairs === 0) {
            return [1.0, false];
        }

        return [$total / $pairs, $vectorUsed];
    }

    /**
     * @return array{value: float, vector_used: bool}
     */
    protected function textSimilarityPair(string $first, string $second): array
    {
        $first = Str::lower(Str::of($first)->squish()->toString());
        $second = Str::lower(Str::of($second)->squish()->toString());

        if ($first === '' && $second === '') {
            return ['value' => 1.0, 'vector_used' => false];
        }

        if ($this->supportsPgvector()) {
            $vectorSimilarity = $this->pgvectorSimilarity($first, $second);
            if ($vectorSimilarity !== null) {
                return ['value' => max(0.0, min(1.0, $vectorSimilarity)), 'vector_used' => true];
            }
        }

        return ['value' => $this->tokenSimilarity($first, $second), 'vector_used' => false];
    }

    protected function supportsPgvector(): bool
    {
        if ($this->pgvectorAvailable !== null) {
            return $this->pgvectorAvailable;
        }

        if (! config('metric-harmony.features.vector')) {
            return $this->pgvectorAvailable = false;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            return $this->pgvectorAvailable = false;
        }

        try {
            $result = $connection->selectOne("select exists(select 1 from pg_extension where extname = 'vector') as installed");

            return $this->pgvectorAvailable = (bool) ($result->installed ?? false);
        } catch (Throwable $exception) {
            Log::debug('pgvector extension unavailable; falling back to token similarity.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->pgvectorAvailable = false;
        }
    }

    protected function pgvectorSimilarity(string $first, string $second): ?float
    {
        $vectorA = $this->encodeTextVector($first);
        $vectorB = $this->encodeTextVector($second);

        if ($vectorA === $vectorB) {
            return 1.0;
        }

        $literalA = $this->formatVectorLiteral($vectorA);
        $literalB = $this->formatVectorLiteral($vectorB);

        try {
            $result = DB::selectOne("select greatest(0, 1 - ('{$literalA}'::vector <=> '{$literalB}'::vector)) as similarity");

            return isset($result->similarity) ? (float) $result->similarity : null;
        } catch (Throwable $exception) {
            Log::debug('pgvector similarity calculation failed; disabling vector path.', [
                'message' => $exception->getMessage(),
            ]);

            $this->pgvectorAvailable = false;

            return null;
        }
    }

    /**
     * @return array<int, float>
     */
    protected function encodeTextVector(string $text, int $dimensions = 24): array
    {
        $tokens = $this->tokenize($text);

        if (empty($tokens)) {
            return array_fill(0, $dimensions, 0.0);
        }

        $vector = array_fill(0, $dimensions, 0.0);

        foreach ($tokens as $token) {
            $hash = hexdec(substr(hash('sha256', $token), 0, 8));
            $index = $hash % $dimensions;
            $vector[$index] += 1;
        }

        $norm = sqrt(array_reduce($vector, fn ($carry, $value) => $carry + ($value ** 2), 0));

        if ($norm === 0.0) {
            return $vector;
        }

        return array_map(fn ($value) => $value / $norm, $vector);
    }

    /**
     * @param  array<int, float>  $vector
     */
    protected function formatVectorLiteral(array $vector): string
    {
        $components = array_map(fn ($value) => rtrim(rtrim(sprintf('%.6f', $value), '0'), '.'), $vector);

        return '['.implode(',', $components).']';
    }

    protected function tokenSimilarity(string $first, string $second): float
    {
        $tokensA = array_values(array_unique($this->tokenize($first)));
        $tokensB = array_values(array_unique($this->tokenize($second)));

        if (empty($tokensA) && empty($tokensB)) {
            return 1.0;
        }

        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));

        if ($union === 0) {
            return 1.0;
        }

        return $intersection / $union;
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $clean = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/u', ' ')
            ->squish();

        if ($clean->isEmpty()) {
            return [];
        }

        return $clean->explode(' ')->filter()->values()->all();
    }

    protected function pairCount(int $valueCount): int
    {
        if ($valueCount <= 1) {
            return 0;
        }

        return (int) (($valueCount * ($valueCount - 1)) / 2);
    }

    /**
     * @param  array{severity: string, score: float, vector_used: bool, details: array<string, mixed>}  $analysis
     */
    protected function logDiscrepancy(
        Metric $metric,
        Discrepancy $discrepancy,
        array $analysis,
        ?User $actor = null
    ): void {
        app(LogMetricEvent::class)->handle(
            $metric,
            'metric.discrepancy.opened',
            [
                'discrepancy_id' => (string) $discrepancy->getKey(),
                'field_key' => $discrepancy->field_key,
                'severity' => $analysis['severity'],
                'score' => $analysis['score'],
                'vector_used' => $analysis['vector_used'],
                'description' => $discrepancy->description,
            ],
            $actor,
            $discrepancy
        );
    }

    protected function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
