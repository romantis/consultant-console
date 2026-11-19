<?php

namespace App\Console\Commands;

use App\Models\HandbookExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneLegacyEngagementHandbookExports extends Command
{
    protected $signature = 'handbook:prune-legacy-exports
        {--dry-run : Report counts without deleting rows or artifacts}';

    protected $description = 'Delete legacy engagement handbook exports and their artifacts. Run before dropping engagement export columns.';

    public function handle(): int
    {
        if (! Schema::hasTable('handbook_exports')) {
            $this->warn('handbook_exports table not found; nothing to prune.');
            Log::warning('Legacy engagement export prune skipped: handbook_exports table missing.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('handbook_exports', 'engagement_id')) {
            $this->info('handbook_exports.engagement_id already removed; nothing to prune.');
            Log::info('Legacy engagement export prune skipped: engagement_id column missing.');

            return self::SUCCESS;
        }

        $total = (int) HandbookExport::query()
            ->whereNotNull('engagement_id')
            ->count();

        if ($total === 0) {
            $this->info('No legacy engagement exports found.');
            Log::info('Legacy engagement export prune complete: no rows to delete.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line(sprintf('Dry run: %d legacy engagement exports would be removed.', $total));
            Log::info('Legacy engagement export prune dry run complete.', ['total' => $total]);

            return self::SUCCESS;
        }

        $deletedExports = 0;
        $deletedArtifacts = 0;
        $missingArtifacts = 0;
        $failedArtifacts = 0;

        $exports = HandbookExport::query()
            ->whereNotNull('engagement_id')
            ->orderBy('id')
            ->lazyById(100);

        foreach ($exports as $export) {
            $result = $this->deleteArtifact($export);

            if ($result === 'deleted') {
                $deletedArtifacts++;
            } elseif ($result === 'failed') {
                $failedArtifacts++;
            } else {
                $missingArtifacts++;
            }

            $export->delete();
            $deletedExports++;
        }

        $summary = [
            'total' => $total,
            'deleted_exports' => $deletedExports,
            'deleted_artifacts' => $deletedArtifacts,
            'missing_artifacts' => $missingArtifacts,
            'failed_artifacts' => $failedArtifacts,
        ];

        $this->info(sprintf(
            'Pruned %d legacy engagement exports (%d artifacts deleted, %d missing, %d failed).',
            $deletedExports,
            $deletedArtifacts,
            $missingArtifacts,
            $failedArtifacts
        ));

        Log::info('Pruned legacy engagement handbook exports.', $summary);

        return self::SUCCESS;
    }

    protected function deleteArtifact(HandbookExport $export): string
    {
        $disk = $export->storageDisk();
        $path = $export->storage_path;

        if (! $disk || ! $path) {
            return 'missing';
        }

        try {
            return Storage::disk($disk)->delete($path) ? 'deleted' : 'missing';
        } catch (Throwable $exception) {
            Log::warning('Failed deleting legacy handbook export artifact.', [
                'export_id' => $export->getKey(),
                'disk' => $disk,
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return 'failed';
        }
    }
}
