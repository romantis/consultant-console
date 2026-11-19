export const index = (orgId: string | number) => `/organizations/${orgId}/engagements`;
export const show = (params: [string | number, string | number]) => {
    const [orgId, engagementId] = params;
    return `/organizations/${orgId}/engagements/${engagementId}`;
};

export const create = (orgId: string | number) => ({
    url: `/organizations/${orgId}/engagements/create`,
});

export const launch = (orgId: string | number) => ({
    url: `/organizations/${orgId}/engagements/launch`,
});

export const workshop = (
    params: [string | number, string | number],
    options?: { query?: Record<string, string> },
) => {
    const [orgId, engagementId] = params;
    const base = `/organizations/${orgId}/engagements/${engagementId}/workshop`;
    const query = options?.query ?? {};
    const queryString = Object.keys(query).length
        ? `?${new URLSearchParams(query).toString()}`
        : '';

    return { url: `${base}${queryString}` };
};
