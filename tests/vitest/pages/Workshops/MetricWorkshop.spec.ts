import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, inject, nextTick, provide, ref, watch } from 'vue';

import MatrixAnswersView from '@/components/metrics/MatrixAnswersView.vue';
import MetricWorkshop from '@/pages/Workshops/MetricWorkshop.vue';

const routerPut = vi.hoisted(() => vi.fn());
const routerPost = vi.hoisted(() => vi.fn());
const routerPatch = vi.hoisted(() => vi.fn());
const routerVisit = vi.hoisted(() => vi.fn());
const toastSpy = vi.hoisted(() => vi.fn());
const fetchMock = vi.hoisted(() => vi.fn());
const pageProps = vi.hoisted(() => ({
    value: {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: false,
                can_operate_delivery: false,
            },
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

const tabsValueKey = Symbol('tabs-value');
const tabsSetValueKey = Symbol('tabs-set-value');

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

    return {
        Head,
        Link,
        router: {
            get: vi.fn(),
            post: routerPost,
            put: routerPut,
            patch: routerPatch,
            delete: vi.fn(),
            visit: routerVisit,
            reload: vi.fn(),
            remember: vi.fn(),
        },
        usePage: () => ({ props: pageProps.value }),
    };
});

vi.mock('@/composables/useWayfinder', () => {
    const update = (organizationId: string, metricId: string) => ({
        url: `/stub/organizations/${organizationId}/org-metrics/${metricId}/signals`,
    });

    Object.defineProperty(update, 'can', {
        get: () => Boolean(pageProps.value.auth?.user?.is_internal_staff),
    });

    return {
        useWayfinder: () => ({
            org: {
                orgMetrics: {
                    signals: {
                        update,
                    },
                },
            },
        }),
    };
});

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({ toast: toastSpy }),
}));

vi.mock('@/lib/csrf', () => ({
    resolveCsrfHeaders: () => ({}),
}));

vi.mock('@/routes/org', () => ({
    default: {
        index: () => ({ url: '/stub/orgs' }),
    },
    show: () => ({ url: '/stub/orgs/1' }),
}));

vi.mock('@/routes/org/engagements', () => ({
    default: {
        workshopSessions: {
            index: () => ({ url: '/stub/workshop-sessions' }),
            start: () => ({
                url: '/stub/workshop-sessions/session-1/start',
            }),
            complete: () => ({
                url: '/stub/workshop-sessions/session-1/complete',
            }),
        },
    },
    index: () => ({ url: '/stub/engagements' }),
    show: () => ({ url: '/stub/engagements/1' }),
}));

vi.mock('@/routes/org/metrics', () => ({
    signOff: () => ({ url: '/stub/metrics/sign-off' }),
}));

