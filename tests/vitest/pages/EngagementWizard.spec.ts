import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick, reactive } from 'vue';

import LaunchWizard from '@/pages/Engagements/LaunchWizard.vue';

type StepId = 'overview' | 'metrics' | 'participants' | 'review';

type WizardOverviewState = {
    name: string;
    timelineStart: string;
    timelineEnd: string;
    repository: {
        driver: string;
        identifier: string;
        notes?: string;
    };
};

type WizardParticipantAssignments = {
    contributors?: string[];
    approvers?: string[];
    observers?: string[];
    availability?: Record<string, string>;
};

type WizardInviteInput = {
    email: string;
    name?: string;
    role?: string;
    department?: string;
};

interface LaunchWizardHarness {
    readonly steps: readonly StepId[];
    readonly currentStep: StepId;
    readonly canAdvance: boolean;
    readonly organizationId: string;
    selectedMetricKeys(): string[];
    updateOverview(patch: Partial<WizardOverviewState>): void;
    toggleMetric(key: string, selected?: boolean): void;
    setMetricDueDate(metricKey: string, dueDate: string | null): void;
    assignParticipants(
        metricKey: string,
        assignments: WizardParticipantAssignments,
    ): void;
    addInvite(invite: WizardInviteInput): string;
    next(): boolean | Promise<boolean>;
    previous(): void;
    submit(): Promise<void> | void;
    getSubmissionPayload(): Record<string, unknown>;
}

const routerVisit = vi.hoisted(() => vi.fn());
const fetchMock = vi.hoisted(() => vi.fn());

vi.stubGlobal('fetch', fetchMock);

const basePageProps = () => ({
    name: 'Launch Engagement Wizard',
    quote: { message: '', author: '' },
    auth: {
        user: {
            id: 'user-1',
            name: 'Consultant Owner',
            email: 'owner@example.test',
            is_staff: true,
        },
        abilities: ['staff', 'engagement.launch'],
    },
    flash: {
        success: null,
        error: null,
    },
    flags: {
        allow_public_registration: false,
    },
    sidebarOpen: true,
    organizations: [] as Array<{ id: string; name: string }>,
    activeOrganizationId: null as string | null,
    engagements: [] as Array<{ id: string; title: string }>,
    activeEngagementId: null as string | null,
    staffWorkspace: null,
    impersonation: null,
    ownerOrganizations: null,
    abilities: ['staff', 'engagement.launch'],
});

const page = reactive({
    url: '/consultant/engagements/new',
    props: basePageProps(),
});

