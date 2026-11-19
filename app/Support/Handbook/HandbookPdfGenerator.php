<?php

namespace App\Support\Handbook;

use Carbon\CarbonImmutable;
use RuntimeException;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class HandbookPdfGenerator implements HandbookPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data = []): string
    {
        $document = HandbookPdfDocument::fromPayload($data);

        try {
            $pdf = Pdf::view($view, [
                'document' => $document,
            ])
                ->name('metrics-handbook.pdf')
                ->format(Format::A4);

            if ($this->shouldAttachMetadata()) {
                $pdf->meta(
                    $document->metadata['title'],
                    $document->metadata['author'],
                    $document->metadata['subject'],
                    null,
                    $document->metadata['creator'],
                    $this->creationDate($document->metadata['generated_at'])
                );
            }

            if ($this->supportsTaggedPdf()) {
                $pdf->tagged();
            }

            $bytes = base64_decode($pdf->base64(), true);

            if ($bytes === false || ! str_starts_with($bytes, '%PDF')) {
                throw new RuntimeException('PDF renderer returned an invalid PDF payload.');
            }

            return $bytes;
        } catch (Throwable $exception) {
            throw new RuntimeException($this->mapFailureReason($exception), previous: $exception);
        }
    }

    private function supportsTaggedPdf(): bool
    {
        return $this->configuredDriver() === 'cloudflare';
    }

    private function shouldAttachMetadata(): bool
    {
        return $this->configuredDriver() !== 'chrome';
    }

    private function configuredDriver(): string
    {
        return (string) config('laravel-pdf.driver', '');
    }

    private function creationDate(string $generatedAt): string|CarbonImmutable|null
    {
        if ($generatedAt === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($generatedAt);
        } catch (Throwable) {
            return $generatedAt;
        }
    }

    private function mapFailureReason(Throwable $exception): string
    {
        $driver = $this->configuredDriver();
        $message = strtolower(trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?? ''));

        if (
            $driver === 'cloudflare'
            && (
                blank(config('laravel-pdf.cloudflare.api_token'))
                || blank(config('laravel-pdf.cloudflare.account_id'))
            )
        ) {
            return 'Cloudflare PDF configuration is incomplete. Set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID.';
        }

        if (
            str_contains($message, '429')
            || str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
        ) {
            return 'Cloudflare PDF generation hit a rate limit (429). Check Browser Rendering limits or move to a paid Workers plan.';
        }

        if (
            str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'curl error 28')
        ) {
            return $driver === 'cloudflare'
                ? 'Cloudflare PDF generation timed out. Check Browser Rendering availability and timeout settings.'
                : 'Local Chrome PDF generation timed out. Check Chrome availability and laravel-pdf timeout settings.';
        }

        if (str_contains($message, 'invalid pdf payload')) {
            return 'PDF renderer returned an invalid PDF payload.';
        }

        if (
            $driver === 'chrome'
            && (
                str_contains($message, 'chrome')
                || str_contains($message, 'browser')
            )
        ) {
            return 'Local Chrome PDF generation failed. Install Chrome/Chromium or set LARAVEL_PDF_CHROME_BINARY.';
        }

        if ($driver === 'cloudflare') {
            return 'Cloudflare PDF generation failed. Check Cloudflare Browser Rendering credentials and application logs.';
        }

        return 'PDF renderer failed. Check laravel-pdf driver configuration and application logs.';
    }
}
