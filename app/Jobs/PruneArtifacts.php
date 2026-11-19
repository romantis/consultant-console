<?php

namespace App\Jobs;

use App\Models\HandbookExport;
use App\Models\NotificationAttempt;
use App\Support\Observability\Telemetry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneArtifacts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<int, string>
     */
    protected array $segments;

    protected ?int $notificationRetentionDays;

    protected ?int $handbookRetentionDays;

    /**
     * @param  array<int, string>  $segments
     */
    public function __construct(
        array $segments = ['notifications', 'handbook'],
        ?int $notificationRetentionDays = null,
        ?int $handbookRetentionDays = null
    ) {
        $allowed = ['notifications', 'handbook'];

        $segments = array_values(array_unique(array_filter(
            array_map(static fn ($segment) => in_array($segment, $allowed, true) ? $segment : null, $segments)
        )));

        $this->segments = empty($segments) ? $allowed : $segments;
        $this->notificationRetentionDays = $notificationRetentionDays;
        $this->handbookRetentionDays = $handbookRetentionDays;
    }

    public function handle(): void
    {
        $retention = (array) config('metric-harmony.retention', []);
        $notificationDays = $this->notificationRetentionDays ?? (int) ($retention['notification_attempts_days'] ?? 30);
        $handbookDays = $this->handbookRetentionDays ?? (int) ($retention['handbook_exports_days'] ?? 60);

        if ($notificationDays > 0 && $this->shouldRun('notifications')) {
            $this->pruneNotificationAttempts($notificationDays);
        }

        if ($handbookDays > 0 && $this->shouldRun('handbook')) {
            $this->pruneHandbookExports($handbookDays);
        }
    }

    protected function shouldRun(string $segment): bool
    {
        return in_array($segment, $this->segments, true);
    }

    protected function pruneNotificationAttempts(int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        NotificationAttempt::query()
            ->where(function (Builder $builder) use ($cutoff) {
                $builder
                    ->where(function (Builder $query) use ($cutoff) {
                        $query
                            ->whereNotNull('attempted_at')
                            ->where('attempted_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $query) use ($cutoff) {
                        $query
                            ->whereNull('attempted_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->chunkById(500, function ($attempts) use (&$deleted) {
                $ids = $attempts->pluck('id');

                if ($ids->isEmpty()) {
                    return;
                }

                NotificationAttempt::query()
                    ->whereIn('id', $ids)
                    ->delete();

                $deleted += $ids->count();
            });

        Log::info('Pruned stale notification attempts', [
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
            'tags' => Telemetry::tags([], ['job:prune-artifacts', 'artifact:notifications']),
        ]);
    }

    protected function pruneHandbookExports(int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        HandbookExport::query()
            ->whereIn('status', [HandbookExport::STATUS_READY, HandbookExport::STATUS_FAILED])
            ->where(function (Builder $builder) use ($cutoff) {
                $builder
                    ->where(function (Builder $query) use ($cutoff) {
                        $query
                            ->whereNotNull('completed_at')
                            ->where('completed_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $query) use ($cutoff) {
                        $query
                            ->whereNotNull('failed_at')
                            ->where('failed_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $query) use ($cutoff) {
                        $query
                            ->whereNull('completed_at')
                            ->whereNull('failed_at')
                            ->where('created_at', '<', $cutoff);
                    });
            })
            ->where(function (Builder $builder) use ($cutoff) {
                $builder
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<', $cutoff);
            })
            ->chunkById(100, function ($exports) use (&$deleted) {
                /** @var HandbookExport $export */
                foreach ($exports as $export) {
                    $this->deleteHandbookArtifact($export);
                    $export->delete();
                    $deleted++;
                }
            });

        Log::info('Pruned stale handbook exports', [
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
            'tags' => Telemetry::tags([], ['job:prune-artifacts', 'artifact:handbook']),
        ]);
    }

    protected function deleteHandbookArtifact(HandbookExport $export): void
    {
        $disk = $export->storageDisk();
        $path = $export->storage_path;

        if (! $disk || ! $path) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Failed deleting handbook export artifact during pruning', [
                'export_id' => $export->getKey(),
                'disk' => $disk,
                'path' => $path,
                'message' => $exception->getMessage(),
                'tags' => Telemetry::tags([], ['job:prune-artifacts', 'artifact:handbook']),
            ]);
        }
    }

    public function tags(): array
    {
        $artifactTags = array_map(
            static fn (string $segment) => 'artifact:'.$segment,
            $this->segments
        );

        return Telemetry::tags([], array_merge(['job:prune-artifacts'], $artifactTags));
    }

    /**
     * @return array<int, string>
     */
    public function artifacts(): array
    {
        return $this->segments;
    }
}
