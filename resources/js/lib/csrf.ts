const XSRF_COOKIE_NAME = 'XSRF-TOKEN';

const readCookieValue = (name: string): string | null => {
    if (typeof document === 'undefined') {
        return null;
    }

    const rawCookie = document.cookie ?? '';
    if (!rawCookie) {
        return null;
    }

    const entries = rawCookie.split(';');

    for (const entry of entries) {
        const trimmed = entry.trim();
        if (!trimmed) {
            continue;
        }

        const equalsIndex = trimmed.indexOf('=');
        if (equalsIndex < 0) {
            continue;
        }

        const key = trimmed.slice(0, equalsIndex);
        if (key !== name) {
            continue;
        }

        return trimmed.slice(equalsIndex + 1);
    }

    return null;
};

export const resolveCsrfHeaders = (): Record<string, string> => {
    if (typeof document === 'undefined') {
        return {};
    }

    const cookieToken = readCookieValue(XSRF_COOKIE_NAME);
    if (cookieToken) {
        try {
            return { 'X-XSRF-TOKEN': decodeURIComponent(cookieToken) };
        } catch {
            return { 'X-XSRF-TOKEN': cookieToken };
        }
    }

    const metaToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    return metaToken ? { 'X-CSRF-TOKEN': metaToken } : {};
};
