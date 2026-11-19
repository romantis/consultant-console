<?php

namespace App\Actions\Engagements;

use App\Actions\Audit\LogEngagementEvent;
use App\Enums\EngagementStatus;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteEngagementDraft
{
    public function __construct(
        private readonly LogEngagementEvent $logEngagementEvent
    ) {}

    public function handle(Engagement $engagement, ?User $actor = null): void
    {
        $engagementStatus = $engagement->status instanceof EngagementStatus
            ? $engagement->status
            : EngagementStatus::tryFromLegacy((string) $engagement->status);

        if ($engagementStatus !== EngagementStatus::DRAFT) {
            throw ValidationException::withMessages([
                'engagement' => __('Only draft engagements can be deleted.'),
            ]);
        }

        DB::transaction(function () use ($engagement, $engagementStatus, $actor): void {
            $this->logEngagementEvent->handle(
                $engagement,
                'engagement.draft.deleted',
                [
                    'status' => $engagementStatus?->value ?? (string) $engagement->status,
                    'title' => $engagement->title,
                ],
                $actor,
                $engagement
            );

            $engagement->forceDelete();
        });
    }
}
