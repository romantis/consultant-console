<?php

namespace App\Http\Controllers\Client;

use App\Actions\Handbook\RequestHandbookExport;
use App\Http\Controllers\Controller;
use App\Models\Engagement;
use App\Models\Organization;
use App\Support\ImpersonationGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HandbookExportRequestController extends Controller
{
    public function __invoke(
        Request $request,
        Organization $organization,
        Engagement $engagement,
        RequestHandbookExport $requestHandbookExport
    ): RedirectResponse {
        if ($engagement->organization_id !== $organization->id) {
            abort(404);
        }

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $context = ImpersonationGuard::resolve($user, $organization, $request);

        if (! $context->impersonating() && ! $context->membership()) {
            abort(403);
        }

        $result = $requestHandbookExport->handle($organization, $user);

        $message = match ($result['state']) {
            'processing' => 'A handbook export is already processing. Check back shortly for the updated download.',
            'unchanged' => 'No handbook changes detected. The latest export is ready to download.',
            default => 'Handbook export queued. We will notify you when it is ready.',
        };

        return back()->with('success', $message);
    }
}
