import { flushPromises, mount } from '@vue/test-utils';
import { computed, defineComponent, h, type PropType } from 'vue';

import { useWorkshopSessions } from '@/features/workshops/metric-workshop/useWorkshopSessions';
import type {
    WorkshopSession,
    WorkshopSessionContext,
} from '@/features/workshops/metric-workshop/types';

const toastSpy = vi.hoisted(() => vi.fn());
const fetchMock = vi.hoisted(() => vi.fn());

vi.mock('@/lib/csrf', () => ({
    resolveCsrfHeaders: () => ({}),
}));

type SessionHarnessState = {
    workshopClosed: boolean;
    workshopSessionError: string | null;
    showScheduledWorkshopTime: boolean;
    activeWorkshopSessionScheduledLabel: string | null;
    showWorkshopStartAction: boolean;
    showWorkshopFinishAction: boolean;
    isForceWorkshopStartDialogOpen: boolean;
    requestWorkshopStart: () => void;
    confirmForceWorkshopStart: () => void;
    isWorkshopMetricLive: (metricId: string) => boolean;
    activeWorkshopSession: WorkshopSession | null;
};

const workshopSessionsRouteApi = {
    index: ([organizationId, engagementId]: [string, string]) => ({
        url: `/stub/orgs/${organizationId}/engagements/${engagementId}/workshop-sessions`,
    }),
    start: ([organizationId, engagementId, sessionId]: [
        string,
        string,
        string,
    ]) => ({
        url: `/stub/orgs/${organizationId}/engagements/${engagementId}/workshop-sessions/${sessionId}/start`,
    }),
    complete: ([organizationId, engagementId, sessionId]: [
        string,
        string,
        string,
    ]) => ({
        url: `/stub/orgs/${organizationId}/engagements/${engagementId}/workshop-sessions/${sessionId}/complete`,
    }),
};

const SessionHarness = defineComponent({
    name: 'SessionHarness',
    props: {
        organizationId: {
            type: String,
            default: 'org-1',
        },
        engagementId: {
            type: String,
            default: 'eng-1',
        },
        engagementStatus: {
            type: String,
            default: 'InWorkshop',
        },
        metricId: {
            type: String,
            default: 'metric-1',
        },
        isStaff: {
            type: Boolean,
            default: true,
        },
        canManageWorkshopSessions: {
            type: Boolean,
            default: true,
        },
        workshopForceStartEnabled: {
            type: Boolean,
            default: false,
        },
        workshopClosedValue: {
            type: String as PropType<'true' | 'false' | 'unset'>,
            default: 'unset',
        },
        sessionContext: {
            type: Object as PropType<WorkshopSessionContext>,
            default: () => ({
                selected_session_id: null,
                sessions: [],
            }),
        },
        probeUrl: {
            type: String,
            default: '/stub/workshop?probe=status',
        },
        statusPollIntervalMs: {
            type: Number,
            default: 10,
        },
    },
    setup(props, { expose }) {
        const state = useWorkshopSessions({
            organizationId: computed(() => props.organizationId),
            engagementId: computed(() => props.engagementId),
            engagementStatus: computed(() => props.engagementStatus),
            metricId: computed(() => props.metricId),
            workshopClosedProp: computed(() => {
                if (props.workshopClosedValue === 'true') {
                    return true;
                }

                if (props.workshopClosedValue === 'false') {
                    return false;
                }

                return undefined;
            }),
            workshopSessionContext: computed(() => props.sessionContext),
            isStaff: computed(() => props.isStaff),
            canManageWorkshopSessions: computed(
                () => props.canManageWorkshopSessions,
            ),
            workshopForceStartEnabled: computed(
                () => props.workshopForceStartEnabled,
            ),
            workshopSessionsRouteApi: computed(() => workshopSessionsRouteApi),
            statusProbeUrl: () => props.probeUrl,
            toast: toastSpy,
            fetchImpl: fetchMock as typeof fetch,
            statusPollIntervalMs: props.statusPollIntervalMs,
        });

        expose(state);

        return () => h('div');
    },
});

const baseSessionContext = (
    overrides: Partial<WorkshopSessionContext> = {},
): WorkshopSessionContext => ({
    selected_session_id: null,
    sessions: [],
    ...overrides,
});

const mountedWrappers: Array<ReturnType<typeof mount>> = [];

const mountHarness = (overrides: Record<string, unknown> = {}) => {
    const wrapper = mount(SessionHarness, {
        props: {
            ...overrides,
        },
    });
    mountedWrappers.push(wrapper);

    return wrapper;
};

const sessionState = (
    wrapper: ReturnType<typeof mountHarness>,
): SessionHarnessState => wrapper.vm as unknown as SessionHarnessState;

beforeEach(() => {
    fetchMock.mockReset();
    toastSpy.mockReset();
    vi.useRealTimers();

    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        value: 'visible',
    });
});