vi.mock('@/routes/org/metrics/final-definition', () => ({
    update: () => ({ url: '/stub/metrics/final-definition' }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: basicWrapper('div'),
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
            type: { type: String, default: 'button' },
            disabled: { type: Boolean, default: false },
        },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'button',
                    { type: props.type, disabled: props.disabled, ...attrs },
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

vi.mock('@/components/ui/tabs', () => ({
    Tabs: defineComponent({
        name: 'TabsStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: String,
                default: '',
            },
            defaultValue: {
                type: String,
                default: '',
            },
        },
        emits: ['update:modelValue'],
        setup(props, { slots, attrs, emit }) {
            const activeValue = ref(
                props.modelValue || props.defaultValue || '',
            );
            watch(
                () => props.modelValue,
                (value) => {
                    if (typeof value === 'string') {
                        activeValue.value = value;
                    }
                },
            );

            const updateValue = (value: string): void => {
                activeValue.value = value;
                emit('update:modelValue', value);
            };

            provide(tabsValueKey, activeValue);
            provide(tabsSetValueKey, updateValue);

            return () => h('div', attrs, slots.default ? slots.default() : []);
        },
    }),
    TabsList: basicWrapper('div'),
    TabsTrigger: defineComponent({
        name: 'TabsTriggerStub',
        inheritAttrs: false,
        props: {
            value: {
                type: String,
                required: true,
            },
        },
        setup(props, { slots, attrs }) {
            const activeValue = inject<ReturnType<typeof ref<string>>>(
                tabsValueKey,
                ref(''),
            );
            const updateValue = inject<(value: string) => void>(
                tabsSetValueKey,
                () => undefined,
            );

            return () =>
                h(
                    'button',
                    {
                        ...attrs,
                        type: 'button',
                        'data-state':
                            activeValue.value === props.value
                                ? 'active'
                                : 'inactive',
                        onClick: () => updateValue(props.value),
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
    TabsContent: defineComponent({
        name: 'TabsContentStub',
        inheritAttrs: false,
        props: {
            value: {
                type: String,
                required: true,
            },
        },
        setup(props, { slots, attrs }) {
            const activeValue = inject<ReturnType<typeof ref<string>>>(
                tabsValueKey,
                ref(''),
            );

            return () =>
                activeValue.value === props.value
                    ? h('div', attrs, slots.default ? slots.default() : [])
                    : null;
        },
    }),
}));

vi.mock('@/components/ui/collapsible', () => ({
    Collapsible: basicWrapper('div'),
    CollapsibleTrigger: basicWrapper('button'),
    CollapsibleContent: basicWrapper('div'),
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
        DialogClose: Wrapper,
    };
});

vi.mock('@/components/ui/input', () => ({
    Input: basicWrapper('input'),
}));

vi.mock('@/components/ui/label', () => ({
    Label: basicWrapper('label'),
}));

vi.mock('@/components/ui/select', () => ({
    Select: basicWrapper('div'),
    SelectContent: basicWrapper('div'),
    SelectItem: basicWrapper('div'),
    SelectTrigger: basicWrapper('button'),
    SelectValue: basicWrapper('span'),
}));

vi.mock('@/components/ui/tooltip', () => ({
    Tooltip: basicWrapper('div'),
    TooltipContent: basicWrapper('div'),
    TooltipProvider: basicWrapper('div'),
    TooltipTrigger: basicWrapper('div'),
}));

vi.mock('@/components/ui/textarea', () => ({
    Textarea: basicWrapper('textarea'),
}));

vi.mock('lucide-vue-next', () => ({
    Loader2: basicWrapper('span'),
}));

const baseProps = () => ({
    organization: { id: 'org-1', name: 'Org' },
    engagement: { id: 'eng-1', title: 'Engagement', status: 'InWorkshop' },
    metric: {
        id: 'metric-1',
        name: 'Metric',
        status: 'InWorkshop',
        engagement_id: 'eng-1',
        last_submitted_at: null,
        matrix: [
            {
                field: 'value',
                label: 'Metric Value',
                type: 'text',
                values: [],
                discrepancy: null,
            },
        ],
        context_fields: [],
        field_roles: [],
        unclassified: [],
        definitions: [],
    },
    finalDefinition: {
        id: 'final-1',
        status: 'Draft',
        definition_json: {
            value: { label: 'Metric Value', value: '' },
        },
        notes: null,
        alignment_summary: null,
        operational_status: null,
        operational_status_notes: null,
        implementation_gaps: null,
        validation_steps: null,
        common_mismatch_causes: null,
        owner_participant_id: null,
        owner_participant: null,
        signed_off_at: null,
        signed_off_by: null,
    },
    can: {
        update_final_definition: false,
        sign_off: false,
        manage_workshop_sessions: false,
        sign_off_message: null,
    },
    ownerOptions: [
        {
            id: 'owner-1',
            label: 'Owner',
            responsibility: 'Approver',
            is_default: true,
            disabled: false,
            user: { id: 'user-1', name: 'Owner' },
        },
    ],
    workshop: null,
    final_definition_requirements: {
        required_fields: [{ key: 'value', label: 'Metric Value' }],
        missing_keys: ['value'],
        owner_eligible: false,
    },
});

const mountPage = (
    overrides: Record<string, unknown> = {},
    options: { attachTo?: HTMLElement } = {},
) =>
    mount(MetricWorkshop as unknown as { props: Record<string, unknown> }, {
        ...options,
        props: {
            ...baseProps(),
            ...overrides,
        },
    });

beforeEach(() => {
    routerPut.mockReset();
    routerPost.mockReset();
    routerPatch.mockReset();
    routerVisit.mockReset();
    toastSpy.mockReset();
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: false,
                can_operate_delivery: false,
            },
        },
    };
});

const resolveOwnerParticipantId = (
    wrapper: ReturnType<typeof mountPage>,
): string => {
    const value = (wrapper.vm as { ownerParticipantId: unknown })
        .ownerParticipantId;

    if (typeof value === 'object' && value !== null && 'value' in value) {
        return (value as { value: string }).value;
    }

    return value as string;
};

const updateDraftAlignmentSummary = (
    wrapper: ReturnType<typeof mountPage>,
    value: string,
): void => {
    (wrapper.vm as { alignmentSummary: string }).alignmentSummary = value;
};

it('shows Edit draft only for editors', () => {
    const editorWrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
    });

    expect(editorWrapper.text()).toContain('Edit draft');

    const viewerWrapper = mountPage({
        can: { update_final_definition: false, sign_off: false },
    });

    expect(viewerWrapper.text()).not.toContain('Edit draft');
});

it('hides sign off for non-approvers', () => {
    const wrapper = mountPage({
        can: { update_final_definition: false, sign_off: false },
    });

    expect(wrapper.text()).not.toContain('Sign off');
});

it('renders title selector and switches to another workshop metric', () => {
    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
                {
                    id: 'metric-2',
                    name: 'Retention',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: { state: 'ready' },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    expect(wrapper.find('[data-test="metric-title-selector"]').exists()).toBe(
        true,
    );

    (
        wrapper.vm as {
            switchWorkshopMetric: (metricId: string | null) => void;
        }
    ).switchWorkshopMetric('metric-2');

    expect(routerVisit).toHaveBeenCalledWith('/stub/workshop?metric=metric-2', {
        preserveScroll: true,
    });
});

it('renders title selector metric status badges with outline style', () => {
    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
                {
                    id: 'metric-2',
                    name: 'Retention',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: { state: 'ready' },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    const statusBadges = wrapper.findAll(
        '[data-test="metric-title-option-status-badge"]',
    );
    expect(statusBadges.length).toBe(2);
    statusBadges.forEach((badge) =>
        expect(badge.attributes('data-variant')).toBe('outline'),
    );
});

it('shows title selector progress badge only for missing metrics', () => {
    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
                {
                    id: 'metric-2',
                    name: 'Retention',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: { state: 'ready' },
                },
                {
                    id: 'metric-3',
                    name: 'NPS',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: { state: 'signed_off' },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    const progressBadges = wrapper.findAll(
        '[data-test="metric-title-option-progress-badge"]',
    );
    expect(progressBadges.length).toBe(1);
    expect(progressBadges[0].text()).toContain('Missing 1');
    expect(wrapper.text()).not.toContain('Ready');
});

it('shows live indicator on the option whose metric is in progress', async () => {
    const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: vi.fn().mockResolvedValue({
            data: [
                {
                    id: 'session-2',
                    organization_id: 'org-1',
                    engagement_id: 'eng-1',
                    status: 'InProgress',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    timezone: 'UTC',
                    metric_ids: ['metric-2'],
                    participant_ids: [],
                },
            ],
        }),
    });

    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
                {
                    id: 'metric-2',
                    name: 'Retention',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 2,
                    },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    await flushPromises();

    const options = wrapper.findAll(
        '[data-test="metric-title-selector-option"]',
    );
    const currentMetricOption = options.find((option) =>
        option.text().includes('Metric'),
    );
    const inProgressMetricOption = options.find((option) =>
        option.text().includes('Retention'),
    );

    expect(currentMetricOption).toBeTruthy();
    expect(currentMetricOption!.text()).not.toContain('Workshop live');
    expect(inProgressMetricOption).toBeTruthy();
    expect(inProgressMetricOption!.text()).toContain('Workshop live');
    expect(
        wrapper.find('[data-test="metric-title-option-live-dot"]').exists(),
    ).toBe(true);
});

it('hides metric option live indicator when session is scheduled', async () => {
    const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
            ],
            recompute: null,
            session_context: {
                selected_session_id: 'session-1',
                sessions: [
                    {
                        id: 'session-1',
                        status: 'Scheduled',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            },
        },
    });

    await flushPromises();

    expect(
        wrapper
            .find('[data-test="metric-title-option-live-indicator"]')
            .exists(),
    ).toBe(false);
});