vi.mock('@inertiajs/vue3', async () => {
    const actual =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );

    const Head = defineComponent({
        name: 'InertiaHeadStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'inertia-head-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    });

    const Link = defineComponent({
        name: 'InertiaLinkStub',
        props: {
            href: {
                type: [String, Object],
                required: true,
            },
        },
        setup(props, { slots }) {
            const resolveHref = () =>
                typeof props.href === 'string'
                    ? props.href
                    : ((props.href as Record<string, unknown>)?.url ?? '');

            return () =>
                h(
                    'a',
                    { 'data-href': resolveHref() },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        ...actual,
        Head,
        Link,
        usePage: () => page,
        router: {
            get: routerVisit,
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reload: vi.fn(),
            visit: routerVisit,
            remember: vi.fn(),
        },
    };
});

vi.mock('@/layouts/AppShell.vue', () => ({
    default: defineComponent({
        name: 'AppShellStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'app-shell-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/AppBreadcrumbs.vue', () => ({
    default: defineComponent({
        name: 'AppBreadcrumbsStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'nav',
                    { 'data-test': 'breadcrumbs-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/ToastHub.vue', () => ({
    default: defineComponent({
        name: 'ToastHubStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'toast-hub-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        can: () => true,
        dashboard: Object.assign(() => ({ url: '/dashboard' }), { can: true }),
        org: {
            index: Object.assign(() => ({ url: '/organizations' }), {
                can: true,
            }),
            show: Object.assign(
                (orgId: string) => ({
                    url: `/organizations/${orgId}`,
                }),
                { can: true },
            ),
            engagements: {
                index: Object.assign(
                    (orgId: string) => ({
                        url: `/organizations/${orgId}/engagements`,
                    }),
                    { can: true },
                ),
                show: Object.assign(
                    (orgId: string, engagementId: string) => ({
                        url: `/organizations/${orgId}/engagements/${engagementId}`,
                    }),
                    { can: true },
                ),
            },
        },
    }),
}));

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value));

const baseMetrics = () =>
    Array.from({ length: 11 }, (_, index) => {
        const position = index + 1;
        return {
            id: `canonical-${position}`,
            key: `metric-${position}`,
            name: `Metric ${position}`,
            description: `Definition guidance for metric ${position}`,
            is_active: true,
            active_engagement: null,
            templates: [
                {
                    id: `tmpl-${position}`,
                    key: `metric-${position}-template`,
                    name: `Metric ${position} Template`,
                    description: `Template ${position} description`,
                    form_schema_id: `schema-${position}`,
                    default_due_days: 14 + position,
                    required_departments:
                        position % 2 === 0 ? ['Finance'] : ['Product'],
                },
            ],
        };
    });

const baseMembers = () => [
    {
        id: 'mem-1',
        name: 'Dana Data',
        email: 'dana.data@example.test',
        role: 'member',
        departments: ['Finance'],
    },
    {
        id: 'mem-2',
        name: 'Parker Product',
        email: 'parker.product@example.test',
        role: 'member',
        departments: ['Product'],
    },
    {
        id: 'mem-3',
        name: 'Olivia Ops',
        email: 'olivia.ops@example.test',
        role: 'member',
        departments: ['Operations'],
    },
    {
        id: 'mem-4',
        name: 'Mia Marketing',
        email: 'mia.marketing@example.test',
        role: 'member',
        departments: ['Marketing'],
    },
    {
        id: 'mem-5',
        name: 'Gary Growth',
        email: 'gary.growth@example.test',
        role: 'member',
        departments: ['Growth'],
    },
    {
        id: 'mem-6',
        name: 'Henry HR',
        email: 'henry.hr@example.test',
        role: 'member',
        departments: ['HR'],
    },
    {
        id: 'mem-7',
        name: 'Selena Sales',
        email: 'selena.sales@example.test',
        role: 'member',
        departments: ['Sales'],
    },
];

const baseStakeholderRoles = () => [
    { value: 'Admin', label: 'Admin' },
    { value: 'Member', label: 'Member' },
    { value: 'Viewer', label: 'Viewer' },
];

const baseRepositoryOptions = () => [
    { driver: 'notion', label: 'Notion Workspace' },
    { driver: 'confluence', label: 'Confluence Space' },
];

const baseWizardProps = () => ({
    submitUrl: '/api/engagements',
    organization: {
        id: 'org-1',
        name: 'Northwind Analytics',
        slug: 'northwind-analytics',
    },
    metrics: baseMetrics(),
    members: baseMembers(),
    pendingMembers: [],
    currentMembershipId: null,
    stakeholderRoles: baseStakeholderRoles(),
    repositoryOptions: baseRepositoryOptions(),
    repositoryFeatureEnabled: true,
    defaultTimeline: {
        start: '',
        end: '',
    },
    permissions: {
        can_launch: true,
    },
});

type LaunchWizardProps = ReturnType<typeof baseWizardProps>;

function createWizardProps(): LaunchWizardProps {
    return clone(baseWizardProps());
}

function resolveMaybePromise<T>(value: T | Promise<T>): Promise<T> {
    if (value && typeof (value as Promise<T>).then === 'function') {
        return value as Promise<T>;
    }

    return Promise.resolve(value as T);
}

function resetPageContext() {
    routerVisit.mockReset();
    fetchMock.mockReset();

    const nextProps = basePageProps();
    Object.keys(nextProps).forEach((key) => {
        (page.props as Record<string, unknown>)[key] = (
            nextProps as Record<string, unknown>
        )[key];
    });

    page.url = '/consultant/engagements/new';
}

function mountWizard(overrides?: Partial<LaunchWizardProps>) {
    const props = Object.assign(createWizardProps(), overrides ?? {});

    page.props.organizations = [
        {
            id: props.organization.id,
            name: props.organization.name,
        },
    ];
    page.props.activeOrganizationId = props.organization.id;

    return mount(LaunchWizard, {
        props,
    });
}

function expectHarness(
    wrapper: ReturnType<typeof mountWizard>,
): LaunchWizardHarness {
    const harness = wrapper.vm as unknown as Partial<LaunchWizardHarness>;

    expect(harness).toBeTruthy();
    expect(Array.isArray(harness?.steps)).toBe(true);
    expect(typeof harness?.currentStep).toBe('string');
    expect(typeof harness?.updateOverview).toBe('function');
    expect(typeof harness?.toggleMetric).toBe('function');
    expect(typeof harness?.setMetricDueDate).toBe('function');
    expect(typeof harness?.assignParticipants).toBe('function');
    expect(typeof harness?.addInvite).toBe('function');
    expect(typeof harness?.selectedMetricKeys).toBe('function');
    expect(typeof harness?.next).toBe('function');
    expect(typeof harness?.submit).toBe('function');
    expect(typeof harness?.getSubmissionPayload).toBe('function');

    return harness as LaunchWizardHarness;
}

describe('EngagementWizard page contract', () => {
    beforeEach(() => {
        resetPageContext();
    });

    it('prefills timeline defaults and syncs end date with start changes', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2025-01-01T12:00:00Z'));

        const wrapper = mountWizard({
            defaultTimeline: {
                start: '',
                end: '',
            },
        });

        try {
            const startInput = wrapper.get<HTMLInputElement>(
                '#wizard-overview-timeline-start',
            );
            const endInput = wrapper.get<HTMLInputElement>(
                '#wizard-overview-timeline-end',
            );

            await nextTick();
            expect(startInput.element.value).toBe('2025-01-02');
            expect(endInput.element.value).toBe('2025-01-16');

            await startInput.setValue('2025-02-10');
            await nextTick();
            expect(endInput.element.value).toBe('2025-02-24');

            await endInput.setValue('2025-03-01');
            await nextTick();
            await startInput.setValue('2025-03-05');
            await nextTick();
            expect(endInput.element.value).toBe('2025-03-01');
        } finally {
            wrapper.unmount();
            vi.useRealTimers();
        }
    });

    it('treats prefilled timeline values that match the auto suggestion as auto-managed', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2025-01-01T12:00:00Z'));

        const wrapper = mountWizard({
            defaultTimeline: {
                start: '2025-01-02',
                end: '2025-01-16',
            },
        });

        try {
            const startInput = wrapper.get<HTMLInputElement>(
                '#wizard-overview-timeline-start',
            );
            const endInput = wrapper.get<HTMLInputElement>(
                '#wizard-overview-timeline-end',
            );

            await nextTick();
            expect(startInput.element.value).toBe('2025-01-02');
            expect(endInput.element.value).toBe('2025-01-16');

            await startInput.setValue('2025-02-10');
            await nextTick();
            expect(endInput.element.value).toBe('2025-02-24');

            await endInput.setValue('2025-03-01');
            await nextTick();
            await startInput.setValue('2025-03-05');
            await nextTick();
            expect(endInput.element.value).toBe('2025-03-01');
        } finally {
            wrapper.unmount();
            vi.useRealTimers();
        }
    });

    it('omits handbook repository payload until a destination is configured', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Northwind Launch',
            timelineStart: '2025-02-01',
            timelineEnd: '2025-02-15',
            repository: {
                driver: '',
                identifier: '',
                notes: '',
            },
        });

        await nextTick();

        const payload = wizard.getSubmissionPayload() as Record<
            string,
            unknown
        >;
        expect('handbook_repository' in payload).toBe(false);

        wrapper.unmount();
    });

    it('includes handbook repository details when a driver is selected', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Northwind Launch',
            timelineStart: '2025-02-01',
            timelineEnd: '2025-02-15',
            repository: {
                driver: 'notion',
                identifier: ' https://northwind.notion.site/handbook ',
                notes: 'Ready for handbook draft',
            },
        });

        await nextTick();

        const payload = wizard.getSubmissionPayload() as Record<
            string,
            unknown
        >;
        const repository = payload['handbook_repository'] as
            | Record<string, unknown>
            | undefined;

        expect(repository).toEqual({
            driver: 'notion',
            identifier: 'https://northwind.notion.site/handbook',
            notes: 'Ready for handbook draft',
        });

        wrapper.unmount();
    });

    it('requires overview details before moving to metrics', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        expect(wizard.currentStep).toBe('overview');

        wizard.updateOverview({
            name: '',
            timelineStart: '',
            timelineEnd: '',
            repository: {
                driver: '',
                identifier: '',
                notes: null,
            },
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(false);

        wizard.updateOverview({
            name: 'Q1 2025 Northwind Program',
            timelineStart: '2025-01-06',
            timelineEnd: '2025-04-05',
            repository: {
                driver: 'notion',
                identifier: 'northwind/onboarding',
                notes: 'Link to the onboarding workspace.',
            },
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(true);

        const advanced = await resolveMaybePromise(wizard.next());
        expect(advanced).toBe(true);
        expect(wizard.currentStep).toBe('metrics');

        wrapper.unmount();
    });

    it('enforces selecting between three and ten metrics', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Q2 2025 Launch',
            timelineStart: '2025-04-01',
            timelineEnd: '2025-06-30',
            repository: {
                driver: 'confluence',
                identifier: 'spaces/NORTHWIND',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('metrics');

        expect(wizard.canAdvance).toBe(false);

        const selectionOrder = wizard.selectedMetricKeys();
        expect(selectionOrder).toEqual([]);

        const props = createWizardProps();
        const selectableKeys = props.metrics.map((metric) => metric.key);

        selectableKeys.slice(0, 3).forEach((key) => {
            wizard.toggleMetric(key, true);
        });

        await nextTick();
        expect(new Set(wizard.selectedMetricKeys()).size).toBe(3);
        expect(wizard.canAdvance).toBe(true);

        wizard.setMetricDueDate(selectableKeys[0], null);
        await nextTick();
        expect(wizard.canAdvance).toBe(false);

        wizard.setMetricDueDate(selectableKeys[0], '2025-07-01');
        await nextTick();
        expect(wizard.canAdvance).toBe(true);

        // Selecting additional metrics up to ten should remain valid.
        selectableKeys.slice(3, 10).forEach((key) => {
            wizard.toggleMetric(key, true);
        });

        await nextTick();
        expect(new Set(wizard.selectedMetricKeys()).size).toBe(10);
        expect(wizard.canAdvance).toBe(true);

        // Attempt to add an eleventh metric should be ignored.
        const added = wizard.toggleMetric(selectableKeys[10], true);
        await nextTick();

        expect(new Set(wizard.selectedMetricKeys()).size).toBe(10);
        expect(added).toBeFalsy();
        expect(wizard.canAdvance).toBe(true);

        wrapper.unmount();
    });

    it('requires contributors and approvers for each metric and caps observers at five', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Northwind Launch',
            timelineStart: '2025-02-01',
            timelineEnd: '2025-05-15',
            repository: {
                driver: 'notion',
                identifier: 'northwind/program',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const props = createWizardProps();
        const metricKeys = props.metrics
            .slice(0, 5)
            .map((metric) => metric.key);
        metricKeys.forEach((key) => wizard.toggleMetric(key, true));

        await nextTick();
        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('participants');
        expect(wizard.canAdvance).toBe(false);

        const [primaryKey, ...remainingKeys] = metricKeys;
        const observerOverflow = props.members
            .slice(0, 6)
            .map((member) => member.id);

        expect(() =>
            wizard.assignParticipants(primaryKey, {
                contributors: ['mem-1', 'mem-2'],
                observers: observerOverflow,
            }),
        ).toThrow(/observer/i);

        wizard.assignParticipants(primaryKey, {
            contributors: ['mem-1', 'mem-2'],
            approvers: ['mem-3'],
            observers: ['mem-4', 'mem-5'],
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(false);

        // Assign contributors for remaining metrics except last to confirm gating.
        remainingKeys.slice(0, -1).forEach((key, index) => {
            const memberA =
                props.members[(index + 3) % props.members.length]?.id ??
                'mem-3';
            const memberB =
                props.members[(index + 4) % props.members.length]?.id ??
                'mem-4';
            const memberApprover =
                props.members[(index + 5) % props.members.length]?.id ??
                'mem-5';
            wizard.assignParticipants(key, {
                contributors: [memberA, memberB],
                approvers: [memberApprover],
                observers: ['mem-6'],
            });
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(false);

        // Final metric receives contributor to unlock next step.
        const finalMetricKey = remainingKeys[remainingKeys.length - 1];
        wizard.assignParticipants(finalMetricKey, {
            contributors: ['mem-6', 'mem-7'],
            approvers: ['mem-2'],
            observers: ['mem-1'],
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(true);

        wrapper.unmount();
    });

    it('defaults selected metrics to the current membership as approver, including staff users', async () => {
        const props = createWizardProps();
        props.currentMembershipId = 'mem-1';
        props.members = props.members.map((member) =>
            member.id === 'mem-1'
                ? { ...member, is_staff: true }
                : member,
        );

        const wrapper = mountWizard(props);
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Northwind Launch',
            timelineStart: '2025-02-01',
            timelineEnd: '2025-05-15',
            repository: {
                driver: 'notion',
                identifier: 'northwind/program',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const selectedKeys = props.metrics
            .slice(0, 3)
            .map((metric) => metric.key);
        selectedKeys.forEach((key) => wizard.toggleMetric(key, true));

        await nextTick();

        const payload = wizard.getSubmissionPayload();
        const metricPayload = payload.metrics.find(
            (metric: Record<string, unknown>) =>
                metric.key === selectedKeys[0],
        ) as Record<string, unknown> | undefined;

        expect(metricPayload).toBeTruthy();
        expect(metricPayload?.participants).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    participant_id: 'mem-1',
                    role: 'approver',
                }),
            ]),
        );

        wrapper.unmount();
    });

    it('submits aggregated payload including invited stakeholders and assignments', async () => {
        const wrapper = mountWizard();
        const wizard = expectHarness(wrapper);

        fetchMock.mockResolvedValueOnce({
            ok: true,
            status: 201,
            json: vi.fn().mockResolvedValue({
                id: 'eng-42',
                organization_id: 'org-1',
            }),
        });

        wizard.updateOverview({
            name: 'Northwind Transformation',
            timelineStart: '2025-03-10',
            timelineEnd: '2025-07-31',
            repository: {
                driver: 'notion',
                identifier: 'northwind/launchpad',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const props = createWizardProps();
        const selectedKeys = props.metrics
            .slice(0, 5)
            .map((metric) => metric.key);
        selectedKeys.forEach((key) => wizard.toggleMetric(key, true));

        await nextTick();
        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('participants');

        const inviteId = wizard.addInvite({
            email: 'ops.lead@example.test',
            name: 'Opal Ops',
            role: 'Viewer',
            department: 'Operations',
        });

        const [firstMetric, secondMetric, ...rest] = selectedKeys;

        wizard.assignParticipants(firstMetric, {
            contributors: ['mem-1', 'mem-2'],
            approvers: ['mem-3'],
            observers: [inviteId],
        });

        wizard.assignParticipants(secondMetric, {
            contributors: ['mem-3', 'mem-4'],
            approvers: ['mem-2'],
            observers: ['mem-5'],
        });

        rest.forEach((key, index) => {
            wizard.assignParticipants(key, {
                contributors: [
                    props.members[(index + 4) % props.members.length].id,
                    props.members[(index + 5) % props.members.length].id,
                ],
                approvers: [
                    props.members[(index + 1) % props.members.length].id,
                ],
                observers: ['mem-6'],
            });
        });

        await nextTick();
        expect(wizard.canAdvance).toBe(true);

        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('review');

        await resolveMaybePromise(wizard.submit());

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [submitUrl, requestInit] = fetchMock.mock.calls[0];

        expect(submitUrl).toBe('/api/engagements');
        expect(requestInit?.method).toBe('POST');
        const payload = JSON.parse(String(requestInit?.body ?? '{}'));

        expect(payload).toMatchObject({
            organization_id: 'org-1',
            name: 'Northwind Transformation',
            timeline_start: '2025-03-10',
            timeline_end: '2025-07-31',
        });

        expect(Array.isArray(payload.metrics)).toBe(true);
        expect(payload.metrics).toHaveLength(5);

        const primaryMetric = props.metrics.find(
            (metric) => metric.key === firstMetric,
        );
        const primaryTemplate = primaryMetric?.templates[0];

        const primaryMetricPayload = payload.metrics.find(
            (metric: Record<string, unknown>) => metric.key === firstMetric,
        );

        expect(primaryMetricPayload).toBeTruthy();
        expect(primaryMetricPayload).toMatchObject({
            canonical_metric_id: primaryMetric?.id,
            template_id: primaryTemplate?.id,
        });
        expect(primaryMetricPayload.due_date).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        expect(primaryMetricPayload.participants).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    participant_id: 'mem-1',
                    role: 'contributor',
                }),
                expect.objectContaining({
                    participant_id: 'mem-2',
                    role: 'contributor',
                }),
                expect.objectContaining({
                    participant_id: 'mem-3',
                    role: 'approver',
                }),
                expect.objectContaining({
                    participant_id: inviteId,
                    role: 'observer',
                }),
            ]),
        );
        expect(
            (
                primaryMetricPayload.participants as Array<
                    Record<string, unknown>
                >
            ).every(
                (participant) =>
                    !Object.prototype.hasOwnProperty.call(
                        participant,
                        'availability',
                    ) &&
                    !Object.prototype.hasOwnProperty.call(
                        participant,
                        'availability_cadence',
                    ),
            ),
        ).toBe(true);

        expect(Array.isArray(payload.stakeholders)).toBe(true);
        expect(payload.stakeholders).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    participant_id: 'mem-1',
                    role: 'Member',
                }),
                expect.objectContaining({
                    participant_id: 'mem-2',
                    role: 'Admin',
                }),
                expect.objectContaining({
                    email: 'ops.lead@example.test',
                    role: 'Viewer',
                }),
            ]),
        );

        expect(payload.invited_emails).toContain('ops.lead@example.test');

        expect(routerVisit).toHaveBeenCalledTimes(1);
        const visitArgs = routerVisit.mock.calls[0];
        expect(visitArgs[0]).toBe('/organizations/org-1/engagements/eng-42');

        wrapper.unmount();
    });

    it('blocks launch submission when the page no longer grants launch permission', async () => {
        const wrapper = mountWizard({
            permissions: {
                can_launch: false,
            },
        });
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Northwind Transformation',
            timelineStart: '2025-03-10',
            timelineEnd: '2025-07-31',
            repository: {
                driver: 'notion',
                identifier: 'northwind/launchpad',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const props = createWizardProps();
        const selectedKeys = props.metrics
            .slice(0, 5)
            .map((metric) => metric.key);
        selectedKeys.forEach((key) => wizard.toggleMetric(key, true));

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const inviteId = wizard.addInvite({
            email: 'ops.lead@example.test',
            name: 'Opal Ops',
            role: 'Viewer',
            department: 'Operations',
        });

        const [firstMetric, secondMetric, ...rest] = selectedKeys;

        wizard.assignParticipants(firstMetric, {
            contributors: ['mem-1', 'mem-2'],
            approvers: ['mem-3'],
            observers: [inviteId],
        });

        wizard.assignParticipants(secondMetric, {
            contributors: ['mem-3', 'mem-4'],
            approvers: ['mem-2'],
            observers: ['mem-5'],
        });

        rest.forEach((key, index) => {
            wizard.assignParticipants(key, {
                contributors: [
                    props.members[(index + 4) % props.members.length].id,
                    props.members[(index + 5) % props.members.length].id,
                ],
                approvers: [
                    props.members[(index + 1) % props.members.length].id,
                ],
                observers: ['mem-6'],
            });
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('review');

        await resolveMaybePromise(wizard.submit());

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain(
            'You no longer have permission to launch this engagement.',
        );

        wrapper.unmount();
    });

    it('allows assigning pending members and marks them as pending stakeholders', async () => {
        const pendingInvite = {
            id: 'pm-1',
            name: 'Pending Paula',
            email: 'pending.paula@example.test',
            role: 'Member',
        };

        const wrapper = mountWizard({
            pendingMembers: [pendingInvite],
        });
        const wizard = expectHarness(wrapper);

        wizard.updateOverview({
            name: 'Pending Participants Launch',
            timelineStart: '2025-04-01',
            timelineEnd: '2025-06-15',
            repository: {
                driver: 'notion',
                identifier: 'pending/launch',
            },
        });

        await nextTick();
        await resolveMaybePromise(wizard.next());

        const props = createWizardProps();
        const selectedKeys = props.metrics
            .slice(0, 3)
            .map((metric) => metric.key);
        selectedKeys.forEach((key) => wizard.toggleMetric(key, true));

        await nextTick();
        await resolveMaybePromise(wizard.next());
        expect(wizard.currentStep).toBe('participants');

        const pendingParticipantId = `pending:${pendingInvite.id}`;
        wizard.assignParticipants(selectedKeys[0], {
            contributors: [pendingParticipantId, props.members[0].id],
            observers: ['mem-6'],
        });

        selectedKeys.slice(1).forEach((key, index) => {
            const memberA = props.members[index + 1]?.id ?? 'mem-2';
            const memberB = props.members[index + 2]?.id ?? 'mem-3';
            wizard.assignParticipants(key, {
                contributors: [memberA, memberB],
                observers: ['mem-7'],
            });
        });

        await nextTick();
        const payload = wizard.getSubmissionPayload();

        const pendingStakeholder = payload.stakeholders.find(
            (stakeholder: Record<string, unknown>) =>
                stakeholder.email === pendingInvite.email,
        );

        expect(pendingStakeholder).toMatchObject({
            email: pendingInvite.email,
            name: pendingInvite.name,
        });

        const firstMetric = payload.metrics.find(
            (metric: Record<string, unknown>) => metric.key === selectedKeys[0],
        );

        expect(firstMetric?.participants).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    participant_id: pendingParticipantId,
                    role: 'contributor',
                }),
            ]),
        );

        wrapper.unmount();
    });
});
