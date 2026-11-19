import { resolveCsrfHeaders } from '@/lib/csrf';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    watch,
    type ComputedRef,
} from 'vue';

import type {
    WorkshopSession,
    WorkshopSessionContext,
} from '@/features/workshops/metric-workshop/types';
import {
    buildInProgressWorkshopMetricIds,
    cloneWorkshopSessions,
    formatWorkshopSessionDateTime,
    hasUpcomingOrInProgressWorkshopSession,
    isWorkshopSessionStartWindowOpen,
    resolveLastCompletedWorkshopEndedLabel,
    resolvePreferredWorkshopSession,
} from '@/features/workshops/metric-workshop/workshop-session';

type SessionRouteDescriptor =
    | string
    | null
    | undefined
    | {
          url?: string | null;
      };

type WorkshopSessionsRouteApi = {
    index?: (
        params: [organizationId: string, engagementId: string],
    ) => SessionRouteDescriptor;
    start?: (
        params: [
            organizationId: string,
            engagementId: string,
            sessionId: string,
        ],
    ) => SessionRouteDescriptor;
    complete?: (
        params: [
            organizationId: string,
            engagementId: string,
            sessionId: string,
        ],
    ) => SessionRouteDescriptor;
};

type ToastFn = (variant: 'success' | 'error', message: string) => void;

interface UseWorkshopSessionsOptions {
    organizationId: ComputedRef<string>;
    engagementId: ComputedRef<string | null>;
    engagementStatus: ComputedRef<string | null | undefined>;
    metricId: ComputedRef<string>;
    workshopClosedProp: ComputedRef<boolean | undefined>;
    workshopSessionContext: ComputedRef<WorkshopSessionContext>;
    isStaff: ComputedRef<boolean>;
    canManageWorkshopSessions: ComputedRef<boolean>;
    workshopForceStartEnabled: ComputedRef<boolean>;
    workshopSessionsRouteApi: ComputedRef<
        WorkshopSessionsRouteApi | null | undefined
    >;
    statusProbeUrl: () => string;
    toast: ToastFn;
    fetchImpl?: typeof fetch;
    statusPollIntervalMs?: number;
}

const resolveRouteUrl = (route: SessionRouteDescriptor): string | null => {
    if (!route) {
        return null;
    }

    if (typeof route === 'string') {
        return route;
    }

    return route.url ?? null;
};

const resolveWorkshopClosedState = ({
    workshopClosedProp,
    engagementStatus,
}: {
    workshopClosedProp?: boolean;
    engagementStatus?: string | null;
}): boolean => {
    if (typeof workshopClosedProp === 'boolean') {
        return workshopClosedProp;
    }

    return engagementStatus !== 'InWorkshop';
};

