import { resolveCsrfHeaders } from './csrf';

const ENDPOINT = '/organizations/active';

export async function persistActiveOrganizationSelection(
    organizationId: string,
): Promise<void> {
    if (
        typeof document === 'undefined' ||
        typeof window === 'undefined' ||
        organizationId === ''
    ) {
        return;
    }

    const csrfHeaders = resolveCsrfHeaders();
    if (Object.keys(csrfHeaders).length === 0 || typeof fetch === 'undefined') {
        return;
    }

    const response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...csrfHeaders,
        },
        body: JSON.stringify({ organization_id: organizationId }),
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(
            `Failed to persist active organization selection (${response.status})`,
        );
    }
}