it('renders Unknown for title selector metric status when status is missing', () => {
    const wrapper = mountPage({
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: null,
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    const statusBadge = wrapper.find(
        '[data-test="metric-title-option-status-badge"]',
    );
    expect(statusBadge.exists()).toBe(true);
    expect(statusBadge.text()).toContain('Unknown');
});

it('does not navigate from title selector when target is invalid', () => {
    const wrapper = mountPage({
        workshop: {
            base_url: '',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    expect(wrapper.find('[data-test="metric-title-selector"]').exists()).toBe(
        false,
    );

    const viewModel = wrapper.vm as {
        switchWorkshopMetric: (metricId: string | null) => void;
    };
    viewModel.switchWorkshopMetric('metric-1');
    viewModel.switchWorkshopMetric('metric-2');
    viewModel.switchWorkshopMetric(null);

    expect(routerVisit).not.toHaveBeenCalled();
});

it('renders the matrix answers view by default', () => {
    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });

    expect(wrapper.find('[data-test="answers-view-compare"]').exists()).toBe(
        true,
    );
    expect(
        wrapper.find('[data-test="answers-view-compare-table"]').exists(),
    ).toBe(false);
    expect(
        wrapper
            .find('[data-test="answers-view-compare-empty-contributors"]')
            .exists(),
    ).toBe(true);
});

it('supports compare definition response interactions and draft handoff', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        metric: {
            ...baseProps().metric,
            matrix: [
                {
                    field: 'first',
                    label: 'First question',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'first_answer',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                        {
                            definition_id: 'def-2',
                            raw: 'alternative_answer',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    discrepancy: null,
                },
            ],
            context_fields: [
                {
                    field: 'second',
                    label: 'Second question',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-2',
                            raw: 'second_answer',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    options: null,
                },
            ],
            field_roles: [
                {
                    key: 'first',
                    label: 'First question',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'second',
                    label: 'Second question',
                    role: 'context',
                    exclude_from_consensus: true,
                    value: null,
                    unclassified: false,
                },
            ],
        },
        finalDefinition: {
            ...baseProps().finalDefinition,
            definition_json: {
                first: {
                    label: 'First question',
                    value: 'First Answer',
                },
            },
        },
    });

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );

    const compareRows = wrapper.findAll(
        '[data-test="answers-view-compare-row"]',
    );
    expect(compareRows.length).toBe(2);
    expect(compareRows[0].attributes('data-role')).toBe('definition');
    expect(compareRows[0].attributes('data-different')).toBe('true');
    expect(compareRows[1].attributes('data-role')).toBe('context');
    expect(compareRows[1].attributes('data-different')).toBe('true');

    const definitionAliceResponse = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:nth-child(2)',
    );
    const definitionBobResponse = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:nth-child(3)',
    );
    const contextBobResponse = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="context"] td:nth-child(3)',
    );

    expect(definitionAliceResponse.attributes('data-saved-match')).toBe('true');
    expect(definitionAliceResponse.classes()).toContain(
        'border-emerald-200/70',
    );
    expect(definitionAliceResponse.classes()).not.toContain('border-l-4');

    expect(definitionBobResponse.attributes('data-saved-match')).toBe('false');
    expect(
        compareRows[0]
            .find('[data-test="answers-view-difference-indicator"]')
            .exists(),
    ).toBe(true);
    expect(definitionBobResponse.classes()).not.toContain('border-l-4');
    expect(definitionBobResponse.attributes('data-selectable')).toBe('true');

    expect(contextBobResponse.attributes('data-selectable')).toBe('false');
    expect(contextBobResponse.classes()).toContain('text-muted-foreground');
    expect(contextBobResponse.classes()).not.toContain('border-l-4');

    await definitionBobResponse.trigger('click');
    expect(definitionBobResponse.attributes('data-selected')).toBe('true');
    expect(definitionBobResponse.classes()).toContain('border-amber-400');
    expect(definitionBobResponse.classes()).toContain('bg-amber-50/60');
    expect(definitionBobResponse.classes()).toContain('ring-2');
    expect(definitionBobResponse.classes()).not.toContain('border-l-4');

    await contextBobResponse.trigger('dblclick');
    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );

    await definitionAliceResponse.trigger('dblclick');
    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        true,
    );
    expect(
        (
            wrapper.vm as {
                editableRows: Array<{ field: string; value: string }>;
            }
        ).editableRows.find((row) => row.field === 'first')?.value,
    ).toBe('First Answer');
    expect(routerPut).not.toHaveBeenCalled();
});

