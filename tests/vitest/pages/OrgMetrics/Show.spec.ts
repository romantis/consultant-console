import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import OrgMetricShow from '@/pages/OrgMetrics/Show.vue';

const pageProps = vi.hoisted(() => ({
    value: {
        flags: {
            org_metric_overrides: true,
        },
    } as Record<string, unknown>,
}));

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@inertiajs/vue3', async () => {
    const actual =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );
    const vue = await vi.importActual<typeof import('vue')>('vue');

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

    const useForm = (data: Record<string, unknown>) => {
        const form = vue.reactive({
            ...data,
            errors: {},
            processing: false,
        });

        return Object.assign(form, {
            patch: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
            reset: vi.fn(),
            clearErrors: vi.fn(),
            transform: vi.fn(),
        });
    };

    return {
        ...actual,
        Head,
        Link,
        useForm,
        usePage: () => ({ props: pageProps.value }),
        router: {
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            visit: vi.fn(),
            reload: vi.fn(),
            remember: vi.fn(),
        },
    };
});

beforeEach(() => {
    pageProps.value = {
        flags: {
            org_metric_overrides: true,
        },
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        name: 'AppLayoutStub',
        props: {
            breadcrumbs: {
                type: Array,
                default: () => [],
            },
        },
        setup(_, { slots }) {
            return () =>
                h(
                    'section',
                    { 'data-test': 'layout-stub' },
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

vi.mock('@/components/ui/input', () => ({
    Input: basicWrapper('input'),
}));

vi.mock('@/components/ui/label', () => ({
    Label: basicWrapper('label'),
}));

vi.mock('@/components/ui/checkbox', () => ({
    Checkbox: basicWrapper('input'),
}));

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        org: {
            orgMetrics: {
                index: Object.assign(
                    (organizationId: string) =>
                        `/organizations/${organizationId}/org-metrics`,
                    { can: true },
                ),
                show: Object.assign(
                    (args: [string, string]) =>
                        `/organizations/${args[0]}/org-metrics/${args[1]}`,
                    { can: true },
                ),
                update: Object.assign(
                    (args: [string, string]) =>
                        `/organizations/${args[0]}/org-metrics/${args[1]}`,
                    { can: true },
                ),
                participants: {
                    update: Object.assign(
                        (args: [string, string]) =>
                            `/organizations/${args[0]}/org-metrics/${args[1]}/participants`,
                        { can: true },
                    ),
                },
            },
            engagements: {
                show: Object.assign(
                    (args: [string, string]) =>
                        `/organizations/${args[0]}/engagements/${args[1]}`,
                    { can: true },
                ),
            },
            metrics: {
                show: Object.assign(
                    (args: [string, string]) =>
                        `/organizations/${args[0]}/metrics/${args[1]}`,
                    { can: true },
                ),
            },
        },
    }),
}));

