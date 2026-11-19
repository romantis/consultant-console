<?php

namespace App\Services\Owner;

use App\Enums\EngagementStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OwnerDashboardService
{
    /**
     * @var array<string, string>
     */
    private const STATUS_SCOPES = [
        'active' => 'active',
        'onboarding' => 'onboarding',
        'completed' => 'completed',
        'idle' => 'idle',
    ];

    public function organizations(?string $search = null, ?string $status = null): array
    {
        $activeStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::activePhases()
        );
        $terminalStatuses = array_map(
            static fn (EngagementStatus $status) => $status->value,
            EngagementStatus::terminalPhases()
        );

        $query = Organization::query()
            ->withCount([
                'engagements',
                'engagements as active_engagements_count' => function (Builder $builder) use ($activeStatuses) {
                    $builder->whereIn('status', $activeStatuses);
                },
                'engagements as draft_engagements_count' => function (Builder $builder) {
                    $builder->where('status', EngagementStatus::DRAFT);
                },
                'engagements as completed_engagements_count' => function (Builder $builder) use ($terminalStatuses) {
                    $builder->whereIn('status', $terminalStatuses);
                },
                'metrics',
                'memberships',
                'pendingMemberships',
            ])
            ->orderBy('name');

        $this->applySearch($query, $search);

        if ($status && isset(self::STATUS_SCOPES[$status])) {
            $scope = self::STATUS_SCOPES[$status];
            $query->$scope();
        }

        /** @var Collection<int, Organization> $organizations */
        $organizations = $query->get();

        return [
            'organizations' => $organizations->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status_badges' => $this->statusBadges($organization),
                'engagements_count' => $organization->engagements_count,
                'active_engagements_count' => $organization->active_engagements_count,
                'draft_engagements_count' => $organization->draft_engagements_count,
                'completed_engagements_count' => $organization->completed_engagements_count,
                'metrics_count' => $organization->metrics_count,
                'memberships_count' => $organization->memberships_count,
                'pending_memberships_count' => $organization->pending_memberships_count,
            ])->values()->all(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'status_options' => $this->statusOptions($search),
            'summary' => [
                'organizations' => $organizations->count(),
                'active_engagements' => (int) $organizations->sum('active_engagements_count'),
                'metrics' => (int) $organizations->sum('metrics_count'),
                'pending_invites' => (int) $organizations->sum('pending_memberships_count'),
            ],
        ];
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $term = mb_strtolower($search);

        $query->where(function (Builder $builder) use ($term) {
            $builder
                ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                ->orWhereRaw('LOWER(slug) LIKE ?', ["%{$term}%"]);
        });
    }

    private function statusOptions(?string $search): array
    {
        return collect(self::STATUS_SCOPES)
            ->map(function (string $scope, string $key) use ($search) {
                $builder = Organization::query();
                $this->applySearch($builder, $search);
                $builder->$scope();

                return [
                    'value' => $key,
                    'label' => Str::headline($key),
                    'count' => $builder->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function statusBadges(Organization $organization): array
    {
        $badges = [];

        if ($organization->active_engagements_count > 0) {
            $badges[] = [
                'label' => 'Active',
                'variant' => 'success',
                'count' => (int) $organization->active_engagements_count,
            ];
        }

        if ($organization->draft_engagements_count > 0) {
            $badges[] = [
                'label' => 'Onboarding',
                'variant' => 'warning',
                'count' => (int) $organization->draft_engagements_count,
            ];
        }

        if ($organization->completed_engagements_count > 0
            && $organization->active_engagements_count === 0
            && $organization->draft_engagements_count === 0) {
            $badges[] = [
                'label' => 'Finalized',
                'variant' => 'muted',
                'count' => (int) $organization->completed_engagements_count,
            ];
        }

        if (empty($badges)) {
            $badges[] = [
                'label' => 'Idle',
                'variant' => 'outline',
                'count' => 0,
            ];
        }

        return $badges;
    }
}
