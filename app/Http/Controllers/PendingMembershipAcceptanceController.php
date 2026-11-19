<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptPendingInvitation;
use App\Models\PendingMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PendingMembershipAcceptanceController extends Controller
{
    public function __construct(
        private AcceptPendingInvitation $acceptPendingInvitation,
    ) {}

    public function __invoke(Request $request, PendingMembership $pendingMembership): RedirectResponse|Response
    {
        return $this->acceptPendingInvitation->handle($request, $pendingMembership);
    }
}
