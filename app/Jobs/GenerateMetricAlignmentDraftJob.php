<?php

namespace App\Jobs;

use App\Actions\Metrics\BuildMetricAlignmentBrief;
use App\Actions\Metrics\GenerateMetricAlignmentDraft;
use App\Models\Metric;
use App\Support\Metrics\Drafting\BriefFingerprint;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMetricAlignmentDraftJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 120;

    public int $uniqueFor = 180;

    public function uniqueId(): string
    {
        return $this->metricId;
    }

    public function __construct(
        public string $metricId,
        public array $brief,
        public string $fingerprintBefore,
        public int $revisionBefore,
        public ?string $requestedByUserId = null
    ) {
        $this->queue = config('queue.queues.default', 'default');
    }

    public function handle(
        GenerateMetricAlignmentDraft $generateMetricAlignmentDraft,
        BuildMetricAlignmentBrief $buildMetricAlignmentBrief
    ): void {
        $metric = Metric::query()->find($this->metricId);

        if (! $metric) {
            return;
        }

        Telemetry::replace($metric->organization_id, $metric->engagement_id);

        try {
            if (! $this->sourceBriefStillMatches($metric, $buildMetricAlignmentBrief)) {
                Log::warning('Metric alignment draft generation skipped before LLM call: source brief changed.', [
                    'metric_id' => $metric->id,
                ]);

                $metric->forceFill([
                    'draft_generation_status' => 'failed',
                    'draft_generation_error' => 'The metric source data changed before draft generation started. Please try again.',
                ])->save();

                return;
            }

            $metric->forceFill([
                'draft_generation_status' => 'generating',
                'draft_generation_error' => null,
            ])->save();

            $result = $generateMetricAlignmentDraft->handle($this->brief);

            if ($result->isErr()) {
                $failure = $result->error();

                Log::warning('Metric alignment draft generation failed.', [
                    'metric_id' => $metric->id,
                    'user_id' => $this->requestedByUserId,
                    'code' => $failure->code,
                    'message' => $failure->message,
                    'profile_version' => $failure->profileVersion,
                    'provider' => $failure->provider,
                    'requested_model' => $failure->requestedModel,
                    'validation_errors' => $failure->validationErrors,
                ]);

                $errorMessage = $failure->code === 'invalid_draft_output'
                    ? 'The generated draft did not pass source validation. Try again.'
                    : 'Draft generation is temporarily unavailable. Try again.';

                $metric->forceFill([
                    'draft_generation_status' => 'failed',
                    'draft_generation_error' => $errorMessage,
                ])->save();

                return;
            }

            $saved = DB::transaction(function () use ($metric, $buildMetricAlignmentBrief, $result) {
                $lockedMetric = Metric::where('id', $metric->id)->lockForUpdate()->first();

                if (! $lockedMetric) {
                    return false;
                }

                if ($lockedMetric->brief_source_revision !== $this->revisionBefore) {
                    return false;
                }

                // Re-build brief to check fingerprint
                $briefAfter = $buildMetricAlignmentBrief->build($lockedMetric);
                $fingerprintAfter = BriefFingerprint::calculate($briefAfter);

                if ($this->fingerprintBefore !== $fingerprintAfter) {
                    return false;
                }

                $lockedMetric->alignmentDraft()->updateOrCreate(
                    ['metric_id' => $lockedMetric->id],
                    [
                        'profile_version' => $result->value()->profileVersion,
                        'prompt_version' => config('metric-alignment-drafting.prompt_version'),
                        'provider' => $result->value()->provider,
                        'requested_model' => $result->value()->requestedModel,
                        'returned_model' => $result->value()->returnedModel,
                        'source_fingerprint' => $fingerprintAfter,
                        'draft' => $result->value()->draft,
                        'usage' => $result->value()->usage,
                    ]
                );

                $lockedMetric->forceFill([
                    'draft_generation_status' => 'idle',
                    'draft_generation_error' => null,
                ])->save();

                return true;
            });

            if (! $saved) {
                Log::warning('Metric alignment draft generation aborted in background job: source brief changed.', [
                    'metric_id' => $metric->id,
                ]);

                $metric->forceFill([
                    'draft_generation_status' => 'failed',
                    'draft_generation_error' => 'The metric source data changed while generating the draft. Please try again.',
                ])->save();

                return;
            }

            Log::info('Metric alignment draft generated successfully.', [
                'metric_id' => $metric->id,
                'user_id' => $this->requestedByUserId,
                'profile_version' => $result->value()->profileVersion,
                'provider' => $result->value()->provider,
                'requested_model' => $result->value()->requestedModel,
                'returned_model' => $result->value()->returnedModel,
                'usage' => $result->value()->usage,
            ]);

        } catch (Throwable $e) {
            Log::error('Unhandled exception in draft generation job.', [
                'metric_id' => $metric->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function sourceBriefStillMatches(
        Metric $metric,
        BuildMetricAlignmentBrief $buildMetricAlignmentBrief
    ): bool {
        return DB::transaction(function () use ($metric, $buildMetricAlignmentBrief) {
            $lockedMetric = Metric::where('id', $metric->id)->lockForUpdate()->first();

            if (! $lockedMetric) {
                return false;
            }

            if ($lockedMetric->brief_source_revision !== $this->revisionBefore) {
                return false;
            }

            $brief = $buildMetricAlignmentBrief->build($lockedMetric);

            return BriefFingerprint::calculate($brief) === $this->fingerprintBefore;
        });
    }

    public function failed(Throwable $exception): void
    {
        $metric = Metric::query()->find($this->metricId);

        if ($metric) {
            $metric->forceFill([
                'draft_generation_status' => 'failed',
                'draft_generation_error' => 'An unexpected error occurred during draft generation. Please try again.',
            ])->save();
        }
    }
}
