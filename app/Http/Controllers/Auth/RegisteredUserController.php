<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingMembership;
use App\Models\User;
use App\Support\Staff;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(Request $request): Response
    {
        $pendingMembershipId = $request->string('pending_membership_id')->trim();

        $pendingMembership = null;

        if ($pendingMembershipId->isNotEmpty()) {
            $pendingMembership = PendingMembership::query()->find($pendingMembershipId->toString());
        }

        $inviteEmail = $pendingMembership?->email;

        if (! $inviteEmail) {
            $emailQuery = $request->string('email')->trim();

            if ($emailQuery->isNotEmpty()) {
                $inviteEmail = $emailQuery->toString();
            }
        }

        return Inertia::render('auth/Register', [
            'pendingMembershipId' => $pendingMembership?->getKey(),
            'inviteEmail' => $inviteEmail,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $pendingMembershipId = $request->string('pending_membership_id')->trim();

        if ($pendingMembershipId->isNotEmpty()) {
            $pendingMembership = PendingMembership::query()->find($pendingMembershipId->toString());

            if ($pendingMembership && mb_strtolower($pendingMembership->email) === mb_strtolower($user->email)) {
                $ttlMinutes = (int) Config::get('invitations.pending.accept_ttl_minutes', 60 * 24 * 7);

                $referenceTimestamp = $pendingMembership->updated_at ?? $pendingMembership->created_at;

                if ($pendingMembership->created_at && $pendingMembership->created_at->greaterThan($referenceTimestamp)) {
                    $referenceTimestamp = $pendingMembership->created_at;
                }

                $referenceTimestamp ??= now();

                $expiresAt = $referenceTimestamp->copy()->addMinutes($ttlMinutes);

                if ($expiresAt->isPast()) {
                    $redirectRoute = $this->landingRouteFor($user);

                    return redirect()
                        ->route($redirectRoute)
                        ->with('error', 'Your invitation has expired. Ask the inviter to send a new one.');
                }

                $acceptUrl = URL::temporarySignedRoute(
                    'invites.accept',
                    $expiresAt,
                    [
                        'pendingMembership' => $pendingMembership,
                    ]
                );

                return redirect()->to($acceptUrl);
            }
        }

        return to_route($this->landingRouteFor($user));
    }

    private function landingRouteFor(User $user): string
    {
        if (Staff::isStaff($user)) {
            return 'dashboard';
        }

        if (Staff::isInternalStaff($user)) {
            return 'profile.edit';
        }

        return 'home';
    }
}
