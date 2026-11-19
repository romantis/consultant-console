import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import WorkshopHeaderCard from '@/features/workshops/metric-workshop/WorkshopHeaderCard.vue';

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

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
}));

vi.mock('@/components/ui/dialog', () => {
    const Wrapper = basicWrapper('div');
    return {
        Dialog: Wrapper,
        DialogClose: Wrapper,
        DialogContent: Wrapper,
        DialogDescription: Wrapper,
        DialogFooter: Wrapper,
        DialogHeader: Wrapper,
        DialogTitle: Wrapper,
    };
});

vi.mock('@/components/ui/select', () => ({
    Select: basicWrapper('div'),
    SelectContent: basicWrapper('div'),
    SelectItem: basicWrapper('div'),
    SelectTrigger: basicWrapper('button'),
}));

vi.mock('@/components/ui/tooltip', () => ({
    Tooltip: basicWrapper('div'),
    TooltipContent: basicWrapper('div'),
    TooltipProvider: basicWrapper('div'),
    TooltipTrigger: basicWrapper('div'),
}));

vi.mock('lucide-vue-next', () => ({
    Loader2: basicWrapper('span'),
}));

const baseProps = () => ({
    metric: {
        id: 'metric-1',
        name: 'Metric',
        status: 'InWorkshop',
        engagement_id: 'eng-1',
        last_submitted_at: null,
        matrix: [],
        context_fields: [],
        field_roles: [],
        unclassified: [],
        definitions: [],
    },
    hasMetricTitleSelector: false,
    workshopMetrics: [],
    workshopSessionAttentionBadgeLabel: null,
    workshopSessionAttentionBadgeVariant: 'destructive' as const,
    showWorkshopSessionInProgressText: false,
    showScheduledWorkshopTime: false,
    activeWorkshopSessionScheduledLabel: null,
    showWorkshopStartAction: false,
    showWorkshopFinishAction: false,
    isWorkshopStartBusy: false,
    isWorkshopFinishBusy: false,
    canForceStartFromHeaderAction: false,
    shouldShowForceStartTooltip: false,
    isWorkshopStartButtonDisabled: false,
    showLastWorkshopEndedLabel: false,
    lastCompletedWorkshopEndedLabel: null,
    workshopSessionError: null,
    showWorkshopDiscrepancies: false,
    recomputeStatusLabel: null,
    isForceWorkshopStartDialogOpen: false,
    isFinishWorkshopDialogOpen: false,
    showMetricOptionLiveIndicator: () => false,
});

it('emits start and finish workshop actions from the header buttons', async () => {
    const wrapper = mount(WorkshopHeaderCard, {
        props: {
            ...baseProps(),
            showWorkshopStartAction: true,
            showWorkshopFinishAction: true,
        },
    });

    await wrapper.get('[data-test="workshop-session-start"]').trigger('click');
    await wrapper
        .get('[data-test="workshop-session-finish-confirm"]')
        .trigger('click');

    expect(wrapper.emitted('request-workshop-start')).toHaveLength(1);
    expect(wrapper.emitted('confirm-finish-workshop')).toHaveLength(1);
});

it('disables the start action when the session is unavailable', () => {
    const wrapper = mount(WorkshopHeaderCard, {
        props: {
            ...baseProps(),
            showWorkshopStartAction: true,
            isWorkshopStartButtonDisabled: true,
        },
    });

    expect(
        wrapper.get('[data-test="workshop-session-start"]').attributes(
            'disabled',
        ),
    ).toBeDefined();
});
