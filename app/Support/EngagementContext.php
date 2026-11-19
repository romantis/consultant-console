<?php

namespace App\Support;

use App\Models\Engagement;
use App\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class EngagementContext
{
    public static function resolve(Organization $organization, ?string $engagementId = null): Engagement
    {
        $query = $organization->engagements();

        if ($engagementId) {
            $query->where('id', $engagementId);
        }

        $engagement = $query
            ->orderByDesc('starts_on')
            ->orderByDesc('created_at')
            ->first();

        if (! $engagement) {
            throw ValidationException::withMessages([
                'engagement' => __('No engagement configured for :organization', ['organization' => $organization->name]),
            ]);
        }

        return $engagement;
    }

    public static function resolveOrFail(Organization $organization, string $engagementId): Engagement
    {
        $engagement = $organization->engagements()->where('id', $engagementId)->first();

        if (! $engagement) {
            throw (new ModelNotFoundException)->setModel(Engagement::class, [$engagementId]);
        }

        return $engagement;
    }
}
