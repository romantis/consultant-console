import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    return (
        normalizeUrlForComparison(toUrl(urlToCheck)) ===
        normalizeUrlForComparison(currentUrl)
    );
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    if (typeof href === 'string') {
        return href;
    }

    if (href instanceof URL) {
        return href.toString();
    }

    if (href && typeof href === 'object') {
        if (
            'url' in href &&
            typeof (href as { url?: unknown }).url === 'string'
        ) {
            return (href as { url: string }).url;
        }

        if (
            'href' in href &&
            typeof (href as { href?: unknown }).href === 'string'
        ) {
            return (href as { href: string }).href;
        }
    }

    return String(href);
}

function normalizeUrlForComparison(input: string) {
    const trimmed = input.trim();
    if (trimmed === '') {
        return '';
    }

    try {
        const parsed = new URL(trimmed, 'http://inertia.local');
        const pathname = parsed.pathname.replace(/\/+$/, '') || '/';
        return `${pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return trimmed;
    }
}
