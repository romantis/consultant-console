export const recompute = (metricId: string | number) => ({
    url: `/api/metrics/${metricId}/discrepancies/recompute`,
});
