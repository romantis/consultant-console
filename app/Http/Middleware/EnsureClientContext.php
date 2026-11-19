<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Impersonation;
use App\Support\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $this->clearInternalActiveOrganizationContext($request);

        if ($this->routeTargetsInternalOrganization($request)) {
            abort(404);
        }

        $requiresImpersonation = Staff::isInternalStaff($user);

        if (! $requiresImpersonation) {
            return $next($request);
        }

        $impersonatedOrganizationId = Impersonation::currentOrganizationId($request);

        if (! $impersonatedOrganizationId) {
            abort(403);
        }

        return $next($request);
    }

    private function clearInternalActiveOrganizationContext(Request $request): void
    {
        $activeOrganizationId = $request->attributes->get('active_organization_id');

        if (! is_string($activeOrganizationId) && ! is_numeric($activeOrganizationId)) {
            return;
        }

        $activeOrganization = Organization::query()
            ->select('id', 'is_internal')
            ->find($activeOrganizationId);

        if (! $activeOrganization?->is_internal) {
            return;
        }

        Cookie::queue(Cookie::forget(Impersonation::ACTIVE_ORGANIZATION_COOKIE));
        $request->attributes->set('active_organization_id', null);
    }

    private function routeTargetsInternalOrganization(Request $request): bool
    {
        $routeOrganization = $request->route('organization');

        if ($routeOrganization instanceof Organization) {
            return $routeOrganization->is_internal;
        }

        if (! is_string($routeOrganization) && ! is_numeric($routeOrganization)) {
            return false;
        }

        return Organization::query()
            ->whereKey($routeOrganization)
            ->where('is_internal', true)
            ->exists();
    }
}
