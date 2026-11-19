<?php

namespace App\Http\Controllers;

use App\Models\HandbookExport;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandbookExportController extends Controller
{
    public function publish(
        Request $request,
        Organization $organization,
        HandbookExport $handbookExport
    ): RedirectResponse {
        $this->assertContext($organization, $handbookExport);

        $this->authorize('publish', $handbookExport);

        if (! $handbookExport->isReady()) {
            return back()->with('error', 'Handbook export is still processing. Try again after it completes.');
        }

        if (! $this->storageExists($handbookExport)) {
            return back()->with('error', 'The handbook artifact is missing. Regenerate the export before publishing.');
        }

        $handbookExport->publish($request->user());

        return back()->with('success', 'Handbook export published and ready to share.');
    }

    public function unpublish(
        Organization $organization,
        HandbookExport $handbookExport
    ): RedirectResponse {
        $this->assertContext($organization, $handbookExport);

        $this->authorize('unpublish', $handbookExport);

        $handbookExport->unpublish();

        return back()->with('success', 'Handbook export publication revoked.');
    }

    public function download(
        Organization $organization,
        HandbookExport $handbookExport
    ): StreamedResponse|RedirectResponse {
        $this->assertContext($organization, $handbookExport);

        $this->authorize('download', $handbookExport);

        if (! $handbookExport->isReady()) {
            return back()->with('error', 'Handbook export is still processing. Try again after it completes.');
        }

        if (! $this->storageExists($handbookExport)) {
            return back()->with('error', 'The handbook artifact is missing. Regenerate the export before downloading.');
        }

        $temporaryUrl = $this->resolveTemporaryDownloadUrl($handbookExport);

        if ($temporaryUrl) {
            return redirect()->away($temporaryUrl);
        }

        return $this->downloadResponse($handbookExport);
    }

    /**
     * @return StreamedResponse|RedirectResponse
     */
    public function showPublic(HandbookExport $handbookExport)
    {
        $this->authorize('accessPublic', $handbookExport);

        return $this->downloadResponse($handbookExport);
    }

    protected function assertContext(
        Organization $organization,
        HandbookExport $handbookExport
    ): void {
        if (
            $handbookExport->organization_id !== $organization->id
        ) {
            abort(404);
        }
    }

    protected function storageExists(HandbookExport $handbookExport): bool
    {
        $disk = $handbookExport->storageDisk();
        $path = $handbookExport->storage_path;

        if (! $disk || ! $path) {
            return false;
        }

        return Storage::disk($disk)->exists($path);
    }

    protected function downloadResponse(HandbookExport $handbookExport): StreamedResponse|RedirectResponse
    {
        $disk = $handbookExport->storageDisk();
        $path = $handbookExport->storage_path;

        if (! $disk || ! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->download($path, $this->buildDownloadName($handbookExport));
    }

    protected function resolveTemporaryDownloadUrl(HandbookExport $handbookExport): ?string
    {
        $disk = $handbookExport->storageDisk();
        $path = $handbookExport->storage_path;

        if (! $disk || ! $path) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (! method_exists($storage, 'temporaryUrl')) {
            return null;
        }

        try {
            return $storage->temporaryUrl(
                $path,
                now()->addMinutes((int) config('metric-harmony.handbook_url_expires_minutes', 15))
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildDownloadName(HandbookExport $handbookExport): string
    {
        $organization = $handbookExport->relationLoaded('organization')
            ? $handbookExport->organization
            : $handbookExport->organization()->first();

        $title = $organization?->name;

        if (! $title) {
            return 'metrics-handbook.pdf';
        }

        return Str::slug($title).'-metrics-handbook.pdf';
    }
}
