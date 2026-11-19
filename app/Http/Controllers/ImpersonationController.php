<?php

namespace App\Http\Controllers;

use App\Actions\Audit\LogImpersonationEvent;
use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Support\Impersonation;
use App\Support\Staff;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImpersonationController extends Controller
{
    public function __construct(private LogImpersonationEvent $logImpersonationEvent) {}

    public function start(Request $request, Organization $organization): RedirectResponse
    {
        $justification = $this->rememberJustification($request);

        try {
            $this->authorize('impersonate', $organization);
        } catch (AuthorizationException $exception) {
            $response = $this->denyImpersonationAttempt(
                $request,
                $organization,
                $justification,
                $this->stringifyRoleValue($request->input('role')),
            );

            if ($response) {
                return $response;
            }

            throw $exception;
        }

        $validator = Validator::make(
            $request->only('role'),
            [
                'role' => [
                    'required',
                    'string',
                    Rule::in(array_map(
                        static fn (MembershipRole $role) => $role->value,
                        MembershipRole::cases(),
                    )),
                ],
            ],
        );

        if ($validator->fails()) {
            $failed = $validator->failed();

            if (isset($failed['role']['In'])) {
                $this->flashInvalidRoleError($request);
                $this->logImpersonationEvent->denied(
                    $organization,
                    $request->user(),
                    $justification,
                    $this->stringifyRoleValue($request->input('role')),
                );

                throw ValidationException::withMessages([
                    'role' => $this->roleErrorMessage(),
                ]);
            }

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $role = MembershipRole::from($validated['role']);

        $currentOrganizationId = Impersonation::currentOrganizationId($request);
        $currentRole = Impersonation::currentRole($request);

        if ($currentOrganizationId === (string) $organization->id) {
            if ($currentRole?->value === $role->value) {
                return Redirect::route('org.show', $organization);
            }

            Impersonation::rememberRole($request, $role);

            $this->logImpersonationEvent->roleChanged(
                $organization,
                $request->user(),
                $justification,
                $role,
            );

            return Redirect::route('org.show', $organization)
                ->with('success', sprintf(
                    'Updated impersonation role to %s.',
                    ucfirst($role->value),
                ));
        }

        Impersonation::start($request, (string) $organization->id, $role, $justification);

        $this->logImpersonationEvent->started(
            $organization,
            $request->user(),
            $justification,
            $role,
        );

        return Redirect::route('org.show', $organization)
            ->with('success', sprintf(
                'Impersonating %s as %s.',
                $organization->name,
                ucfirst($role->value),
            ));
    }

    public function stop(Request $request): RedirectResponse
    {
        $this->authorize('impersonateAny', Organization::class);

        $organizationId = Impersonation::currentOrganizationId($request);

        if (! $organizationId) {
            return $this->respondWithMissingImpersonation($request);
        }

        $impersonatedOrganization = Organization::query()->find($organizationId);
        $role = Impersonation::currentRole($request);
        $justification = $this->rememberJustification($request);

        if (! Impersonation::stop($request, true)) {
            return $this->respondWithMissingImpersonation($request);
        }

        if ($impersonatedOrganization) {
            $this->logImpersonationEvent->stopped(
                $impersonatedOrganization,
                $request->user(),
                $justification,
                $role,
            );
        }

        return Redirect::route($this->workspaceRoute($request))
            ->with('success', 'Stopped impersonating.');
    }

    private function rememberJustification(Request $request): ?string
    {
        if (! $request->has('justification')) {
            return Impersonation::justification($request);
        }

        $value = $request->input('justification');

        if ($value === null) {
            return Impersonation::rememberJustification($request, null);
        }

        if (! is_scalar($value)) {
            return Impersonation::rememberJustification($request, null);
        }

        $stringValue = trim((string) $value);

        return Impersonation::rememberJustification($request, $stringValue);
    }

    private function respondWithMissingImpersonation(Request $request): RedirectResponse
    {
        $message = 'No active impersonation session to stop.';

        $request->session()->flash('impersonation_error', $message);

        return Redirect::route($this->workspaceRoute($request));
    }

    private function roleErrorMessage(): string
    {
        return 'Select a valid role to impersonate this organization.';
    }

    private function organizationDeniedMessage(): string
    {
        return 'You are not allowed to impersonate this organization.';
    }

    private function stringifyRoleValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function flashInvalidRoleError(Request $request): void
    {
        $request->session()->flash('impersonation_error', $this->roleErrorMessage());
    }

    private function flashOrganizationDeniedError(Request $request): void
    {
        $request->session()->flash('impersonation_error', $this->organizationDeniedMessage());
    }

    private function denyImpersonationAttempt(
        Request $request,
        Organization $organization,
        ?string $justification,
        ?string $attemptedRole
    ): ?RedirectResponse {
        $user = $request->user();

        if (! $user || ! Staff::canImpersonate($user)) {
            return null;
        }

        $this->flashOrganizationDeniedError($request);

        $this->logImpersonationEvent->denied(
            $organization,
            $user,
            $justification,
            $attemptedRole,
        );

        if ($request->expectsJson()) {
            return null;
        }

        return Redirect::route($this->workspaceRoute($request));
    }

    private function workspaceRoute(Request $request): string
    {
        $user = $request->user();

        if ($user && Staff::canGovernInternal($user)) {
            return 'owner.console';
        }

        return 'dashboard';
    }
}
