export const update = (params: [string | number, string | number]) => {
    const [organizationId, metricId] = params;
    return `/organizations/${organizationId}/metrics/${metricId}/final-definition`;
};
