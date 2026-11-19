import { flushPromises, mount } from '@vue/test-utils';
import { computed, defineComponent, h, type PropType } from 'vue';

import type {
    FinalDefinition,
    FinalDefinitionRequirements,
    MetricView,
    OwnerOption,
    Permissions,
} from '@/features/workshops/metric-workshop/types';
import { useFinalDefinitionEditor } from '@/features/workshops/metric-workshop/useFinalDefinitionEditor';

const routerPut = vi.hoisted(() => vi.fn());
const routerPatch = vi.hoisted(() => vi.fn());
const routerPost = vi.hoisted(() => vi.fn());
const toastSpy = vi.hoisted(() => vi.fn());
const onSignOffSuccessSpy = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    router: {
        put: routerPut,
        patch: routerPatch,
        post: routerPost,
    },
}));

vi.mock('@/routes/org/metrics/final-definition', () => ({
    update: () => ({ url: '/stub/metrics/final-definition' }),
}));

vi.mock('@/routes/org/metrics', () => ({
    signOff: () => ({ url: '/stub/metrics/sign-off' }),
}));

type EditorHarnessState = {
    editableRows: Array<{ field: string; label: string; value: string }>;
    alignmentSummary: string;
    isEditingDraft: boolean;
    isSignOffDialogOpen: boolean;
    hasDraftChanges: boolean;
    enterEditMode: () => void;
    applyDefinitionAnswerFromMatrix: (payload: {
        field: string;
        label: string;
        value: string;
    }) => void;
    cancelEditMode: () => void;
    submit: () => void;
    confirmSignOff: () => void;
};

const baseMetric = (): MetricView => ({
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
});

const baseFinalDefinition = (): FinalDefinition => ({
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
});

const baseCan = (): Permissions => ({
    update_final_definition: true,
    sign_off: false,
    sign_off_message: null,
});

const baseOwnerOptions = (): OwnerOption[] => [
    {
        id: 'owner-1',
        label: 'Owner',
        responsibility: 'Approver',
        affiliation: 'Finance',
        is_default: true,
        disabled: false,
        user: { id: 'user-1', name: 'Owner' },
    },
];

const baseFinalDefinitionRequirements = (): FinalDefinitionRequirements => ({
    required_fields: [{ key: 'value', label: 'Metric Value' }],
    missing_keys: [],
    owner_eligible: true,
});

const EditorHarness = defineComponent({
    name: 'FinalDefinitionEditorHarness',
    props: {
        organizationId: {
            type: String,
            default: 'org-1',
        },
        engagementId: {
            type: String,
            default: 'eng-1',
        },
        metric: {
            type: Object as PropType<MetricView>,
            default: () => baseMetric(),
        },
        finalDefinition: {
            type: Object as PropType<FinalDefinition>,
            default: () => baseFinalDefinition(),
        },
        can: {
            type: Object as PropType<Permissions>,
            default: () => baseCan(),
        },
        ownerOptions: {
            type: Array as PropType<OwnerOption[]>,
            default: () => baseOwnerOptions(),
        },
        finalDefinitionRequirements: {
            type: Object as PropType<FinalDefinitionRequirements>,
            default: () => baseFinalDefinitionRequirements(),
        },
    },
    setup(props, { expose }) {
        const state = useFinalDefinitionEditor({
            organizationId: computed(() => props.organizationId),
            engagementId: computed(() => props.engagementId),
            metric: computed(() => props.metric),
            finalDefinition: computed(() => props.finalDefinition),
            can: computed(() => props.can),
            ownerOptionsProp: computed(() => props.ownerOptions),
            finalDefinitionRequirements: computed(
                () => props.finalDefinitionRequirements,
            ),
            toast: toastSpy,
            onSignOffSuccess: onSignOffSuccessSpy,
        });

        expose(state);

        return () => h('div');
    },
});

const mountedWrappers: Array<ReturnType<typeof mount>> = [];

