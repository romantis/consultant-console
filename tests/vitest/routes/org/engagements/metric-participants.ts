export const update = (
    params: [string | number, string | number, string | number, string | number],
) => {
    const [orgId, engagementId, metricId, participantId] = params;
    return {
        url: `/organizations/${orgId}/engagements/${engagementId}/metrics/${metricId}/participants/${participantId}`,
    };
};
