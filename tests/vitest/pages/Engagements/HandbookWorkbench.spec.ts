import HandbookSignalEditor from '@/features/handbook-workbench/HandbookSignalEditor.vue';
import HandbookWorkbench from '@/pages/Engagements/HandbookWorkbench.vue';
import { mount } from '@vue/test-utils';

const mockRouterPatch = vi.hoisted(() => vi.fn());
const mockRouterVisit = vi.hoisted(() => vi.fn());
const mockRouterOn = vi.hoisted(() => vi.fn());
const mockToast = vi.hoisted(() => vi.fn());

const listeners: Record<string, Set<(...args: any[]) => any>> = {};

const triggerRouterEvent = (event: string, detail: any) => {
    const eventObj = {
        detail,
        preventDefault: vi.fn(),
        defaultPrevented: false,
    };
    eventObj.preventDefault.mockImplementation(() => {
        eventObj.defaultPrevented = true;
    });

    if (listeners[event]) {
        for (const cb of listeners[event]) {
            cb(eventObj);
        }
    }
    return eventObj;
};

mockRouterOn.mockImplementation(
    (event: string, callback: (...args: any[]) => any) => {
        if (!listeners[event]) {
            listeners[event] = new Set();
        }
        listeners[event].add(callback);
        return () => {
            listeners[event]?.delete(callback);
        };
    },
);

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({ toast: mockToast }),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
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

            const handleClick = (e: MouseEvent) => {
                e.preventDefault();
                router.visit(href);
            };

            return () =>
                h(
                    'a',
                    {
                        href,
                        'data-href': href,
                        onClick: handleClick,
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });

    const router = {
        patch: vi.fn((url: string, data?: any, options?: any) => {
            const opts = options || {};
            const eventObj = triggerRouterEvent('before', {
                visit: {
                    url,
                    method: 'patch',
                    data,
                    ...opts,
                },
            });
            if (eventObj.defaultPrevented) {
                return;
            }
            return mockRouterPatch(url, data, options);
        }),
        visit: vi.fn((url: string, options?: any) => {
            const opts = options || {};
            const method = opts.method || 'get';
            const eventObj = triggerRouterEvent('before', {
                visit: {
                    url,
                    method,
                    ...opts,
                },
            });
            if (eventObj.defaultPrevented || opts.prefetch) {
                return;
            }
            if (options !== undefined) {
                mockRouterVisit(url, options);
            } else {
                mockRouterVisit(url);
            }
        }),
        on: mockRouterOn,
    };

    return {
        Head: defineComponent({
            name: 'div-stub',
            inheritAttrs: false,
            setup(_, { slots, attrs }) {
                return () =>
                    h('div', attrs, slots.default ? slots.default() : []);
            },
        }),
        Link,
        router,
    };
});

vi.mock('@/components/ui/select', async () => {
    const { defineComponent, h } = await import('vue');
    const Select = defineComponent({
        name: 'SelectStub',
        props: ['modelValue', 'disabled'],
        emits: ['update:modelValue'],
        setup(props, { slots, emit }) {
            return () =>
                h(
                    'select',
                    {
                        value: props.modelValue ?? '',
                        disabled: props.disabled,
                        onChange: (e: any) =>
                            emit('update:modelValue', e.target.value),
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });
    const SelectTrigger = defineComponent({
        setup() {
            return () => null;
        },
    });
    const SelectValue = defineComponent({
        setup() {
            return () => null;
        },
    });
    const SelectContent = defineComponent({
        setup(_, { slots }) {
            return () => (slots.default ? slots.default() : []);
        },
    });
    const SelectItem = defineComponent({
        name: 'SelectItemStub',
        props: ['value'],
        setup(props, { slots }) {
            return () =>
                h(
                    'option',
                    { value: props.value },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        Select,
        SelectTrigger,
        SelectValue,
        SelectContent,
        SelectItem,
    };
});

vi.mock('@/components/ui/textarea', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Textarea: defineComponent({
            name: 'TextareaStub',
            props: ['modelValue', 'disabled'],
            emits: ['update:modelValue'],
            setup(props, { emit }) {
                return () =>
                    h('textarea', {
                        value: props.modelValue ?? '',
                        disabled: props.disabled,
                        onInput: (e: any) =>
                            emit('update:modelValue', e.target.value),
                    });
            },
        }),
    };
});

vi.mock('@/components/ui/label', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Label: defineComponent({
            name: 'label-stub',
            inheritAttrs: false,
            setup(_, { slots, attrs }) {
                return () =>
                    h('label', attrs, slots.default ? slots.default() : []);
            },
        }),
    };
});

vi.mock('@/layouts/AppLayout.vue', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        default: defineComponent({
            name: 'AppLayoutStub',
            props: ['breadcrumbs'],
            setup(props, { slots }) {
                return () =>
                    h('div', {}, [
                        h(
                            'div',
                            { 'data-test': 'breadcrumbs-stub' },
                            JSON.stringify(props.breadcrumbs),
                        ),
                        slots.default ? slots.default() : [],
                    ]);
            },
        }),
    };
});

vi.mock('@/components/ui/badge', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Badge: defineComponent({
            name: 'span-stub',
            inheritAttrs: false,
            setup(_, { slots, attrs }) {
                return () =>
                    h('span', attrs, slots.default ? slots.default() : []);
            },
        }),
    };
});

vi.mock('@/components/ui/dialog', async () => {
    const { defineComponent, h } = await import('vue');
    const Dialog = defineComponent({
        name: 'DialogStub',
        props: ['open'],
        setup(props, { slots }) {
            return () =>
                props.open
                    ? h('div', {}, slots.default ? slots.default() : [])
                    : null;
        },
    });
    const Wrapper = defineComponent({
        name: 'div-stub',
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h('div', attrs, slots.default ? slots.default() : []);
        },
    });
    return {
        Dialog,
        DialogTrigger: Wrapper,
        DialogContent: Wrapper,
        DialogDescription: Wrapper,
        DialogFooter: Wrapper,
        DialogHeader: Wrapper,
        DialogTitle: Wrapper,
    };
});

vi.mock('@/components/ui/input', async () => {
    const { defineComponent, h } = await import('vue');
    return {
        Input: defineComponent({
            name: 'InputStub',
            props: ['modelValue', 'disabled'],
            emits: ['update:modelValue'],
            setup(props, { emit }) {
                return () =>
                    h('input', {
                        value: props.modelValue ?? '',
                        disabled: props.disabled,
                        onInput: (e: any) =>
                            emit('update:modelValue', e.target.value),
                    });
            },
        }),
    };
});

vi.mock('@/routes/org', () => {
    return {
        default: {
            index: () => ({ url: '/organizations' }),
        },
        show: (id: string) => ({ url: `/organizations/${id}` }),
    };
});

vi.mock('@/routes/org/engagements', () => {
    return {
        index: (orgId: string) => ({
            url: `/organizations/${orgId}/engagements`,
        }),
        show: (args: [string, string]) => ({
            url: `/organizations/${args[0]}/engagements/${args[1]}`,
        }),
        workshop: (
            args: [string, string],
            options?: { query?: Record<string, string> },
        ) => {
            const base = `/organizations/${args[0]}/engagements/${args[1]}/workshop`;
            const query = options?.query ?? {};
            const queryString = Object.keys(query).length
                ? `?${new URLSearchParams(query).toString()}`
                : '';
            return { url: `${base}${queryString}` };
        },
    };
});

vi.mock('@/routes/org/handbook-exports/index', () => {
    return {
        index: (orgId: string) => ({
            url: `/organizations/${orgId}/handbook-exports`,
        }),
    };
});

