<?php

namespace App\Http\Controllers;

use App\Actions\Handbook\RequestHandbookExport;
use App\Models\Organization;
use App\Support\ImpersonationGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EngagementHandbookController extends Controller
{
    public function store(
        Request $request,
        Organization $organization,
        RequestHandbookExport $requestHandbookExport
    ): RedirectResponse {
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
