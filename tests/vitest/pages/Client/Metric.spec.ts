import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import MetricPage from '@/pages/Client/Metric.vue';

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
        inheritAttrs: false,
        props: {
            href: {
                type: [String, Object],
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

            const resolvedHref =
                typeof props.href === 'string'
                    ? props.href
                    : ((props.href as { url?: string })?.url ?? '');

            return () =>
                h(
                    'a',
                    { href: resolvedHref, ...attrs, onClick: handleClick },
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

const buildDetail = () => ({
    org_metric: {
        id: 'org-metric-1',
        key: 'revenue',
        name: 'Revenue',
        slug: 'revenue',
    },
    official_definition: {
        id: 'official-1',
        status: 'Approved',
        version: 2,
        signed_off_at: '2024-01-10T00:00:00Z',
        signed_off_by: { id: 'user-2', name: 'Jamie Approver' },
        owner: {
            key: 'metric_owner',
            label: 'Metric Owner',
            value: 'Taylor Finance',
        },
        alignment_summary:
            'The team aligned revenue recognition to one booked-sales source after resolving CRM versus finance naming drift.',
        operational_status: 'needs_follow_up',
        operational_status_notes:
            'Finance can report monthly totals, but the weekly close process still needs a documented owner.',
        implementation_gaps: {
            current_state: 'Board pack still comes from a workbook export.',
            gap: 'Internal tenants remain in the dashboard.',
            owner: 'Revenue ops',
            suggested_next_step:
                'Ship the exclusion before the next weekly close.',
        },
        validation_steps: 'Compare workbook totals to the board pack.',
        common_mismatch_causes:
            'Late renewal backfills and internal tenants can skew the board pack.',
        definition_json: {
            summary: { label: 'Summary', value: 'Official definition' },
        },
        notes: null,
    },
    official_definition_history: [
        {
            id: 'official-0',
            version: 1,
            signed_off_at: '2023-12-10T00:00:00Z',
            signed_off_by: { id: 'user-3', name: 'Morgan Lead' },
            metric: {
                id: 'metric-legacy',
                name: 'Revenue',
                engagement: { id: 'eng-2', title: 'Legacy Engagement' },
            },
        },
    ],
    engagement_metric: {
        metric: {
            id: 'metric-1',
            status: 'InReview',
            last_submitted_at: '2024-02-02T00:00:00Z',
            field_roles: [
                {
                    key: 'summary',
                    label: 'Summary',
                    role: 'definition',
                },
            ],
        },
    },
    executive_sections: [
        {
            key: 'official_definition',
            title: 'Official Definition',
            items: [
                {
                    key: 'summary',
                    label: 'Summary',
                    value: 'Official definition',
                    companions: [],
                },
            ],
        },
    ],
});

const formatDateLabel = (value: string) =>
    new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

describe('Client Metric page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders aligned executive sections for implementation, validation, and approval context', () => {
        const wrapper = mount(MetricPage, {
            props: {
                organization: { id: 'org-1', name: 'Northwind' },
                engagement: { id: 'eng-1', title: 'Northwind Expansion' },
                detail: buildDetail(),
            },
        });
        const layout = wrapper.getComponent({ name: 'ClientLayoutStub' });

        expect(layout.props('description')).toContain('Official definition.');
        expect(layout.props('description')).toContain('Signed off');
        expect(wrapper.text()).toContain('Official Definition');
        expect(wrapper.text()).not.toContain('Why It Matters');
        expect(wrapper.text()).not.toContain('Discrepancy matrix');
        expect(wrapper.text()).not.toContain('Context fields');
        expect(wrapper.text()).not.toContain('View history (1)');
        expect(wrapper.text()).not.toContain('Legacy Engagement');
        expect(wrapper.text()).toContain('Official definition');
        expect(
            wrapper.get('[data-test="alignment-summary-block"]').text(),
        ).toContain(
            'The team aligned revenue recognition to one booked-sales source',
        );
        expect(
            wrapper.get('[data-test="executive-alignment-section"]').text(),
        ).toContain('Executive Alignment');
        expect(
            wrapper.get('[data-test="implementation-status-section"]').text(),
        ).toContain('Implementation Status');
        expect(
            wrapper.get('[data-test="operational-status-badge"]').text(),
        ).toBe('Implementation Follow-up Needed');
        expect(
            wrapper.get('[data-test="operational-status-badge"]').attributes(
                'data-variant',
            ),
        ).toBe('warning');
        expect(wrapper.get('[data-test="operational-status-notes"]').text()).toBe(
            'Finance can report monthly totals, but the weekly close process still needs a documented owner.',
        );
        expect(
            wrapper.get('[data-test="implementation-status-section"]').text(),
        ).toContain(
            "Status reflects the organization's confirmed implementation state.",
        );
        expect(
            wrapper.get('[data-test="implementation-follow-up-section"]').text(),
        ).toContain('Implementation Follow-up');
        expect(
            wrapper.get('[data-test="implementation-follow-up-current_state"]').text(),
        ).toBe('Board pack still comes from a workbook export.');
        expect(
            wrapper.get('[data-test="implementation-follow-up-gap"]').text(),
        ).toBe('Internal tenants remain in the dashboard.');
        expect(
            wrapper.get('[data-test="implementation-follow-up-owner"]').text(),
        ).toBe('Revenue ops');
        expect(
            wrapper.get(
                '[data-test="implementation-follow-up-suggested_next_step"]',
            ).text(),
        ).toBe('Ship the exclusion before the next weekly close.');
        expect(
            wrapper.get('[data-test="validation-guidance-section"]').text(),
        ).toContain('Operational Validation Checklist');
        expect(wrapper.get('[data-test="validation_steps"]').text()).toBe(
            'Compare workbook totals to the board pack.',
        );
        expect(wrapper.get('[data-test="common_mismatch_causes"]').text()).toBe(
            'Late renewal backfills and internal tenants can skew the board pack.',
        );
        expect(
            wrapper.get('[data-test="approval-version-section"]').text(),
        ).toContain('Approval & Version');
        expect(
            wrapper.get('[data-test="approval-version-owner"]').text(),
        ).toBe('Taylor Finance');
        expect(
            wrapper.get('[data-test="approval-version-version"]').text(),
        ).toBe('Version 2');
        expect(
            wrapper.get('[data-test="approval-version-signed_off_at"]').text(),
        ).toBe(formatDateLabel('2024-01-10T00:00:00Z'));
        expect(
            wrapper.get('[data-test="approval-version-signed_off_by"]').text(),
        ).toBe('Jamie Approver');
        expect(wrapper.get('[data-test="revision-history-section"]').text()).toContain(
            'Revision history',
        );
        expect(
            wrapper.find('[data-test="revision-history-toggle"]').exists(),
        ).toBe(false);
        expect(wrapper.get('[data-test="revision-history-version"]').text()).toBe(
            'Version 1',
        );
        expect(wrapper.get('[data-test="revision-history-date"]').text()).toBe(
            formatDateLabel('2023-12-10T00:00:00Z'),
        );
        expect(wrapper.get('[data-test="revision-history-author"]').text()).toBe(
            'Signed off by Morgan Lead',
        );
        expect(wrapper.text()).not.toContain('Reassign owner');
        expect(wrapper.text()).not.toContain('Update owner');
    });

    it('omits the client signal section when alignment and operational fields are empty', () => {
        const detail = buildDetail();
        detail.official_definition.alignment_summary = null;
        detail.official_definition.operational_status = null;
        detail.official_definition.operational_status_notes = '   ';
        detail.official_definition.implementation_gaps = null;
        detail.official_definition.validation_steps = null;
        detail.official_definition.common_mismatch_causes = '   ';
        detail.official_definition.version = null;
        detail.official_definition.signed_off_at = null;
        detail.official_definition.signed_off_by = null;
        detail.official_definition.owner = null;
        detail.official_definition_history = [];

        const wrapper = mount(MetricPage, {
            props: {
                organization: { id: 'org-1', name: 'Northwind' },
                engagement: { id: 'eng-1', title: 'Northwind Expansion' },
                detail,
            },
        });

        expect(
            wrapper.find('[data-test="executive-alignment-section"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="alignment-summary-block"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="implementation-status-section"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="operational-status-badge"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="implementation-follow-up-section"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="validation-guidance-section"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="approval-version-section"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="revision-history-section"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('Official definition');
    });

    it('keeps multi-entry revision history collapsed until the user expands it', async () => {
        const detail = buildDetail();
        detail.official_definition.version = 3;
        detail.official_definition_history = [
            {
                id: 'official-2',
                version: 2,
                signed_off_at: '2024-01-03T00:00:00Z',
                signed_off_by: { id: 'user-4', name: 'Avery Director' },
                metric: {
                    id: 'metric-mid',
                    name: 'Revenue',
                    engagement: { id: 'eng-3', title: 'Expansion Reset' },
                },
            },
            {
                id: 'official-1',
                version: 1,
                signed_off_at: '2023-12-10T00:00:00Z',
                signed_off_by: { id: 'user-3', name: 'Morgan Lead' },
                metric: {
                    id: 'metric-legacy',
                    name: 'Revenue',
                    engagement: { id: 'eng-2', title: 'Legacy Engagement' },
                },
            },
        ];

        const wrapper = mount(MetricPage, {
            props: {
                organization: { id: 'org-1', name: 'Northwind' },
                engagement: { id: 'eng-1', title: 'Northwind Expansion' },
                detail,
            },
        });

        expect(wrapper.get('[data-test="revision-history-toggle"]').text()).toBe(
            'View history (2)',
        );
        expect(
            wrapper.find('[data-test="revision-history-list"]').exists(),
        ).toBe(false);

        await wrapper.get('[data-test="revision-history-toggle"]').trigger('click');

        expect(wrapper.get('[data-test="revision-history-toggle"]').text()).toBe(
            'Hide history',
        );

        const versions = wrapper
            .findAll('[data-test="revision-history-version"]')
            .map((entry) => entry.text());
        const dates = wrapper
            .findAll('[data-test="revision-history-date"]')
            .map((entry) => entry.text());
        const authors = wrapper
            .findAll('[data-test="revision-history-author"]')
            .map((entry) => entry.text());

        expect(versions).toEqual(['Version 2', 'Version 1']);
        expect(dates).toEqual([
            formatDateLabel('2024-01-03T00:00:00Z'),
            formatDateLabel('2023-12-10T00:00:00Z'),
        ]);
        expect(authors).toEqual([
            'Signed off by Avery Director',
            'Signed off by Morgan Lead',
        ]);
    });

    it('still renders core metric details when engagement data is missing', () => {
        const detail = buildDetail();
        detail.engagement_metric = null;

        const wrapper = mount(MetricPage, {
            props: {
                organization: { id: 'org-1', name: 'Northwind' },
                engagement: { id: 'eng-1', title: 'Northwind Expansion' },
                detail,
            },
        });

        expect(wrapper.text()).toContain('Official definition');
        expect(wrapper.text()).not.toContain('Why It Matters');
        expect(wrapper.text()).not.toContain(
            'This metric is not part of the active engagement.',
        );
        expect(wrapper.text()).not.toContain('Context fields');
        expect(wrapper.text()).not.toContain('Discrepancy matrix');
        expect(wrapper.text()).not.toContain('Legacy Engagement');
        expect(
            wrapper.find('[data-test="executive-alignment-section"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="revision-history-section"]').exists(),
        ).toBe(true);
    });

    it('renders explicit companion fields under the parent official definition field', () => {
        const detail = buildDetail();
        detail.executive_sections = [
            {
                key: 'official_definition',
                title: 'Official Definition',
                items: [
                    {
                        key: 'active_metrics_tracked',
                        label: 'Active user reporting windows tracked',
                        value: 'DAU, WAU',
                        companions: [
                            {
                                key: 'active_metrics_segmentation_context',
                                label: 'Active user window segmentation notes',
                                value: 'DAU watches daily adoption and WAU monitors activation cohorts.',
                            },
                        ],
                    },
                    {
                        key: 'summary',
                        label: 'Summary',
                        value: 'Official summary',
                        companions: [],
                    },
                ],
            },
        ];

        const wrapper = mount(MetricPage, {
            props: {
                organization: { id: 'org-1', name: 'Northwind' },
                engagement: { id: 'eng-1', title: 'Northwind Expansion' },
                detail,
            },
        });

        expect(
            wrapper.find(
                '[data-test="official-definition-field-active_metrics_tracked"]',
            ).exists(),
        ).toBe(true);
        expect(
            wrapper.find(
                '[data-test="official-definition-companion-active_metrics_segmentation_context"]',
            ).exists(),
        ).toBe(true);
        expect(
            wrapper.find(
                '[data-test="official-definition-field-active_metrics_segmentation_context"]',
            ).exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="executive-section-official_definition"]').exists(),
        ).toBe(true);

        const fieldOrder = wrapper
            .findAll('[data-test^="official-definition-field-"]')
            .map((field) =>
                (field.attributes('data-test') ?? '').replace(
                    'official-definition-field-',
                    '',
                ),
            );

        expect(fieldOrder).toEqual(['active_metrics_tracked', 'summary']);
    });
});