function createProps() {
    return {
        organization: {
            id: 'org-123',
            name: 'Acme Corp',
        },
        engagement: {
            id: 'eng-456',
            title: 'Q2 Performance Audit',
            status: 'InReview',
        },
        metrics: [
            {
                id: 'metric-1',
                org_metric_id: 'org-metric-1',
                name: 'Monthly Active Users',
                status: 'Finalized',
                is_signed_off: true,
                template: {
                    id: 'tmpl-1',
                    key: 'mau',
                    name: 'MAU Template',
                },
                signals: {
                    org_final_definition_id: 'def-1',
                    version: 2,
                    signed_off_at: '2026-05-22T12:00:00Z',
                    signed_off_by: {
                        id: 'usr-1',
                        name: 'Jane Doe',
                    },
                    executive_takeaway: 'This is the MAU executive takeaway.',
                    alignment_summary: 'Aligned with standards.',
                    operational_status: 'operationally_ready',
                    operational_status_notes: 'Fully functional.',
                    implementation_gaps: {
                        current_state: 'Current MAU track',
                        gap: 'None',
                        owner: 'Jane Doe',
                        suggested_next_step: 'Proceed.',
                    },
                    validation_steps: 'Check database queries.',
                    common_mismatch_causes: 'Timezone discrepancies.',
                },
                readiness_checklist: {
                    signed_definition: {
                        label: 'Signed Definition',
                        status: 'ready',
                    },
                    approval_version: {
                        label: 'Approval & Version',
                        status: 'ready',
                    },
                    executive_takeaway: {
                        label: 'Executive Takeaway',
                        status: 'ready',
                    },
                    executive_alignment: {
                        label: 'Executive Alignment',
                        status: 'ready',
                    },
                    implementation_review: {
                        label: 'Implementation Follow-up',
                        status: 'ready',
                    },
                    validation_guidance: {
                        label: 'Validation Guidance',
                        status: 'ready',
                    },
                },
                source_context: {
                    signed_definition: {
                        version: 2,
                        signed_off_at: '2026-05-22T12:00:00Z',
                        signed_off_by: {
                            id: 'usr-1',
                            name: 'Jane Doe',
                        },
                        notes: 'Signed after workshop review.',
                        owner: {
                            label: 'Metric Owner',
                            value: 'Jane Doe',
                        },
                        sections: [
                            {
                                key: 'official_definition',
                                title: 'Official Definition',
                                items: [
                                    {
                                        key: 'mau_definition',
                                        label: 'MAU Definition',
                                        value: 'Monthly active users counted via login events.',
                                        companions: [],
                                    },
                                ],
                            },
                        ],
                    },
                    workshop_alignment: {
                        has_evidence: true,
                        workshop: {
                            id: 'wk-1',
                            scheduled_at: '2026-05-20T10:00:00Z',
                            notes: 'Discussion about MAU. Finance agreed to key features.',
                            decisions: {
                                consensus: 'Use key features action for MAU',
                                follow_up: ['Finance to reconcile accounts'],
                                field_decisions: {
                                    mau_definition: {
                                        selected: 'key_feature_action',
                                        rationale:
                                            'Finance and Product agreed that key feature action is the board-ready MAU activity event.',
                                    },
                                },
                            },
                            participants: [],
                            workshop_url:
                                '/organizations/org-123/engagements/eng-1/workshop?metric=metric-1',
                        },
                        discrepancies: [
                            {
                                field_key: 'mau_definition',
                                severity: 'medium',
                                status: 'Resolved',
                                description:
                                    'Marketing counts seats, Product counts key actions.',
                            },
                        ],
                    },
                },
            },
            {
                id: 'metric-2',
                org_metric_id: 'org-metric-2',
                name: 'Customer Churn Rate',
                status: 'Draft',
                is_signed_off: false,
                template: null,
                signals: null,
                readiness_checklist: {
                    signed_definition: {
                        label: 'Signed Definition',
                        status: 'missing',
                    },
                    approval_version: {
                        label: 'Approval & Version',
                        status: 'missing',
                    },
                    executive_takeaway: {
                        label: 'Executive Takeaway',
                        status: 'missing',
                    },
                    executive_alignment: {
                        label: 'Executive Alignment',
                        status: 'missing',
                    },
                    implementation_review: {
                        label: 'Implementation Follow-up',
                        status: 'missing',
                    },
                    validation_guidance: {
                        label: 'Validation Guidance',
                        status: 'missing',
                    },
                },
                source_context: null,
            },
            {
                id: 'metric-3',
                org_metric_id: 'org-metric-3',
                name: 'Net Promoter Score',
                status: 'Finalized',
                is_signed_off: true,
                template: {
                    id: 'tmpl-2',
                    key: 'nps',
                    name: 'NPS Template',
                },
                signals: {
                    org_final_definition_id: 'def-2',
                    version: 1,
                    signed_off_at: '2026-05-22T12:00:00Z',
                    signed_off_by: {
                        id: 'usr-1',
                        name: 'Jane Doe',
                    },
                    executive_takeaway: 'NPS takeaway.',
                    alignment_summary: 'NPS aligned.',
                    operational_status: 'needs_follow_up',
                    operational_status_notes: 'Needs follow-up.',
                    implementation_gaps: {
                        current_state: 'Current NPS track',
                        gap: 'Needs refinement',
                        owner: 'Jane Doe',
                        suggested_next_step: 'Refine.',
                    },
                    validation_steps: 'Check NPS tools.',
                    common_mismatch_causes: 'Incorrect calculations.',
                },
                readiness_checklist: {
                    signed_definition: {
                        label: 'Signed Definition',
                        status: 'ready',
                    },
                    approval_version: {
                        label: 'Approval & Version',
                        status: 'ready',
                    },
                    executive_takeaway: {
                        label: 'Executive Takeaway',
                        status: 'ready',
                    },
                    executive_alignment: {
                        label: 'Executive Alignment',
                        status: 'ready',
                    },
                    implementation_review: {
                        label: 'Implementation Follow-up',
                        status: 'ready',
                    },
                    validation_guidance: {
                        label: 'Validation Guidance',
                        status: 'ready',
                    },
                },
                source_context: {
                    signed_definition: {
                        version: 1,
                        signed_off_at: '2026-05-22T12:00:00Z',
                        signed_off_by: {
                            id: 'usr-1',
                            name: 'Jane Doe',
                        },
                        notes: null,
                        owner: null,
                        sections: [],
                    },
                    workshop_alignment: {
                        has_evidence: false,
                        workshop: null,
                        discrepancies: [],
                    },
                },
            },
        ],
    };
}