it('renders saved matches for checkbox-group final definition values', () => {
    const wrapper = mountPage({
        metric: {
            ...baseProps().metric,
            matrix: [
                {
                    field: 'costs_included',
                    label: 'CAC/CPL cost components included',
                    type: 'checkbox_group',
                    options: {
                        ad_spend: 'Direct advertising spend',
                        marketing_comp: 'Marketing salaries / agency fees',
                        sales_comp: 'Sales salaries / commissions',
                        overhead: 'Overhead',
                        direct_campaign_only: 'Only direct campaign costs',
                    },
                    values: [
                        {
                            definition_id: 'def-finance',
                            raw: [
                                'ad_spend',
                                'marketing_comp',
                                'sales_comp',
                                'overhead',
                            ],
                            other_text: null,
                            user: { id: 'user-finance', name: 'Finance' },
                            submitted_at: null,
                        },
                        {
                            definition_id: 'def-marketing',
                            raw: ['ad_spend', 'direct_campaign_only'],
                            other_text: null,
                            user: { id: 'user-marketing', name: 'Marketing' },
                            submitted_at: null,
                        },
                    ],
                    discrepancy: null,
                },
            ],
            context_fields: [],
            field_roles: [
                {
                    key: 'costs_included',
                    label: 'CAC/CPL cost components included',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
            ],
        },
        finalDefinition: {
            ...baseProps().finalDefinition,
            definition_json: {
                costs_included: {
                    label: 'CAC/CPL cost components included',
                    value: [
                        'ad_spend',
                        'marketing_comp',
                        'sales_comp',
                        'overhead',
                    ],
                },
            },
        },
        final_definition_requirements: {
            required_fields: [
                {
                    key: 'costs_included',
                    label: 'CAC/CPL cost components included',
                },
            ],
            missing_keys: [],
            owner_eligible: true,
        },
    });

    const financeResponse = wrapper.get(
        '[data-test="answers-view-compare-row"] td:nth-child(2)',
    );
    const marketingResponse = wrapper.get(
        '[data-test="answers-view-compare-row"] td:nth-child(3)',
    );

    expect(wrapper.text()).toContain('Direct advertising spend');
    expect(financeResponse.attributes('data-saved-match')).toBe('true');
    expect(marketingResponse.attributes('data-saved-match')).toBe('false');
});

it('renders compare table and highlights differing rows', async () => {
    const wrapper = mountPage({
        metric: {
            ...baseProps().metric,
            matrix: [
                {
                    field: 'value',
                    label: 'Metric Value',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'north_america',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                        {
                            definition_id: 'def-2',
                            raw: 'europe',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    discrepancy: null,
                },
            ],
            context_fields: [
                {
                    field: 'timezone',
                    label: 'Team timezone',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'utc',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                        {
                            definition_id: 'def-2',
                            raw: 'utc',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    options: null,
                },
            ],
            field_roles: [
                {
                    key: 'value',
                    label: 'Metric Value',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'timezone',
                    label: 'Team timezone',
                    role: 'context',
                    exclude_from_consensus: true,
                    value: null,
                    unclassified: false,
                },
            ],
        },
        finalDefinition: {
            ...baseProps().finalDefinition,
            definition_json: {
                value: {
                    label: 'Metric Value',
                    value: 'North America',
                },
            },
        },
    });

    expect(
        wrapper.find('[data-test="answers-view-compare-table"]').exists(),
    ).toBe(true);
    expect(wrapper.text()).toContain('Alice');
    expect(wrapper.text()).toContain('Bob');
    expect(wrapper.text()).toContain('North America');
    expect(wrapper.text()).toContain('europe');
    expect(
        wrapper
            .find(
                '[data-test="answers-view-compare-row"][data-role="definition"]',
            )
            .exists(),
    ).toBe(true);
    expect(
        wrapper
            .find('[data-test="answers-view-compare-row"][data-role="context"]')
            .exists(),
    ).toBe(true);

    const definitionQuestionCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:first-child',
    );
    const contextQuestionCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="context"] td:first-child',
    );
    const definitionAnswerCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:nth-child(2)',
    );
    const definitionSecondAnswerCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:nth-child(3)',
    );
    const contextAnswerCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="context"] td:nth-child(2)',
    );

    expect(definitionQuestionCell.classes()).toContain('font-bold');
    expect(definitionQuestionCell.classes()).toContain('border-b-2');
    expect(definitionAnswerCell.classes()).toContain('border-b-2');
    expect(contextQuestionCell.classes()).toContain('font-normal');
    expect(contextQuestionCell.classes()).toContain('text-muted-foreground');
    expect(contextQuestionCell.classes()).not.toContain('font-bold');
    expect(contextQuestionCell.classes()).not.toContain('border-b-2');
    expect(contextAnswerCell.classes()).toContain('text-muted-foreground');
    expect(definitionAnswerCell.attributes('data-saved-match')).toBe('true');
    expect(definitionAnswerCell.classes()).toContain('border-emerald-200/70');
    expect(definitionAnswerCell.classes()).toContain('bg-emerald-50/90');
    expect(definitionSecondAnswerCell.attributes('data-saved-match')).toBe(
        'false',
    );
    expect(definitionAnswerCell.classes()).not.toContain('border-l-4');
    expect(definitionSecondAnswerCell.classes()).not.toContain('border-l-4');
    expect(
        wrapper
            .find(
                '[data-test="answers-view-compare-row"][data-role="definition"] [data-test="answers-view-difference-indicator"]',
            )
            .exists(),
    ).toBe(true);
    expect(contextAnswerCell.classes()).not.toContain('border-l-4');
    expect(definitionAnswerCell.attributes('data-selectable')).toBe('true');
    expect(definitionAnswerCell.attributes('data-selected')).toBe('false');
    expect(contextAnswerCell.attributes('data-selectable')).toBe('false');
    expect(contextAnswerCell.attributes('data-selected')).toBe('false');

    await definitionAnswerCell.trigger('click');
    expect(definitionAnswerCell.attributes('data-selected')).toBe('true');
    expect(definitionAnswerCell.classes()).toContain('border-amber-400');
    expect(definitionAnswerCell.classes()).toContain('bg-amber-50/60');
    expect(definitionAnswerCell.classes()).toContain('ring-2');
    expect(definitionAnswerCell.classes()).toContain('ring-amber-400');
    expect(definitionAnswerCell.classes()).toContain('border-b-2');
    expect(definitionAnswerCell.classes()).not.toContain('border-l-4');

    await definitionAnswerCell.trigger('click');
    expect(definitionAnswerCell.attributes('data-selected')).toBe('true');
    expect(definitionAnswerCell.classes()).toContain('border-amber-400');
    expect(definitionAnswerCell.classes()).toContain('bg-amber-50/60');
    expect(definitionAnswerCell.classes()).toContain('ring-2');
    expect(definitionAnswerCell.classes()).toContain('border-b-2');
    expect(definitionAnswerCell.classes()).not.toContain('border-l-4');

    await definitionSecondAnswerCell.trigger('click');
    expect(definitionSecondAnswerCell.attributes('data-selected')).toBe('true');
    expect(definitionAnswerCell.attributes('data-selected')).toBe('false');
    expect(definitionSecondAnswerCell.classes()).toContain('border-b-2');
    expect(definitionSecondAnswerCell.classes()).not.toContain('border-l-4');

    await contextAnswerCell.trigger('click');
    expect(contextAnswerCell.attributes('data-selected')).toBe('false');
    expect(contextAnswerCell.classes()).not.toContain('border-amber-400');
    expect(contextAnswerCell.classes()).not.toContain('bg-amber-50/60');
    expect(
        wrapper.findAll(
            '[data-test="answers-view-compare-row"][data-different="true"]',
        ).length,
    ).toBe(1);
});

