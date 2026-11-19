<?php

namespace App\Actions\Engagements;

use App\Enums\EngagementStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnsureOrgMetricExclusivity
{
    /**
     * @param  Collection<int, array{org_metric_id?: string|null, index?: int|null, canonical_name?: string|null, canonical_key?: string|null}>  $selections
     */
    public function handle(
        Organization $organization,
        Collection $selections,
        ?Engagement $ignoreEngagement = null,
        ?string $fallbackField = null
    ): void {
        $orgMetricIds = $selections
            ->pluck('org_metric_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($orgMetricIds->isEmpty()) {
            return;
        }

        $blockedStatuses = array_merge(
            [EngagementStatus::DRAFT->value],
            array_map(static fn (EngagementStatus $status) => $status->value, EngagementStatus::activePhases())
        );

        $conflicts = Metric::query()
            ->select([
                'metrics.org_metric_id',
                'engagements.id as engagement_id',
                'engagements.title as engagement_title',
                'engagements.status as engagement_status',
            ])
            ->join('engagements', 'engagements.id', '=', 'metrics.engagement_id')
            ->where('metrics.organization_id', $organization->id)
            ->whereIn('metrics.org_metric_id', $orgMetricIds->all())
            ->whereIn('engagements.status', $blockedStatuses)
            ->when(
                $ignoreEngagement,
                fn ($query) => $query->where('engagements.id', '!=', $ignoreEngagement->id)
            )
            ->orderByDesc('engagements.updated_at')
            ->get()
            ->groupBy('org_metric_id')
            ->map(fn (Collection $rows) => $rows->first());

        if ($conflicts->isEmpty()) {
            return;
        }

        $errors = [];

        foreach ($selections as $selection) {
            $orgMetricId = isset($selection['org_metric_id']) ? (string) $selection['org_metric_id'] : null;

            if (! $orgMetricId || ! $conflicts->has($orgMetricId)) {
                continue;
            }

            $conflict = $conflicts->get($orgMetricId);

            $field = $fallbackField;
            if (! $field) {
                $index = $selection['index'] ?? null;
                $field = is_int($index) ? "metrics.$index.canonical_metric_id" : 'metrics';
            }

            $metricLabel = (string) ($selection['canonical_name'] ?? $selection['canonical_key'] ?? __('This metric'));
            $statusLabel = $this->formatStatusLabel((string) ($conflict->engagement_status ?? ''));
            $engagementTitle = (string) ($conflict->engagement_title ?? __('(Untitled engagement)'));

            $errors[$field][] = __(
                ':metric is already in use by the :status engagement ":title".',
                [
                    'metric' => $metricLabel,
                    'status' => $statusLabel,
                    'title' => $engagementTitle,
                ]
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function formatStatusLabel(string $status): string
    {
        $trimmed = trim($status);

        if ($trimmed === '') {
            return __('active');
        }

        return Str::headline($trimmed);
    }
}