export function useWorkshopSessions(options: UseWorkshopSessionsOptions) {
    const workshopSessions = ref<WorkshopSession[]>([]);
    const workshopSessionError = ref<string | null>(null);
    const workshopSessionsLoading = ref(false);
    const workshopSessionStartBusy = ref<string | null>(null);
    const workshopSessionCompleteBusy = ref<string | null>(null);
    const selectedWorkshopSessionId = ref<string | null>(
        options.workshopSessionContext.value.selected_session_id ?? null,
    );
    const workshopClosed = ref(
        resolveWorkshopClosedState({
            workshopClosedProp: options.workshopClosedProp.value,
            engagementStatus: options.engagementStatus.value,
        }),
    );
    const pollingEngagementStatus = ref(false);
    const isForceWorkshopStartDialogOpen = ref(false);
    const isFinishWorkshopDialogOpen = ref(false);

    let statusPoller: number | null = null;

    const canLoadWorkshopSessions = computed(
        () =>
            Boolean(options.engagementId.value) &&
            typeof options.workshopSessionsRouteApi.value?.index === 'function',
    );

    const workshopSessionsIndexUrl = computed(() => {
        const engagementId = options.engagementId.value;
        const organizationId = options.organizationId.value;
        const buildIndexRoute = options.workshopSessionsRouteApi.value?.index;

        if (!engagementId || typeof buildIndexRoute !== 'function') {
            return null;
        }

        return resolveRouteUrl(buildIndexRoute([organizationId, engagementId]));
    });

    const applyWorkshopSessionContext = (): void => {
        workshopSessions.value = cloneWorkshopSessions(
            options.workshopSessionContext.value.sessions,
        );
        selectedWorkshopSessionId.value =
            options.workshopSessionContext.value.selected_session_id ?? null;
        workshopSessionError.value = null;
    };

    const loadWorkshopSessions = async (): Promise<void> => {
        const url = workshopSessionsIndexUrl.value;
        if (!canLoadWorkshopSessions.value || !url) {
            return;
        }

        workshopSessionsLoading.value = true;
        workshopSessionError.value = null;

        try {
            const response = await (options.fetchImpl ?? fetch)(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            const payload = (await response.json()) as {
                data?: WorkshopSession[];
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                if (
                    !options.canManageWorkshopSessions.value &&
                    response.status === 403
                ) {
                    applyWorkshopSessionContext();
                    return;
                }

                throw new Error(
                    payload.message ??
                        `Unable to load workshop sessions (${response.status})`,
                );
            }

            workshopSessions.value = cloneWorkshopSessions(payload.data);

            if (
                !selectedWorkshopSessionId.value ||
                !workshopSessions.value.some(
                    (session) => session.id === selectedWorkshopSessionId.value,
                )
            ) {
                selectedWorkshopSessionId.value =
                    options.workshopSessionContext.value.selected_session_id ??
                    null;
            }
        } catch (error) {
            workshopSessionError.value =
                error instanceof Error
                    ? error.message
                    : 'Unable to load workshop sessions.';
        } finally {
            workshopSessionsLoading.value = false;
        }
    };

    const workshopSessionsForMetric = computed(() =>
        workshopSessions.value.filter((session) =>
            (session.metric_ids ?? []).includes(options.metricId.value),
        ),
    );

    const activeWorkshopSession = computed<WorkshopSession | null>(() => {
        if (workshopSessionsForMetric.value.length === 0) {
            return null;
        }

        if (selectedWorkshopSessionId.value) {
            const selected = workshopSessionsForMetric.value.find(
                (session) => session.id === selectedWorkshopSessionId.value,
            );

            if (selected) {
                return selected;
            }
        }

        return resolvePreferredWorkshopSession(workshopSessionsForMetric.value);
    });

    const activeWorkshopSessionStatus = computed(() =>
        (activeWorkshopSession.value?.status ?? '').toLowerCase(),
    );

    const canStartActiveWorkshopSession = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            !workshopClosed.value &&
            activeWorkshopSessionStatus.value === 'scheduled' &&
            isWorkshopSessionStartWindowOpen({
                session: activeWorkshopSession.value,
            }),
    );

    const canForceStartActiveWorkshopSession = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            !workshopClosed.value &&
            options.workshopForceStartEnabled.value &&
            activeWorkshopSessionStatus.value === 'scheduled',
    );

    const showWorkshopFinishAction = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            activeWorkshopSessionStatus.value === 'inprogress',
    );

    const showWorkshopStartAction = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            activeWorkshopSessionStatus.value === 'scheduled',
    );

    const isWorkshopStartBusy = computed(
        () =>
            workshopSessionStartBusy.value === activeWorkshopSession.value?.id,
    );

    const isWorkshopFinishBusy = computed(
        () =>
            workshopSessionCompleteBusy.value ===
            activeWorkshopSession.value?.id,
    );

    const canForceStartFromHeaderAction = computed(
        () =>
            showWorkshopStartAction.value &&
            !canStartActiveWorkshopSession.value &&
            canForceStartActiveWorkshopSession.value,
    );

    const isWorkshopStartUnavailable = computed(
        () =>
            showWorkshopStartAction.value &&
            !canStartActiveWorkshopSession.value &&
            !canForceStartFromHeaderAction.value,
    );

    const shouldShowForceStartTooltip = computed(
        () => canForceStartFromHeaderAction.value && !isWorkshopStartBusy.value,
    );

    const isWorkshopStartButtonDisabled = computed(
        () =>
            isWorkshopStartBusy.value ||
            isWorkshopFinishBusy.value ||
            isWorkshopStartUnavailable.value,
    );

    const workshopSessionAttentionBadgeLabel = computed(() => {
        if (!options.canManageWorkshopSessions.value) {
            return null;
        }

        if (isWorkshopStartBusy.value) {
            return 'Starting...';
        }

        if (activeWorkshopSessionStatus.value === 'missed') {
            return 'Session missed';
        }

        return null;
    });

    const workshopSessionAttentionBadgeVariant = computed<
        'secondary' | 'destructive'
    >(() => {
        if (isWorkshopStartBusy.value) {
            return 'secondary';
        }

        return 'destructive';
    });

    const showWorkshopSessionInProgressText = computed(
        () =>
            !isWorkshopStartBusy.value &&
            activeWorkshopSessionStatus.value === 'inprogress',
    );

    const inProgressWorkshopMetricIds = computed(() =>
        buildInProgressWorkshopMetricIds({
            sessions: workshopSessions.value,
            activeSessionStatus: activeWorkshopSessionStatus.value,
            fallbackMetricId: options.metricId.value,
        }),
    );

    const showScheduledWorkshopTime = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            !workshopClosed.value &&
            !isWorkshopStartBusy.value &&
            activeWorkshopSessionStatus.value === 'scheduled' &&
            Boolean(activeWorkshopSessionScheduledLabel.value),
    );

    const hasUpcomingOrInProgressForMetric = computed(() =>
        hasUpcomingOrInProgressWorkshopSession(workshopSessionsForMetric.value),
    );

    const lastCompletedWorkshopEndedLabel = computed(() =>
        resolveLastCompletedWorkshopEndedLabel(workshopSessionsForMetric.value),
    );

    const showLastWorkshopEndedLabel = computed(
        () =>
            options.canManageWorkshopSessions.value &&
            !hasUpcomingOrInProgressForMetric.value &&
            Boolean(lastCompletedWorkshopEndedLabel.value),
    );

    const activeWorkshopSessionScheduledLabel = computed(() => {
        const startsAt = activeWorkshopSession.value?.starts_at;
        if (!startsAt) {
            return null;
        }

        return formatWorkshopSessionDateTime(
            startsAt,
            activeWorkshopSession.value?.timezone,
        );
    });

    const startActiveWorkshopSession = async (
        forceStart = false,
    ): Promise<void> => {
        const session = activeWorkshopSession.value;
        const engagementId = options.engagementId.value;
        const organizationId = options.organizationId.value;

        if (
            !session ||
            !engagementId ||
            isWorkshopFinishBusy.value ||
            !(forceStart
                ? canForceStartActiveWorkshopSession.value
                : canStartActiveWorkshopSession.value) ||
            workshopSessionStartBusy.value
        ) {
            return;
        }

        const buildStartRoute = options.workshopSessionsRouteApi.value?.start;
        if (typeof buildStartRoute !== 'function') {
            return;
        }

        const url = resolveRouteUrl(
            buildStartRoute([organizationId, engagementId, session.id]),
        );
        if (!url) {
            return;
        }

        workshopSessionStartBusy.value = session.id;
        workshopSessionError.value = null;

        try {
            const response = await (options.fetchImpl ?? fetch)(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...resolveCsrfHeaders(),
                },
                credentials: 'same-origin',
                body: forceStart ? JSON.stringify({ force_start: true }) : '{}',
            });

            const payload = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                const errorMessage =
                    payload.errors?.status?.[0] ??
                    payload.message ??
                    `Unable to start workshop session (${response.status})`;
                throw new Error(errorMessage);
            }

            await loadWorkshopSessions();
            options.toast(
                'success',
                forceStart
                    ? 'Workshop session force-started.'
                    : 'Workshop session started.',
            );
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to start workshop session.';
            workshopSessionError.value = message;
            options.toast('error', message);
        } finally {
            workshopSessionStartBusy.value = null;
        }
    };

    const finishActiveWorkshopSession = async (): Promise<void> => {
        const session = activeWorkshopSession.value;
        const engagementId = options.engagementId.value;
        const organizationId = options.organizationId.value;

        if (
            !session ||
            !engagementId ||
            !showWorkshopFinishAction.value ||
            isWorkshopFinishBusy.value ||
            isWorkshopStartBusy.value
        ) {
            return;
        }

        const buildCompleteRoute =
            options.workshopSessionsRouteApi.value?.complete;
        if (typeof buildCompleteRoute !== 'function') {
            return;
        }

        const url = resolveRouteUrl(
            buildCompleteRoute([organizationId, engagementId, session.id]),
        );
        if (!url) {
            return;
        }

        workshopSessionCompleteBusy.value = session.id;
        workshopSessionError.value = null;

        try {
            const response = await (options.fetchImpl ?? fetch)(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...resolveCsrfHeaders(),
                },
                credentials: 'same-origin',
                body: '{}',
            });

            const payload = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                const errorMessage =
                    payload.errors?.status?.[0] ??
                    payload.message ??
                    `Unable to complete workshop session (${response.status})`;
                throw new Error(errorMessage);
            }

            await loadWorkshopSessions();
            options.toast('success', 'Workshop session completed.');
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to complete workshop session.';
            workshopSessionError.value = message;
            options.toast('error', message);
        } finally {
            workshopSessionCompleteBusy.value = null;
        }
    };

    const requestWorkshopStart = (): void => {
        if (isWorkshopStartBusy.value || !showWorkshopStartAction.value) {
            return;
        }

        if (canStartActiveWorkshopSession.value) {
            void startActiveWorkshopSession(false);
            return;
        }

        if (canForceStartFromHeaderAction.value) {
            isForceWorkshopStartDialogOpen.value = true;
        }
    };

    const requestWorkshopFinish = (): void => {
        if (!showWorkshopFinishAction.value || isWorkshopFinishBusy.value) {
            return;
        }

        isFinishWorkshopDialogOpen.value = true;
    };

    const confirmForceWorkshopStart = (): void => {
        if (isWorkshopStartBusy.value || !canForceStartFromHeaderAction.value) {
            isForceWorkshopStartDialogOpen.value = false;
            return;
        }

        isForceWorkshopStartDialogOpen.value = false;
        void startActiveWorkshopSession(true);
    };

    const confirmFinishWorkshop = (): void => {
        if (!showWorkshopFinishAction.value || isWorkshopFinishBusy.value) {
            isFinishWorkshopDialogOpen.value = false;
            return;
        }

        isFinishWorkshopDialogOpen.value = false;
        void finishActiveWorkshopSession();
    };

    const pollEngagementStatus = async (): Promise<void> => {
        if (pollingEngagementStatus.value || workshopClosed.value) {
            return;
        }

        pollingEngagementStatus.value = true;

        try {
            const response = await (options.fetchImpl ?? fetch)(
                options.statusProbeUrl(),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as {
                engagement_status?: string | null;
            };
            const status = (payload.engagement_status ?? '').toString();

            if (status && status !== 'InWorkshop') {
                workshopClosed.value = true;
            }
        } catch {
            // Ignore polling failures; next interval will retry.
        } finally {
            pollingEngagementStatus.value = false;
        }
    };

    const clearStatusPoller = (): void => {
        if (statusPoller === null || typeof window === 'undefined') {
            return;
        }

        window.clearInterval(statusPoller);
        statusPoller = null;
    };

    const startStatusPolling = (): void => {
        if (typeof window === 'undefined') {
            return;
        }

        void pollEngagementStatus();
        statusPoller = window.setInterval(() => {
            if (
                typeof document !== 'undefined' &&
                document.visibilityState === 'hidden'
            ) {
                return;
            }

            void pollEngagementStatus();
        }, options.statusPollIntervalMs ?? 30000);
    };

    onMounted(() => {
        if (canLoadWorkshopSessions.value) {
            void loadWorkshopSessions();
        } else {
            applyWorkshopSessionContext();
        }

        if (workshopClosed.value || options.isStaff.value) {
            return;
        }

        startStatusPolling();
    });

    onUnmounted(() => {
        clearStatusPoller();
    });

    watch(workshopClosed, (value) => {
        if (!value) {
            return;
        }

        clearStatusPoller();
    });

    watch(
        [options.workshopClosedProp, options.engagementStatus],
        ([workshopClosedProp, engagementStatus]) => {
            workshopClosed.value = resolveWorkshopClosedState({
                workshopClosedProp,
                engagementStatus,
            });
        },
    );

    watch(
        options.workshopSessionContext,
        () => {
            if (!canLoadWorkshopSessions.value) {
                applyWorkshopSessionContext();
            }
        },
        { deep: true },
    );

    watch(
        workshopSessionsForMetric,
        (sessions) => {
            if (sessions.length === 0) {
                selectedWorkshopSessionId.value = null;
                return;
            }

            const hasSelected = selectedWorkshopSessionId.value
                ? sessions.some(
                      (session) =>
                          session.id === selectedWorkshopSessionId.value,
                  )
                : false;

            if (hasSelected) {
                return;
            }

            const preferred = resolvePreferredWorkshopSession(sessions);
            selectedWorkshopSessionId.value = preferred?.id ?? null;
        },
        { immediate: true },
    );

    watch(
        [canLoadWorkshopSessions, options.engagementId, options.metricId],
        ([canLoadWorkshopSessionsValue]) => {
            if (!canLoadWorkshopSessionsValue) {
                applyWorkshopSessionContext();
                return;
            }

            void loadWorkshopSessions();
        },
    );

    const isWorkshopMetricLive = (metricId: string): boolean =>
        inProgressWorkshopMetricIds.value.has(metricId);

    return {
        workshopClosed,
        workshopSessionError,
        isForceWorkshopStartDialogOpen,
        isFinishWorkshopDialogOpen,
        workshopSessionAttentionBadgeLabel,
        workshopSessionAttentionBadgeVariant,
        showWorkshopSessionInProgressText,
        showScheduledWorkshopTime,
        activeWorkshopSessionScheduledLabel,
        showWorkshopStartAction,
        showWorkshopFinishAction,
        isWorkshopStartBusy,
        isWorkshopFinishBusy,
        canForceStartFromHeaderAction,
        shouldShowForceStartTooltip,
        isWorkshopStartButtonDisabled,
        showLastWorkshopEndedLabel,
        lastCompletedWorkshopEndedLabel,
        requestWorkshopStart,
        requestWorkshopFinish,
        confirmForceWorkshopStart,
        confirmFinishWorkshop,
        isWorkshopMetricLive,
        activeWorkshopSession,
        canStartActiveWorkshopSession,
        workshopSessionsLoading,
        loadWorkshopSessions,
        pollEngagementStatus,
    };
}
