import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import OrgMetricsIndex from '@/pages/OrgMetrics/Index.vue';

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
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>(
        '@inertiajs/vue3',
    );

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

    return {
        ...actual,
        Head,
        Link,
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

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        org: {
            engagements: {
                show: Object.assign(
                    (organizationId: string, engagementId: string) => ({
                        url: `/organizations/${organizationId}/engagements/${engagementId}`,
                    }),
                    { can: true },
                ),
            },
            orgMetrics: {
                show: Object.assign(
                    (args: unknown) => {
                        if (Array.isArray(args)) {
                            const [organizationId, orgMetricId] = args;
                            return {
                                url: `/organizations/${organizationId}/org-metrics/${orgMetricId}`,
                            };
                        }

                        if (args && typeof args === 'object') {
                            const typed = args as {
                                organization?: { id?: string } | string;
                                orgMetric?: { id?: string } | string;
                            };
                            const organizationId =
                                typeof typed.organization === 'string'
                                    ? typed.organization
                                    : typed.organization?.id;
                            const orgMetricId =
                                typeof typed.orgMetric === 'string'
                                    ? typed.orgMetric
                                    : typed.orgMetric?.id;

                            return {
                                url: `/organizations/${organizationId}/org-metrics/${orgMetricId}`,
                            };
                        }

                        return { url: '/organizations//org-metrics/' };
                    },
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
    metrics: [
        {
            id: 'metric-1',
            alias: 'MRR',
            canonical_name: 'Monthly Recurring Revenue',
            canonical_key: 'mrr',
            active_engagement: {
                id: 'eng-1',
                title: 'Q1 Engagement',
                status: 'AwaitingForms',
            },
            latest_sign_off: {
                id: 'signoff-1',
                status: 'Approved',
                signed_off_at: '2025-01-02T10:00:00Z',
                signed_off_by: {
                    id: 'user-1',
                    name: 'Ava Approver',
                },
            },
            past_engagements_count: 2,
        },
        {
            id: 'metric-2',
            alias: null,
            canonical_name: 'Net Revenue Retention',
            canonical_key: 'nrr',
            active_engagement: null,
            latest_sign_off: null,
            past_engagements_count: 0,
        },
    ],
});

it('renders org metrics rollups', () => {
    const wrapper = mount(OrgMetricsIndex, {
        props: baseProps(),
    });

    expect(wrapper.text()).toContain('MRR');
    expect(wrapper.text()).toContain('Monthly Recurring Revenue');
    expect(wrapper.text()).toContain('Q1 Engagement');
    expect(wrapper.text()).toContain('AwaitingForms');
    expect(wrapper.text()).toContain('Approved');
    expect(wrapper.text()).toContain('Ava Approver');
    expect(wrapper.text()).toContain('No active engagement');
});

it('shows an empty state when there are no metrics', () => {
    const wrapper = mount(OrgMetricsIndex, {
        props: {
            ...baseProps(),
            metrics: [],
        },
    });

    expect(wrapper.text()).toContain('No org metrics yet');
});
