<?php

namespace App\Support\Observability;

use Illuminate\Support\Facades\Context;

class Telemetry
{
    private const ORG_KEY = 'observability.organization_id';

    private const ENGAGEMENT_KEY = 'observability.engagement_id';

    private const METRIC_KEY = 'observability.metric_id';

    public static function replace(
        ?string $organizationId = null,
        ?string $engagementId = null,
        ?string $metricId = null
    ): void {
        static::clear();
        static::extend($organizationId, $engagementId, $metricId);
    }

    public static function extend(
        ?string $organizationId = null,
        ?string $engagementId = null,
        ?string $metricId = null
    ): void {
        if ($organizationId) {
            Context::add(self::ORG_KEY, (string) $organizationId);
        }

        if ($engagementId) {
            Context::add(self::ENGAGEMENT_KEY, (string) $engagementId);
        }

        if ($metricId) {
            Context::add(self::METRIC_KEY, (string) $metricId);
        }
    }

    public static function scoped(
        ?string $organizationId,
        ?string $engagementId,
        ?string $metricId,
        callable $callback
    ): mixed {
        $previous = static::context();

        static::replace($organizationId, $engagementId, $metricId);

        try {
            return $callback();
        } finally {
            static::replace(
                $previous['organization_id'] ?? null,
                $previous['engagement_id'] ?? null,
                $previous['metric_id'] ?? null
            );
        }
    }

    public static function clear(): void
    {
        Context::forget([self::ORG_KEY, self::ENGAGEMENT_KEY, self::METRIC_KEY]);
    }

    /**
     * @return array{organization_id:?string,engagement_id:?string,metric_id:?string}
     */
    public static function context(): array
    {
        return [
            'organization_id' => Context::get(self::ORG_KEY),
            'engagement_id' => Context::get(self::ENGAGEMENT_KEY),
            'metric_id' => Context::get(self::METRIC_KEY),
        ];
    }

    /**
     * Build a normalized, deduplicated tag list for Horizon, logs, etc.
     *
     * @param  array{organization_id?:?string,engagement_id?:?string,metric_id?:?string}  $context
     * @param  array<int, string|null>  $extra
     * @return array<int, string>
     */
    public static function tags(array $context = [], array $extra = []): array
    {
        $context = array_merge(static::context(), array_filter($context, fn ($value) => $value !== null && $value !== ''));

        $tags = [];

        if (! empty($context['organization_id'])) {
            $tags[] = 'org:'.$context['organization_id'];
        }

        if (! empty($context['engagement_id'])) {
            $tags[] = 'engagement:'.$context['engagement_id'];
        }

        if (! empty($context['metric_id'])) {
            $tags[] = 'metric:'.$context['metric_id'];
        }

        foreach ($extra as $tag) {
            if (! $tag) {
                continue;
            }

            $tags[] = (string) $tag;
        }

        return array_values(array_unique($tags));
    }
}
