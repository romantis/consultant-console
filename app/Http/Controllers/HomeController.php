<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Client\HandbookController as ClientHandbookController;
use App\Support\Impersonation;
use App\Support\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class HomeController
{
    public function __invoke(Request $request)
    {
        if (! $request->user()) {
            return Inertia::render('Welcome');
        }

        $user = $request->user();

        if (Staff::isInternalStaff($user) && ! Staff::isStaff($user)) {
            Cookie::queue(Cookie::forget(Impersonation::ACTIVE_ORGANIZATION_COOKIE));

            return redirect()->route('profile.edit');
        }

        if (! Staff::isStaff($user)) {
            return app(ClientHandbookController::class)($request);
        }

        // Prefer an explicit current_org_id from the session (org switcher later)
        $currentOrgId = session('current_org_id');

        if (! $currentOrgId) {
            // Pick the first NON-internal org the user belongs to
            $currentOrgId = $user->memberships()
                ->whereHas('organization', fn ($q) => $q->where('is_internal', false))
                ->value('organization_id');
        }

        if ($currentOrgId) {
            return redirect()->route('org.show', $currentOrgId);
        }

        // Staff without client orgs can create one immediately.
        return redirect()->route('org.create');
    }
}
