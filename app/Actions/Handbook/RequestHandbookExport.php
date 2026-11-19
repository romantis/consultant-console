<?php

namespace App\Actions\Handbook;

use App\Jobs\GenerateHandbookExport;
use App\Models\HandbookExport;
use App\Models\Organization;
use App\Models\User;

class RequestHandbookExport
{
    public function __construct(
        private readonly BuildHandbookExportPayload $payloadBuilder,
        private readonly SelectOfficialMetrics $officialSelector
    ) {}

    /**
     * @return array{export:HandbookExport, state:string}
     */
    public function handle(Organization $organization, ?User $actor = null): array
    {
        $metrics = $this->officialSelector->handle($organization);
        $checksum = $this->payloadBuilder->sourceChecksum($organization, $metrics);

        $pendingExport = HandbookExport::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [HandbookExport::STATUS_QUEUED, HandbookExport::STATUS_PROCESSING])
            ->orderByDesc('created_at')
            ->first();

        if ($pendingExport) {
            return [
                'export' => $pendingExport,
                'state' => 'processing',
            ];
        }

        $readyExport = HandbookExport::query()
            ->where('organization_id', $organization->id)
            ->where('status', HandbookExport::STATUS_READY)
            ->where('source_checksum', $checksum)
            ->orderByDesc('completed_at')
            ->first();

        if ($readyExport) {
            return [
                'export' => $readyExport,
                'state' => 'unchanged',
            ];
        }

        $queuedAt = now();
        $storagePath = sprintf(
            'handbooks/%s/metrics-handbook-%s.pdf',
            $organization->getKey(),
            $queuedAt->format('Ymd-His')
        );

        $export = HandbookExport::create([
            'organization_id' => $organization->id,
            'format' => 'pdf',
            'storage_disk' => config('metric-harmony.handbook_disk', config('filesystems.default')),
            'storage_path' => $storagePath,
            'status' => HandbookExport::STATUS_QUEUED,
            'queued_at' => $queuedAt,
            'source_checksum' => $checksum,
        ]);

        GenerateHandbookExport::dispatch(
            (string) $export->getKey(),
            $actor?->getKey(),
            (string) $organization->getKey(),
            null
        );

        return [
            'export' => $export,
            'state' => 'queued',
        ];
    }
}