afterEach(() => {
    while (mountedWrappers.length > 0) {
        mountedWrappers.pop()?.unmount();
    }

    vi.useRealTimers();
});

describe('useWorkshopSessions', () => {
    it('falls back to the server-provided session context after a non-manager 403 load', async () => {
        const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();

        fetchMock.mockResolvedValueOnce({
            ok: false,
            status: 403,
            json: vi.fn().mockResolvedValue({
                message: 'Forbidden.',
            }),
        });

        const wrapper = mountHarness({
            isStaff: false,
            canManageWorkshopSessions: false,
            sessionContext: baseSessionContext({
                selected_session_id: 'session-local',
                sessions: [
                    {
                        id: 'session-local',
                        status: 'Scheduled',
                        starts_at: startsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                    },
                ],
            }),
        });

        await flushPromises();

        expect(sessionState(wrapper).activeWorkshopSession?.id).toBe(
            'session-local',
        );
        expect(sessionState(wrapper).showWorkshopStartAction).toBe(false);
        expect(sessionState(wrapper).workshopSessionError).toBeNull();
    });

    it('starts a scheduled workshop session and reloads the active session state', async () => {
        const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
        const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    data: [
                        {
                            id: 'session-1',
                            status: 'Scheduled',
                            starts_at: startsAt,
                            ends_at: endsAt,
                            timezone: 'UTC',
                            metric_ids: ['metric-1'],
                        },
                    ],
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    data: {
                        id: 'session-1',
                        status: 'InProgress',
                    },
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    data: [
                        {
                            id: 'session-1',
                            status: 'InProgress',
                            starts_at: startsAt,
                            ends_at: endsAt,
                            timezone: 'UTC',
                            metric_ids: ['metric-1'],
                        },
                    ],
                }),
            });

        const wrapper = mountHarness({
            isStaff: true,
            canManageWorkshopSessions: true,
        });

        await flushPromises();

        expect(sessionState(wrapper).showWorkshopStartAction).toBe(true);
        expect(sessionState(wrapper).showScheduledWorkshopTime).toBe(true);
        expect(
            sessionState(wrapper).activeWorkshopSessionScheduledLabel,
        ).not.toBeNull();

        sessionState(wrapper).requestWorkshopStart();
        await flushPromises();

        expect(fetchMock).toHaveBeenNthCalledWith(
            2,
            '/stub/orgs/org-1/engagements/eng-1/workshop-sessions/session-1/start',
            expect.objectContaining({
                method: 'POST',
            }),
        );
        expect(toastSpy).toHaveBeenCalledWith(
            'success',
            'Workshop session started.',
        );
        expect(sessionState(wrapper).showWorkshopFinishAction).toBe(true);
        expect(sessionState(wrapper).isWorkshopMetricLive('metric-1')).toBe(
            true,
        );
    });

    it('surfaces workshop session start failures without clearing the scheduled state', async () => {
        const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    data: [
                        {
                            id: 'session-1',
                            status: 'Scheduled',
                            starts_at: startsAt,
                            timezone: 'UTC',
                            metric_ids: ['metric-1'],
                        },
                    ],
                }),
            })
            .mockResolvedValueOnce({
                ok: false,
                status: 500,
                json: vi.fn().mockResolvedValue({
                    message: 'Unable to start workshop session (500)',
                }),
            })
            .mockResolvedValueOnce({
                ok: false,
                status: 500,
                json: vi.fn().mockResolvedValue({
                    message: 'Unable to start workshop session (500)',
                }),
            });

        const wrapper = mountHarness({
            isStaff: true,
            canManageWorkshopSessions: true,
        });

        await flushPromises();

        sessionState(wrapper).requestWorkshopStart();
        await flushPromises();

        expect(toastSpy).toHaveBeenCalledWith(
            'error',
            'Unable to start workshop session (500)',
        );
        expect(sessionState(wrapper).workshopSessionError).toBe(
            'Unable to start workshop session (500)',
        );
        expect(sessionState(wrapper).showWorkshopStartAction).toBe(true);
        expect(sessionState(wrapper).showWorkshopFinishAction).toBe(false);
    });

    it('polls engagement status for non-staff viewers and closes the workshop when the engagement leaves workshop state', async () => {
        vi.useFakeTimers();

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    data: [],
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    engagement_status: 'InWorkshop',
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    engagement_status: 'InWorkshop',
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({
                    engagement_status: 'Finalized',
                }),
            });

        const wrapper = mountHarness({
            isStaff: false,
            canManageWorkshopSessions: false,
        });

        await flushPromises();
        expect(sessionState(wrapper).workshopClosed).toBe(false);

        await vi.advanceTimersByTimeAsync(25);
        await flushPromises();

        expect(fetchMock).toHaveBeenNthCalledWith(
            2,
            '/stub/workshop?probe=status',
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
        expect(sessionState(wrapper).workshopClosed).toBe(true);
    });
});
