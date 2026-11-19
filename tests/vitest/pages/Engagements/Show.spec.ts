import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';

import EngagementShow from '@/pages/Engagements/Show.vue';

const fetchMock = vi.hoisted(() => vi.fn());
const toastSpy = vi.hoisted(() => vi.fn());
const page = vi.hoisted(() => ({
    props: {
        auth: {
            user: {
                is_internal_staff: true,
            },
        },
    },
}));
const inertiaRouter = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
    reload: vi.fn(),
    remember: vi.fn(),
}));

vi.stubGlobal('fetch', fetchMock);

const basicWrapper = vi.hoisted(
    () => (tag: string) =>
        defineComponent({
            name: `${tag}-stub`,
            inheritAttrs: false,
            setup(_, { slots, attrs }) {
                return () =>
                    h(tag, attrs, slots.default ? slots.default() : []);
            },
        }),
);

vi.mock('@inertiajs/vue3', () => {
    const Head = defineComponent({
        name: 'InertiaHeadStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'inertia-head' },
                    slots.default ? slots.default() : [],
                );
        },
    });

    const Link = defineComponent({
        name: 'InertiaLinkStub',
        props: {
            href: {
                type: [String, Object],
                default: '',
            },
        },
        setup(props, { slots }) {
            const href = typeof props.href === 'string' ? props.href : '';
            return () =>
                h(
                    'a',
                    { href, 'data-href': href },
                    slots.default ? slots.default() : [],
                );
        },
    });

    const Form = defineComponent({
        name: 'InertiaFormStub',
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () =>
                h(
                    'form',
                    attrs,
                    slots.default
                        ? slots.default({
                              errors: {},
                              processing: false,
                          })
                        : [],
                );
        },
    });

    return {
        Head,
        Link,
        Form,
        router: inertiaRouter,
        usePage: () => page,
    };
});

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({ toast: toastSpy }),
}));

vi.mock(
    '@/routes/org/engagements/reminders',
    () => ({
        store: () => ({ url: '/stub/reminders' }),
    }),
    { virtual: true },
);

vi.mock(
    '@/routes/org/engagements/status',
    () => ({
        update: () => ({ url: '/stub/status' }),
    }),
    { virtual: true },
);

