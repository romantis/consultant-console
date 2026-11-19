<?php

namespace App\Jobs;

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Actions\Handbook\SelectOfficialMetrics;
use App\Models\AuditLog;
use App\Models\HandbookExport;
use App\Models\User;
use App\Support\Handbook\HandbookPdfRenderer;
use App\Support\Observability\Telemetry;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateHandbookExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $handbookExportId,
        public ?string $requestedByUserId = null,
        public ?string $organizationId = null,
        public ?string $engagementId = null
    ) {
        $this->queue = config('queue.queues.exports', config('queue.queues.default', 'default'));
    }

    public function handle(
        BuildHandbookExportPayload $payloadBuilder,
        SelectOfficialMetrics $officialSelector,
        HandbookPdfRenderer $renderer
    ): void {
        $export = HandbookExport::query()
            ->with(['organization'])
            ->find($this->handbookExportId);

        if (! $export) {
            return;
        }

        $this->organizationId ??= (string) ($export->organization_id ?? '');
        $this->engagementId = null;

        Telemetry::replace(
            $this->organizationId !== '' ? $this->organizationId : null,
            null
        );

        $export->markProcessing();

        try {
            $organization = $export->organization;

            if (! $organization) {
                throw new \RuntimeException('Handbook export missing organization context.');
            }

            $metrics = $officialSelector->handle($organization);
            $generatedAt = now();
            $requestedBy = $this->resolveActor();

            $payloadBundle = $payloadBuilder->build($organization, $metrics, $requestedBy, $generatedAt);
            $payload = $payloadBundle['payload'];
            $sourceChecksum = $payloadBundle['source_checksum'];

            $pdf = $renderer->render('pdf.organization-handbook', $payload);

            $disk = $export->storage_disk ?: config('metric-harmony.handbook_disk', config('filesystems.default'));
            $storagePath = $export->storage_path ?: $this->buildStoragePath($export, $generatedAt);

            Storage::disk($disk)->put($storagePath, $pdf);

            $export->markReady($storagePath, checksum: hash('sha256', $pdf));

            $export->forceFill([
                'storage_disk' => $disk,
                'source_checksum' => $sourceChecksum,
                'expires_at' => $this->calculateExpiry($generatedAt),
            ])->save();

            AuditLog::create([
                'organization_id' => $organization->getKey(),
                'user_id' => $requestedBy?->getKey(),
                'action' => 'organization.handbook.generated',
                'subject_type' => 'organization',
                'subject_id' => $organization->getKey(),
                'properties' => [
                    'metric_ids' => $metrics->pluck('id')->all(),
                    'storage_path' => $storagePath,
                    'disk' => $disk,
                    'handbook_export_id' => $export->getKey(),
                ],
            ]);
        } catch (Throwable $exception) {
            $export->markFailed($this->buildFailureReason($exception));

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($export = HandbookExport::query()->find($this->handbookExportId)) {
            $export->markFailed($this->buildFailureReason($exception));
        }
    }

    protected function resolveActor(): ?User
    {
        if (! $this->requestedByUserId) {
            return null;
        }

        return User::query()->find($this->requestedByUserId);
    }

    protected function buildStoragePath(HandbookExport $export, CarbonInterface $generatedAt): string
    {
        return sprintf(
            'handbooks/%s/metrics-handbook-%s.pdf',
            $export->organization_id,
            $generatedAt->format('Ymd-His')
        );
    }

    protected function calculateExpiry(CarbonInterface $generatedAt): ?CarbonInterface
    {
        $ttl = (int) config('metric-harmony.handbook_url_expires_minutes', 15);

        return $ttl > 0 ? $generatedAt->copy()->addMinutes($ttl) : null;
    }

    protected function buildFailureReason(Throwable $exception): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?? '');

        if ($message === '') {
            return 'Handbook PDF generation failed. Check renderer configuration and application logs.';
        }

        $message = preg_replace(
            '/\b(api[_ -]?token|token|secret|password)\b\s*[:=]?\s*\S+/i',
            '$1 [redacted]',
            $message
        ) ?? $message;

        return sprintf(
            'Handbook PDF generation failed: %s',
            str($message)->limit(180)->value()
        );
    }

    public function tags(): array
    {
        return Telemetry::tags(
            [
                'organization_id' => $this->organizationId ?: null,
            ],
            [
                'job:generate-handbook-export',
                'export:'.$this->handbookExportId,
            ]
        );
    }
}