const baseProps = () => ({
    organization: {
        id: 'org-1',
        name: 'Acme Co',
    },
    org_metric: {
        id: 'metric-1',
        alias: 'MRR',
        key: 'mrr',
        name: 'Monthly Recurring Revenue',
        canonical: {
            id: 'canon-1',
            key: 'mrr',
            name: 'Monthly Recurring Revenue',
            description: 'Recurring revenue per month.',
            is_active: true,
        },
    },
    active_engagement: {
        id: 'eng-1',
        title: 'Q1 Engagement',
        status: 'AwaitingForms',
        metric: {
            id: 'metric-123',
            status: 'InReview',
            last_submitted_at: '2025-01-01T10:00:00Z',
            final_definition_progress: {
                state: 'missing',
                missing_count: 1,
            },
        },
    },
    template_variants: [
        {
            id: 'template-1',
            key: 'mrr-growth',
            name: 'MRR Growth',
            description: 'Growth definition',
            engagements_count: 2,
            last_used_at: '2025-01-02T10:00:00Z',
        },
        {
            id: 'template-2',
            key: 'mrr-retention',
            name: 'MRR Retention',
            description: 'Retention definition',
            engagements_count: 0,
            last_used_at: null,
        },
    ],
    participants: {
        owners: [
            {
                id: 'owner-1',
                participant_id: 'member-1',
                label: 'Alex Owner',
                email: 'owner@example.com',
                is_pending: false,
            },
        ],
        approvers: [
            {
                id: 'approver-1',
                participant_id: 'pending:pending-1',
                label: 'Pending invite',
                email: 'pending@example.com',
                is_pending: true,
            },
        ],
        options: [
            {
                participant_id: 'member-1',
                label: 'Alex Owner',
                email: 'owner@example.com',
                is_pending: false,
            },
            {
                participant_id: 'pending:pending-1',
                label: 'Pending invite',
                email: 'pending@example.com',
                is_pending: true,
            },
        ],
    },
    official_definition: {
        id: 'def-1',
        status: 'Approved',
        version: 2,
        signed_off_at: '2025-01-05T10:00:00Z',
        signed_off_by: {
            id: 'user-1',
            name: 'Ava Approver',
        },
        metric: {
            id: 'metric-123',
            name: 'MRR',
            engagement: {
                id: 'eng-1',
                title: 'Q1 Engagement',
            },
        },
    },
    official_definition_history: [
        {
            id: 'def-0',
            version: 1,
            status: 'Approved',
            signed_off_at: '2024-12-01T10:00:00Z',
            signed_off_by: {
                id: 'user-2',
                name: 'Sam Approver',
            },
            metric: {
                id: 'metric-999',
                name: 'MRR',
                engagement: {
                    id: 'eng-0',
                    title: 'Q4 Engagement',
                },
            },
        },
    ],
    can: {
        update: true,
        manage_participants: true,
    },
});

it('renders org metric detail rollups', () => {
    const wrapper = mount(OrgMetricShow, {
        props: baseProps(),
    });

    expect(wrapper.text()).toContain('MRR');
    expect(wrapper.text()).toContain('Monthly Recurring Revenue');
    expect(wrapper.text()).toContain('Q1 Engagement');
    expect(wrapper.text()).toContain('Missing 1');
    expect(wrapper.text()).not.toContain('Open discrepancies');
    expect(wrapper.text()).toContain('MRR Growth');
    expect(wrapper.text()).toContain('MRR Retention');
    expect(wrapper.text()).toContain('Approved');
    expect(wrapper.text()).toContain('Ava Approver');
});

it('shows empty states when no activity exists', () => {
    const wrapper = mount(OrgMetricShow, {
        props: {
            ...baseProps(),
            active_engagement: null,
            template_variants: [],
            official_definition: null,
            official_definition_history: [],
        },
    });

    expect(wrapper.text()).toContain('No active engagement');
    expect(wrapper.text()).toContain('No template variants yet');
    expect(wrapper.text()).toContain('No org-level definitions yet');
});

it('hides override controls when org metric overrides are disabled', () => {
    pageProps.value = {
        flags: {
            org_metric_overrides: false,
        },
    };

    const wrapper = mount(OrgMetricShow, {
        props: baseProps(),
    });

    expect(wrapper.find('[data-test="org-metric-overrides-disabled"]').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('Save alias');
    expect(wrapper.text()).not.toContain('Save participants');
});

it('hides override editing surfaces when the viewer lacks update permissions', () => {
    const wrapper = mount(OrgMetricShow, {
        props: {
            ...baseProps(),
            can: {
                update: false,
                manage_participants: false,
            },
        },
    });

    expect(wrapper.text()).not.toContain('Org alias');
    expect(wrapper.text()).not.toContain('Default template');
    expect(wrapper.text()).not.toContain('Org-level participants');
    expect(wrapper.find('[data-test="org-metric-overrides-disabled"]').exists()).toBe(false);
});