it('keeps validation guidance out of the comparison matrix', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        metric: {
            ...baseProps().metric,
            matrix: [
                {
                    field: 'value',
                    label: 'Metric Value',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'north_america',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                        {
                            definition_id: 'def-2',
                            raw: 'north_america',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    discrepancy: null,
                },
            ],
            context_fields: [
                {
                    field: 'source_system',
                    label: 'Source System',
                    type: 'text',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'warehouse',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                    ],
                    options: null,
                },
                {
                    field: 'validation_steps',
                    label: 'Validation Steps',
                    type: 'textarea',
                    values: [
                        {
                            definition_id: 'def-1',
                            raw: 'Compare workbook totals.',
                            other_text: null,
                            user: { id: 'user-1', name: 'Alice' },
                            submitted_at: null,
                        },
                    ],
                    options: null,
                },
                {
                    field: 'common_mismatch_causes',
                    label: 'Common Mismatch Causes',
                    type: 'textarea',
                    values: [
                        {
                            definition_id: 'def-2',
                            raw: 'Dashboard filters drift.',
                            other_text: null,
                            user: { id: 'user-2', name: 'Bob' },
                            submitted_at: null,
                        },
                    ],
                    options: null,
                },
            ],
            field_roles: [
                {
                    key: 'value',
                    label: 'Metric Value',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'source_system',
                    label: 'Source System',
                    role: 'context',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'validation_steps',
                    label: 'Validation Steps',
                    role: 'governance',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'common_mismatch_causes',
                    label: 'Common Mismatch Causes',
                    role: 'diagnostic',
                    value: null,
                    unclassified: false,
                },
            ],
            unclassified: [
                'source_system',
                'validation_steps',
                'common_mismatch_causes',
            ],
        },
        finalDefinition: {
            ...baseProps().finalDefinition,
            validation_steps: 'Compare workbook totals.',
            common_mismatch_causes: 'Dashboard filters drift.',
            definition_json: {
                value: { label: 'Metric Value', value: 'North America' },
                validation_steps: {
                    label: 'Validation Steps',
                    value: 'Compare workbook totals.',
                },
                common_mismatch_causes: {
                    label: 'Common Mismatch Causes',
                    value: 'Dashboard filters drift.',
                },
            },
        },
    });

    const answersView = wrapper.get('[data-test="answers-view-compare"]');

    expect(answersView.text()).toContain('Metric Value');
    expect(answersView.text()).toContain('Source System');
    expect(answersView.text()).not.toContain('Validation Steps');
    expect(answersView.text()).not.toContain('Common Mismatch Causes');
    expect(
        wrapper.findAll('[data-test="answers-view-compare-row"]'),
    ).toHaveLength(2);

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');
    await flushPromises();

    const definitionLabels = wrapper
        .findAll(
            '[data-test="final-definition-form"] label[for^="definition-"]',
        )
        .map((label) => label.text());

    expect(definitionLabels).toEqual(['Metric Value', 'Source System']);
});

it('enters edit mode and populates a final-definition field on definition-cell double click', async () => {
    const host = document.createElement('div');
    document.body.appendChild(host);

    const wrapper = mountPage(
        {
            can: { update_final_definition: true, sign_off: false },
            metric: {
                ...baseProps().metric,
                matrix: [
                    {
                        field: 'value',
                        label: 'Metric Value',
                        type: 'text',
                        values: [
                            {
                                definition_id: 'def-1',
                                raw: 'north_america',
                                other_text: null,
                                user: { id: 'user-1', name: 'Alice' },
                                submitted_at: null,
                            },
                            {
                                definition_id: 'def-2',
                                raw: 'europe',
                                other_text: null,
                                user: { id: 'user-2', name: 'Bob' },
                                submitted_at: null,
                            },
                        ],
                        discrepancy: null,
                    },
                ],
                context_fields: [
                    {
                        field: 'timezone',
                        label: 'Team timezone',
                        type: 'text',
                        values: [
                            {
                                definition_id: 'def-1',
                                raw: 'utc',
                                other_text: null,
                                user: { id: 'user-1', name: 'Alice' },
                                submitted_at: null,
                            },
                            {
                                definition_id: 'def-2',
                                raw: 'utc',
                                other_text: null,
                                user: { id: 'user-2', name: 'Bob' },
                                submitted_at: null,
                            },
                        ],
                        options: null,
                    },
                ],
                field_roles: [
                    {
                        key: 'value',
                        label: 'Metric Value',
                        role: 'definition',
                        value: null,
                        unclassified: false,
                    },
                    {
                        key: 'timezone',
                        label: 'Team timezone',
                        role: 'context',
                        exclude_from_consensus: true,
                        value: null,
                        unclassified: false,
                    },
                ],
            },
        },
        { attachTo: host },
    );

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );

    const contextAnswerCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="context"] td:nth-child(2)',
    );
    await contextAnswerCell.trigger('dblclick');

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );
    expect(document.activeElement?.id).not.toBe('definition-value');

    const definitionAnswerCell = wrapper.get(
        '[data-test="answers-view-compare-row"][data-role="definition"] td:nth-child(2)',
    );
    await definitionAnswerCell.trigger('dblclick');
    await nextTick();
    await nextTick();

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        true,
    );
    expect(
        (
            wrapper.vm as {
                editableRows: Array<{ field: string; value: string }>;
            }
        ).editableRows.find((row) => row.field === 'value')?.value,
    ).toBe('North America');
    expect(document.activeElement?.id).toBe('definition-value');
    expect(routerPut).not.toHaveBeenCalled();

    wrapper.unmount();
    host.remove();
});

it('shows empty compare state when contributors have no responses', () => {
    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });

    expect(
        wrapper
            .find('[data-test="answers-view-compare-empty-contributors"]')
            .exists(),
    ).toBe(true);
    expect(
        wrapper.find('[data-test="answers-view-compare-table"]').exists(),
    ).toBe(false);
});

it('shows empty compare state when there are no answer rows', () => {
    const wrapper = mountPage({
        metric: {
            ...baseProps().metric,
            matrix: [],
            context_fields: [],
            field_roles: [],
        },
    });

    expect(
        wrapper
            .find('[data-test="answers-view-compare-empty-fields"]')
            .exists(),
    ).toBe(true);
    expect(
        wrapper.find('[data-test="answers-view-compare-table"]').exists(),
    ).toBe(false);
});

it('lists missing fields and disables sign off when incomplete', () => {
    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: true,
            sign_off_message:
                'Complete required fields (Metric Value) and assign a metric owner before approving sign-off.',
        },
        final_definition_requirements: {
            required_fields: [{ key: 'value', label: 'Metric Value' }],
            missing_keys: ['value'],
            owner_eligible: false,
        },
    });

    expect(wrapper.text()).toContain('Metric Value');
    expect(wrapper.text()).not.toContain('Draft incomplete');
    expect(
        wrapper.find('[data-test="final-definition-owner-missing"]').exists(),
    ).toBe(true);
    expect(
        wrapper
            .find('[data-test="final-definition-field-missing-value"]')
            .exists(),
    ).toBe(true);
    const signOffButton = wrapper.find(
        '[data-test="final-definition-signoff"]',
    );
    expect(signOffButton.exists()).toBe(true);
    expect(signOffButton.attributes('disabled')).toBeDefined();
    expect(
        wrapper.find('[data-test="final-definition-signoff-message"]').text(),
    ).toContain(
        'Complete required fields (Metric Value) and assign a metric owner before approving sign-off.',
    );
});

it('switches between summary and edit modes', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
    });

    expect(
        wrapper.find('[data-test="final-definition-summary"]').exists(),
    ).toBe(true);
    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        true,
    );
});

