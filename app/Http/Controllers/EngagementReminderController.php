<?php

namespace App\Http\Controllers;

use App\Enums\EngagementStatus;
use App\Jobs\ProcessAssignmentReminders;
use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class EngagementReminderController extends Controller
{
    public function __invoke(Organization $organization, Engagement $engagement): RedirectResponse
    {
        abort_unless($engagement->organization_id === $organization->id, 404);

        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::fromLegacy((string) $engagement->status);

        if ($engagementStatus === EngagementStatus::FINALIZED) {
            throw ValidationException::withMessages([
                'engagement' => __('Engagement is finalized and read-only.'),
            ]);
        }

        $this->authorize('update', $engagement);

        if ($engagementStatus !== EngagementStatus::AWAITING_FORMS) {
            return back()->with('error', __('Reminder pass is available only when engagement status is Awaiting Forms.'));
        }

        try {
            ProcessAssignmentReminders::dispatch(
                organizationId: (string) $organization->id,
                engagementId: (string) $engagement->id,
            );

            return back()->with('success', __('Reminder jobs queued; assignments will be evaluated shortly.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Unable to queue reminders right now.'));
        }
    }
}
