import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, reactive, nextTick, type Component } from 'vue';

const routerPost = vi.hoisted(() => vi.fn().mockResolvedValue(undefined));
const routerPut = vi.hoisted(() => vi.fn());
const routerDelete = vi.hoisted(() => vi.fn());

const changeGovernanceModules = import.meta.glob<
    typeof import('@/pages/Metrics/ChangeGovernance.vue')
>('@/pages/Metrics/ChangeGovernance.vue');
const moduleEntries = Object.entries(changeGovernanceModules);

const metricId = 'metric-net-revenue-retention';

const proposalPlaceholder = '__PROPOSAL__';

const baseEndpoints = Object.freeze({
    store: `/api/metrics/${metricId}/change-proposals`,
    decision: `/api/change-proposals/${proposalPlaceholder}/decision`,
    merge: `/api/change-proposals/${proposalPlaceholder}/merge`,
});

type PageContext = {
    flags: {
        change_proposals: boolean;
    };
    auth: {
        user: {
            id: string;
            name: string;
            email: string;
            abilities: string[];
        };
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    routes: {
        changeProposals: typeof baseEndpoints;
    };
};

const basePageContext = (): PageContext => ({
        flags: {
            change_proposals: true,
        },
        auth: {
            user: {
                id: 'user-consultant-owner',
                name: 'Consultant Owner',
                email: 'owner@example.test',
                abilities: ['metric.manage'],
            },
        },
        flash: {
            success: null,
            error: null,
        },
        routes: {
            changeProposals: { ...baseEndpoints },
        },
    });

const page = reactive<{ url: string; props: PageContext }>({
    url: `/metrics/${metricId}/governance`,
    props: basePageContext(),
});

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
                required: true,
            },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    'a',
                    { 'data-href': typeof props.href === 'string' ? props.href : '' },
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
            get: vi.fn(),
            post: routerPost,
            put: routerPut,
            patch: vi.fn(),
            delete: routerDelete,
            visit: vi.fn(),
            reload: vi.fn(),
            remember: vi.fn(),
        },
    };
});

const basicWrapper = (tag: string) =>
    defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });

vi.mock('@/layouts/AppShell.vue', () => ({
    default: defineComponent({
        name: 'AppShellStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'section',
                    { 'data-test': 'app-shell-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/AppBreadcrumbs.vue', () => ({
    default: defineComponent({
        name: 'BreadcrumbStub',
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

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardDescription: basicWrapper('p'),
    CardContent: basicWrapper('article'),
    CardFooter: basicWrapper('footer'),
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
            disabled: {
                type: Boolean,
                default: false,
            },
            variant: {
                type: String,
                default: 'default',
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

vi.mock('@/components/ui/checkbox', () => ({
    Checkbox: defineComponent({
        name: 'CheckboxStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: [Boolean, String, Number],
                default: false,
            },
        },
        emits: ['update:model-value'],
        setup(props, { emit, attrs }) {
            return () =>
                h('input', {
                    type: 'checkbox',
                    checked: Boolean(props.modelValue),
                    ...attrs,
                    onChange: (event: Event) =>
                        emit(
                            'update:model-value',
                            (event.target as HTMLInputElement)?.checked ?? false,
                        ),
                });
        },
    }),
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

vi.mock('@/components/ui/input', () => ({
    Input: defineComponent({
        name: 'InputStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: [String, Number],
                default: '',
            },
        },
        emits: ['update:modelValue', 'input', 'blur'],
        setup(props, { emit, attrs }) {
            return () =>
                h('input', {
                    ...attrs,
                    value: props.modelValue ?? '',
                    onInput: (event: Event) => {
                        const value = (event.target as HTMLInputElement).value;
                        emit('update:modelValue', value);
                        emit('input', event);
                    },
                });
        },
    }),
}));

vi.mock('@/components/ui/textarea', () => ({
    Textarea: defineComponent({
        name: 'TextareaStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: String,
                default: '',
            },
        },
        emits: ['update:modelValue', 'input', 'blur'],
        setup(props, { emit, attrs }) {
            return () =>
                h('textarea', {
                    ...attrs,
                    value: props.modelValue ?? '',
                    onInput: (event: Event) => {
                        const value = (event.target as HTMLTextAreaElement).value;
                        emit('update:modelValue', value);
                        emit('input', event);
                    },
                });
        },
    }),
}));

vi.mock('@/components/ui/separator', () => ({
    Separator: basicWrapper('hr'),
}));

const baseFinalDefinition = Object.freeze({
    version: 3,
    fields: [
        { key: 'summary', label: 'Summary', value: 'Baseline throughput narrative', type: 'markdown' },
        { key: 'owner', label: 'Owner', value: 'RevOps', type: 'text' },
        { key: 'notes', label: 'Notes', value: 'Lock after Q1 sign-off', type: 'markdown' },
    ],
});

const baseChangeProposals = Object.freeze([
    {
        id: 'proposal-approved',
        status: 'Approved',
        rationale: 'Reassign owner to Product Ops after reorg.',
        opened_at: '2025-03-03T08:00:00Z',
        opened_by: { name: 'Jordan Lee', role: 'Consultant' },
        diff: [
            {
                field: 'owner',
                label: 'Owner',
                before: 'RevOps',
                after: 'Product Ops',
            },
        ],
        approvals: [
            {
                id: 'approval-1',
                name: 'Alex Approver',
                status: 'Approved',
                decided_at: '2025-03-04T10:00:00Z',
            },
        ],
        actions: {
            decision_required: false,
            can_decide: false,
            can_merge: true,
        },
    },
    {
        id: 'proposal-open',
        status: 'Open',
        rationale: 'Incorporate FY26 adjustments',
        opened_at: '2025-03-05T10:30:00Z',
        opened_by: { name: 'Casey Patel', role: 'Consultant' },
        diff: [
            {
                field: 'summary',
                label: 'Summary',
                before: 'Baseline throughput narrative',
                after: 'Add FY26 revenue alignment',
            },
        ],
        approvals: [
            {
                id: 'approval-2',
                name: 'Priya Approver',
                status: 'Pending',
            },
        ],
        actions: {
            decision_required: true,
            can_decide: true,
            can_merge: false,
        },
    },
    {
        id: 'proposal-merged',
        status: 'Merged',
        rationale: 'Backfilled Q4 retro tweaks',
        opened_at: '2025-02-15T18:30:00Z',
        opened_by: { name: 'Morgan Analyst', role: 'Consultant' },
        diff: [
            {
                field: 'notes',
                label: 'Notes',
                before: 'Lock after Q1 sign-off',
                after: 'Lock after Q2 board review',
            },
        ],
        approvals: [
            {
                id: 'approval-3',
                name: 'Dana Approver',
                status: 'Approved',
                decided_at: '2025-02-20T12:00:00Z',
            },
        ],
        actions: {
            decision_required: false,
            can_decide: false,
            can_merge: false,
        },
    },
]);

const baseStats = Object.freeze({
    open: 1,
    approved: 1,
    merged: 1,
    rejected: 0,
});

const basePermissions = Object.freeze({
    canOpen: true,
    canMerge: true,
});

const baseApproverOptions = Object.freeze([
    { id: 'approver-dana', name: 'Dana Approver', email: 'dana@example.test' },
    { id: 'approver-sam', name: 'Sam Stakeholder', email: 'sam@example.test' },
]);

type ChangeGovernancePageProps = ReturnType<typeof baseComponentProps>;

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value));
}

function baseComponentProps() {
    return {
        metric: {
            id: metricId,
            name: 'Net Revenue Retention',
            status: 'Finalized',
            slug: 'net-revenue-retention',
        },
        finalDefinition: clone(baseFinalDefinition),
        changeProposals: clone(baseChangeProposals),
        stats: { ...baseStats },
        permissions: { ...basePermissions },
        endpoints: { ...baseEndpoints },
        approverOptions: clone(baseApproverOptions),
    };
}

function resetPageContext() {
    routerPost.mockReset();
    routerPut.mockReset();
    routerDelete.mockReset();

    page.props = basePageContext();
    page.url = `/metrics/${metricId}/governance`;
}

if (moduleEntries.length === 0) {
    describe.skip(
        'Change proposal governance page (pending resources/js/pages/Metrics/ChangeGovernance.vue)',
        () => {
            it('awaits UI implementation from T053 before running assertions', () => {
                expect(true).toBe(true);
            });
        },
    );
} else {
    describe('Change proposal governance UI', () => {
        let ChangeGovernancePage: Component;
        const mountedWrappers: VueWrapper[] = [];

        beforeAll(async () => {
            const [, loader] = moduleEntries[0];
            const module = await loader();
            ChangeGovernancePage = module.default;
        });

        beforeEach(() => {
            resetPageContext();
        });

        afterEach(() => {
            mountedWrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        });

        function mountPage(
            overrides?: Partial<ChangeGovernancePageProps> | ((draft: ChangeGovernancePageProps) => ChangeGovernancePageProps),
        ) {
            const props = baseComponentProps();
            const resolvedProps =
                typeof overrides === 'function' ? overrides(props) : { ...props, ...overrides };

            const wrapper = mount(ChangeGovernancePage, {
                props: resolvedProps,
                attachTo: document.body,
            });

            mountedWrappers.push(wrapper);
            return wrapper;
        }

        it('renders metric context, stats, and a timeline sorted by opened_at', () => {
            const wrapper = mountPage();

            expect(
                wrapper.get('[data-test="governance-metric-title"]').text(),
            ).toContain('Net Revenue Retention');

            const statExpectations: Array<[string, string]> = [
                ['open', '1'],
                ['approved', '1'],
                ['merged', '1'],
                ['rejected', '0'],
            ];

            statExpectations.forEach(([key, expected]) => {
                const stat = wrapper.get(`[data-test="proposal-stat-${key}"]`);
                expect(stat.attributes('data-count')).toBe(expected);
            });

            const rows = wrapper.findAll('[data-test="proposal-row"]');
            expect(rows).toHaveLength(3);
            expect(rows.map((row) => row.attributes('data-id'))).toEqual([
                'proposal-open',
                'proposal-approved',
                'proposal-merged',
            ]);

            expect(
                rows[0].get('[data-test="proposal-status-badge"]').text(),
            ).toContain('Open');
        });

        it('requires rationale and payload deltas before submitting a new proposal', async () => {
            const wrapper = mountPage();

            await wrapper.get('[data-test="open-proposal-trigger"]').trigger('click');

            const submitButton = wrapper.get('[data-test="submit-proposal-button"]');
            expect(submitButton.attributes('disabled')).toBeDefined();

            await wrapper
                .get<HTMLTextAreaElement>('[data-test="proposal-rationale-input"]')
                .setValue('Need to incorporate FY26 adjustments.');
            await wrapper
                .get<HTMLInputElement>('[data-test="proposal-field-input"][data-field="summary"]')
                .setValue('Adjusted for FY26 targets');
            await wrapper
                .get<HTMLInputElement>('[data-test="proposal-field-input"][data-field="owner"]')
                .setValue('Product Ops');

            await nextTick();

            expect(submitButton.attributes('disabled')).toBeDefined();

            await wrapper.get<HTMLInputElement>('#approver-approver-dana').setValue(true);

            const summaryPreview = wrapper.get(
                '[data-test="proposal-change-preview"][data-field="summary"]',
            );
            expect(summaryPreview.text()).toContain('Adjusted for FY26 targets');

            expect(submitButton.attributes('disabled')).toBeUndefined();

            await submitButton.trigger('click');

            expect(routerPost).toHaveBeenCalledTimes(1);
            const [url, payload] = routerPost.mock.calls[0];

            expect(url).toBe(page.props.routes.changeProposals.store);
            expect(payload).toMatchObject({
                rationale: 'Need to incorporate FY26 adjustments.',
                payload: {
                    summary: { value: 'Adjusted for FY26 targets' },
                    owner: { value: 'Product Ops' },
                },
                approver_ids: ['approver-dana'],
            });

            await nextTick();
            expect(wrapper.find('[data-test="proposal-editor"]').exists()).toBe(false);
        });

        it('routes approver decisions and merge actions to the expected endpoints', async () => {
            const wrapper = mountPage();

            const openCard = wrapper.get(
                '[data-test="proposal-card"][data-id="proposal-open"]',
            );

            await openCard
                .get<HTMLTextAreaElement>('[data-test="proposal-decision-comment"]')
                .setValue('Ship it after review');
            await openCard.get('[data-test="proposal-approve"]').trigger('click');

            expect(routerPost).toHaveBeenCalledTimes(1);
            const [decisionUrl, decisionPayload] = routerPost.mock.calls[0];
            expect(decisionUrl).toBe(
                page.props.routes.changeProposals.decision.replace(proposalPlaceholder, 'proposal-open'),
            );
            expect(decisionPayload).toMatchObject({
                decision: 'approve',
                comment: 'Ship it after review',
            });

            routerPost.mockClear();

            const approvedCard = wrapper.get(
                '[data-test="proposal-card"][data-id="proposal-approved"]',
            );
            await approvedCard.get('[data-test="proposal-merge"]').trigger('click');

            expect(routerPost).toHaveBeenCalledTimes(1);
            const [mergeUrl] = routerPost.mock.calls[0];
            expect(mergeUrl).toBe(
                page.props.routes.changeProposals.merge.replace(proposalPlaceholder, 'proposal-approved'),
            );
        });

        it('hides proposal mutation controls when change proposals are disabled', () => {
            page.props.flags.change_proposals = false;

            const wrapper = mountPage();

            expect(wrapper.find('[data-test="change-proposals-disabled"]').exists()).toBe(true);
            expect(wrapper.find('[data-test="open-proposal-trigger"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="proposal-approve"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="proposal-merge"]').exists()).toBe(false);
        });

        it('hides proposal creation CTA when the viewer cannot open proposals', () => {
            const wrapper = mountPage((draft) => ({
                ...draft,
                permissions: {
                    ...draft.permissions,
                    canOpen: false,
                },
            }));

            expect(wrapper.find('[data-test="open-proposal-trigger"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="change-proposals-disabled"]').exists()).toBe(false);
        });
    });
}