it('submits alignment summary on draft save', async () => {
    routerPut.mockImplementationOnce((_url, _payload, options) => {
        options?.onSuccess?.();
        options?.onFinish?.();
    });

    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        finalDefinition: {
            ...baseProps().finalDefinition,
            owner_participant_id: 'owner-1',
        },
    });

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');
    expect(wrapper.find('[data-test="alignment-summary-input"]').exists()).toBe(
        true,
    );

    updateDraftAlignmentSummary(
        wrapper,
        'Finance and product aligned on contracted ARR for the board pack.',
    );
    await flushPromises();

    await wrapper.get('[data-test="final-definition-form"]').trigger('submit');
    await flushPromises();

    expect(routerPut).toHaveBeenCalledTimes(1);
    expect(routerPut.mock.calls[0]?.[0]).toEqual({
        url: '/stub/metrics/final-definition',
    });
    expect(routerPut.mock.calls[0]?.[1]).toEqual(
        expect.objectContaining({
            alignment_summary:
                'Finance and product aligned on contracted ARR for the board pack.',
            owner_participant_id: 'owner-1',
        }),
    );
    expect(toastSpy).toHaveBeenCalledWith('success', 'Saved successfully.');
    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );
});

it('restores consultant draft alignment summary when canceling edits', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        finalDefinition: {
            ...baseProps().finalDefinition,
            alignment_summary: 'Saved alignment summary',
        },
    });

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    updateDraftAlignmentSummary(wrapper, 'Unsaved alignment summary');
    await flushPromises();

    await wrapper.get('[data-test="final-definition-cancel"]').trigger('click');

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        false,
    );
    expect((wrapper.vm as { alignmentSummary: string }).alignmentSummary).toBe(
        'Saved alignment summary',
    );
});

it('renders the selected view-mode style and hides it in edit mode', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
    });

    expect(
        wrapper.find('[data-test="final-definition-variant-a"]').exists(),
    ).toBe(false);
    expect(
        wrapper.find('[data-test="final-definition-variant-b"]').exists(),
    ).toBe(false);
    expect(wrapper.find('[data-test="final-definition"]').exists()).toBe(true);

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    expect(wrapper.find('[data-test="final-definition"]').exists()).toBe(false);
});

it('does not default-select a metric owner when none is set', () => {
    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });

    expect(resolveOwnerParticipantId(wrapper)).toBe('');
});

it('keeps the assigned metric owner selected when provided', () => {
    const wrapper = mountPage({
        finalDefinition: {
            ...baseProps().finalDefinition,
            owner_participant_id: 'owner-1',
        },
    });

    expect(resolveOwnerParticipantId(wrapper)).toBe('owner-1');
});

it('shows owner affiliation when provided', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        ownerOptions: [
            {
                id: 'owner-1',
                label: 'Owner',
                responsibility: 'Approver',
                affiliation: 'Finance',
                is_default: true,
                disabled: false,
                user: { id: 'user-1', name: 'Owner' },
            },
        ],
    });

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    expect(wrapper.text()).toContain('Finance');
});

it('renders selected owner details in the trigger', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        finalDefinition: {
            ...baseProps().finalDefinition,
            owner_participant_id: 'owner-1',
        },
        ownerOptions: [
            {
                id: 'owner-1',
                label: 'Finance Acme',
                responsibility: 'Approver',
                affiliation: 'Finance',
                is_default: false,
                disabled: false,
                user: { id: 'user-1', name: 'Finance Acme' },
            },
        ],
    });

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    const trigger = wrapper.get('#owner-participant');
    expect(trigger.text()).toContain('Finance Acme');
    expect(trigger.text()).toContain('Approver');
    expect(trigger.text()).toContain('Finance');
});

it('omits owner details when none are available', async () => {
    const wrapper = mountPage({
        can: { update_final_definition: true, sign_off: false },
        finalDefinition: {
            ...baseProps().finalDefinition,
            owner_participant_id: 'owner-1',
        },
        ownerOptions: [
            {
                id: 'owner-1',
                label: 'Owner Only',
                responsibility: '',
                affiliation: null,
                is_default: false,
                disabled: false,
                user: { id: 'user-1', name: 'Owner Only' },
            },
        ],
    });

    await wrapper.get('[data-test="final-definition-edit"]').trigger('click');

    const trigger = wrapper.get('#owner-participant');
    expect(trigger.text()).toBe('Owner Only');
});

it('renders the sign-off confirmation copy without discrepancies', () => {
    const wrapper = mountPage({
        can: { update_final_definition: false, sign_off: true },
    });

    expect(wrapper.text()).toContain('Sign off this metric?');
    expect(wrapper.text()).toContain(
        'This finalizes the definition and locks further edits in this workshop.',
    );
});

it('shows the workshop closed banner for clients', () => {
    const wrapper = mountPage({
        workshop_closed: true,
    });

    expect(wrapper.text()).toContain('Workshop closed');
    expect(wrapper.text()).toContain('Open handbook');
});

it('updates the workshop banner immediately when workshop_closed prop flips true', async () => {
    const wrapper = mountPage({
        workshop_closed: false,
    });

    expect(wrapper.text()).not.toContain('Workshop closed');

    await wrapper.setProps({
        workshop_closed: true,
    });

    expect(wrapper.text()).toContain('Workshop closed');
});

it('derives workshop banner visibility from engagement status updates', async () => {
    const wrapper = mountPage({
        workshop_closed: undefined,
        engagement: {
            id: 'eng-1',
            title: 'Engagement',
            status: 'InWorkshop',
        },
    });

    expect(wrapper.text()).not.toContain('Workshop closed');

    await wrapper.setProps({
        workshop_closed: undefined,
        engagement: {
            id: 'eng-1',
            title: 'Engagement',
            status: 'Finalized',
        },
    });

    expect(wrapper.text()).toContain('Workshop closed');
});

it('hides the workshop closed banner for staff', () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const wrapper = mountPage({
        workshop_closed: true,
    });

    expect(wrapper.text()).not.toContain('Workshop closed');
});

it('shows scheduled workshop time without a session badge for scheduled staff sessions', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: vi.fn().mockResolvedValue({
            data: [
                {
                    id: 'session-1',
                    organization_id: 'org-1',
                    engagement_id: 'eng-1',
                    status: 'Scheduled',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    timezone: 'UTC',
                    metric_ids: ['metric-1'],
                    participant_ids: [],
                },
            ],
        }),
    });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    expect(
        wrapper.find('[data-test="workshop-session-scheduled-time"]').exists(),
    ).toBe(true);
    expect(
        wrapper.find('[data-test="workshop-session-status-badge"]').exists(),
    ).toBe(false);
    expect(
        wrapper.find('[data-test="workshop-session-status-live-dot"]').exists(),
    ).toBe(false);
});

