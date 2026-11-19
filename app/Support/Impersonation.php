<?php

namespace App\Support;

use App\Enums\MembershipRole;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Throwable;

class Impersonation
{
    public const SESSION_KEY = 'impersonation.organization_id';

    public const SESSION_ROLE_KEY = 'impersonation.role';

    public const SESSION_JUSTIFICATION_KEY = 'impersonation.justification';

    public const SESSION_STARTED_AT_KEY = 'impersonation.started_at';

    public const PREVIOUS_ACTIVE_SESSION_KEY = 'impersonation.previous_active_organization_id';

    public const ACTIVE_ORGANIZATION_COOKIE = 'active_organization_id';

    public const DEFAULT_COOKIE_TTL_MINUTES = 60 * 24 * 30; // 30 days

    public static function cookieTtlMinutes(): int
    {
        $configured = config('console.impersonation.cookie_ttl_minutes');

        if (is_numeric($configured)) {
            return (int) $configured;
        }

        return self::DEFAULT_COOKIE_TTL_MINUTES;
    }

    public static function queueActiveOrganizationCookie(string $organizationId): void
    {
        Cookie::queue(cookie(
            self::ACTIVE_ORGANIZATION_COOKIE,
            $organizationId,
            self::cookieTtlMinutes(),
            path: config('session.path', '/'),
            domain: config('session.domain'),
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            sameSite: config('session.same_site', 'lax'),
        ));
    }

    public static function currentOrganizationId(Request $request): ?string
    {
        $value = $request->session()->get(self::SESSION_KEY);

        return $value ? (string) $value : null;
    }

    public static function currentRole(Request $request): ?MembershipRole
    {
        $value = $request->session()->get(self::SESSION_ROLE_KEY);

        if (! $value) {
            return null;
        }

        return MembershipRole::tryFrom((string) $value);
    }

    public static function justification(Request $request): ?string
    {
        $value = $request->session()->get(self::SESSION_JUSTIFICATION_KEY);

        if (! is_scalar($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    public static function startedAt(Request $request): ?Carbon
    {
        $value = $request->session()->get(self::SESSION_STARTED_AT_KEY);

        if (! is_scalar($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function start(
        Request $request,
        string $organizationId,
        ?MembershipRole $role = null,
        ?string $justification = null,
        ?Carbon $startedAt = null
    ): void {
        $session = $request->session();

        if (! $session->has(self::PREVIOUS_ACTIVE_SESSION_KEY)) {
            $current = self::resolveActiveOrganizationCookie($request);

            if ($current) {
                $session->put(self::PREVIOUS_ACTIVE_SESSION_KEY, (string) $current);
            } else {
                $session->forget(self::PREVIOUS_ACTIVE_SESSION_KEY);
            }
        }

        $session->put(self::SESSION_KEY, (string) $organizationId);

        if ($role) {
            self::rememberRole($request, $role);
        }

        if ($justification !== null) {
            self::rememberJustification($request, $justification);
        }

        self::rememberStartedAt($request, $startedAt);

        self::queueActiveOrganizationCookie((string) $organizationId);
    }

    public static function stop(Request $request, bool $clearJustification = false): bool
    {
        $session = $request->session();

        $impersonated = $session->pull(self::SESSION_KEY);
        $session->forget(self::SESSION_STARTED_AT_KEY);
        $session->forget(self::SESSION_ROLE_KEY);

        if ($clearJustification) {
            $session->forget(self::SESSION_JUSTIFICATION_KEY);
        }

        if (! $impersonated) {
            return false;
        }

        $previous = $session->pull(self::PREVIOUS_ACTIVE_SESSION_KEY);

        if ($previous) {
            self::queueActiveOrganizationCookie((string) $previous);

            return true;
        }

        Cookie::queue(Cookie::forget(self::ACTIVE_ORGANIZATION_COOKIE));

        return true;
    }

    public static function rememberRole(Request $request, ?MembershipRole $role): void
    {
        $session = $request->session();

        if ($role) {
            $session->put(self::SESSION_ROLE_KEY, $role->value);

            return;
        }

        $session->forget(self::SESSION_ROLE_KEY);
    }

    public static function rememberJustification(Request $request, ?string $justification): ?string
    {
        $normalized = self::normalizeJustification($justification);

        if ($normalized === null) {
            $request->session()->forget(self::SESSION_JUSTIFICATION_KEY);

            return null;
        }

        $request->session()->put(self::SESSION_JUSTIFICATION_KEY, $normalized);

        return $normalized;
    }

    public static function rememberStartedAt(Request $request, ?Carbon $timestamp = null): void
    {
        $value = ($timestamp ?? Carbon::now())->toISOString();

        $request->session()->put(self::SESSION_STARTED_AT_KEY, $value);
    }

    public static function resolveActiveOrganizationCookie(Request $request, bool $normalize = false): ?string
    {
        $rawValue = $request->cookie(self::ACTIVE_ORGANIZATION_COOKIE);

        if (! is_scalar($rawValue)) {
            return null;
        }

        $trimmed = trim((string) $rawValue);

        if ($trimmed === '') {
            return null;
        }

        if (self::isPlainOrganizationIdentifier($trimmed)) {
            if ($normalize) {
                self::queueActiveOrganizationCookie($trimmed);
            }

            return $trimmed;
        }

        $legacyValue = self::decryptLegacyCookieValue($trimmed);

        if ($normalize) {
            if ($legacyValue) {
                self::queueActiveOrganizationCookie((string) $legacyValue);
            } else {
                Cookie::queue(Cookie::forget(self::ACTIVE_ORGANIZATION_COOKIE));
            }
        }

        return $legacyValue;
    }

    private static function isPlainOrganizationIdentifier(string $value): bool
    {
        return Str::isUlid($value) || Str::isUuid($value);
    }

    private static function decryptLegacyCookieValue(string $value): ?string
    {
        /** @var Encrypter $encrypter */
        $encrypter = app(Encrypter::class);

        try {
            $decrypted = $encrypter->decrypt($value, false);

            if (! is_string($decrypted)) {
                return null;
            }

            return CookieValuePrefix::validate(
                self::ACTIVE_ORGANIZATION_COOKIE,
                $decrypted,
                $encrypter->getAllKeys(),
            );
        } catch (DecryptException) {
            return null;
        }
    }

    private static function normalizeJustification(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        $maxLength = (int) config('console.impersonation.justification_max_length', 500);

        return Str::limit($stringValue, $maxLength, '');
    }
}
