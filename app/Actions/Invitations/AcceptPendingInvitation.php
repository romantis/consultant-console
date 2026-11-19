<?php

namespace App\Actions\Invitations;

use App\Enums\MembershipRole;
use App\Events\Engagements\EngagementInvitationAccepted;
use App\Events\Engagements\EngagementInvitationDeclined;
use App\Events\Engagements\EngagementInvitationRevoked;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\MetricParticipant;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Organizations\MembershipAuditLogger;
use App\Services\Organizations\MembershipRoleManager;
use App\Support\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AcceptPendingInvitation
{
    public function __construct(
        private MembershipRoleManager $membershipRoleManager,
        private MembershipAuditLogger $membershipAuditLogger,
    ) {}

    public function handle(Request $request, PendingMembership $pendingMembership): RedirectResponse|Response
    {
        $intent = $request->string('intent')->trim();

        if ($request->isMethod('post') && $intent->lower()->value() === 'decline') {
            $this->deactivateParticipantsForPending($pendingMembership, EngagementInvitationDeclined::class);

            $pendingMembership->delete();

            return redirect()->route('home')->with('success', 'Invitation declined.');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $this->handleGuest($request, $pendingMembership);
        }

        return $this->completeAcceptance($pendingMembership, $user);
    }

    private function handleGuest(Request $request, PendingMembership $pendingMembership): RedirectResponse|Response
    {
        $pendingEmail = mb_strtolower($pendingMembership->email);
        $existingUser = User::query()->where('email', $pendingEmail)->first();

        if ($request->isMethod('post')) {
            if ($existingUser instanceof User) {
                $request->session()->flash('auth.login.email', $pendingEmail);

                return redirect()->guest(route('login'))
                    ->with('status', 'Sign in with '.$pendingEmail.' to accept your invitation.');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'password_confirmation' => ['required'],
            ]);

            $user = new User([
                'name' => $validated['name'],
                'email' => $pendingEmail,
            ]);

            $user->password = Hash::make($validated['password']);
            $user->email_verified_at = now();
            $user->save();

            Auth::login($user);
            $request->session()->regenerate();

            return $this->completeAcceptance($pendingMembership, $user);
        }

        if ($existingUser instanceof User) {
            $request->session()->flash('auth.login.email', $pendingEmail);

            return redirect()->guest(route('login'))
                ->with('status', 'Sign in with '.$pendingEmail.' to accept your invitation.');
        }

        return Inertia::render('auth/AcceptInvite', $this->buildInviteViewData($request, $pendingMembership));
    }

    private function buildInviteViewData(Request $request, PendingMembership $pendingMembership): array
    {
        $pendingEmail = mb_strtolower($pendingMembership->email);

        $expiresAt = null;

        if ($request->has('expires')) {
            $expiresAt = Carbon::createFromTimestamp((int) $request->query('expires'))
                ->toDayDateTimeString();
        }

        return [
            'email' => $pendingEmail,
            'organization' => [
                'name' => $pendingMembership->organization?->name,
            ],
            'acceptUrl' => $request->fullUrl(),
            'expiresAt' => $expiresAt,
            'sso' => $this->resolveSsoContext($pendingMembership),
        ];
    }

    private function resolveSsoContext(PendingMembership $pendingMembership): ?array
    {
        if (
            ! config('metric-harmony.features.sso')
            || ! config('metric-harmony.features.sso_invites')
        ) {
            return null;
        }

        $organization = $pendingMembership->organization;

        if (! $organization) {
            return null;
        }

        $ssoConfig = Arr::get($organization->settings_json ?? [], 'sso');

        if (! is_array($ssoConfig) || empty($ssoConfig)) {
            return null;
        }

        $providers = $this->normalizeSsoProviders($ssoConfig);

        if ($providers->isEmpty()) {
            return null;
        }

        return [
            'hint' => Arr::get($ssoConfig, 'hint'),
            'providers' => $providers->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $ssoConfig
     */
    private function normalizeSsoProviders(array $ssoConfig): Collection
    {
        $providers = collect();

        $providersConfig = Arr::get($ssoConfig, 'providers');

        if (is_array($providersConfig) && ! empty($providersConfig)) {
            foreach ($providersConfig as $providerConfig) {
                if (! is_array($providerConfig)) {
                    continue;
                }

                $normalized = $this->normalizeSsoProvider($providerConfig, $ssoConfig);

                if ($normalized !== null) {
                    $providers->push($normalized);
                }
            }
        } else {
            $normalized = $this->normalizeSsoProvider($ssoConfig);

            if ($normalized !== null) {
                $providers->push($normalized);
            }
        }

        return $providers;
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @param  array<string, mixed>|null  $fallback
     */
    private function normalizeSsoProvider(array $providerConfig, ?array $fallback = null): ?array
    {
        $loginUrlValue = $this->firstFilledValue(
            $providerConfig,
            ['login_url', 'login_uri', 'url', 'redirect', 'route']
        ) ?? ($fallback ? $this->firstFilledValue($fallback, ['login_url', 'login_uri', 'url', 'redirect', 'route']) : null);

        $loginUrl = $this->normalizeLoginUrl($loginUrlValue);

        if (! is_string($loginUrl) || $loginUrl === '') {
            return null;
        }

        $label = $providerConfig['label']
            ?? $providerConfig['name']
            ?? ($fallback['label'] ?? $fallback['name'] ?? null)
            ?? 'Continue with single sign-on';

        return [
            'label' => $label,
            'loginUrl' => $loginUrl,
            'provider' => $providerConfig['provider'] ?? $providerConfig['driver'] ?? ($fallback['provider'] ?? null),
            'help' => $providerConfig['help'] ?? $providerConfig['description'] ?? ($fallback['help'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, string>  $keys
     */
    private function firstFilledValue(array $source, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($source, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function normalizeLoginUrl(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value)) {
            $routeName = Arr::get($value, 'name');

            if (is_string($routeName) && $routeName !== '' && Route::has($routeName)) {
                $parameters = Arr::get($value, 'parameters', []);

                return route($routeName, is_array($parameters) ? $parameters : []);
            }
        }

        return null;
    }

    private function completeAcceptance(PendingMembership $pendingMembership, User $user): RedirectResponse
    {
        $normalizedEmail = mb_strtolower($user->email);
        $pendingEmail = mb_strtolower($pendingMembership->email);

        abort_unless($pendingEmail === $normalizedEmail, 403);

        $organization = $pendingMembership->organization;

        $existingMembership = $organization?->memberships()
            ->where('user_id', $user->id)
            ->first();

        if ($existingMembership) {
            $this->deactivateParticipantsForPending($pendingMembership);

            $pendingMembership->delete();

            $redirectRoute = $this->landingRouteFor($user);

            return redirect()->route($redirectRoute)->with('success', 'You are already a member of this organization.');
        }

        [$membership, $engagementParticipantIds] = DB::transaction(function () use ($organization, $pendingMembership, $user) {
            $membership = $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => $pendingMembership->role->value,
                'affiliation' => $pendingMembership->affiliation,
            ]);

            $this->membershipAuditLogger->membershipCreated($membership, $user);

            $engagementParticipantIds = [];

            if ($pendingMembership->role === MembershipRole::OWNER) {
                $otherOwners = $organization->memberships()
                    ->where('role', MembershipRole::OWNER->value)
                    ->whereKeyNot($membership->id)
                    ->get();

                foreach ($otherOwners as $otherOwner) {
                    $this->membershipRoleManager->syncRole($otherOwner, MembershipRole::ADMIN, $user);
                }
            }

            $engagementParticipants = EngagementParticipant::query()
                ->where('pending_membership_id', $pendingMembership->id)
                ->lockForUpdate()
                ->get();

            foreach ($engagementParticipants as $engagementParticipant) {
                $engagementParticipant->fill([
                    'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
                    'membership_id' => $membership->id,
                    'pending_membership_id' => null,
                    'membership_role' => $membership->role?->value,
                    'active' => true,
                ]);

                $engagementParticipant->save();

                $engagementParticipantIds[] = $engagementParticipant->id;

                $metricParticipants = MetricParticipant::query()
                    ->where('engagement_participant_id', $engagementParticipant->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($metricParticipants as $metricParticipant) {
                    $metricParticipant->forceFill([
                        'user_id' => $membership->user_id,
                    ])->save();

                    $this->ensureAssignmentsForMetricParticipant($metricParticipant, $membership->user_id);
                }
            }

            $pendingMembership->delete();

            return [$membership, $engagementParticipantIds];
        });

        if (! empty($engagementParticipantIds)) {
            event(new EngagementInvitationAccepted($membership, $engagementParticipantIds));
        }

        $user = $membership->user ?? $membership->user()->first();
        $redirectRoute = $user ? $this->landingRouteFor($user) : 'home';

        return redirect()->route($redirectRoute)->with('success', 'Invite accepted.');
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

    private function deactivateParticipantsForPending(PendingMembership $pendingMembership, string $eventClass = EngagementInvitationRevoked::class): void
    {
        $participantIds = EngagementParticipant::query()
            ->where('pending_membership_id', $pendingMembership->id)
            ->pluck('id')
            ->all();

        if (! empty($participantIds)) {
            event(new $eventClass($pendingMembership, $participantIds));
        }
    }

    private function ensureAssignmentsForMetricParticipant(MetricParticipant $metricParticipant, string $userId): void
    {
        if (! $userId || ! $metricParticipant->metric_id) {
            return;
        }

        if (! in_array($metricParticipant->responsibility, ['contributor', 'approver'], true)) {
            return;
        }

        $forms = Form::query()
            ->where('metric_id', $metricParticipant->metric_id)
            ->get();

        foreach ($forms as $form) {
            $assignment = FormAssignment::firstOrNew([
                'form_id' => $form->id,
                'user_id' => $userId,
            ]);

            $assignment->fill([
                'metric_id' => $metricParticipant->metric_id,
                'metric_participant_id' => $metricParticipant->id,
                'responsibility' => ucfirst($metricParticipant->responsibility),
                'role_tags_json' => $metricParticipant->role_tags_json ?? [],
            ]);

            if (! $assignment->exists || $assignment->isDirty()) {
                $assignment->save();
            }

            FormSubmission::firstOrCreate([
                'form_id' => $form->id,
                'user_id' => $userId,
            ]);
        }
    }
}
