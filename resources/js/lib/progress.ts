export type ProgressCounts =
    | Record<string, number | null | undefined>
    | null
    | undefined;

export type ProgressSegment = {
    key: string;
    label: string;
    value: number;
    percentage: number;
};

export type ProgressSummary = {
    total: number;
    completion: number;
    segments: ProgressSegment[];
};

export type ProgressOptions = {
    completionKeys?: string[];
    labels?: Record<string, string>;
    precision?: number;
};

const defaultHumanize = (key: string): string =>
    key
        .replace(/_/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());

const roundTo = (value: number, precision: number): number => {
    const factor = 10 ** precision;
    return Math.round(value * factor) / factor;
};

export const summarizeProgress = (
    counts: ProgressCounts,
    options?: ProgressOptions,
): ProgressSummary => {
    const safeCounts = counts ?? {};
    const precision = options?.precision ?? 0;
    const entries = Object.entries(safeCounts).map(([key, raw]) => ({
        key,
        value: typeof raw === 'number' && Number.isFinite(raw) ? raw : 0,
    }));

    const total = entries.reduce((sum, entry) => sum + entry.value, 0);
    const completionKeys = options?.completionKeys ?? [];
    const completionValue = entries
        .filter((entry) => completionKeys.includes(entry.key))
        .reduce((sum, entry) => sum + entry.value, 0);

    const segments: ProgressSegment[] = entries.map((entry) => {
        const label =
            options?.labels?.[entry.key] ?? defaultHumanize(entry.key);
        const percentage =
            total > 0 ? roundTo((entry.value / total) * 100, precision) : 0;
        return {
            key: entry.key,
            label,
            value: entry.value,
            percentage,
        };
    });

    const completion =
        total > 0 ? roundTo((completionValue / total) * 100, precision) : 0;

    return {
        total,
        completion,
        segments,
    };
};
