export const prep = (params: [string | number, string | number, string | number]) => {
    const [orgId, engagementId, metricId] = params;
    return { url: `/organizations/${orgId}/engagements/${engagementId}/metrics/${metricId}/prep` };
};
