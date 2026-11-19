import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import FinalDefinitionPanel from '@/features/workshops/metric-workshop/FinalDefinitionPanel.vue';

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
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
    CardContent: basicWrapper('div'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
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
        DialogTrigger: Wrapper,
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

vi.mock('@/components/ui/textarea', () => ({
    Textarea: basicWrapper('textarea'),
}));

vi.mock('lucide-vue-next', () => ({
    Loader2: basicWrapper('span'),
}));

const baseProps = () => ({
    finalDefinition: {
        id: 'final-1',
        status: 'Draft',
        definition_json: {
            value: { label: 'Metric Value', value: 'North America' },
        },
        notes: 'Saved notes',
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
    showGovernanceLink: false,
    governanceHref: '',
    showEditDraftButton: true,
    ownerEligible: true,
    isReadyForSignOff: true,
    isDefinitionFieldMissing: () => false,
    editableRows: [{ field: 'value', label: 'Metric Value', value: '' }],
    notes: '',
    alignmentSummary: '',
    ownerParticipantId: '',
    isSigningOff: false,
    isSignOffDialogOpen: false,
    isSavingDraft: false,
    resolvedOwnerOptions: [],
    ownerSelectionRequired: false,
    selectedOwnerOption: null,
    selectedOwnerDetails: null,
    isLocked: false,
    showSignOffAction: true,
    signOffReadinessMessage: null,
    isEditingDraft: false,
    isEditingDisabled: false,
    formattedSignedOffAt: null,
    summaryRows: [
        {
            key: 'value',
            label: 'Metric Value',
            required: true,
            value: 'North America',
        },
    ],
    ownerSummaryLabel: 'Unassigned',
    isSaveDisabled: false,
});

it('emits edit and save actions for the draft form', async () => {
    const wrapper = mount(FinalDefinitionPanel, {
        props: {
            ...baseProps(),
            isEditingDraft: true,
        },
    });

    await wrapper.get('[data-test="final-definition-form"]').trigger('submit');
    expect(wrapper.emitted('save-draft')).toHaveLength(1);

    const summaryWrapper = mount(FinalDefinitionPanel, {
        props: baseProps(),
    });

    await summaryWrapper
        .get('[data-test="final-definition-edit"]')
        .trigger('click');
    expect(summaryWrapper.emitted('edit-draft')).toHaveLength(1);
});

it('shows the readiness message and disables sign off when incomplete', () => {
    const wrapper = mount(FinalDefinitionPanel, {
        props: {
            ...baseProps(),
            isReadyForSignOff: false,
            signOffReadinessMessage:
                'Complete all required sign-off fields before approving.',
        },
    });

    expect(
        wrapper.get('[data-test="final-definition-signoff-message"]').text(),
    ).toContain('Complete all required sign-off fields');
    expect(
        wrapper
            .get('[data-test="final-definition-signoff"]')
            .attributes('disabled'),
    ).toBeDefined();
});
