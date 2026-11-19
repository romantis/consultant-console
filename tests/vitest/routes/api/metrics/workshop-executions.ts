type MetricArg = string | number | { id: string | number };

const resolveId = (value: MetricArg): string => {
    if (typeof value === 'object' && value !== null && 'id' in value) {
        return String(value.id);
    }

    return String(value);
};

export const store = (metric: MetricArg) => ({
    url: `/api/metrics/${resolveId(metric)}/workshop-executions`,
});

export default {
    store,
};
