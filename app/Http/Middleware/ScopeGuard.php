<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Observability\Telemetry;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if (! $route) {
            return $next($request);
        }

        $parameters = $route->parameters();

        $organizationParam = $parameters['organization'] ?? null;
        $engagementParam = $parameters['engagement'] ?? null;
        $metricParam = $parameters['metric'] ?? null;

        $organizationId = $this->extractId($organizationParam);
        $engagementId = $this->extractId($engagementParam);
        $metricId = $this->extractId($metricParam);

        $organizationIds = [];

        foreach ($parameters as $key => $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            if ($parameter instanceof Organization) {
                $paramOrgId = (string) $parameter->getKey();
                $organizationIds[] = $paramOrgId;

                if ($organizationId && $organizationId !== $paramOrgId) {
                    abort(404);
                }

                $organizationId = $paramOrgId;

                continue;
            }

            $paramOrgId = $this->attributeValue($parameter, 'organization_id');

            if ($paramOrgId) {
                $organizationIds[] = $paramOrgId;

                if ($organizationId && $organizationId !== $paramOrgId) {
                    abort(404);
                }

                $organizationId ??= $paramOrgId;
            }

            $paramEngagementId = $this->attributeValue($parameter, 'engagement_id');

            if ($paramEngagementId && $engagementId && $paramEngagementId !== $engagementId) {
                abort(404);
            }

            $paramMetricId = $this->attributeValue($parameter, 'metric_id');

            if ($paramMetricId && $metricId && $paramMetricId !== $metricId) {
                abort(404);
            }
        }

        $organizationIds = array_values(array_unique(array_filter($organizationIds)));

        if ($organizationId && $organizationIds && ! in_array($organizationId, $organizationIds, true)) {
            abort(404);
        }

        if (! $organizationId && count($organizationIds) === 1) {
            $organizationId = $organizationIds[0];
        }

        if ($organizationId) {
            $request->attributes->set('active_organization_id', $organizationId);
        }

        Telemetry::replace($organizationId, $engagementId, $metricId);

        return $next($request);
    }

    private function extractId(mixed $resource): ?string
    {
        if ($resource instanceof Model) {
            return (string) $resource->getKey();
        }

        if (is_string($resource) || is_int($resource)) {
            return (string) $resource;
        }

        return null;
    }

    private function attributeValue(Model $model, string $key): ?string
    {
        $attributes = $model->getAttributes();

        if (! array_key_exists($key, $attributes)) {
            return null;
        }

        $value = $model->getAttribute($key);

        return $value !== null ? (string) $value : null;
    }
}