it('hides scheduled workshop time when active session is in progress', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: vi.fn().mockResolvedValue({
            data: [
                {
                    id: 'session-1',
                    organization_id: 'org-1',
                    engagement_id: 'eng-1',
                    status: 'InProgress',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    timezone: 'UTC',
                    metric_ids: ['metric-1'],
                    participant_ids: [],
                },
            ],
        }),
    });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    expect(
        wrapper.find('[data-test="workshop-session-scheduled-time"]').exists(),
    ).toBe(false);
    expect(
        wrapper.find('[data-test="workshop-session-status-text"]').text(),
    ).toContain('Workshop live');
    expect(
        wrapper.find('[data-test="workshop-session-status-live-dot"]').exists(),
    ).toBe(true);
    expect(wrapper.find('[data-test="workshop-session-finish"]').exists()).toBe(
        true,
    );
    expect(
        wrapper.find('[data-test="workshop-session-status-badge"]').exists(),
    ).toBe(false);
});

it('does not show start workshop action to non-staff users', async () => {
    const startsAt = new Date(Date.now() + 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 65 * 60_000).toISOString();

    const wrapper = mountPage({
        workshop_closed: true,
        workshop: {
            base_url: '/stub/workshop',
            metrics: [],
            recompute: null,
            session_context: {
                selected_session_id: 'session-1',
                sessions: [
                    {
                        id: 'session-1',
                        status: 'Scheduled',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            },
        },
    });

    await flushPromises();

    expect(wrapper.find('[data-test="workshop-session-start"]').exists()).toBe(
        false,
    );
    expect(wrapper.find('[data-test="workshop-session-finish"]').exists()).toBe(
        false,
    );
});

it('shows non-staff dropdown live indicator for metrics with in-progress sessions', async () => {
    const startsAt = new Date(Date.now() - 5 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 55 * 60_000).toISOString();

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: vi.fn().mockResolvedValue({
            data: [
                {
                    id: 'session-2',
                    organization_id: 'org-1',
                    engagement_id: 'eng-1',
                    status: 'InProgress',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    timezone: 'UTC',
                    metric_ids: ['metric-2'],
                    participant_ids: [],
                },
            ],
        }),
    });

    const wrapper = mountPage({
        workshop_closed: false,
        workshop: {
            base_url: '/stub/workshop',
            metrics: [
                {
                    id: 'metric-1',
                    name: 'Metric',
                    status: 'InWorkshop',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 1,
                    },
                },
                {
                    id: 'metric-2',
                    name: 'Retention',
                    status: 'InReview',
                    last_submitted_at: null,
                    final_definition_progress: {
                        state: 'missing',
                        missing_count: 2,
                    },
                },
            ],
            recompute: null,
            session_context: { selected_session_id: null, sessions: [] },
        },
    });

    await flushPromises();

    expect(
        wrapper.find('[data-test="workshop-session-status-text"]').exists(),
    ).toBe(false);

    const options = wrapper.findAll(
        '[data-test="metric-title-selector-option"]',
    );
    const selectedOption = options.find((option) =>
        option.text().includes('Metric'),
    );
    const otherOption = options.find((option) =>
        option.text().includes('Retention'),
    );

    expect(selectedOption).toBeTruthy();
    expect(selectedOption!.text()).not.toContain('Workshop live');
    expect(otherOption).toBeTruthy();
    expect(otherOption!.text()).toContain('Workshop live');
});

it('completes an in-progress workshop session from the metrics header', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() - 30 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 30 * 60_000).toISOString();
    const completedEndsAt = new Date(Date.now() - 5 * 60_000).toISOString();

    fetchMock
        .mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: vi.fn().mockResolvedValue({
                data: [
                    {
                        id: 'session-1',
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'InProgress',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            }),
        })
        .mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: vi.fn().mockResolvedValue({
                data: { id: 'session-1', status: 'Completed' },
            }),
        })
        .mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: vi.fn().mockResolvedValue({
                data: [
                    {
                        id: 'session-1',
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'Completed',
                        starts_at: startsAt,
                        ends_at: completedEndsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            }),
        });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    const finishButton = wrapper.find('[data-test="workshop-session-finish"]');
    expect(finishButton.exists()).toBe(true);

    await finishButton.trigger('click');
    await flushPromises();

    await wrapper
        .get('[data-test="workshop-session-finish-confirm"]')
        .trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(
        2,
        '/stub/workshop-sessions/session-1/complete',
        expect.objectContaining({
            method: 'POST',
        }),
    );
    expect(toastSpy).toHaveBeenCalledWith(
        'success',
        'Workshop session completed.',
    );
    expect(wrapper.find('[data-test="workshop-session-finish"]').exists()).toBe(
        false,
    );
    expect(
        wrapper.find('[data-test="workshop-session-last-ended"]').exists(),
    ).toBe(true);
});

it('shows an error toast when completing workshop session fails', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() - 30 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 30 * 60_000).toISOString();

    fetchMock
        .mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: vi.fn().mockResolvedValue({
                data: [
                    {
                        id: 'session-1',
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'InProgress',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            }),
        })
        .mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: vi.fn().mockResolvedValue({
                message: 'Unable to complete workshop session (500)',
            }),
        });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    await wrapper.get('[data-test="workshop-session-finish"]').trigger('click');
    await flushPromises();
    await wrapper
        .get('[data-test="workshop-session-finish-confirm"]')
        .trigger('click');
    await flushPromises();

    expect(toastSpy).toHaveBeenCalledWith(
        'error',
        'Unable to complete workshop session (500)',
    );
    expect(wrapper.find('[data-test="workshop-session-finish"]').exists()).toBe(
        true,
    );
});

it('starts scheduled workshop sessions from the workshop page within the start window', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

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
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'Scheduled',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
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
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'InProgress',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            }),
        });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    const startButton = wrapper.find('[data-test="workshop-session-start"]');
    expect(startButton.exists()).toBe(true);
    expect(startButton.attributes('disabled')).toBeUndefined();

    await startButton.trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledWith(
        '/stub/workshop-sessions/session-1/start',
        expect.objectContaining({
            method: 'POST',
        }),
    );
    expect(toastSpy).toHaveBeenCalledWith(
        'success',
        'Workshop session started.',
    );
});

