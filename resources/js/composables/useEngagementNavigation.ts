import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch, type ComputedRef } from 'vue';

import { persistEngagementSelection } from '@/lib/engagements';
import { show as orgEngagementsShow } from '@/routes/org/engagements';
import type { AppPageProps, EngagementSummary } from '@/types';

interface UseEngagementNavigationOptions {
    organizationId: ComputedRef<string | null>;
}

export function useEngagementNavigation({
    organizationId,
}: UseEngagementNavigationOptions) {
    const page = usePage<AppPageProps>();
    const engagementOptions = computed<EngagementSummary[]>(() => {
        const raw = page.props.engagements;

        if (Array.isArray(raw)) {
            return raw as EngagementSummary[];
        }

        if (raw && typeof raw === 'object' && 'data' in raw) {
            const collection = (raw as { data?: unknown }).data;

            if (Array.isArray(collection)) {
                return collection
                    .map((item) => {
                        if (!item || typeof item !== 'object') {
                            return null;
                        }

                        const source = item as Record<string, unknown>;
                        const id = source.id;
                        const title = source.title;
                        const status = source.status;

                        if (typeof id !== 'string' && typeof id !== 'number') {
                            return null;
                        }

                        return {
                            id: String(id),
                            title:
                                typeof title === 'string' ? title : String(id),
                            status:
                                typeof status === 'string' ? status : undefined,
                        } satisfies EngagementSummary;
                    })
                    .filter(
                        (value): value is EngagementSummary => value !== null,
                    );
            }
        }

        return [];
    });
    const activeEngagementIdFromPage = computed(
        () =>
            (page.props.activeEngagementId as string | null | undefined) ??
            null,
    );
    const selectedEngagementId = ref<string | null>(null);
    const pendingManualSelection = ref(false);

    watch(
        [organizationId, engagementOptions, activeEngagementIdFromPage],
        ([orgId, options, activeId]) => {
            if (!orgId || options.length === 0) {
                selectedEngagementId.value = null;
                pendingManualSelection.value = false;
                return;
            }

            if (pendingManualSelection.value) {
                if (activeId === selectedEngagementId.value) {
                    pendingManualSelection.value = false;
                }

                if (
                    options.some(
                        (item) => item.id === selectedEngagementId.value,
                    )
                ) {
                    return;
                }

                pendingManualSelection.value = false;
            }

            if (activeId && options.some((item) => item.id === activeId)) {
                selectedEngagementId.value = activeId;
                return;
            }

            if (
                selectedEngagementId.value &&
                options.some((item) => item.id === selectedEngagementId.value)
            ) {
                return;
            }

            selectedEngagementId.value = options[0].id;
        },
        { immediate: true },
    );

    const activeEngagement = computed(
        () =>
            engagementOptions.value.find(
                (item) => item.id === selectedEngagementId.value,
            ) ?? null,
    );

    const buildEngagementUrlFor = (engagementId?: string | null) => {
        const orgId = organizationId.value;
        const id = engagementId ?? selectedEngagementId.value;
        if (!orgId || !id) {
            return null;
        }

        const base = orgEngagementsShow([orgId, id]);
        const href = typeof base === 'string' ? base : base?.url;

        return href ?? null;
    };

    const buildMetricsUrlFor = (engagementId?: string | null) => {
        const href = buildEngagementUrlFor(engagementId);

        return href ? `${href}#metrics-panel` : null;
    };

    const engagementHref = computed(() => buildEngagementUrlFor());
    const metricsHref = computed(() => buildMetricsUrlFor());

    const setSelectedEngagement = (engagementId: string) => {
        const orgId = organizationId.value;
        if (!orgId) {
            return;
        }

        selectedEngagementId.value = engagementId;
        pendingManualSelection.value = true;
        persistEngagementSelection(orgId, engagementId);
    };

    const rememberSelection = () => {
        const orgId = organizationId.value;
        const id = selectedEngagementId.value;
        if (!orgId || !id) {
            return;
        }

        persistEngagementSelection(orgId, id);
    };

    return {
        engagementOptions,
        activeEngagement,
        selectedEngagementId,
        engagementHref,
        metricsHref,
        buildEngagementUrlFor,
        buildMetricsUrlFor,
        setSelectedEngagement,
        rememberSelection,
    };
}
