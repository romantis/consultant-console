<?php

namespace App\Observers;

use App\Models\PendingMembership;
use App\Notifications\PendingMembershipInvited;
use Illuminate\Support\Facades\Notification;

class PendingMembershipObserver
{
    public function created(PendingMembership $pending): void
    {
        if (empty($pending->email) || $pending->engagement_id) {
            return;
        }

        Notification::route('mail', $pending->email)
            ->notify(new PendingMembershipInvited($pending));
    }
}
