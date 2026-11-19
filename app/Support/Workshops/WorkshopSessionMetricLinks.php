<?php

namespace App\Support\Workshops;

use App\Models\EngagementWorkshopSession;

final class WorkshopSessionMetricLinks
{
    /**
     * @return array<int, array{id: string, name: string, url: string}>
     */
    public static function build(EngagementWorkshopSession $session): array
    {
        $session->loadMissing('metrics.metric:id,name');

        return $session->metrics
            ->map(function ($metricLink) use ($session): array {
                $metricId = (string) $metricLink->metric_id;
                $metricName = trim((string) ($metricLink->metric?->name ?? 'Metric'));

                return [
                    'id' => $metricId,
                    'name' => $metricName !== '' ? $metricName : 'Metric',
                    'url' => route('org.engagements.workshop', [
                        'organization' => $session->organization_id,
                        'engagement' => $session->engagement_id,
                        'metric' => $metricId,
                    ]),
                ];
            })
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array{id: string, name: string, url: string}  $metricLink
     */
    public static function toMarkdownListItem(array $metricLink): string
    {
        $metricName = trim((string) ($metricLink['name'] ?? 'Metric'));
        $safeMetricName = $metricName !== '' ? $metricName : 'Metric';
        $safeMetricName = str_replace(
            ['\\', '[', ']'],
            ['\\\\', '\\[', '\\]'],
            $safeMetricName
        );

        return sprintf('- [%s](%s)', $safeMetricName, (string) ($metricLink['url'] ?? ''));
    }
}