describe('HandbookWorkbench.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        for (const key in listeners) {
            delete listeners[key];
        }
    });

    it('renders the page breadcrumbs and metric listing correctly', () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Check page title and organization name in breadcrumbs stub
        const breadcrumbsStub = wrapper.find('[data-test="breadcrumbs-stub"]');
        expect(breadcrumbsStub.exists()).toBe(true);
        expect(breadcrumbsStub.text()).toContain('Acme Corp');
        expect(breadcrumbsStub.text()).toContain('Q2 Performance Audit');

        // Check metric sidebar listing
        expect(wrapper.text()).toContain('Monthly Active Users');
        expect(wrapper.text()).toContain('Customer Churn Rate');

        wrapper.unmount();
    });

    it('displays signals for signed-off metrics', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric button (operationally_ready)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        // Check content area for signals
        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.text()).toContain('This is the MAU executive takeaway.');
        expect(wrapper.text()).toContain('Ready for Use');
        expect(wrapper.text()).toContain('Current MAU track');
        expect(wrapper.text()).toContain('Check database queries.');
        expect(wrapper.text()).toContain('Responsible Team');
        // The signal editor should use "Responsible Team" not "Owner:" for gaps
        // (source context panel legitimately shows "Metric Owner:")
        const editorText = wrapper.find('[data-test="signal-editor"]').exists()
            ? wrapper.find('[data-test="signal-editor"]').text()
            : wrapper.text();
        expect(editorText).toContain('Responsible Team');

        // Select the metric with needs_follow_up status
        const metricItem3 = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        expect(metricItem3).toBeTruthy();
        await metricItem3!.trigger('click');

        // Check content area for needs_follow_up signal
        expect(wrapper.text()).toContain('Implementation Follow-up Needed');

        wrapper.unmount();
    });

    it('keeps signed revision metadata in the metric header and out of the signal editor', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const metricItem = wrapper
            .findAll('button')
            .find((el) => el.text().includes('Monthly Active Users'));
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        const metadata = wrapper.get('[data-test="selected-metric-metadata"]');
        expect(
            metadata.get('[data-test="selected-metric-version"]').text(),
        ).toBe('v2');
        expect(
            metadata.get('[data-test="selected-metric-signed-at"]').text(),
        ).toContain('Signed');
        expect(
            metadata.get('[data-test="selected-metric-signed-by"]').text(),
        ).toContain('Jane Doe');

        const editor = wrapper.get('[data-test="handbook-signal-editor"]');
        expect(editor.text()).not.toContain('Approval & Version');
        expect(editor.text()).not.toContain('Signed Off At');
        expect(editor.text()).not.toContain('Signed Off By');

        wrapper.unmount();
    });

    it('displays compact banner and skeleton sections for unsigned metrics', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the pending metric (Customer Churn Rate)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Customer Churn Rate'),
        );
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        // Check for the compact unsigned banner and link
        const banner = wrapper.find('[data-test="unsigned-metric-banner"]');
        expect(banner.exists()).toBe(true);
        expect(banner.text()).toContain('Consultant Signals Unsigned');
        expect(banner.text()).toContain('Go to Workshop Alignment');

        // Check for the workshop link href
        const workshopLink = wrapper.find(
            '[data-test="unsigned-workshop-link"]',
        );
        expect(workshopLink.exists()).toBe(true);
        expect(workshopLink.attributes('href')).toContain(
            '/workshop?metric=metric-2',
        );

        // Check that the skeleton signal editor wrapper is rendered and disabled/dimmed
        const editorWrapper = wrapper.find(
            '[data-test="unsigned-signal-editor-wrapper"]',
        );
        expect(editorWrapper.exists()).toBe(true);
        expect(editorWrapper.classes()).toContain('pointer-events-none');
        expect(editorWrapper.classes()).toContain('opacity-60');

        // Check that the editor shows "Awaiting sign-off" placeholders
        expect(editorWrapper.text()).toContain('Awaiting sign-off');
        expect(editorWrapper.text()).not.toContain(
            'Missing for handbook review',
        );
        expect(editorWrapper.text()).not.toContain('Not Specified');

        wrapper.unmount();
    });

    it('renders bounded metric list summary, preserved order, and composite readiness indicators', () => {
        const props = createProps();

        // Modify metric-3 to have one missing item so we can test "{N} missing" indicator and secondary detail
        props.metrics[2].readiness_checklist.executive_takeaway.status =
            'missing';

        const wrapper = mount(HandbookWorkbench, { props });

        // 1. Verify page-level readiness summary (metric-1 is ready, metric-2 is unsigned, metric-3 has missing items)
        const pageSummary = wrapper.find(
            '[data-test="page-readiness-summary"]',
        );
        expect(pageSummary.exists()).toBe(true);
        expect(pageSummary.text()).toBe('1/3 Ready');

        // 2. Verify metric list order is preserved
        const buttons = wrapper.findAll('button');
        const metricRowTexts = buttons
            .filter((btn) => btn.attributes('class')?.includes('text-left'))
            .map((btn) => btn.text());

        expect(metricRowTexts.length).toBe(3);
        expect(metricRowTexts[0]).toContain('Monthly Active Users');
        expect(metricRowTexts[1]).toContain('Customer Churn Rate');
        expect(metricRowTexts[2]).toContain('Net Promoter Score');

        // 3. Verify composite readiness indicators
        const indicators = wrapper.findAll(
            '[data-test="composite-readiness-indicator"]',
        );
        expect(indicators.length).toBe(3);

        // metric-1: "Ready for export"
        expect(indicators[0].text()).toBe('Ready for export');
        expect(indicators[0].classes()).toContain('text-emerald-700');

        // metric-2: "Pending sign-off"
        expect(indicators[1].text()).toBe('Pending sign-off');
        expect(indicators[1].classes()).toContain('text-muted-foreground');

        // metric-3: "1 missing"
        expect(indicators[2].text()).toBe('1 missing');
        expect(indicators[2].classes()).toContain('text-amber-700');

        // 4. Verify secondary missing details
        const details = wrapper.findAll(
            '[data-test="secondary-missing-detail"]',
        );
        expect(details.length).toBe(1);
        expect(details[0].text()).toContain('Missing: Executive Takeaway');

        wrapper.unmount();
    });

    it('renders section-local read-only source references with proper details and quiet missing states', async () => {
        const props = createProps();

        // Let's add mock sections for gaps and validation to metric-1 to cover all cases
        props.metrics[0].source_context!.signed_definition.sections.push(
            {
                key: 'controls_caveats',
                title: 'Controls & Caveats',
                items: [
                    {
                        key: 'gap_1',
                        label: 'Data Gap',
                        value: 'Missing historical data before 2025.',
                        companions: [],
                    },
                ],
            },
            {
                key: 'measurement_logic_rules',
                title: 'Measurement Logic',
                items: [
                    {
                        key: 'logic_1',
                        label: 'Calculations',
                        value: 'Divide active by total.',
                        companions: [],
                    },
                ],
            },
        );

        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (Monthly Active Users)
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Verify takeaway local source reference
        const takeawaySource = wrapper.find(
            '[data-test="source-reference-takeaway"]',
        );
        expect(takeawaySource.exists()).toBe(true);
        expect(takeawaySource.text()).toContain(
            'Source Reference: Official Definition',
        );
        expect(takeawaySource.text()).toContain('MAU Definition');
        expect(takeawaySource.text()).toContain(
            'Monthly active users counted via login events.',
        );

        // Verify alignment local source reference
        const alignmentSource = wrapper.find(
            '[data-test="source-reference-alignment"]',
        );
        expect(alignmentSource.exists()).toBe(true);
        expect(alignmentSource.text()).toContain(
            'Source Reference: Workshop Alignment',
        );
        expect(alignmentSource.text()).toContain('Consensus Decision:');
        expect(alignmentSource.text()).toContain(
            'Use key features action for MAU',
        );
        expect(alignmentSource.text()).toContain('Resolved Discrepancies');
        expect(alignmentSource.text()).toContain('Marketing counts seats');

        // Verify status local source reference
        const statusSource = wrapper.find(
            '[data-test="source-reference-status"]',
        );
        expect(statusSource.exists()).toBe(true);
        expect(statusSource.text()).toContain(
            'Source Reference: Signed Definition Header',
        );
        expect(statusSource.text()).toContain('v2');
        expect(statusSource.text()).toContain('Metric Owner: Jane Doe');
        expect(statusSource.text()).toContain('Signed after workshop review.');

        // Verify gaps local source reference
        const gapsSource = wrapper.find('[data-test="source-reference-gaps"]');
        expect(gapsSource.exists()).toBe(true);
        expect(gapsSource.text()).toContain(
            'Source Reference: Controls & Caveats',
        );
        expect(gapsSource.text()).toContain('Data Gap');
        expect(gapsSource.text()).toContain(
            'Missing historical data before 2025.',
        );

        // Verify validation local source reference
        const validationSource = wrapper.find(
            '[data-test="source-reference-validation"]',
        );
        expect(validationSource.exists()).toBe(true);
        expect(validationSource.text()).toContain(
            'Source Reference: Measurement Logic & Rules',
        );
        expect(validationSource.text()).toContain('Calculations');
        expect(validationSource.text()).toContain('Divide active by total.');

        // Verify the full source context sidebar is still openable and functional
        const fullSource = wrapper.find('[data-test="source-context-panel"]');
        expect(fullSource.exists()).toBe(true);

        // Select Net Promoter Score (which has missing evidence/sections)
        await buttons
            .find((el) => el.text().includes('Net Promoter Score'))!
            .trigger('click');

        // Verify quiet missing-source states
        const takeawaySource3 = wrapper.find(
            '[data-test="source-reference-takeaway"]',
        );
        expect(takeawaySource3.exists()).toBe(true);
        expect(takeawaySource3.text()).toContain(
            'No official definition fields are available.',
        );

        const alignmentSource3 = wrapper.find(
            '[data-test="source-reference-alignment"]',
        );
        expect(alignmentSource3.exists()).toBe(true);
        expect(alignmentSource3.text()).toContain(
            'No workshop alignment evidence recorded.',
        );

        const gapsSource3 = wrapper.find('[data-test="source-reference-gaps"]');
        expect(gapsSource3.exists()).toBe(true);
        expect(gapsSource3.text()).toContain(
            'No controls or caveats fields are available.',
        );

        const validationSource3 = wrapper.find(
            '[data-test="source-reference-validation"]',
        );
        expect(validationSource3.exists()).toBe(true);
        expect(validationSource3.text()).toContain(
            'No measurement logic or rules fields are available.',
        );

        // Select the pending unsigned metric (Customer Churn Rate, which has source_context = null)
        await buttons
            .find((el) => el.text().includes('Customer Churn Rate'))!
            .trigger('click');

        // Local source reference disclosures should not be rendered
        expect(
            wrapper.find('[data-test="source-reference-takeaway"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="source-reference-alignment"]').exists(),
        ).toBe(false);

        wrapper.unmount();
    });

    it('saves full normalized payload when editing locally and applies dirty guard', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (Monthly Active Users)
        const metricItem = wrapper.findAll('button').find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        await metricItem!.trigger('click');

        // Enter local edit mode for Takeaway
        await wrapper.find('[data-test="edit-section-takeaway-btn"]').trigger('click');

        // Verify other section Edit buttons are disabled while one is active
        expect(
            wrapper.find('[data-test="edit-section-alignment-btn"]').element.hasAttribute('disabled'),
        ).toBe(true);

        // Verify dirty guard prevents unforced navigation when local section is dirty
        const takeawayTextarea = wrapper.find('textarea#executive_takeaway');
        await takeawayTextarea.setValue('Dirty local takeaway.');

        // Attempt to switch metrics
        const otherMetric = wrapper.findAll('button').find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        await otherMetric!.trigger('click');

        // Modal should appear
        const dialog = wrapper.find('[data-test="discard-confirmation-dialog"]');
        expect(dialog.exists()).toBe(true);

        // Cancel the discard (stay)
        await wrapper.find('[data-test="stay-btn"]').trigger('click');
        expect(wrapper.find('[data-test="discard-confirmation-dialog"]').exists()).toBe(false);

        // Mock successful patch call
        mockRouterPatch.mockImplementationOnce((url, data, options) => {
            if (props.metrics[0].signals) {
                props.metrics[0].signals = {
                    ...props.metrics[0].signals,
                    ...data,
                };
            }
            if (options && options.onSuccess) options.onSuccess();
            if (options && options.onFinish) options.onFinish();
        });

        try {
            // Save local section
            const saveBtn = wrapper.find('[data-test="save-section-takeaway-btn"]');
            await saveBtn.trigger('click');

            // Verify full payload is sent
            expect(mockRouterPatch).toHaveBeenCalledWith(
                '/organizations/org-123/org-metrics/org-metric-1/signals',
                expect.objectContaining({
                    executive_takeaway: 'Dirty local takeaway.',
                    alignment_summary: 'Aligned with standards.', // Unchanged full payload data
                    operational_status: 'operationally_ready',
                }),
                expect.any(Object),
            );

            // Should exit local edit mode and toast success
            // Wait for next tick so watch can execute
            await wrapper.vm.$nextTick();
            expect(wrapper.find('[data-test="save-section-takeaway-btn"]').exists()).toBe(false);
            expect(mockToast).toHaveBeenCalledWith(
                'success',
                'Official metric signals updated.',
            );
        } finally {
            wrapper.unmount();
        }
    });

    it('manages signals inline editing lifecycle with local and global edit all modes', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (Monthly Active Users)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        // Initially, Global Edit button should be visible
        let globalEditBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(globalEditBtn).toBeTruthy();

        // And local edit buttons for sections should be visible
        const localTakeawayEditBtn = wrapper.find('[data-test="edit-section-takeaway-btn"]');
        expect(localTakeawayEditBtn.exists()).toBe(true);

        // 1. Enter local edit mode for Takeaway
        await localTakeawayEditBtn.trigger('click');

        // Local Takeaway Save/Cancel should show, fields should be editable
        const localTakeawayCancelBtn = wrapper.find('[data-test="cancel-section-takeaway-btn"]');
        const localTakeawaySaveBtn = wrapper.find('[data-test="save-section-takeaway-btn"]');
        expect(localTakeawayCancelBtn.exists()).toBe(true);
        expect(localTakeawaySaveBtn.exists()).toBe(true);
        expect(localTakeawaySaveBtn.element.hasAttribute('disabled')).toBe(true);

        const takeawayTextarea = wrapper.find('textarea#executive_takeaway');
        expect(takeawayTextarea.exists()).toBe(true);
        expect((takeawayTextarea.element as HTMLTextAreaElement).value).toBe(
            'This is the MAU executive takeaway.',
        );

        // Modify field to make local section dirty
        await takeawayTextarea.setValue('Updated MAU takeaway.');
        expect(localTakeawaySaveBtn.element.hasAttribute('disabled')).toBe(false);

        // Global Edit button should still be there to "Edit All"
        globalEditBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(globalEditBtn).toBeTruthy();

        // 2. Cancel local edit
        await localTakeawayCancelBtn.trigger('click');
        expect(wrapper.find('[data-test="edit-section-takeaway-btn"]').exists()).toBe(true);

        // Re-enter local edit to check reset
        await wrapper.find('[data-test="edit-section-takeaway-btn"]').trigger('click');
        const resetTakeaway = wrapper.find('textarea#executive_takeaway');
        expect((resetTakeaway.element as HTMLTextAreaElement).value).toBe(
            'This is the MAU executive takeaway.',
        );
        await wrapper.find('[data-test="cancel-section-takeaway-btn"]').trigger('click');

        // 3. Enter Global Edit (Edit All) mode
        await globalEditBtn!.trigger('click');

        // Global Edit button should hide, Global Cancel and Save should show
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Edit'),
        ).toBeFalsy();
        const globalCancelBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Cancel');
        const globalSaveBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save');
        expect(globalCancelBtn).toBeTruthy();
        expect(globalSaveBtn).toBeTruthy();

        // Local edit buttons should be hidden when in global edit mode
        expect(wrapper.find('[data-test="edit-section-takeaway-btn"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="save-section-takeaway-btn"]').exists()).toBe(false);

        const alignmentTextarea = wrapper.find('textarea#alignment_summary');
        expect(alignmentTextarea.exists()).toBe(true);

        // Make dirty
        await alignmentTextarea.setValue('Updated MAU alignment summary globally.');
        expect(globalSaveBtn!.element.hasAttribute('disabled')).toBe(false);

        // Cancel Global Edit
        await globalCancelBtn!.trigger('click');

        // Check if value was reset
        await globalEditBtn!.trigger('click');
        const resetAlignmentTextarea = wrapper.find('textarea#alignment_summary');
        expect((resetAlignmentTextarea.element as HTMLTextAreaElement).value).toBe(
            'Aligned with standards.',
        );

        expect(
            (
                wrapper.find('textarea#executive_takeaway')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('This is the MAU executive takeaway.');
        expect(
            (
                wrapper.find('textarea#alignment_summary')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Aligned with standards.');
        expect(
            (
                wrapper.find('select#operational_status')
                    .element as HTMLSelectElement
            ).value,
        ).toBe('operationally_ready');
        expect(
            (
                wrapper.find('textarea#operational_status_notes')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Fully functional.');
        expect(
            (wrapper.find('input#gap_owner').element as HTMLInputElement).value,
        ).toBe('Jane Doe');

        // 4. Save signals - happy path
        // Make dirty again
        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Updated MAU takeaway.');
        await wrapper
            .find('textarea#alignment_summary')
            .setValue('Updated MAU alignment summary.');
        await wrapper
            .find('textarea#operational_status_notes')
            .setValue('Fully functional and updated.');
        const updatedSaveBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save');
        expect(updatedSaveBtn!.element.hasAttribute('disabled')).toBe(false);

        // Mock successful patch call
        mockRouterPatch.mockImplementationOnce((url, data, options) => {
            if (props.metrics[0].signals) {
                props.metrics[0].signals = {
                    ...props.metrics[0].signals,
                    ...data,
                };
            }
            if (options && options.onSuccess) {
                options.onSuccess();
            }
            if (options && options.onFinish) {
                options.onFinish();
            }
        });

        await updatedSaveBtn!.trigger('click');

        // Check patch details
        expect(mockRouterPatch).toHaveBeenCalledWith(
            '/organizations/org-123/org-metrics/org-metric-1/signals',
            expect.objectContaining({
                executive_takeaway: 'Updated MAU takeaway.',
                alignment_summary: 'Updated MAU alignment summary.',
                operational_status: 'operationally_ready',
                operational_status_notes: 'Fully functional and updated.',
                implementation_gaps: expect.objectContaining({
                    owner: 'Jane Doe',
                }),
            }),
            expect.any(Object),
        );

        // Should exit edit mode and toast success
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Edit'),
        ).toBeTruthy();
        expect(mockToast).toHaveBeenCalledWith(
            'success',
            'Official metric signals updated.',
        );

        // 5. Switch metrics resets editing state
        globalEditBtn = wrapper.findAll('button').find((el) => el.text() === 'Edit');
        await globalEditBtn!.trigger('click');
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Cancel'),
        ).toBeTruthy();

        // Switch to Net Promoter Score (metric-3)
        const metricItem3 = wrapper
            .findAll('button')
            .find((el) => el.text().includes('Net Promoter Score'));
        await metricItem3!.trigger('click');

        // Form should exit edit mode
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Cancel'),
        ).toBeFalsy();
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Edit'),
        ).toBeTruthy();

        wrapper.unmount();
    });

    it('shows error toast when saving signals fails', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select MAU metric and go to edit mode
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit')!
            .trigger('click');

        // Make dirty
        await wrapper
            .find('textarea#operational_status_notes')
            .setValue('Dirty note.');

        // Mock error patch call
        mockRouterPatch.mockImplementationOnce((url, data, options) => {
            if (options && options.onError) {
                options.onError({ notes: 'Notes field is invalid.' });
            }
            if (options && options.onFinish) {
                options.onFinish();
            }
        });

        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save')!
            .trigger('click');

        // Toast error should have been called
        expect(mockToast).toHaveBeenCalledWith(
            'error',
            'Notes field is invalid.',
        );
        // Should still be in edit mode
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Cancel'),
        ).toBeTruthy();

        wrapper.unmount();
    });

    it('enables Save button when only alignment_summary is changed', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (Monthly Active Users)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        await metricItem!.trigger('click');

        // Enter edit mode
        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        await editBtn!.trigger('click');

        const saveBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save');
        expect(saveBtn!.element.hasAttribute('disabled')).toBe(true);

        // Modify only alignment_summary
        const alignmentTextarea = wrapper.find('textarea#alignment_summary');
        await alignmentTextarea.setValue('New alignment summary value');

        // Save button should now be enabled
        expect(saveBtn!.element.hasAttribute('disabled')).toBe(false);

        wrapper.unmount();
    });

    it('sends null for executive_takeaway when cleared', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric and enter edit mode
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit')!
            .trigger('click');

        // Clear the takeaway field (whitespace only should trim to null)
        const takeawayTextarea = wrapper.find('textarea#executive_takeaway');
        expect(takeawayTextarea.exists()).toBe(true);
        await takeawayTextarea.setValue('   ');

        // Mock successful patch call
        mockRouterPatch.mockImplementationOnce((url, data, options) => {
            if (options && options.onSuccess) {
                options.onSuccess();
            }
            if (options && options.onFinish) {
                options.onFinish();
            }
        });

        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save')!
            .trigger('click');

        // Verify payload sends null for executive_takeaway
        expect(mockRouterPatch).toHaveBeenCalledWith(
            '/organizations/org-123/org-metrics/org-metric-1/signals',
            expect.objectContaining({
                executive_takeaway: null,
            }),
            expect.any(Object),
        );

        wrapper.unmount();
    });

    it('renders the export readiness checklist status badges for a signed metric', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Check if the Export Readiness Checklist is NOT rendered as a separate card
        expect(
            wrapper.find('[data-test="export-readiness-checklist"]').exists(),
        ).toBe(false);

        // Check the Go to Handbook Exports link exists in the header
        const exportsLink = wrapper.find('[data-test="handbook-exports-link"]');
        expect(exportsLink.exists()).toBe(true);
        expect(exportsLink.attributes('href')).toBe(
            '/organizations/org-123/handbook-exports',
        );

        // Check if the inline status badges are rendered with ready status
        const takeawayBadge = wrapper.find(
            '[data-test="status-badge-executive_takeaway"]',
        );
        expect(takeawayBadge.exists()).toBe(true);
        expect(takeawayBadge.text()).toContain('ready');

        const alignmentBadge = wrapper.find(
            '[data-test="status-badge-executive_alignment"]',
        );
        expect(alignmentBadge.exists()).toBe(true);
        expect(alignmentBadge.text()).toContain('ready');

        const implStatusBadge = wrapper.find(
            '[data-test="status-badge-implementation_review_status"]',
        );
        expect(implStatusBadge.exists()).toBe(true);
        expect(implStatusBadge.text()).toContain('ready');

        const implFollowupBadge = wrapper.find(
            '[data-test="status-badge-implementation_review_followup"]',
        );
        expect(implFollowupBadge.exists()).toBe(true);
        expect(implFollowupBadge.text()).toContain('ready');

        const validationBadge = wrapper.find(
            '[data-test="status-badge-validation_guidance"]',
        );
        expect(validationBadge.exists()).toBe(true);
        expect(validationBadge.text()).toContain('ready');

        wrapper.unmount();
    });

    it('shows missing checklist states on badges without hiding the handbook exports link', async () => {
        const props = createProps();
        props.metrics[0].readiness_checklist.executive_takeaway.status =
            'missing';
        props.metrics[0].readiness_checklist.executive_alignment.status =
            'missing';
        props.metrics[0].readiness_checklist.validation_guidance.status =
            'missing';

        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Check if the inline status badges are rendered with missing status
        const takeawayBadge = wrapper.find(
            '[data-test="status-badge-executive_takeaway"]',
        );
        expect(takeawayBadge.exists()).toBe(true);
        expect(takeawayBadge.text()).toContain('missing');

        const alignmentBadge = wrapper.find(
            '[data-test="status-badge-executive_alignment"]',
        );
        expect(alignmentBadge.exists()).toBe(true);
        expect(alignmentBadge.text()).toContain('missing');

        const validationBadge = wrapper.find(
            '[data-test="status-badge-validation_guidance"]',
        );
        expect(validationBadge.exists()).toBe(true);
        expect(validationBadge.text()).toContain('missing');

        expect(
            wrapper.find('[data-test="handbook-exports-link"]').exists(),
        ).toBe(true);

        wrapper.unmount();
    });

    it('renders source context panel with signed definition sections for signed metric', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (MAU)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        await metricItem!.trigger('click');

        // Source context panel should be rendered
        const panel = wrapper.find('[data-test="source-context-panel"]');
        expect(panel.exists()).toBe(true);

        // Source context component should render
        const sourceContext = wrapper.find(
            '[data-test="metric-source-context"]',
        );
        expect(sourceContext.exists()).toBe(true);

        // Header should show version and signed-by
        const header = wrapper.find('[data-test="source-context-header"]');
        expect(header.exists()).toBe(true);
        expect(header.text()).toContain('v2');
        expect(header.text()).toContain('Jane Doe');

        // Notes should be displayed
        const notes = wrapper.find('[data-test="source-context-notes"]');
        expect(notes.exists()).toBe(true);
        expect(notes.text()).toContain('Signed after workshop review.');

        // Owner should be displayed
        const owner = wrapper.find('[data-test="source-context-owner"]');
        expect(owner.exists()).toBe(true);
        expect(owner.text()).toContain('Jane Doe');

        // Sections should render
        const section = wrapper.find(
            '[data-test="source-section-official_definition"]',
        );
        expect(section.exists()).toBe(true);
        expect(section.text()).toContain('Official Definition');
        expect(section.text()).toContain('MAU Definition');
        expect(section.text()).toContain(
            'Monthly active users counted via login events.',
        );

        wrapper.unmount();
    });

    it('does not render source context panel for unsigned metrics', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select unsigned metric (Customer Churn Rate)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Customer Churn Rate'),
        );
        await metricItem!.trigger('click');

        // Source context panel should NOT be rendered (unsigned shows lock screen)
        const panel = wrapper.find('[data-test="source-context-panel"]');
        expect(panel.exists()).toBe(false);

        wrapper.unmount();
    });

    it('renders empty sections state when source context has no definition fields', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select NPS metric (which has empty sections)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        await metricItem!.trigger('click');

        // Source context panel exists
        const panel = wrapper.find('[data-test="source-context-panel"]');
        expect(panel.exists()).toBe(true);

        // Empty sections state should show
        const emptySections = wrapper.find(
            '[data-test="source-context-empty-sections"]',
        );
        expect(emptySections.exists()).toBe(true);
        expect(emptySections.text()).toContain(
            'No structured definition fields',
        );

        wrapper.unmount();
    });

    it('renders workshop alignment context with decisions, discrepancies, collapsible notes, and link', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (MAU)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        await metricItem!.trigger('click');

        // Check if workshop alignment panel is rendered
        const alignmentPanel = wrapper.find(
            '[data-test="workshop-alignment-panel"]',
        );
        expect(alignmentPanel.exists()).toBe(true);
        expect(alignmentPanel.text()).toContain('Workshop Alignment');

        // Decisions rendered
        const decisions = wrapper.find(
            '[data-test="workshop-alignment-decisions"]',
        );
        expect(decisions.exists()).toBe(true);
        expect(decisions.text()).toContain('Consensus Decision:');
        expect(decisions.text()).toContain('Use key features action for MAU');
        expect(decisions.text()).toContain('Follow-up:');
        expect(decisions.text()).toContain('Finance to reconcile accounts');

        // Field-level decisions rendered
        const fieldDecisions = wrapper.find(
            '[data-test="workshop-alignment-field-decisions"]',
        );
        expect(fieldDecisions.exists()).toBe(true);
        expect(fieldDecisions.text()).toContain('Field Decisions');
        expect(fieldDecisions.text()).toContain('MAU Definition');
        expect(fieldDecisions.text()).toContain('Selected: key_feature_action');
        expect(fieldDecisions.text()).toContain(
            'Finance and Product agreed that key feature action is the board-ready MAU activity event.',
        );

        // Discrepancies rendered
        const discrepancies = wrapper.find(
            '[data-test="workshop-alignment-discrepancies"]',
        );
        expect(discrepancies.exists()).toBe(true);
        expect(discrepancies.text()).toContain('Resolved Discrepancies');
        expect(discrepancies.text()).toContain(
            'Marketing counts seats, Product counts key actions.',
        );
        expect(discrepancies.text()).toContain('medium severity');

        // Notes details exists and contains text
        const notesDetails = wrapper.find(
            '[data-test="workshop-alignment-notes-details"]',
        );
        expect(notesDetails.exists()).toBe(true);
        expect(notesDetails.text()).toContain('View Discussion Notes');
        expect(notesDetails.text()).toContain(
            'Discussion about MAU. Finance agreed to key features.',
        );

        // Workshop link exists and points to route
        const link = wrapper.find('[data-test="workshop-alignment-link"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe(
            '/organizations/org-123/engagements/eng-1/workshop?metric=metric-1',
        );

        wrapper.unmount();
    });

    it('renders quiet missing state for workshop alignment when evidence is not available', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select Net Promoter Score (which has has_evidence: false)
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        await metricItem!.trigger('click');

        // Check if workshop alignment panel is rendered
        const alignmentPanel = wrapper.find(
            '[data-test="workshop-alignment-panel"]',
        );
        expect(alignmentPanel.exists()).toBe(true);

        // Should show missing evidence message
        const missingEvidence = wrapper.find(
            '[data-test="workshop-alignment-missing"]',
        );
        expect(missingEvidence.exists()).toBe(true);
        expect(missingEvidence.text()).toContain(
            'No workshop alignment evidence recorded',
        );

        wrapper.unmount();
    });

    it('preserves signal save behavior when source context is present', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select signed metric
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Enter edit mode
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit')!
            .trigger('click');

        // Make dirty
        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Updated takeaway with source context.');

        // Mock successful patch
        mockRouterPatch.mockImplementationOnce((_url, _data, options) => {
            if (options && options.onSuccess) options.onSuccess();
            if (options && options.onFinish) options.onFinish();
        });

        // Save
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save')!
            .trigger('click');

        // Verify save worked
        expect(mockRouterPatch).toHaveBeenCalledWith(
            '/organizations/org-123/org-metrics/org-metric-1/signals',
            expect.objectContaining({
                executive_takeaway: 'Updated takeaway with source context.',
            }),
            expect.any(Object),
        );
        expect(mockToast).toHaveBeenCalledWith(
            'success',
            'Official metric signals updated.',
        );

        // Source context panel still present after save
        const panel = wrapper.find('[data-test="source-context-panel"]');
        expect(panel.exists()).toBe(true);

        wrapper.unmount();
    });

    it('renders inline source cues for all editable fields in edit mode', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (MAU)
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Enter edit mode
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit')!
            .trigger('click');

        // All 5 source cues should be rendered in the DOM
        expect(
            wrapper
                .find('[data-test="executive-takeaway-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="alignment-summary-source-cue"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="operational-status-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="implementation-gaps-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="validation-checklist-source-cue"]')
                .exists(),
        ).toBe(true);

        // Content verification
        expect(
            wrapper.find('[data-test="executive-takeaway-source-cue"]').text(),
        ).toContain('Official Definition');
        expect(
            wrapper.find('[data-test="alignment-summary-source-cue"]').text(),
        ).toContain('Workshop Alignment');
        expect(
            wrapper.find('[data-test="operational-status-source-cue"]').text(),
        ).toContain('Signed Definition Header');
        expect(
            wrapper.find('[data-test="implementation-gaps-source-cue"]').text(),
        ).toContain('Controls & Caveats');
        expect(
            wrapper
                .find('[data-test="validation-checklist-source-cue"]')
                .text(),
        ).toContain('Measurement Logic & Rules');

        // No warning cues should be rendered since source context data is fully present
        expect(
            wrapper
                .find('[data-test="executive-takeaway-missing-source-cue"]')
                .exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-test="alignment-summary-missing-source-cue"]')
                .exists(),
        ).toBe(false);

        // Assert no automatic field mutation
        expect(
            (
                wrapper.find('textarea#executive_takeaway')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('This is the MAU executive takeaway.');
        expect(
            (
                wrapper.find('textarea#alignment_summary')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Aligned with standards.');
        expect(
            (
                wrapper.find('select#operational_status')
                    .element as HTMLSelectElement
            ).value,
        ).toBe('operationally_ready');
        expect(
            (
                wrapper.find('textarea#operational_status_notes')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Fully functional.');
        expect(
            (
                wrapper.find('input#gap_current_state')
                    .element as HTMLInputElement
            ).value,
        ).toBe('Current MAU track');
        expect(
            (wrapper.find('input#gap_gap').element as HTMLInputElement).value,
        ).toBe('None');
        expect(
            (wrapper.find('input#gap_owner').element as HTMLInputElement).value,
        ).toBe('Jane Doe');
        expect(
            (wrapper.find('input#gap_next_step').element as HTMLInputElement)
                .value,
        ).toBe('Proceed.');
        expect(
            (
                wrapper.find('textarea#validation_steps')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Check database queries.');
        expect(
            (
                wrapper.find('textarea#common_mismatch_causes')
                    .element as HTMLTextAreaElement
            ).value,
        ).toBe('Timezone discrepancies.');

        expect(mockRouterPatch).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('renders field-level warning cues when source context evidence is missing in edit mode', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select Net Promoter Score (metric-3) which has has_evidence: false and sections: []
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Net Promoter Score'))!
            .trigger('click');

        // Enter edit mode
        await wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit')!
            .trigger('click');

        // Warning cue for missing workshop evidence should render on alignment_summary
        const alignmentWarning = wrapper.find(
            '[data-test="alignment-summary-missing-source-cue"]',
        );
        expect(alignmentWarning.exists()).toBe(true);
        expect(alignmentWarning.text()).toContain(
            'No workshop alignment evidence',
        );

        // Warning cues for missing definition sections should render
        expect(
            wrapper
                .find('[data-test="executive-takeaway-missing-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="operational-status-missing-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="implementation-gaps-missing-source-cue"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="validation-checklist-missing-source-cue"]')
                .exists(),
        ).toBe(true);

        wrapper.unmount();
    });

    it('supports collapsing and expanding the left metric list sidebar with a collapsed rail', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Sidebar initially visible
        expect(
            wrapper.find('[data-test="collapse-metric-list-btn"]').exists(),
        ).toBe(true);
        expect(wrapper.find('div.lg\\:w-\\[380px\\]').exists()).toBe(true);
        expect(wrapper.find('[data-test="collapsed-metric-rail"]').exists()).toBe(false);

        // Click collapse button
        await wrapper
            .find('[data-test="collapse-metric-list-btn"]')
            .trigger('click');

        // Sidebar should now be hidden
        expect(wrapper.find('div.lg\\:w-\\[380px\\]').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="collapse-metric-list-btn"]').exists(),
        ).toBe(false);

        // Collapsed rail should be visible
        const rail = wrapper.find('[data-test="collapsed-metric-rail"]');
        expect(rail.exists()).toBe(true);

        // Verify metric count indicator ready/total count (e.g. 2 of 3 ready)
        const railCount = wrapper.find('[data-test="collapsed-metric-count"]');
        expect(railCount.exists()).toBe(true);
        expect(railCount.text()).toBe('2/3');

        // Expand button should now be visible in header (with lg:hidden check)
        const expandBtn = wrapper.find('[data-test="expand-metric-list-btn"]');
        expect(expandBtn.exists()).toBe(true);
        expect(expandBtn.classes()).toContain('lg:hidden');

        // Click expand button from the collapsed rail
        const railExpandBtn = wrapper.find('[data-test="expand-metric-list-rail-btn"]');
        expect(railExpandBtn.exists()).toBe(true);

        // Check metric buttons on the rail
        const firstMetricId = props.metrics[0].id;
        const secondMetricId = props.metrics[1].id;
        const firstBtn = wrapper.find(`[data-test="rail-metric-btn-${firstMetricId}"]`);
        const secondBtn = wrapper.find(`[data-test="rail-metric-btn-${secondMetricId}"]`);

        expect(firstBtn.exists()).toBe(true);
        expect(secondBtn.exists()).toBe(true);

        // Verify indicators (numbered 1 and 2)
        expect(firstBtn.text()).toBe('1');
        expect(secondBtn.text()).toBe('2');

        // Verify title tooltips contain the metric name
        expect(firstBtn.attributes('title')).toContain(props.metrics[0].name);
        expect(secondBtn.attributes('title')).toContain(props.metrics[1].name);

        // Verify aria-label contains the metric name and readiness label
        expect(firstBtn.attributes('aria-label')).toContain(props.metrics[0].name);
        expect(firstBtn.attributes('aria-label')).toContain('Ready for export');
        expect(secondBtn.attributes('aria-label')).toContain(props.metrics[1].name);
        expect(secondBtn.attributes('aria-label')).toContain('Pending sign-off');

        // Verify status dot tones/colors
        const firstDot = firstBtn.find('[data-test="rail-metric-readiness-dot"]');
        const secondDot = secondBtn.find('[data-test="rail-metric-readiness-dot"]');
        expect(firstDot.exists()).toBe(true);
        expect(secondDot.exists()).toBe(true);
        expect(firstDot.classes()).toContain('bg-emerald-500'); // metric-1 is ready
        expect(secondDot.classes()).toContain('bg-muted-foreground/45'); // metric-2 is unsigned/muted

        // Switch to the second metric using the rail button
        await secondBtn.trigger('click');
        expect(wrapper.find('h2.truncate').text()).toBe(props.metrics[1].name);

        // Click rail expand button to restore the sidebar
        await railExpandBtn.trigger('click');

        // Sidebar should be visible again
        expect(wrapper.find('div.lg\\:w-\\[380px\\]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="collapse-metric-list-btn"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="collapsed-metric-rail"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('renders deep links to Organization Metric details page and Reconciliation Workshop page', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select the signed-off metric (MAU)
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // Deep link to Org Metric details should exist
        const orgMetricLink = wrapper.find(
            '[data-test="org-metric-details-link"]',
        );
        expect(orgMetricLink.exists()).toBe(true);
        expect(orgMetricLink.attributes('href')).toBe(
            '/organizations/org-123/org-metrics/org-metric-1',
        );

        // Select the unsigned metric (Customer Churn Rate)
        await buttons
            .find((el) => el.text().includes('Customer Churn Rate'))!
            .trigger('click');

        // Link to Reconciliation Workshop should exist on the lock screen
        const workshopLink = wrapper.find(
            '[data-test="unsigned-workshop-link"]',
        );
        expect(workshopLink.exists()).toBe(true);
        expect(workshopLink.attributes('href')).toBe(
            '/organizations/org-123/engagements/eng-456/workshop?metric=metric-2',
        );

        wrapper.unmount();
    });

    it('toggles the responsive Source Context Sheet and sidebar elements appropriately', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select a signed metric (MAU) to show signals
        const buttons = wrapper.findAll('button');
        await buttons
            .find((el) => el.text().includes('Monthly Active Users'))!
            .trigger('click');

        // By default (not collapsed), sidebar has classes that hide it below xl screens
        const sidebar = wrapper.find('[data-test="source-context-panel"]');
        expect(sidebar.classes()).toContain('hidden');
        expect(sidebar.classes()).toContain('xl:block');

        // Sheet button should have class xl:hidden (visible below xl screens)
        const sheetTrigger = wrapper.find(
            '[data-test="open-source-context-sheet-btn"]',
        );
        expect(sheetTrigger.exists()).toBe(true);
        expect(sheetTrigger.classes()).toContain('xl:hidden');

        // Collapse sidebar list
        await wrapper
            .find('[data-test="collapse-metric-list-btn"]')
            .trigger('click');

        // Sidebar classes should update to show it on lg screens
        expect(sidebar.classes()).toContain('hidden');
        expect(sidebar.classes()).toContain('lg:block');

        // Sheet button classes should update to be hidden on lg screens
        expect(sheetTrigger.classes()).toContain('lg:hidden');

        // Click the trigger button to open the responsive Source Context Sheet
        await sheetTrigger.trigger('click');

        // Verify the Sheet's content is rendered in the DOM
        expect(document.body.innerHTML).toContain(
            'Reference definition fields and workshop evidence',
        );

        wrapper.unmount();
    });

    it('humanizes multi-underscore statuses for engagement and selected metric', async () => {
        const props = createProps();
        props.engagement.status = 'in_active_review';
        props.metrics[0].status = 'pending_sign_off';

        const wrapper = mount(HandbookWorkbench, { props });

        // Engagement status should replace all underscores
        expect(wrapper.text()).toContain('in active review');

        // Select the metric to show its details/status
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        // Metric status should replace all underscores
        expect(wrapper.text()).toContain('pending sign off');

        wrapper.unmount();
    });

    it('renders the takeaway writing guide in a collapsed-by-default details element', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        // Select a metric and enter edit mode
        const buttons = wrapper.findAll('button');
        const metricItem = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem).toBeTruthy();
        await metricItem!.trigger('click');

        // Toggle edit mode
        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        // Check if details element exists
        const details = wrapper.find(
            'details[data-test="takeaway-writing-guide"]',
        );
        expect(details.exists()).toBe(true);

        // By default, a details element is collapsed (has no "open" attribute)
        expect(details.attributes('open')).toBeUndefined();

        // Verify summary text
        expect(details.find('summary').text()).toContain(
            'Takeaway Writing Guide:',
        );
        expect(details.find('summary').text()).toContain('(click to expand)');

        // Click summary to expand
        const summary = details.find('summary');
        await summary.trigger('click');
        expect(details.attributes('open')).toBeDefined();
        expect(summary.text()).toContain('(click to collapse)');

        // Click summary again to collapse
        await summary.trigger('click');
        expect(details.attributes('open')).toBeUndefined();
        expect(summary.text()).toContain('(click to expand)');

        wrapper.unmount();
    });

    it('guards against unsaved changes on metric switches and allows staying', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        const takeawayTextarea = wrapper.find('textarea#executive_takeaway');
        expect(takeawayTextarea.exists()).toBe(true);
        await takeawayTextarea.setValue('Dirty edits here');

        const editor = wrapper.findComponent(HandbookSignalEditor);
        expect(editor.vm.isDirty).toBe(true);

        const metricItem3 = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        expect(metricItem3).toBeTruthy();
        await metricItem3!.trigger('click');

        const dialog = wrapper.find(
            '[data-test="discard-confirmation-dialog"]',
        );
        expect(dialog.exists()).toBe(true);

        const stayBtn = wrapper.find('[data-test="stay-btn"]');
        expect(stayBtn.exists()).toBe(true);
        await stayBtn.trigger('click');

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(wrapper.find('h2').text()).toContain('Monthly Active Users');

        wrapper.unmount();
    });

    it('guards against unsaved changes on metric switches and allows discarding', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        const metricItem3 = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        expect(metricItem3).toBeTruthy();
        await metricItem3!.trigger('click');

        const dialog = wrapper.find(
            '[data-test="discard-confirmation-dialog"]',
        );
        expect(dialog.exists()).toBe(true);

        const discardBtn = wrapper.find('[data-test="discard-btn"]');
        expect(discardBtn.exists()).toBe(true);
        await discardBtn.trigger('click');

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(wrapper.find('h2').text()).toContain('Net Promoter Score');
        expect(
            wrapper.findAll('button').find((el) => el.text() === 'Edit'),
        ).toBeTruthy();

        wrapper.unmount();
    });

    it('allows immediate metric switch when not dirty', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const metricItem3 = buttons.find((el) =>
            el.text().includes('Net Promoter Score'),
        );
        expect(metricItem3).toBeTruthy();
        await metricItem3!.trigger('click');

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(wrapper.find('h2').text()).toContain('Net Promoter Score');

        wrapper.unmount();
    });

    it('guards against unsaved changes on back navigation', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        mockRouterVisit.mockClear();

        const backLink = wrapper.find('[data-test="back-link"]');
        expect(backLink.exists()).toBe(true);
        await backLink.trigger('click');

        const dialog = wrapper.find(
            '[data-test="discard-confirmation-dialog"]',
        );
        expect(dialog.exists()).toBe(true);
        expect(mockRouterVisit).not.toHaveBeenCalled();

        const discardBtn = wrapper.find('[data-test="discard-btn"]');
        await discardBtn.trigger('click');
        expect(mockRouterVisit).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456',
        );

        wrapper.unmount();
    });

    it('allows immediate back navigation when not dirty', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const backLink = wrapper.find('[data-test="back-link"]');
        expect(backLink.exists()).toBe(true);

        mockRouterVisit.mockClear();
        await backLink.trigger('click');

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(mockRouterVisit).toHaveBeenCalledWith(
            '/organizations/org-123/engagements/eng-456',
        );

        wrapper.unmount();
    });

    it('guards in-app navigation when dirty', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        mockRouterVisit.mockClear();

        const exportsLink = wrapper.find('[data-test="handbook-exports-link"]');
        expect(exportsLink.exists()).toBe(true);
        await exportsLink.trigger('click');

        const dialog = wrapper.find(
            '[data-test="discard-confirmation-dialog"]',
        );
        expect(dialog.exists()).toBe(true);
        expect(mockRouterVisit).not.toHaveBeenCalled();

        const stayBtn = wrapper.find('[data-test="stay-btn"]');
        await stayBtn.trigger('click');
        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(mockRouterVisit).not.toHaveBeenCalled();

        await exportsLink.trigger('click');
        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(true);

        const discardBtn = wrapper.find('[data-test="discard-btn"]');
        await discardBtn.trigger('click');
        expect(mockRouterVisit).toHaveBeenCalledWith(
            '/organizations/org-123/handbook-exports',
            expect.any(Object),
        );

        wrapper.unmount();
    });

    it('registers native beforeunload warning when dirty', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        const cleanEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(cleanEvent);
        expect(cleanEvent.defaultPrevented).toBe(false);

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        const dirtyEvent = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(dirtyEvent);
        expect(dirtyEvent.defaultPrevented).toBe(true);

        wrapper.unmount();
    });

    it('does not block signal save requests', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        mockRouterPatch.mockClear();
        mockRouterPatch.mockImplementationOnce((url, data, options) => {
            if (options && options.onSuccess) {
                options.onSuccess();
            }
        });

        const saveBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save');
        expect(saveBtn).toBeTruthy();
        await saveBtn!.trigger('click');

        expect(mockRouterPatch).toHaveBeenCalled();
        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);

        wrapper.unmount();
    });

    it('ignores prefetch requests when dirty', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        mockRouterVisit.mockClear();

        const { router } = await import('@inertiajs/vue3');
        router.visit('/organizations/org-123/handbook-exports', {
            prefetch: true,
        });

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(false);
        expect(mockRouterVisit).not.toHaveBeenCalled();

        wrapper.unmount();
    });

    it('guards other visits even if save is in progress', async () => {
        const props = createProps();
        const wrapper = mount(HandbookWorkbench, { props });

        const buttons = wrapper.findAll('button');
        const metricItem1 = buttons.find((el) =>
            el.text().includes('Monthly Active Users'),
        );
        expect(metricItem1).toBeTruthy();
        await metricItem1!.trigger('click');

        const editBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Edit');
        expect(editBtn).toBeTruthy();
        await editBtn!.trigger('click');

        await wrapper
            .find('textarea#executive_takeaway')
            .setValue('Dirty edits here');

        mockRouterPatch.mockClear();
        mockRouterPatch.mockImplementationOnce(() => {
            const exportsLink = wrapper.find(
                '[data-test="handbook-exports-link"]',
            );
            exportsLink.trigger('click');
        });

        const saveBtn = wrapper
            .findAll('button')
            .find((el) => el.text() === 'Save');
        expect(saveBtn).toBeTruthy();
        await saveBtn!.trigger('click');

        expect(
            wrapper.find('[data-test="discard-confirmation-dialog"]').exists(),
        ).toBe(true);

        wrapper.unmount();
    });
});

