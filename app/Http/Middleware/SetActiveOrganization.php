<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Support\Impersonation;
use App\Support\Staff;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SetActiveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonated = Impersonation::currentOrganizationId($request);
        $impersonatedRole = Impersonation::currentRole($request);
        $impersonationJustification = Impersonation::justification($request);
        $impersonationStartedAt = Impersonation::startedAt($request);
        $activeCookieValue = Impersonation::resolveActiveOrganizationCookie($request, normalize: ! $impersonated);
        $request->attributes->set('impersonated_role', $impersonatedRole?->value);
        $request->attributes->set('impersonation_justification', $impersonationJustification);
        $request->attributes->set('impersonation_started_at', $impersonationStartedAt?->toISOString());

        if ($impersonated) {
            if (! $this->impersonationContextIsValid($request, (string) $impersonated, $impersonatedRole)) {
                $this->clearImpersonation($request);
                $request->session()->flash(
                    'impersonation_error',
                    'Your impersonation session expired. Select an organization to continue.',
                );

                if (! $this->shouldBypassInvalidRedirect($request)) {
                    return $this->redirectToWorkspace($request);
                }

                $request->attributes->set('impersonated_role', null);
                $request->attributes->set('impersonated_organization_id', null);
                $request->attributes->set('active_organization_id', null);
                $request->attributes->set('impersonation_justification', null);
                $request->attributes->set('impersonation_started_at', null);

                return $next($request);
            }

            $request->attributes->set('impersonated_organization_id', (string) $impersonated);
            $request->attributes->set('active_organization_id', (string) $impersonated);

            if ($activeCookieValue !== (string) $impersonated) {
                Impersonation::queueActiveOrganizationCookie((string) $impersonated);
            }

            return $next($request);
        }

        $request->attributes->set('active_organization_id', $activeCookieValue ? (string) $activeCookieValue : null);

        return $next($request);
    }

    private function impersonationContextIsValid(
        Request $request,
        string $organizationId,
        ?MembershipRole $role
    ): bool {
        if (! $role) {
            return false;
        }

        $user = $request->user();

        if (! $user || ! Staff::isStaff($user)) {
            return false;
        }

        $organization = Organization::query()
            ->select('id', 'is_internal')
            ->find($organizationId);

        if (! $organization || $organization->is_internal) {
            return false;
        }

        return Gate::forUser($user)->allows('impersonate', $organization);
    }

    private function clearImpersonation(Request $request): void
    {
        Impersonation::stop($request, true);
    }

    private function shouldBypassInvalidRedirect(Request $request): bool
    {
        return $request->routeIs(
            'owner.console',
            'owner.impersonation.start',
            'owner.impersonation.stop',
        );
    }

    private function redirectToWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && Staff::canGovernInternal($user)) {
            return redirect()->route('owner.console');
        }

        return redirect()->route('dashboard');
    }
}