it('keeps workshop session start disabled outside the start window', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: false,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() + 30 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 90 * 60_000).toISOString();

    fetchMock.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: vi.fn().mockResolvedValue({
            data: [
                {
                    id: 'session-1',
                    organization_id: 'org-1',
                    engagement_id: 'eng-1',
                    status: 'Scheduled',
                    starts_at: startsAt,
                    ends_at: endsAt,
                    timezone: 'UTC',
                    metric_ids: ['metric-1'],
                    participant_ids: [],
                },
            ],
        }),
    });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    const startButton = wrapper.find('[data-test="workshop-session-start"]');
    expect(startButton.exists()).toBe(true);
    expect(startButton.attributes('disabled')).toBeDefined();
    expect(wrapper.text()).not.toContain('Click to force start');
});

it('requires force-start confirmation outside the start window and sends force_start payload', async () => {
    pageProps.value = {
        flags: {
            workshop_discrepancies: false,
            workshop_force_start_enabled: true,
        },
        auth: {
            user: {
                is_internal_staff: true,
                can_operate_delivery: true,
            },
        },
    };

    const startsAt = new Date(Date.now() + 30 * 60_000).toISOString();
    const endsAt = new Date(Date.now() + 90 * 60_000).toISOString();

    fetchMock
        .mockResolvedValueOnce({
            ok: true,
            status: 200,
            json: vi.fn().mockResolvedValue({
                data: [
                    {
                        id: 'session-1',
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'Scheduled',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
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
                        organization_id: 'org-1',
                        engagement_id: 'eng-1',
                        status: 'InProgress',
                        starts_at: startsAt,
                        ends_at: endsAt,
                        timezone: 'UTC',
                        metric_ids: ['metric-1'],
                        participant_ids: [],
                    },
                ],
            }),
        });

    const wrapper = mountPage({
        can: {
            update_final_definition: true,
            sign_off: false,
            manage_workshop_sessions: true,
        },
    });
    await flushPromises();

    const startButton = wrapper.find('[data-test="workshop-session-start"]');
    expect(startButton.exists()).toBe(true);
    expect(startButton.attributes('disabled')).toBeUndefined();
    expect(startButton.attributes('aria-disabled')).toBe('true');
    expect(wrapper.text()).toContain('Click to force start');

    await startButton.trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const confirmButton = wrapper.find(
        '[data-test="workshop-session-force-confirm"]',
    );
    expect(confirmButton.exists()).toBe(true);

    await confirmButton.trigger('click');
    await flushPromises();

    expect(fetchMock).toHaveBeenNthCalledWith(
        2,
        '/stub/workshop-sessions/session-1/start',
        expect.objectContaining({
            method: 'POST',
            body: JSON.stringify({ force_start: true }),
        }),
    );
    expect(toastSpy).toHaveBeenCalledWith(
        'success',
        'Workshop session force-started.',
    );
});

it('includes definition-surface context companion fields in the final definition editor without marking them missing', async () => {
    const wrapper = mountPage({
        metric: {
            id: 'metric-1',
            name: 'Active Users',
            status: 'InWorkshop',
            engagement_id: 'eng-1',
            last_submitted_at: null,
            matrix: [
                {
                    field: 'active_metrics_tracked',
                    label: 'Active user reporting windows tracked',
                    type: 'checkbox_group',
                    values: [],
                    discrepancy: null,
                },
                {
                    field: 'reporting_frequency',
                    label: 'How often do you refresh active user metrics?',
                    type: 'select',
                    values: [],
                    discrepancy: null,
                },
            ],
            context_fields: [
                {
                    field: 'active_metrics_notes',
                    label: 'Active user window segmentation notes',
                    type: 'textarea',
                    values: [],
                    options: null,
                },
            ],
            field_roles: [
                {
                    key: 'active_metrics_tracked',
                    label: 'Active user reporting windows tracked',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'active_metrics_notes',
                    label: 'Active user window segmentation notes',
                    role: 'context',
                    companion_of: 'active_metrics_tracked',
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'reporting_frequency',
                    label: 'How often do you refresh active user metrics?',
                    role: 'definition',
                    value: null,
                    unclassified: false,
                },
            ],
            unclassified: [],
            definitions: [],
        },
        finalDefinition: {
            id: 'final-1',
            status: 'Draft',
            definition_json: {
                active_metrics_tracked: {
                    label: 'Active user reporting windows tracked',
                    value: 'DAU, WAU',
                },
                active_metrics_notes: {
                    label: 'Active user window segmentation notes',
                    value: '',
                },
                reporting_frequency: {
                    label: 'How often do you refresh active user metrics?',
                    value: 'Daily',
                },
            },
            notes: null,
            owner_participant_id: 'owner-1',
            owner_participant: null,
            signed_off_at: null,
            signed_off_by: null,
        },
        can: {
            update_final_definition: true,
            sign_off: false,
            sign_off_message: null,
        },
        final_definition_requirements: {
            required_fields: [
                {
                    key: 'active_metrics_tracked',
                    label: 'Active user reporting windows tracked',
                },
                {
                    key: 'reporting_frequency',
                    label: 'How often do you refresh active user metrics?',
                },
            ],
            missing_keys: [],
            owner_eligible: true,
        },
    });

    const editButton = wrapper.get('[data-test="final-definition-edit"]');
    await editButton.trigger('click');
    await flushPromises();

    expect(wrapper.find('[data-test="final-definition-form"]').exists()).toBe(
        true,
    );
    expect(wrapper.text()).toContain('Active user window segmentation notes');
    expect(wrapper.find('#definition-active_metrics_notes').exists()).toBe(
        true,
    );
    const definitionLabels = wrapper
        .findAll(
            '[data-test="final-definition-form"] label[for^="definition-"]',
        )
        .map((label) => label.text());

    expect(definitionLabels).toEqual([
        'Active user reporting windows tracked',
        'Active user window segmentation notes',
        'How often do you refresh active user metrics?',
    ]);
    expect(
        wrapper
            .find(
                '[data-test="final-definition-field-missing-active_metrics_notes"]',
            )
            .exists(),
    ).toBe(false);
});

it('computes and passes a contextual downloadFilename prop to MatrixAnswersView', () => {
    const wrapper = mountPage({
        organization: { id: 'org-123', name: 'Acme Corp!' },
        engagement: { id: 'eng-456', title: 'Q2 Audit-Plan' },
        metric: {
            id: 'metric-789',
            name: 'Gross Revenue Churn',
            status: 'InWorkshop',
            matrix: [],
            context_fields: [],
            field_roles: [],
            unclassified: [],
            definitions: [],
        },
    });

    const matrixView = wrapper.findComponent(MatrixAnswersView);
    expect(matrixView.exists()).toBe(true);

    const dateStr = new Date().toISOString().split('T')[0];
    expect(matrixView.props('downloadFilename')).toBe(`acme-corp_q2-audit-plan_gross-revenue-churn_matrix_${dateStr}`);
});
