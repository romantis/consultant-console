import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';

import HandbookPage from '@/pages/Client/Handbook.vue';

const routerMock = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
    reload: vi.fn(),
    remember: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>(
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
        inheritAttrs: false,
        props: {
            href: {
                type: String,
                default: '',
            },
        },
        setup(props, { slots, attrs }) {
            const handleClick = (event: Event) => {
                event.preventDefault();
                const handler = attrs.onClick;
                if (typeof handler === 'function') {
                    handler(event);
                } else if (Array.isArray(handler)) {
                    handler.forEach((listener) => listener(event));
                }
            };

            return () =>
                h(
                    'a',
                    { href: props.href, ...attrs, onClick: handleClick },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        ...actual,
        Head,
        Link,
        router: routerMock,
    };
});

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

vi.mock('@/layouts/ClientLayout.vue', () => ({
    default: defineComponent({
        name: 'ClientLayoutStub',
        props: {
            title: {
                type: String,
                default: '',
            },
            description: {
                type: String,
                default: '',
            },
        },
        setup(_, { slots }) {
            return () =>
                h(
                    'section',
                    { 'data-test': 'client-layout-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardDescription: basicWrapper('p'),
    CardContent: basicWrapper('div'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: defineComponent({
        name: 'BadgeStub',
        props: {
            variant: {
                type: String,
                default: 'default',
            },
        },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'span',
                    { ...attrs, 'data-variant': props.variant },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/ui/button', () => ({
    Button: defineComponent({
        name: 'ButtonStub',
        inheritAttrs: false,
        props: {
            type: {
                type: String,
                default: 'button',
            },
            variant: {
                type: String,
                default: 'default',
            },
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'button',
                    {
                        type: props.type,
                        disabled: props.disabled,
                        'data-variant': props.variant,
                        ...attrs,
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/ui/select', () => {
    const Wrapper = basicWrapper('div');
    return {
        Select: defineComponent({
            name: 'SelectStub',
            inheritAttrs: false,
            props: {
                modelValue: {
                    type: [String, Number],
                    default: '',
                },
            },
            emits: ['update:modelValue'],
            setup(_, { slots }) {
                return () => (slots.default ? slots.default() : null);
            },
        }),
        SelectTrigger: basicWrapper('button'),
        SelectValue: Wrapper,
        SelectContent: Wrapper,
        SelectItem: Wrapper,
    };
});

vi.mock('@/components/ui/table', () => ({
    Table: basicWrapper('table'),
    TableHeader: basicWrapper('thead'),
    TableBody: basicWrapper('tbody'),
    TableRow: basicWrapper('tr'),
    TableHead: basicWrapper('th'),
    TableCell: basicWrapper('td'),
    TableEmpty: basicWrapper('tr'),
}));

const buildMetrics = () => [
    {
        id: 'org-metric-1',
        engagement_metric_id: 'metric-1',
        alias: 'Northwind Growth',
        name: 'Alpha Growth',
        status: 'InReview',
        urgency: 'needs_attention',
        needs_attention: true,
        engagement: { id: 'eng-1', title: 'Northwind Expansion' },
        owner: {
            key: 'owner_participant',
            label: 'Metric Owner',
            value: 'Alex Owner',
        },
        signed_off_at: null,
        actions: [
            {
                key: 'open_form',
                label: 'Open form',
                href: '/forms/form-1',
            },
            {
                key: 'workshop',
                label: 'Workshop',
                href: '/client/organizations/org-1/engagements/eng-1/workshop?metric=metric-1',
                workshop_live: false,
            },
            {
                key: 'reassign_owner',
                label: 'Reassign owner',
                href: '/client/organizations/org-1/engagements/eng-1/metrics/org-metric-1#role-actions',
            },
            {
                key: 'request_sign_off',
                label: 'Request sign-off',
                href: '/noop',
            },
        ],
    },
    {
        id: 'org-metric-2',
        engagement_metric_id: 'metric-2',
        alias: null,
        name: 'Beta Retention',
        status: 'InWorkshop',
        urgency: 'in_progress',
        needs_attention: false,
        engagement: { id: 'eng-1', title: 'Northwind Expansion' },
        owner: {
            key: 'owner_participant',
            label: 'Metric Owner',
            value: 'Casey Owner',
        },
        signed_off_at: null,
        actions: [],
    },
    {
        id: 'org-metric-3',
        engagement_metric_id: 'metric-3',
        alias: null,
        name: 'Gamma Revenue',
        status: 'Finalized',
        urgency: 'finalized',
        needs_attention: false,
        engagement: { id: 'eng-1', title: 'Northwind Expansion' },
        owner: {
            key: 'owner_participant',
            label: 'Metric Owner',
            value: 'Jordan Owner',
        },
        signed_off_at: '2025-01-01T00:00:00Z',
        actions: [],
    },
];

const buildSelectedEngagementSummary = (
    overrides: Record<string, unknown> = {},
) => ({
    engagement_id: 'eng-1',
    engagement_title: 'Northwind Expansion',
    next_review_on: '2026-06-15',
    review_cadence: 'Quarterly',
    operational_ready_count: 2,
    needs_follow_up_count: 1,
    implementation_gap_count: 3,
    ...overrides,
});

const baseProps = (overrides: Record<string, unknown> = {}) => ({
    organization: { id: 'org-1', name: 'Northwind Analytics' },
    engagements: [
        { id: 'eng-1', title: 'Northwind Expansion', status: 'AwaitingForms' },
    ],
    activeEngagementId: 'eng-1',
    selected_engagement_summary: buildSelectedEngagementSummary(),
    metrics: buildMetrics(),
    ...overrides,
});

describe('Client Handbook page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders metrics with status badges and filters by urgency', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps(),
        });

        await nextTick();

        expect(wrapper.text()).toContain('Northwind Growth');
        expect(wrapper.text()).toContain('Beta Retention');
        expect(wrapper.text()).toContain('Gamma Revenue');

        const badges = wrapper.findAllComponents({ name: 'BadgeStub' });
        const badgeMatches = (label: string, variant: string) =>
            badges.some(
                (badge) =>
                    badge.text() === label &&
                    badge.attributes('data-variant') === variant,
            );

        expect(badgeMatches('Needs attention', 'warning')).toBe(true);
        expect(badgeMatches('In progress', 'secondary')).toBe(true);
        expect(badgeMatches('Finalized', 'success')).toBe(true);

        const filterButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Needs attention'));

        expect(filterButton).toBeTruthy();
        await filterButton?.trigger('click');
        await nextTick();

        expect(wrapper.text()).toContain('Northwind Growth');
        expect(wrapper.text()).not.toContain('Beta Retention');
        expect(wrapper.text()).not.toContain('Gamma Revenue');
    });

    it('does not render inline metric detail sections', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps(),
        });

        await nextTick();

        expect(wrapper.text()).not.toContain('Official definition');
        expect(wrapper.text()).not.toContain('Discrepancy matrix');
    });

    it('renders action links without triggering row navigation', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps(),
        });

        await nextTick();

        const links = wrapper.findAll('a');
        const openFormLink = links.find(
            (link) => link.text() === 'Open form',
        );
        const workshopLink = links.find((link) => link.text() === 'Workshop');

        expect(openFormLink).toBeTruthy();
        expect(workshopLink).toBeTruthy();
        expect(openFormLink?.attributes('href')).toBe('/forms/form-1');
        expect(workshopLink?.attributes('href')).toBe(
            '/client/organizations/org-1/engagements/eng-1/workshop?metric=metric-1',
        );
        expect(wrapper.text()).not.toContain('Reassign owner');
        expect(wrapper.text()).not.toContain('Request sign-off');

        await openFormLink?.trigger('click');

        expect(routerMock.visit).not.toHaveBeenCalled();
    });

    it('renders a workshop live action label with dot only for live workshop actions', async () => {
        const metrics = buildMetrics();
        metrics[0].actions = [
            {
                ...metrics[0].actions[0],
                workshop_live: true,
            },
            {
                ...metrics[0].actions[1],
                workshop_live: true,
            },
        ];
        metrics[1].actions = [
            {
                key: 'workshop',
                label: 'Workshop',
                href: '/client/organizations/org-1/engagements/eng-1/workshop?metric=metric-2',
                workshop_live: false,
            },
        ];

        const wrapper = mount(HandbookPage, {
            props: baseProps({ metrics }),
        });

        await nextTick();

        const liveActionLabels = wrapper.findAll(
            '[data-test="handbook-workshop-live-link"]',
        );
        expect(liveActionLabels).toHaveLength(1);
        expect(liveActionLabels[0]?.text()).toContain('Workshop live');
        expect(
            wrapper.find('[data-test="handbook-workshop-live-dot"]').exists(),
        ).toBe(true);
    });

    it('renders an executive brief for the selected engagement', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps(),
        });

        await nextTick();

        const expectedDate = new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(new Date(2026, 5, 15));

        expect(
            wrapper.find('[data-test="selected-engagement-summary"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Executive brief');
        expect(wrapper.text()).toContain('Northwind Expansion');
        expect(
            wrapper
                .find('[data-test="selected-engagement-summary-next-review"]')
                .text(),
        ).toBe(expectedDate);
        expect(
            wrapper
                .find('[data-test="selected-engagement-summary-readiness"]')
                .text(),
        ).toBe('2 ready / 1 needs follow-up');
        expect(
            wrapper
                .find(
                    '[data-test="selected-engagement-summary-implementation-gaps"]',
                )
                .text(),
        ).toBe('3 metrics');
    });

    it('does not render the executive brief in all-engagement mode', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps({
                activeEngagementId: null,
                selected_engagement_summary: null,
            }),
        });

        await nextTick();

        expect(
            wrapper.find('[data-test="selected-engagement-summary"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).not.toContain('Executive brief');
    });

    it('navigates to the org-metric page when clicking a metric', async () => {
        const wrapper = mount(HandbookPage, {
            props: baseProps(),
        });

        await nextTick();

        const row = wrapper
            .findAll('tr')
            .find((item) => item.text().includes('Northwind Growth'));

        expect(row).toBeTruthy();
        await row?.trigger('click');

        const firstCall = routerMock.visit.mock.calls[0]?.[0];
        const href =
            typeof firstCall === 'string' ? firstCall : firstCall?.url ?? '';

        expect(href).toBe(
            '/client/organizations/org-1/engagements/eng-1/metrics/org-metric-1',
        );
    });
});