const mountHarness = (overrides: Record<string, unknown> = {}) => {
    const wrapper = mount(EditorHarness, {
        props: {
            ...overrides,
        },
    });
    mountedWrappers.push(wrapper);

    return wrapper;
};

const editorState = (
    wrapper: ReturnType<typeof mountHarness>,
): EditorHarnessState => wrapper.vm as unknown as EditorHarnessState;

beforeEach(() => {
    routerPut.mockReset();
    routerPatch.mockReset();
    routerPost.mockReset();
    toastSpy.mockReset();
    onSignOffSuccessSpy.mockReset();
});

afterEach(() => {
    while (mountedWrappers.length > 0) {
        mountedWrappers.pop()?.unmount();
    }
});

describe('useFinalDefinitionEditor', () => {
    it('applies a matrix answer in edit mode and restores the saved draft on cancel', async () => {
        const wrapper = mountHarness();
        const state = editorState(wrapper);

        state.applyDefinitionAnswerFromMatrix({
            field: 'value',
            label: 'Metric Value',
            value: 'Aligned',
        });
        await flushPromises();

        expect(state.isEditingDraft).toBe(true);
        expect(state.editableRows[0]?.value).toBe('Aligned');
        expect(state.hasDraftChanges).toBe(true);

        state.cancelEditMode();

        expect(state.isEditingDraft).toBe(false);
        expect(state.editableRows[0]?.value).toBe('');
        expect(state.hasDraftChanges).toBe(false);
    });

    it('submits draft payloads and updates the saved editor state on success', async () => {
        routerPut.mockImplementationOnce((_url, _payload, options) => {
            options?.onSuccess?.();
            options?.onFinish?.();
        });

        const wrapper = mountHarness({
            finalDefinition: {
                ...baseFinalDefinition(),
                owner_participant_id: 'owner-1',
            },
        });
        const state = editorState(wrapper);

        state.enterEditMode();
        state.editableRows[0].value = 'Saved value';
        state.alignmentSummary =
            'Finance and product aligned on contracted ARR.';

        state.submit();
        await flushPromises();

        expect(routerPut).toHaveBeenCalledTimes(1);
        expect(routerPut.mock.calls[0]?.[0]).toEqual({
            url: '/stub/metrics/final-definition',
        });
        expect(routerPut.mock.calls[0]?.[1]).toEqual(
            expect.objectContaining({
                definition: [
                    {
                        field: 'value',
                        label: 'Metric Value',
                        value: 'Saved value',
                        companion_of: null,
                    },
                ],
                alignment_summary:
                    'Finance and product aligned on contracted ARR.',
                owner_participant_id: 'owner-1',
            }),
        );
        expect(state.isEditingDraft).toBe(false);
        expect(state.hasDraftChanges).toBe(false);
        expect(toastSpy).toHaveBeenCalledWith('success', 'Saved successfully.');
    });

    it('posts sign-off and triggers the follow-up callback when the draft is ready', async () => {
        routerPost.mockImplementationOnce((_url, _payload, options) => {
            options?.onSuccess?.();
            options?.onFinish?.();
        });

        const wrapper = mountHarness({
            can: {
                update_final_definition: true,
                sign_off: true,
                sign_off_message: null,
            },
            finalDefinition: {
                ...baseFinalDefinition(),
                definition_json: {
                    value: { label: 'Metric Value', value: 'Saved value' },
                },
                owner_participant_id: 'owner-1',
            },
        });
        const state = editorState(wrapper);

        state.isSignOffDialogOpen = true;
        state.confirmSignOff();
        await flushPromises();

        expect(routerPost).toHaveBeenCalledTimes(1);
        expect(routerPost.mock.calls[0]?.[0]).toEqual({
            url: '/stub/metrics/sign-off',
        });
        expect(state.isSignOffDialogOpen).toBe(false);
        expect(onSignOffSuccessSpy).toHaveBeenCalledTimes(1);
        expect(toastSpy).toHaveBeenCalledWith(
            'success',
            'Metric signed off. Final definition locked.',
        );
    });
});