describe('Handbook Signals Payload Utilities', () => {
    it('normalizeSignalText trims whitespace and returns null if empty', async () => {
        const { normalizeSignalText } =
            await import('@/features/handbook-workbench/handbook-signals');
        expect(normalizeSignalText('   ')).toBeNull();
        expect(normalizeSignalText('  hello  ')).toBe('hello');
        expect(normalizeSignalText('')).toBeNull();
        expect(normalizeSignalText(null)).toBeNull();
    });

    it('buildSignalPayload normalizes whitespace fields', async () => {
        const { buildSignalPayload } =
            await import('@/features/handbook-workbench/handbook-signals');
        const draft = {
            executive_takeaway: '  Some takeaway  ',
            alignment_summary: '  Some alignment  ',
            operational_status: 'needs_follow_up',
            operational_status_notes: '   ',
            validation_steps: '  Step 1.  ',
            common_mismatch_causes: '',
            implementation_gaps: {
                current_state: '  Partially tracked  ',
                gap: '   ',
                owner: 'Team A',
                suggested_next_step: '  ',
            },
        };
        const payload = buildSignalPayload(draft);
        expect(payload.executive_takeaway).toBe('Some takeaway');
        expect(payload.alignment_summary).toBe('Some alignment');
        expect(payload.operational_status).toBe('needs_follow_up');
        expect(payload.operational_status_notes).toBeNull();
        expect(payload.validation_steps).toBe('Step 1.');
        expect(payload.common_mismatch_causes).toBeNull();
        expect(payload.implementation_gaps.current_state).toBe(
            'Partially tracked',
        );
        expect(payload.implementation_gaps.gap).toBeNull();
        expect(payload.implementation_gaps.owner).toBe('Team A');
        expect(payload.implementation_gaps.suggested_next_step).toBeNull();
    });
});