vi.mock('@/routes/org/engagements', async (importOriginal) => {
    const actual = (await importOriginal()) as Record<string, unknown>;
    const actualDefault =
        (actual.default as Record<string, unknown> | undefined) ?? {};

    return {
        ...actual,
        default: {
            ...actualDefault,
            workshopSessions: {
                ...((actualDefault.workshopSessions as Record<
                    string,
                    unknown
                >) ?? {}),
                index: () => ({ url: '/stub/workshop-sessions' }),
                store: () => ({ url: '/stub/workshop-sessions' }),
                update: () => ({ url: '/stub/workshop-sessions/session-1' }),
                start: () => ({ url: '/stub/workshop-sessions/start' }),
                complete: () => ({ url: '/stub/workshop-sessions/complete' }),
                cancel: () => ({ url: '/stub/workshop-sessions/cancel' }),
            },
        },
        index: () => ({ url: '/organizations/org-1/engagements' }),
        workshop: (
            _args: unknown,
            options?: { query?: { metric?: string } },
        ) => ({
            url: options?.query?.metric
                ? `/workshop?metric=${options.query.metric}`
                : '/workshop',
        }),
        handbookWorkbench: (args: [string, string]) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}/handbook-workbench`,
        }),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: basicWrapper('div'),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardDescription: basicWrapper('p'),
    CardContent: basicWrapper('div'),
}));

vi.mock('@/components/ui/table', () => ({
    Table: basicWrapper('table'),
    TableHeader: basicWrapper('thead'),
    TableBody: basicWrapper('tbody'),
    TableRow: basicWrapper('tr'),
    TableHead: basicWrapper('th'),
    TableCell: basicWrapper('td'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: basicWrapper('span'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: basicWrapper('button'),
}));

vi.mock('@/components/ui/dialog', () => {
    const Wrapper = basicWrapper('div');
    return {
        Dialog: Wrapper,
        DialogTrigger: Wrapper,
        DialogContent: Wrapper,
        DialogDescription: Wrapper,
        DialogFooter: Wrapper,
        DialogHeader: Wrapper,
        DialogTitle: Wrapper,
    };
});

vi.mock('@/components/ui/tooltip', () => {
    const Wrapper = basicWrapper('div');
    return {
        Tooltip: Wrapper,
        TooltipContent: Wrapper,
        TooltipProvider: Wrapper,
        TooltipTrigger: Wrapper,
    };
});

vi.mock('@/components/ui/dropdown-menu', () => {
    const Wrapper = basicWrapper('div');
    return {
        DropdownMenu: Wrapper,
        DropdownMenuTrigger: Wrapper,
        DropdownMenuContent: Wrapper,
        DropdownMenuItem: Wrapper,
        DropdownMenuLabel: Wrapper,
        DropdownMenuSeparator: Wrapper,
        DropdownMenuRadioGroup: Wrapper,
        DropdownMenuRadioItem: Wrapper,
    };
});

vi.mock('@/components/ui/input', () => ({
    Input: basicWrapper('input'),
}));

vi.mock('@/components/ui/label', () => ({
    Label: basicWrapper('label'),
}));

vi.mock('lucide-vue-next', () => ({
    Calendar: basicWrapper('svg'),
    ChevronDown: basicWrapper('svg'),
    Clock: basicWrapper('svg'),
    EllipsisVertical: basicWrapper('svg'),
    Compass: basicWrapper('svg'),
}));

vi.mock('@/pages/Engagements/Show/MetricParticipantAssignments.vue', () => ({
    default: basicWrapper('div'),
}));

const createProps = () => ({
    organization: { id: 'org-1', name: 'Acme Co', timezone: 'UTC' },
    engagement: {
        id: 'eng-1',
        title: 'Northwind Rollout',
        status: 'InProgress',
        starts_on: null,
        ends_on: null,
        repository: { driver: null, reference: null, notes: null },
        stakeholders: [],
        checklist: {},
    },
    forms: {
        total: 0,
        status_counts: {},
        last_submission_at: null,
        items: [],
        templates: [],
        members: [],
        can_create: false,
        refresh_url: null,
    },
    metrics: {
        total: 1,
        status_counts: { Draft: 1 },
        items: [
            {
                id: 'metric-1',
                name: 'Monthly Recurring Revenue',
                status: 'Draft',
                last_submitted_at: null,
                workshop_schedule_readiness: {
                    state: 'ready',
                    forms_total: 1,
                    forms_ready: 1,
                },
                template: null,
                final_definition: null,
                final_definition_progress: {
                    state: 'missing',
                    missing_count: 2,
                },
                participants: [],
            },
        ],
        assignment_options: [],
    },
    deliverables: { finalized_at: null, total: 0, items: [] },
    rail_readiness: {
        intake_ready: false,
        forms_ready: false,
        review_ready: false,
        sign_off_ready: false,
        handbook_ready: false,
        blockers: [],
    },
    workshops: [],
    timeline: {
        engagement: { starts_on: null, ends_on: null, status: 'InProgress' },
        forms: [],
        workshops: [],
        final_definitions: [],
    },
    activity: [],
    reminders: {
        last_schedule_change_at: null,
        last_attempt_at: null,
        pending_due_now_count: 0,
        failed_attempts_24h: 0,
        recent_attempts: [],
    },
    permissions: {
        can_update: true,
        can_send_reminders: false,
        can_change_status: false,
        can_manage_assignments: false,
    },
    statusOptions: [],
});

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));
const differentTimezoneFrom = (timezone: string): string =>
    timezone === 'America/New_York' ? 'UTC' : 'America/New_York';

describe('Engagements/Show metrics section', () => {
    it('renders the Metrics panel with a unified status column and without legacy cards', () => {
        const wrapper = mount(EngagementShow, { props: createProps() });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());
        const metricHeaders = wrapper
            .find('#metrics-panel')
            .findAll('th')
            .map((heading) => heading.text().trim());

        expect(headings).toContain('Metrics');
        expect(headings).not.toContain('Forms');
        expect(headings).not.toContain('Metrics progress');
        expect(headings).not.toContain('Deliverables snapshot');
        expect(headings).not.toContain('Quick links');
        expect(headings).not.toContain('Collaboration feed');
        expect(wrapper.text()).toContain('Monthly Recurring Revenue');
        expect(metricHeaders).toContain('Forms');
        expect(metricHeaders).not.toContain('Discrepancies');
        expect(metricHeaders).not.toContain('Template');
        expect(metricHeaders).not.toContain('Progress');
        expect(metricHeaders).not.toContain('Next due');
        expect(wrapper.text()).not.toContain('Last submission');
        expect(wrapper.text()).not.toContain('Final definition progress');
        expect(wrapper.text()).not.toContain('Final definition');

        wrapper.unmount();
    });

    it('shows form-based metric progress and next-due fallback values', () => {
        page.props.auth.user.is_internal_staff = true;

        const props = createProps();
        props.forms.items = [
            {
                id: 'form-1',
                metric_id: 'metric-1',
                title: 'Revenue Intake',
                template_key: 'mrr-template',
                status: 'InProgress',
                created_at: '2030-01-01T00:00:00Z',
                submitted_count: 2,
                draft_count: 1,
                assignment_count: 3,
                last_submission_at: null,
                next_due_at: null,
                viewer_can_submit: true,
            },
        ];
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                template: {
                    key: 'mrr-template',
                    name: 'MRR Template',
                },
                workshop_schedule_readiness: {
                    state: 'pending_submissions',
                    forms_total: 9,
                    forms_ready: 8,
                },
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const metricRow = wrapper
            .findAll('tr')
            .find((row) => row.text().includes('MRR Template'));

        expect(metricRow).toBeTruthy();

        const cells = metricRow?.findAll('td') ?? [];
        expect(cells.at(1)?.text()).toContain('mrr-template');
        expect(cells.at(1)?.text()).toContain('2');
        expect(cells.at(1)?.text()).toContain('/3');
        expect(cells.at(1)?.text()).toContain('1 pending');
        expect(cells.at(1)?.text()).toContain('Next due');
        expect(cells.at(1)?.text()).not.toContain('/9');
        expect(cells.at(1)?.text()).toContain('—');

        wrapper.unmount();
    });

    it('uses template name for metric link text and shows template key fallback', () => {
        const props = createProps();
        props.metrics.total = 2;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                id: 'metric-1',
                name: 'Revenue Working Name',
                template: {
                    key: 'org_metric_revenue',
                    name: 'Org Revenue Metric',
                },
            },
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Adoption Score',
                template: null,
            },
        ];

        const wrapper = mount(EngagementShow, { props });

        const firstMetricLink = wrapper.find(
            'a[data-href=\"/workshop?metric=metric-1\"]',
        );
        const secondMetricLink = wrapper.find(
            'a[data-href=\"/workshop?metric=metric-2\"]',
        );

        expect(firstMetricLink.exists()).toBe(true);
        expect(firstMetricLink.text()).toBe('Org Revenue Metric');
        expect(secondMetricLink.exists()).toBe(true);
        expect(secondMetricLink.text()).toBe('Adoption Score');
        expect(wrapper.text()).toContain('org_metric_revenue');
        expect(wrapper.text()).toContain('custom_metric');
        expect(wrapper.text()).not.toContain('Custom metric');

        wrapper.unmount();
    });

    it('makes metric template text clickable only when a matching form exists', () => {
        const props = createProps();
        props.forms.items = [
            {
                id: 'form-1',
                metric_id: 'metric-1',
                title: 'Revenue Intake',
                template_key: 'legacy_template_key',
                status: 'InProgress',
                created_at: '2030-01-01T00:00:00Z',
                submitted_count: 0,
                draft_count: 1,
                assignment_count: 1,
                last_submission_at: null,
                next_due_at: null,
                viewer_can_submit: false,
            },
        ];
        props.metrics.total = 2;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                id: 'metric-1',
                name: 'Revenue Working Name',
                template: {
                    key: 'org_metric_revenue',
                    name: 'Org Revenue Metric',
                },
            },
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Churn Working Name',
                template: {
                    key: 'org_metric_churn',
                    name: 'Org Churn Metric',
                },
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const revenueRow = wrapper
            .findAll('tr')
            .find((row) => row.text().includes('Org Revenue Metric'));
        const churnRow = wrapper
            .findAll('tr')
            .find((row) => row.text().includes('Org Churn Metric'));

        expect(revenueRow).toBeTruthy();
        expect(
            revenueRow?.find('a[data-href=\"/forms/form-1\"]').exists(),
        ).toBe(true);
        expect(revenueRow?.find('a[data-href=\"/forms/form-1\"]').text()).toBe(
            'org_metric_revenue',
        );

        expect(churnRow).toBeTruthy();
        expect(churnRow?.findAll('a[data-href^=\"/forms/\"]').length).toBe(0);

        wrapper.unmount();
    });

    it('keeps metrics empty-state messaging without legacy cards', () => {
        const props = createProps();
        props.metrics.total = 0;
        props.metrics.status_counts = {};
        props.metrics.items = [];

        const wrapper = mount(EngagementShow, { props });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());

        expect(headings).toContain('Metrics');
        expect(headings).not.toContain('Metrics progress');
        expect(headings).not.toContain('Deliverables snapshot');
        expect(headings).not.toContain('Quick links');
        expect(headings).not.toContain('Collaboration feed');
        expect(wrapper.text()).toContain(
            'Metrics will appear once forms are submitted.',
        );

        wrapper.unmount();
    });
});

describe('Engagements/Show stage readiness', () => {
    it('renders readiness states and blocker messages', () => {
        const props = createProps();
        props.rail_readiness = {
            intake_ready: true,
            forms_ready: false,
            review_ready: false,
            sign_off_ready: false,
            handbook_ready: false,
            blockers: [
                'Complete all contributor form submissions before entering review.',
                'Schedule, start, or complete a workshop session before entering In Workshop.',
            ],
        };

        const wrapper = mount(EngagementShow, { props });
        const intakePanel = wrapper.find('[data-test="rail-readiness-intake"]');
        const formsPanel = wrapper.find('[data-test="rail-readiness-forms"]');

        expect(intakePanel.text()).toContain('Ready');
        expect(intakePanel.classes()).toContain('dark:border-emerald-500/40');
        expect(formsPanel.text()).toContain('Forms');
        expect(formsPanel.text()).toContain('Blocked');
        expect(formsPanel.classes()).toContain('dark:border-amber-500/40');
        expect(wrapper.find('[data-test="rail-readiness-summary"]').text()).toContain(
            '1/5 ready',
        );
        expect(wrapper.find('[data-test="rail-readiness-blockers"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain(
            'Complete all contributor form submissions before entering review.',
        );
        expect(wrapper.text()).toContain(
            'Schedule, start, or complete a workshop session before entering In Workshop.',
        );
        expect(wrapper.text()).toContain('Stage readiness');
        expect(wrapper.text()).not.toContain('Rail readiness');

        wrapper.unmount();
    });

    it('shows a clear-state message when the rail has no blockers', () => {
        const props = createProps();
        props.rail_readiness = {
            intake_ready: true,
            forms_ready: true,
            review_ready: true,
            sign_off_ready: true,
            handbook_ready: true,
            blockers: [],
        };

        const wrapper = mount(EngagementShow, { props });
        const clearMessage = wrapper.find('[data-test="rail-readiness-clear"]');

        expect(clearMessage.exists()).toBe(true);
        expect(clearMessage.classes()).toContain('dark:text-emerald-300');
        expect(wrapper.find('[data-test="rail-readiness-summary"]').text()).toContain(
            '5/5 ready',
        );
        expect(wrapper.text()).toContain('No blockers on the current rail.');

        wrapper.unmount();
    });
});

describe('Engagements/Show repository and stakeholders sections', () => {
    it('renders repository and stakeholder cards as standalone sections', () => {
        const props = createProps();
        props.engagement.repository = {
            driver: 'github',
            reference: 'https://github.com/acme/handbook',
            notes: 'Sync from default branch.',
        };
        props.engagement.stakeholders = [
            {
                name: 'Riley Partner',
                email: 'riley@example.test',
                role: 'Operations Lead',
                department: 'Operations',
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());
        const repositoryPanel = wrapper.find('[data-test="repository-panel"]');
        const stakeholdersPanel = wrapper.find(
            '[data-test="stakeholders-panel"]',
        );

        expect(headings).not.toContain('Engagement overview');
        expect(headings).toContain('Repository');
        expect(headings).toContain('Stakeholders');
        expect(repositoryPanel.exists()).toBe(true);
        expect(repositoryPanel.text()).toContain('github');
        expect(repositoryPanel.text()).toContain(
            'https://github.com/acme/handbook',
        );
        expect(stakeholdersPanel.exists()).toBe(true);
        expect(stakeholdersPanel.text()).toContain('Riley Partner');
        expect(stakeholdersPanel.text()).toContain('riley@example.test');

        wrapper.unmount();
    });

    it('shows stakeholder empty state and repository note fallback when data is missing', () => {
        const wrapper = mount(EngagementShow, { props: createProps() });
        const repositoryPanel = wrapper.find('[data-test="repository-panel"]');
        const stakeholdersPanel = wrapper.find(
            '[data-test="stakeholders-panel"]',
        );

        expect(repositoryPanel.text()).toContain(
            'No repository notes provided.',
        );
        expect(stakeholdersPanel.text()).toContain(
            'No stakeholders added yet.',
        );

        wrapper.unmount();
    });
});

describe('Engagements/Show workflow timeline section', () => {
    it('renders timeline entries when milestone data exists', () => {
        const props = createProps();
        props.timeline.forms = [
            {
                id: 'form-1',
                title: 'Adoption survey',
                status: 'Completed',
                last_submission_at: '2030-01-12T10:00:00Z',
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());

        expect(headings).toContain('Workflow timeline');
        expect(wrapper.text()).toContain('Adoption survey');
        expect(wrapper.text()).toContain('Marked Completed');

        wrapper.unmount();
    });

    it('shows empty-state copy when no timeline entries exist', () => {
        const wrapper = mount(EngagementShow, { props: createProps() });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());

        expect(headings).toContain('Workflow timeline');
        expect(wrapper.text()).toContain(
            'Activity will appear here as forms are submitted, workshops are held, and definitions are finalized.',
        );

        wrapper.unmount();
    });
});

describe('Engagements/Show workshop prep section', () => {
    it('renders workshop prep as a standalone section for staff', () => {
        page.props.auth.user.is_internal_staff = true;

        const wrapper = mount(EngagementShow, { props: createProps() });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());
        const workflowIndex = headings.indexOf('Workflow timeline');
        const workshopPrepIndex = headings.indexOf('Workshop Prep');

        expect(workflowIndex).toBeGreaterThanOrEqual(0);
        expect(workshopPrepIndex).toBeGreaterThan(workflowIndex);
        expect(wrapper.find('[data-test="workshop-prep-panel"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Monthly Recurring Revenue');
        expect(wrapper.text()).toContain('Workshop Prep');
        expect(wrapper.text()).toContain('Prep');

        const metricsHeaders = wrapper
            .find('#metrics-panel')
            .findAll('th')
            .map((heading) => heading.text().trim());
        expect(metricsHeaders).not.toContain('Workshop Prep');

        const hasDiscrepanciesLink = wrapper
            .findAll('a[data-href]')
            .some((link) =>
                (link.attributes('data-href') ?? '').includes(
                    '/metrics/metric-1/discrepancies',
                ),
            );
        expect(hasDiscrepanciesLink).toBe(false);

        wrapper.unmount();
    });

    it('hides workshop prep section for non-staff users', () => {
        page.props.auth.user.is_internal_staff = false;

        const wrapper = mount(EngagementShow, { props: createProps() });

        expect(wrapper.find('[data-test="workshop-prep-panel"]').exists()).toBe(
            false,
        );
        expect(
            wrapper
                .findAll('a[data-href]')
                .some((link) =>
                    (link.attributes('data-href') ?? '').includes(
                        '/metrics/metric-1/discrepancies',
                    ),
                ),
        ).toBe(false);

        wrapper.unmount();
    });
});

describe('Engagements/Show workshop navigation', () => {
    it('links metrics to the workshop view and prep for staff', () => {
        page.props.auth.user.is_internal_staff = true;

        const wrapper = mount(EngagementShow, { props: createProps() });
        const links = wrapper.findAll('a[data-href]');

        const workshopLink = links.find((link) =>
            (link.attributes('data-href') ?? '').includes('/workshop'),
        );
        expect(workshopLink).toBeTruthy();
        expect(workshopLink?.attributes('data-href')).toContain(
            'metric=metric-1',
        );

        const discrepanciesLink = links.find((link) =>
            (link.attributes('data-href') ?? '').includes(
                '/metrics/metric-1/discrepancies',
            ),
        );
        expect(discrepanciesLink).toBeFalsy();

        const prepLink = links.find((link) =>
            (link.attributes('data-href') ?? '').includes(
                '/metrics/metric-1/prep',
            ),
        );
        expect(prepLink).toBeTruthy();

        wrapper.unmount();
    });

    it('shows workshop links for members before the engagement is in workshop', () => {
        page.props.auth.user.is_internal_staff = false;

        const props = createProps();
        props.engagement.status = 'InReview';

        const wrapper = mount(EngagementShow, { props });
        const links = wrapper.findAll('a[data-href]');
        const workshopLink = links.find((link) =>
            (link.attributes('data-href') ?? '').includes('/workshop'),
        );

        expect(workshopLink).toBeTruthy();

        wrapper.unmount();
    });

    it('shows workshop links for members once the engagement is in workshop', () => {
        page.props.auth.user.is_internal_staff = false;

        const props = createProps();
        props.engagement.status = 'InWorkshop';

        const wrapper = mount(EngagementShow, { props });
        const links = wrapper.findAll('a[data-href]');
        const workshopLink = links.find((link) =>
            (link.attributes('data-href') ?? '').includes('/workshop'),
        );

        expect(workshopLink).toBeTruthy();

        wrapper.unmount();
    });
});

describe('Engagements/Show workshop scheduling in metrics', () => {
    beforeEach(() => {
        fetchMock.mockReset();
        toastSpy.mockReset();
    });

    it('renders scheduled sessions as date and time ranges', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.workshops = [
            {
                id: 'session-1',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'America/New_York',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Prep session',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const timezone = 'America/New_York';
        const startsAt = new Date('2030-01-10T15:00:00Z');
        const endsAt = new Date('2030-01-10T16:00:00Z');
        const expectedDate = new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeZone: timezone,
        }).format(startsAt);
        const expectedStartTime = new Intl.DateTimeFormat(undefined, {
            timeStyle: 'short',
            timeZone: timezone,
        }).format(startsAt);
        const expectedEndTime = new Intl.DateTimeFormat(undefined, {
            timeStyle: 'short',
            timeZone: timezone,
        }).format(endsAt);

        expect(wrapper.text()).toContain(
            `${expectedDate} · ${expectedStartTime} to ${expectedEndTime} (${timezone})`,
        );

        wrapper.unmount();
    });

    it('preselects metric approvers for new workshop sessions', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                participants: [
                    {
                        id: 'metric-participant-approver',
                        responsibility: 'Approver',
                        role_tags: [],
                        assignee: {
                            id: 'participant-approver',
                            name: 'Approver User',
                            email: 'approver@example.test',
                            type: 'member',
                            is_active: true,
                            is_pending: false,
                            membership_role: 'member',
                            stakeholder_role: null,
                        },
                    },
                    {
                        id: 'metric-participant-contributor',
                        responsibility: 'Contributor',
                        role_tags: [],
                        assignee: {
                            id: 'participant-contributor',
                            name: 'Contributor User',
                            email: 'contributor@example.test',
                            type: 'member',
                            is_active: true,
                            is_pending: false,
                            membership_role: 'member',
                            stakeholder_role: null,
                        },
                    },
                ],
            },
        ];
        props.metrics.assignment_options = [
            {
                id: 'participant-contributor',
                name: 'Contributor User',
                email: 'contributor@example.test',
                type: 'member',
                is_active: true,
                is_pending: false,
                membership_role: 'member',
                stakeholder_role: null,
            },
            {
                id: 'participant-approver',
                name: 'Approver User',
                email: 'approver@example.test',
                type: 'member',
                is_active: true,
                is_pending: false,
                membership_role: 'member',
                stakeholder_role: null,
            },
        ];

        const wrapper = mount(EngagementShow, { props });

        const approverLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Approver User'));
        const contributorLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Contributor User'));

        expect(approverLabel).toBeTruthy();
        expect(contributorLabel).toBeTruthy();
        expect(
            (approverLabel!.find('input').element as HTMLInputElement).checked,
        ).toBe(true);
        expect(
            (contributorLabel!.find('input').element as HTMLInputElement)
                .checked,
        ).toBe(false);

        wrapper.unmount();
    });

    it('shows participant roles for selected metric scope', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                id: 'metric-1',
                name: 'Revenue',
                participants: [
                    {
                        id: 'metric-1-approver',
                        responsibility: 'Approver',
                        role_tags: [],
                        assignee: {
                            id: 'participant-approver',
                            name: 'Approver User',
                            email: 'approver@example.test',
                            type: 'member',
                            is_active: true,
                            is_pending: false,
                            membership_role: 'member',
                            stakeholder_role: null,
                        },
                    },
                    {
                        id: 'metric-1-contributor',
                        responsibility: 'Contributor',
                        role_tags: [],
                        assignee: {
                            id: 'participant-contributor',
                            name: 'Contributor User',
                            email: 'contributor@example.test',
                            type: 'member',
                            is_active: true,
                            is_pending: false,
                            membership_role: 'member',
                            stakeholder_role: null,
                        },
                    },
                ],
            },
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Activation',
                participants: [
                    {
                        id: 'metric-2-contributor',
                        responsibility: 'Contributor',
                        role_tags: [],
                        assignee: {
                            id: 'participant-approver',
                            name: 'Approver User',
                            email: 'approver@example.test',
                            type: 'member',
                            is_active: true,
                            is_pending: false,
                            membership_role: 'member',
                            stakeholder_role: null,
                        },
                    },
                ],
            },
        ];
        props.metrics.assignment_options = [
            {
                id: 'participant-contributor',
                name: 'Contributor User',
                email: 'contributor@example.test',
                type: 'member',
                is_active: true,
                is_pending: false,
                membership_role: 'member',
                stakeholder_role: null,
            },
            {
                id: 'participant-approver',
                name: 'Approver User',
                email: 'approver@example.test',
                type: 'member',
                is_active: true,
                is_pending: false,
                membership_role: 'member',
                stakeholder_role: null,
            },
        ];

        const wrapper = mount(EngagementShow, { props });

        const approverLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Approver User'));
        const contributorLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Contributor User'));

        expect(approverLabel?.text()).toContain('Approver');
        expect(contributorLabel?.text()).toContain('Contributor');
        expect(approverLabel?.text()).not.toContain('Revenue:');
        expect(contributorLabel?.text()).not.toContain('Activation:');

        const metricTwoLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Activation'));

        expect(metricTwoLabel).toBeTruthy();
        await metricTwoLabel!.find('input').trigger('change');
        await flushPromises();

        const multiMetricApproverLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Approver User'));
        const multiMetricContributorLabel = wrapper
            .findAll('label')
            .find((label) => label.text().includes('Contributor User'));

        expect(multiMetricApproverLabel?.text()).toContain('Revenue: Approver');
        expect(multiMetricApproverLabel?.text()).toContain(
            'Activation: Contributor',
        );
        expect(multiMetricContributorLabel?.text()).toContain(
            'Revenue: Contributor',
        );
        expect(multiMetricContributorLabel?.text()).toContain(
            'Activation: Viewer',
        );

        wrapper.unmount();
    });

    it('applies datetime bounds and auto-selects end time from start', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.timeline.engagement.ends_on = '2030-01-10';

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const wrapper = mount(EngagementShow, { props });
        const dateInput = wrapper.find('#workshop-session-date');
        const startTimeInput = wrapper.find('#workshop-session-start-time');
        const endTimeInput = wrapper.find('#workshop-session-end-time');

        expect(dateInput.attributes('min')).toBeTruthy();
        expect(dateInput.attributes('max')).toBe('2030-01-10');
        expect(wrapper.text()).toContain('Session date (UTC)');

        await dateInput.setValue('2030-01-10');
        await dateInput.trigger('change');
        await startTimeInput.setValue('10:00');
        await startTimeInput.trigger('change');
        await flushPromises();

        expect(endTimeInput.attributes('min')).toBe('10:15');
        expect(endTimeInput.attributes('max')).toBe('12:00');

        const createButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Create session'));

        expect(createButton).toBeTruthy();
        await createButton!.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0]?.[1]?.method).toBe('POST');
        const payload = JSON.parse(
            String(fetchMock.mock.calls[0]?.[1]?.body ?? '{}'),
        ) as {
            session_date?: string;
            starts_time?: string;
            ends_time?: string;
            timezone?: string;
        };

        expect(payload.session_date).toBe('2030-01-10');
        expect(payload.starts_time).toBe('10:00');
        expect(payload.ends_time).toBe('10:15');
        expect(payload.timezone).toBe('UTC');

        wrapper.unmount();
    });

    it('limits workshop start and end inputs to configured business hours', async () => {
        const props = createProps();
        props.permissions.can_update = true;

        const wrapper = mount(EngagementShow, { props });
        const dateInput = wrapper.find('#workshop-session-date');
        const startTimeInput = wrapper.find('#workshop-session-start-time');
        const endTimeInput = wrapper.find('#workshop-session-end-time');

        await dateInput.setValue('2030-01-10');
        await dateInput.trigger('change');
        await startTimeInput.setValue('17:00');
        await startTimeInput.trigger('change');
        await flushPromises();

        expect(startTimeInput.attributes('min')).toBe('08:00');
        expect(startTimeInput.attributes('max')).toBe('17:00');
        expect(startTimeInput.attributes('step')).toBe('300');
        expect(endTimeInput.attributes('min')).toBe('17:15');
        expect(endTimeInput.attributes('max')).toBe('19:00');
        expect(endTimeInput.attributes('step')).toBe('300');

        const startOptionValues = startTimeInput
            .findAll('option')
            .map((option) => (option.element as HTMLOptionElement).value)
            .filter((value) => value.length > 0);
        const endOptionValues = endTimeInput
            .findAll('option')
            .map((option) => (option.element as HTMLOptionElement).value)
            .filter((value) => value.length > 0);

        expect(startOptionValues).toContain('08:00');
        expect(startOptionValues).toContain('08:05');
        expect(startOptionValues).toContain('17:00');
        expect(startOptionValues).not.toContain('08:01');

        expect(endOptionValues).toContain('17:15');
        expect(endOptionValues).toContain('17:20');
        expect(endOptionValues).toContain('19:00');
        expect(endOptionValues).not.toContain('17:16');

        wrapper.unmount();
    });

    it('auto-shifts today scheduling to the next day when latest start has passed', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-10T18:05:00Z'));

        try {
            const props = createProps();
            props.permissions.can_update = true;
            props.timeline.engagement.ends_on = '2030-01-12';

            fetchMock
                .mockResolvedValueOnce({
                    ok: true,
                    status: 201,
                    json: vi.fn().mockResolvedValue({}),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    status: 200,
                    json: vi.fn().mockResolvedValue({ data: [] }),
                });

            const wrapper = mount(EngagementShow, { props });
            const dateInput = wrapper.find('#workshop-session-date');

            await dateInput.setValue('2030-01-10');
            await dateInput.trigger('change');
            await nextTick();
            await nextTick();

            const createButton = wrapper
                .findAll('button')
                .find((button) => button.text().includes('Create session'));

            expect(createButton).toBeTruthy();
            await createButton!.trigger('click');
            await nextTick();
            await nextTick();

            const payload = JSON.parse(
                String(fetchMock.mock.calls[0]?.[1]?.body ?? '{}'),
            ) as {
                session_date?: string;
                starts_time?: string;
                ends_time?: string;
            };

            expect(payload.session_date).toBe('2030-01-11');
            expect(payload.starts_time).toBe('08:00');
            expect(payload.ends_time).toBe('08:15');

            wrapper.unmount();
        } finally {
            vi.useRealTimers();
        }
    });

    it('auto-clamps starts at to engagement timeline end before submit', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.timeline.engagement.ends_on = '2030-01-10T09:30:00Z';

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const wrapper = mount(EngagementShow, { props });
        const dateInput = wrapper.find('#workshop-session-date');
        const startTimeInput = wrapper.find('#workshop-session-start-time');
        const expectedMaxDate = '2030-01-10';
        const expectedMaxTime = '09:30';

        expect(dateInput.attributes('max')).toBe(expectedMaxDate);

        await dateInput.setValue('2030-01-11');
        await dateInput.trigger('change');
        await startTimeInput.setValue(expectedMaxTime);
        await startTimeInput.trigger('change');
        await flushPromises();

        expect(startTimeInput.attributes('max')).toBe(expectedMaxTime);

        const createButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Create session'));

        expect(createButton).toBeTruthy();
        await createButton!.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0]?.[1]?.method).toBe('POST');
        const payload = JSON.parse(
            String(fetchMock.mock.calls[0]?.[1]?.body ?? '{}'),
        ) as {
            session_date?: string;
            starts_time?: string;
            ends_time?: string;
            timezone?: string;
        };

        expect(payload.session_date).toBe('2030-01-10');
        expect(payload.starts_time).toBe('09:30');
        expect(payload.ends_time).toBe('09:45');
        expect(payload.timezone).toBe('UTC');

        wrapper.unmount();
    });

    it('defaults scheduling timezone from organization settings', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        const localTimezone =
            Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        props.organization.timezone = differentTimezoneFrom(localTimezone);

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const wrapper = mount(EngagementShow, { props });
        const dateInput = wrapper.find('#workshop-session-date');
        const startTimeInput = wrapper.find('#workshop-session-start-time');
        const timezoneInput = wrapper.find('#workshop-session-timezone');

        expect(wrapper.text()).toContain(
            `Session date (${props.organization.timezone})`,
        );
        expect(timezoneInput.exists()).toBe(false);

        await dateInput.setValue('2030-01-10');
        await dateInput.trigger('change');
        await startTimeInput.setValue('10:00');
        await startTimeInput.trigger('change');
        await flushPromises();

        const createButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Create session'));

        expect(createButton).toBeTruthy();
        await createButton!.trigger('click');
        await flushPromises();

        const payload = JSON.parse(
            String(fetchMock.mock.calls[0]?.[1]?.body ?? '{}'),
        ) as {
            session_date?: string;
            starts_time?: string;
            ends_time?: string;
            timezone?: string;
        };

        expect(payload.timezone).toBe(props.organization.timezone);
        expect(payload.session_date).toBe('2030-01-10');
        expect(payload.starts_time).toBe('10:00');
        expect(payload.ends_time).toBe('10:15');
        expect(wrapper.text()).toContain('Consultant local time (');

        wrapper.unmount();
    });

    it('keeps existing session timezone when editing after org timezone changes', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.organization.timezone = 'America/Los_Angeles';
        props.workshops = [
            {
                id: 'session-1',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'America/New_York',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Session',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const wrapper = mount(EngagementShow, { props });
        const editButton = wrapper.find(
            '[data-test="metric-workshop-metric-1"] button',
        );

        expect(editButton.exists()).toBe(true);
        await editButton.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Session date (America/New_York)');

        const saveButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Save session'));

        expect(saveButton).toBeTruthy();
        await saveButton!.trigger('click');
        await flushPromises();

        const payload = JSON.parse(
            String(fetchMock.mock.calls[0]?.[1]?.body ?? '{}'),
        ) as {
            timezone?: string;
        };

        expect(payload.timezone).toBe('America/New_York');

        wrapper.unmount();
    });

    it('shows an error when no valid start slot exists in timeline window', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2030-01-10T08:59:00Z'));

        try {
            const props = createProps();
            props.permissions.can_update = true;
            props.timeline.engagement.ends_on = '2030-01-10T09:00:00Z';

            const wrapper = mount(EngagementShow, { props });
            const dateInput = wrapper.find('#workshop-session-date');
            const startTimeInput = wrapper.find('#workshop-session-start-time');

            await dateInput.setValue('2030-01-10');
            await dateInput.trigger('change');
            await startTimeInput.setValue('09:00');
            await startTimeInput.trigger('change');
            await nextTick();

            vi.setSystemTime(new Date('2030-01-10T09:02:00Z'));

            const createButton = wrapper
                .findAll('button')
                .find((button) => button.text().includes('Create session'));

            expect(createButton).toBeTruthy();
            await createButton!.trigger('click');
            await nextTick();
            await nextTick();

            expect(fetchMock).toHaveBeenCalledTimes(0);
            expect(wrapper.text()).toContain(
                'No valid start time is available within the engagement timeline.',
            );

            wrapper.unmount();
        } finally {
            vi.useRealTimers();
        }
    });

    it('shows workshop schedule actions in the metrics table', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.metrics.total = 2;
        props.metrics.items = [
            props.metrics.items[0],
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Activation',
            },
        ];
        props.workshops = [
            {
                id: 'scheduled-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Scheduled',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const scheduledCell = wrapper.find(
            '[data-test="metric-workshop-metric-1"]',
        );
        const unscheduledCell = wrapper.find(
            '[data-test="metric-workshop-metric-2"]',
        );

        expect(scheduledCell.text()).not.toContain('Schedule');
        expect(unscheduledCell.text()).toContain('Schedule');

        wrapper.unmount();
    });

    it('prioritizes in-progress sessions over scheduled sessions in the workshop cell', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.workshops = [
            {
                id: 'scheduled-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Scheduled',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
            {
                id: 'in-progress-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'InProgress',
                starts_at: '2030-01-09T15:00:00Z',
                ends_at: '2030-01-09T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'In progress',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const workshopCell = wrapper.find(
            '[data-test="metric-workshop-metric-1"]',
        );

        expect(workshopCell.text()).toContain('In progress');

        wrapper.unmount();
    });

    it('shows schedule new action for completed sessions when metric is still schedulable', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.workshops = [
            {
                id: 'completed-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Completed',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Completed',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const workshopCell = wrapper.find(
            '[data-test="metric-workshop-metric-1"]',
        );

        expect(workshopCell.text()).toContain('Completed');
        expect(workshopCell.text()).toContain('Schedule new');

        wrapper.unmount();
    });

    it('hides schedule new action for completed sessions on finalized metrics', () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                status: 'Finalized',
            },
        ];
        props.workshops = [
            {
                id: 'completed-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Completed',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Completed',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const wrapper = mount(EngagementShow, { props });
        const workshopCell = wrapper.find(
            '[data-test="metric-workshop-metric-1"]',
        );

        expect(workshopCell.text()).toContain('Completed');
        expect(workshopCell.text()).not.toContain('Schedule new');

        wrapper.unmount();
    });

    it('allows completing an in-progress workshop session from the edit modal', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.workshops = [
            {
                id: 'in-progress-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'InProgress',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'In progress',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const wrapper = mount(EngagementShow, { props });
        const workshopCellButton = wrapper.find(
            '[data-test="metric-workshop-metric-1"] button',
        );

        expect(workshopCellButton.exists()).toBe(true);
        await workshopCellButton.trigger('click');
        await flushPromises();

        const completeButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Complete session'));

        expect(completeButton).toBeTruthy();
        await completeButton!.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/stub/workshop-sessions/complete',
        );
        expect(fetchMock.mock.calls[0]?.[1]?.method).toBe('POST');

        wrapper.unmount();
    });

    it('asks for confirmation before canceling a workshop session', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.metrics.total = 2;
        props.metrics.items = [
            props.metrics.items[0],
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Activation',
            },
        ];
        props.workshops = [
            {
                id: 'scheduled-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Scheduled',
                metric_ids: ['metric-1', 'metric-2'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        fetchMock
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({}),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: vi.fn().mockResolvedValue({ data: [] }),
            });

        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = mount(EngagementShow, { props });
        const workshopCellButton = wrapper.find(
            '[data-test="metric-workshop-metric-1"] button',
        );

        expect(workshopCellButton.exists()).toBe(true);
        await workshopCellButton.trigger('click');
        await flushPromises();

        const cancelButton = wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text().includes('Cancel session') &&
                    button.attributes('variant') === 'destructive',
            );

        expect(cancelButton).toBeTruthy();
        await cancelButton!.trigger('click');
        await flushPromises();

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        const confirmationMessage = String(confirmSpy.mock.calls[0]?.[0] ?? '');
        expect(confirmationMessage).toContain('all linked metrics');
        expect(confirmationMessage).toContain('Monthly Recurring Revenue');
        expect(confirmationMessage).toContain('Activation');
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/stub/workshop-sessions/cancel',
        );
        expect(fetchMock.mock.calls[0]?.[1]?.method).toBe('POST');

        wrapper.unmount();
        confirmSpy.mockRestore();
    });

    it('does not cancel a workshop session when confirmation is dismissed', async () => {
        const props = createProps();
        props.permissions.can_update = true;
        props.workshops = [
            {
                id: 'scheduled-session',
                organization_id: 'org-1',
                engagement_id: 'eng-1',
                status: 'Scheduled',
                starts_at: '2030-01-10T15:00:00Z',
                ends_at: '2030-01-10T16:00:00Z',
                timezone: 'UTC',
                facilitator_membership_id: 'membership-1',
                facilitator_name: 'Facilitator',
                notes: 'Scheduled',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
                metric_count: 1,
                participant_count: 1,
            },
        ];

        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mount(EngagementShow, { props });
        const workshopCellButton = wrapper.find(
            '[data-test="metric-workshop-metric-1"] button',
        );

        expect(workshopCellButton.exists()).toBe(true);
        await workshopCellButton.trigger('click');
        await flushPromises();

        const cancelButton = wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text().includes('Cancel session') &&
                    button.attributes('variant') === 'destructive',
            );

        expect(cancelButton).toBeTruthy();
        await cancelButton!.trigger('click');
        await flushPromises();

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        const confirmationMessage = String(confirmSpy.mock.calls[0]?.[0] ?? '');
        expect(confirmationMessage).toContain('all linked metrics');
        expect(confirmationMessage).toContain('Monthly Recurring Revenue');
        expect(fetchMock).not.toHaveBeenCalled();
        expect(toastSpy).not.toHaveBeenCalled();

        wrapper.unmount();
        confirmSpy.mockRestore();
    });
});

describe('Engagements/Show final definition progress', () => {
    it('shows definition progress labels inside the merged status column', () => {
        const props = createProps();
        props.metrics.total = 3;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                id: 'metric-1',
                name: 'Metric A',
                final_definition_progress: {
                    state: 'missing',
                    missing_count: 2,
                },
            },
            {
                ...props.metrics.items[0],
                id: 'metric-2',
                name: 'Metric B',
                final_definition_progress: {
                    state: 'ready',
                    missing_count: 0,
                },
            },
            {
                ...props.metrics.items[0],
                id: 'metric-3',
                name: 'Metric C',
                final_definition_progress: {
                    state: 'signed_off',
                },
            },
        ];

        const wrapper = mount(EngagementShow, { props });

        expect(wrapper.text()).toContain('Definition missing 2');
        expect(wrapper.text()).toContain('Definition ready');
        expect(wrapper.text()).toContain('Definition signed off');
        expect(wrapper.text()).toContain('No signed definition yet');
        expect(wrapper.text()).not.toContain(
            'Recent signals from the discrepancy engine',
        );

        wrapper.unmount();
    });

    it('hides duplicate definition signed-off badge when status is finalized', () => {
        const props = createProps();
        props.metrics.total = 1;
        props.metrics.items = [
            {
                ...props.metrics.items[0],
                id: 'metric-finalized',
                status: 'Finalized',
                final_definition_progress: {
                    state: 'signed_off',
                },
                final_definition: {
                    status: 'Approved',
                    signed_off_at: '2026-10-14T09:00:00Z',
                    signed_off_by: { id: 'user-1', name: 'Alex' },
                },
            },
        ];

        const wrapper = mount(EngagementShow, { props });

        expect(wrapper.text()).toContain('Finalized');
        expect(wrapper.text()).not.toContain('Definition signed off');
        expect(wrapper.text()).toContain('Signed');

        wrapper.unmount();
    });
});

describe('Engagements/Show workflow advance', () => {
    const isDisabledAttribute = (value?: string) =>
        value === '' || value === 'true';

    it('shows kickoff action label for draft engagement', () => {
        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'Draft';
        props.rail_readiness.intake_ready = true;

        const wrapper = mount(EngagementShow, { props });
        const advanceButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Kick Off Engagement'));

        expect(advanceButton).toBeTruthy();
        expect(isDisabledAttribute(advanceButton?.attributes('disabled'))).toBe(
            false,
        );

        wrapper.unmount();
    });

    it('disables kickoff action when draft prerequisites are incomplete', () => {
        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'Draft';
        props.rail_readiness.intake_ready = false;

        const wrapper = mount(EngagementShow, { props });
        const advanceButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Kick Off Engagement'));

        expect(advanceButton).toBeTruthy();
        expect(isDisabledAttribute(advanceButton?.attributes('disabled'))).toBe(
            true,
        );
        expect(wrapper.text()).toContain(
            'Select metrics and generate forms to begin.',
        );

        wrapper.unmount();
    });

    it('hides manual finalize advance once engagement is in workshop', () => {
        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InWorkshop';

        const wrapper = mount(EngagementShow, { props });
        const kickoffButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Kick Off Engagement'));

        expect(kickoffButton).toBeUndefined();

        wrapper.unmount();
    });

    it('shows recover stage action for awaiting forms when criteria indicate drift', () => {
        page.props.auth.user.is_internal_staff = false;

        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'AwaitingForms';
        props.rail_readiness.forms_ready = true;

        const wrapper = mount(EngagementShow, { props });
        const recoverButton = wrapper.find('[data-test="recover-stage-action"]');
        const recoverReason = wrapper.find('[data-test="recover-stage-reason"]');

        expect(recoverButton.exists()).toBe(true);
        expect(recoverButton.text()).toContain('Recover Stage: In Review');
        expect(recoverReason.exists()).toBe(true);
        expect(recoverReason.text()).toContain(
            'All forms are complete and this engagement can move to review.',
        );

        wrapper.unmount();
    });

    it('hides recover stage action when review criteria are not yet met', () => {
        page.props.auth.user.is_internal_staff = false;

        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InReview';
        props.rail_readiness.review_ready = false;

        const wrapper = mount(EngagementShow, { props });
        const recoverButton = wrapper.find('[data-test="recover-stage-action"]');
        const recoverReason = wrapper.find('[data-test="recover-stage-reason"]');

        expect(recoverButton.exists()).toBe(false);
        expect(recoverReason.exists()).toBe(false);

        wrapper.unmount();
    });

    it('does not render legacy manual advance actions outside draft', () => {
        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InReview';

        const wrapper = mount(EngagementShow, { props });
        const legacyAdvanceButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Advance to '));

        expect(legacyAdvanceButton).toBeUndefined();
        wrapper.unmount();
    });
});

describe('Engagements/Show stage override', () => {
    beforeEach(() => {
        page.props.auth.user.is_internal_staff = false;
        toastSpy.mockReset();
        inertiaRouter.patch.mockReset();
    });

    it('shows override action for blocked review stage when consultant can change status', () => {
        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InReview';
        props.rail_readiness.review_ready = false;

        const wrapper = mount(EngagementShow, { props });
        const overrideButton = wrapper.find('[data-test="override-stage-action"]');

        expect(overrideButton.exists()).toBe(true);
        expect(overrideButton.text()).toContain('Override to In Workshop');

        wrapper.unmount();
    });

    it('submits override payload with reason and reports override success', async () => {
        const patchMock = inertiaRouter.patch;
        patchMock.mockImplementation(
            (_url, _payload, options?: { onSuccess?: () => void; onFinish?: () => void }) => {
                options?.onSuccess?.();
                options?.onFinish?.();
            },
        );

        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InReview';
        props.rail_readiness.review_ready = false;

        const wrapper = mount(EngagementShow, { props });

        await wrapper.find('[data-test="override-stage-action"]').trigger('click');
        await wrapper
            .find('[data-test="override-reason-input"]')
            .setValue('Workshop happened offline and facilitator confirmed completion.');
        await wrapper.find('[data-test="override-confirm"]').trigger('click');

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(patchMock.mock.calls[0]?.[0]).toBe('/stub/status');
        expect(patchMock.mock.calls[0]?.[1]).toEqual({
            status: 'InWorkshop',
            override: true,
            override_reason:
                'Workshop happened offline and facilitator confirmed completion.',
        });
        expect(toastSpy).toHaveBeenCalledWith(
            'success',
            'Override applied and audit logged.',
        );

        wrapper.unmount();
    });

    it('shows override reason error when backend rejects the override', async () => {
        const patchMock = inertiaRouter.patch;
        patchMock.mockImplementation(
            (
                _url,
                _payload,
                options?: {
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                },
            ) => {
                options?.onError?.({
                    override_reason:
                        'Provide an override reason before applying an override.',
                });
                options?.onFinish?.();
            },
        );

        const props = createProps();
        props.permissions.can_change_status = true;
        props.engagement.status = 'InReview';
        props.rail_readiness.review_ready = false;

        const wrapper = mount(EngagementShow, { props });

        await wrapper.find('[data-test="override-stage-action"]').trigger('click');
        await wrapper
            .find('[data-test="override-reason-input"]')
            .setValue('temporary reason');
        await wrapper.find('[data-test="override-confirm"]').trigger('click');
        await nextTick();

        const errorMessage = wrapper.find('[data-test="override-validation-error"]');
        expect(errorMessage.exists()).toBe(true);
        expect(errorMessage.text()).toContain(
            'Provide an override reason before applying an override.',
        );
        expect(toastSpy).toHaveBeenCalledWith(
            'error',
            'Provide an override reason before applying an override.',
        );

        wrapper.unmount();
    });
});

describe('Engagements/Show reminder controls', () => {
    const isDisabledAttribute = (value?: string) =>
        value === '' || value === 'true';

    it('enables run action in awaiting forms when permitted', () => {
        const props = createProps();
        props.permissions.can_send_reminders = true;
        props.engagement.status = 'AwaitingForms';
        props.reminders = {
            last_schedule_change_at: '2026-02-09T10:00:00Z',
            last_attempt_at: '2026-02-09T10:01:00Z',
            pending_due_now_count: 3,
            failed_attempts_24h: 1,
            recent_attempts: [
                {
                    id: 'attempt-1',
                    status: 'failed',
                    channel: 'email',
                    window: 'T-24h',
                    attempted_at: '2026-02-09T10:01:00Z',
                    metric_name: 'Monthly Recurring Revenue',
                    recipient_email: 'contributor@example.test',
                    reason: 'mailbox deferred',
                },
            ],
        };

        const wrapper = mount(EngagementShow, { props });
        const headings = wrapper.findAll('h2').map((heading) => heading.text());

        expect(headings).toContain('Reminders');
        expect(wrapper.text()).toContain(
            'Auto reminder pass runs every 5 minutes.',
        );
        expect(wrapper.text()).toContain('Due now: 3');
        expect(wrapper.text()).toContain('Failed (24h): 1');
        expect(wrapper.text()).toContain('Recent reminder attempts');
        expect(wrapper.text()).toContain('contributor@example.test');
        expect(wrapper.text()).toContain('mailbox deferred');
        expect(wrapper.text()).toContain('T-24h');
        const runButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Run reminder pass now'));
        expect(runButton).toBeTruthy();
        expect(isDisabledAttribute(runButton?.attributes('disabled'))).toBe(
            false,
        );
        expect(wrapper.text()).not.toContain(
            'Reminder pass is available only when engagement status is Awaiting Forms.',
        );

        wrapper.unmount();
    });

    it('disables run action with explanation outside awaiting forms', () => {
        const props = createProps();
        props.permissions.can_send_reminders = true;
        props.engagement.status = 'InReview';

        const wrapper = mount(EngagementShow, { props });
        const runButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Run reminder pass now'));

        expect(runButton).toBeTruthy();
        expect(isDisabledAttribute(runButton?.attributes('disabled'))).toBe(
            true,
        );
        expect(wrapper.text()).toContain(
            'Reminder pass is available only when engagement status is Awaiting Forms.',
        );
        expect(wrapper.text()).toContain(
            'No reminder attempts for this engagement yet.',
        );

        wrapper.unmount();
    });

    it('hides run action when reminder permission is denied', () => {
        const props = createProps();
        props.permissions.can_send_reminders = false;

        const wrapper = mount(EngagementShow, { props });
        expect(
            wrapper
                .findAll('button')
                .some((button) =>
                    button.text().includes('Run reminder pass now'),
                ),
        ).toBe(false);
        expect(wrapper.text()).toContain(
            'Auto reminder pass runs every 5 minutes.',
        );

        wrapper.unmount();
    });
});

describe('Engagements/Show metrics discrepancy check visibility', () => {
    beforeEach(() => {
        fetchMock.mockReset();
        toastSpy.mockReset();
        page.props.auth.user.is_internal_staff = true;
    });

    it('does not show discrepancy check actions for staff users', () => {
        const wrapper = mount(EngagementShow, { props: createProps() });

        expect(wrapper.text()).not.toContain('Discrepancy check');
        expect(
            wrapper
                .findAll('button')
                .some((button) =>
                    button.text().includes('Recompute discrepancies'),
                ),
        ).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('does not show discrepancy check actions for non-staff users', () => {
        page.props.auth.user.is_internal_staff = false;

        const wrapper = mount(EngagementShow, { props: createProps() });

        expect(wrapper.text()).not.toContain('Discrepancy check');
        expect(
            wrapper
                .findAll('button')
                .some((button) =>
                    button.text().includes('Recompute discrepancies'),
                ),
        ).toBe(false);
        expect(fetchMock).not.toHaveBeenCalled();

        wrapper.unmount();
    });
});

describe('Engagements/Show handbook workbench button visibility', () => {
    beforeEach(() => {
        page.props.auth.user.is_internal_staff = true;
    });

    it('shows handbook workbench link for staff', () => {
        const wrapper = mount(EngagementShow, { props: createProps() });
        const link = wrapper.find('a[data-href*="/handbook-workbench"]');
        expect(link.exists()).toBe(true);
        expect(link.text()).toContain('Handbook Workbench');
        wrapper.unmount();
    });

    it('hides handbook workbench link for non-staff', () => {
        page.props.auth.user.is_internal_staff = false;
        const wrapper = mount(EngagementShow, { props: createProps() });
        const link = wrapper.find('a[data-href*="/handbook-workbench"]');
        expect(link.exists()).toBe(false);
        wrapper.unmount();
    });
});
