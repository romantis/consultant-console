import type { InertiaLinkProps } from '@inertiajs/vue3';

const COOKIE_PREFIX = 'active_engagement_id_';
const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 30; // 30 days

export function withEngagementQuery(
    href: NonNullable<InertiaLinkProps['href']>,
    engagementId: string,
): string {
    const base = typeof href === 'string' ? href : (href?.url ?? '');

    if (!base) {
        return '';
    }

    const separator = base.includes('?') ? '&' : '?';

    return `${base}${separator}engagement=${encodeURIComponent(engagementId)}`;
}

export function persistEngagementSelection(
    organizationId: string,
    engagementId: string,
) {
    if (typeof document === 'undefined') {
        return;
    }

    const cookieName = `${COOKIE_PREFIX}${organizationId}`;
    document.cookie = `${cookieName}=${encodeURIComponent(engagementId)};path=/;max-age=${COOKIE_MAX_AGE_SECONDS};SameSite=Lax`;
}

export function engagementCookieName(organizationId: string) {
    return `${COOKIE_PREFIX}${organizationId}`;
}
