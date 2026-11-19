import { router } from '@inertiajs/vue3';
import { computed, reactive, toRef, watch, type ComputedRef } from 'vue';

import {
    buildDefinitionFieldRows,
    buildFinalDefinitionDraftPayload,
    buildFinalDefinitionValuesByField,
    buildSummaryRows,
    cloneFinalDefinitionDraftValues,
    createFinalDefinitionDraftValues,
    formatOwnerOptionDetails,
    hasFinalDefinitionDraftChanges,
    resolveMissingFieldLabels,
    resolveOwnerSummaryLabel,
} from '@/features/workshops/metric-workshop/final-definition';
import type {
    DefinitionFieldRow,
    FinalDefinition,
    FinalDefinitionDraftValues,
    FinalDefinitionEditorState,
    FinalDefinitionRequirements,
    MatrixAnswerDoubleClickPayload,
    MetricView,
    OwnerOption,
    Permissions,
} from '@/features/workshops/metric-workshop/types';
import { signOff as signOffRoute } from '@/routes/org/metrics';
import { update as updateFinalDefinition } from '@/routes/org/metrics/final-definition';

type RouteDescriptor =
    | string
    | null
    | undefined
    | {
          url?: string | null;
      };

type InertiaRequestOptions = {
    preserveScroll?: boolean;
    onFinish?: () => void;
    onSuccess?: () => void;
    onError?: (errors?: Record<string, string>) => void;
};

type InertiaRouterLike = {
    put: (
        url: RouteDescriptor,
        data?: unknown,
        options?: InertiaRequestOptions,
    ) => void;
    patch: (
        url: RouteDescriptor,
        data?: unknown,
        options?: InertiaRequestOptions,
    ) => void;
    post: (
        url: RouteDescriptor,
        data?: unknown,
        options?: InertiaRequestOptions,
    ) => void;
};

type ToastFn = (variant: 'success' | 'error', message: string) => void;

interface UseFinalDefinitionEditorOptions {
    organizationId: ComputedRef<string>;
    engagementId: ComputedRef<string | null>;
    metric: ComputedRef<MetricView>;
    finalDefinition: ComputedRef<FinalDefinition>;
    can: ComputedRef<Permissions>;
    ownerOptionsProp: ComputedRef<OwnerOption[]>;
    finalDefinitionRequirements: ComputedRef<FinalDefinitionRequirements>;
    toast: ToastFn;
    onSignOffSuccess?: () => void | Promise<void>;
    routerImpl?: InertiaRouterLike;
}

const applyDraftValues = (
    target: FinalDefinitionDraftValues,
    source: FinalDefinitionDraftValues,
): void => {
    const cloned = cloneFinalDefinitionDraftValues(source);

    target.rows.splice(0, target.rows.length, ...cloned.rows);
    target.notes = cloned.notes;
    target.alignmentSummary = cloned.alignmentSummary;
    target.ownerParticipantId = cloned.ownerParticipantId;
};

export function useFinalDefinitionEditor(
    options: UseFinalDefinitionEditorOptions,
) {
    const resolvedRouter = options.routerImpl ?? (router as InertiaRouterLike);

    const requiredDefinitionFields = computed(
        () => options.finalDefinitionRequirements.value.required_fields ?? [],
    );
    const definitionFieldRows = computed<DefinitionFieldRow[]>(() =>
        buildDefinitionFieldRows({
            fieldRoles: options.metric.value.field_roles ?? [],
            requiredFields: requiredDefinitionFields.value,
            definitionJson: options.finalDefinition.value.definition_json ?? {},
        }),
    );
    const missingDefinitionKeys = computed(
        () => options.finalDefinitionRequirements.value.missing_keys ?? [],
    );
    const ownerEligible = computed(
        () => options.finalDefinitionRequirements.value.owner_eligible ?? false,
    );
    const missingFields = computed(() =>
        resolveMissingFieldLabels({
            requiredFields: requiredDefinitionFields.value,
            missingKeys: missingDefinitionKeys.value,
            ownerEligible: ownerEligible.value,
        }),
    );
    const isReadyForSignOff = computed(() => missingFields.value.length === 0);
    const missingDefinitionKeySet = computed(
        () => new Set(missingDefinitionKeys.value),
    );
    const isDefinitionFieldMissing = (fieldKey: string): boolean =>
        missingDefinitionKeySet.value.has(fieldKey);

    const initialDraftValues = createFinalDefinitionDraftValues({
        definitionFieldRows: definitionFieldRows.value,
        finalDefinition: options.finalDefinition.value,
        matrix: options.metric.value.matrix,
    });
    const currentDraft = reactive<FinalDefinitionDraftValues>(
        cloneFinalDefinitionDraftValues(initialDraftValues),
    );
    const editorState = reactive<FinalDefinitionEditorState>({
        draft: {
            current: currentDraft,
            saved: cloneFinalDefinitionDraftValues(initialDraftValues),
        },
        isEditingDraft: false,
        isSavingDraft: false,
        isSigningOff: false,
        isSignOffDialogOpen: false,
    });

    const editableRows = currentDraft.rows;
    const notes = toRef(currentDraft, 'notes');
    const alignmentSummary = toRef(currentDraft, 'alignmentSummary');
    const ownerParticipantId = toRef(currentDraft, 'ownerParticipantId');
    const isEditingDraft = toRef(editorState, 'isEditingDraft');
    const isSavingDraft = toRef(editorState, 'isSavingDraft');
    const isSigningOff = toRef(editorState, 'isSigningOff');
    const isSignOffDialogOpen = toRef(editorState, 'isSignOffDialogOpen');

    const resolvedOwnerOptions = computed<OwnerOption[]>(() => {
        if (options.ownerOptionsProp.value.length > 0) {
            return options.ownerOptionsProp.value;
        }

        return Array.isArray(options.metric.value.owner_options)
            ? options.metric.value.owner_options
            : [];
    });
    const ownerSelectionRequired = computed(() =>
        resolvedOwnerOptions.value.some((option) => !option.disabled),
    );

    watch(
        () => options.finalDefinition.value.owner_participant_id,
        (value) => {
            const resolved = value ?? '';
            currentDraft.ownerParticipantId = resolved;
            editorState.draft.saved.ownerParticipantId = resolved;
        },
    );

    const updateOwnerParticipant = (value: string | null): void => {
        currentDraft.ownerParticipantId = value ?? '';
    };

    const selectedOwnerOption = computed(() =>
        resolvedOwnerOptions.value.find(
            (option) => option.id === currentDraft.ownerParticipantId,
        ),
    );
    const selectedOwnerDetails = computed(() =>
        selectedOwnerOption.value
            ? formatOwnerOptionDetails(selectedOwnerOption.value)
            : null,
    );

    const isLocked = computed(
        () => !!options.finalDefinition.value.signed_off_at,
    );
    const showEditDraftButton = computed(
        () =>
            options.can.value.update_final_definition &&
            !isLocked.value &&
            !editorState.isEditingDraft,
    );
    const showSignOffAction = computed(
        () => options.can.value.sign_off && !isLocked.value,
    );
    const signOffReadinessMessage = computed(() => {
        const backendMessage = options.can.value.sign_off_message?.trim();

        if (backendMessage) {
            return backendMessage;
        }

        if (isReadyForSignOff.value) {
            return null;
        }

        return 'Complete all required sign-off fields before approving.';
    });
    const hasDraftChanges = computed(() =>
        hasFinalDefinitionDraftChanges({
            current: currentDraft,
            saved: editorState.draft.saved,
        }),
    );

    const isEditingDisabled = computed(
        () =>
            !options.can.value.update_final_definition ||
            isLocked.value ||
            editorState.isSigningOff ||
            editorState.isSavingDraft,
    );

    const enterEditMode = (): void => {
        if (isEditingDisabled.value) {
            return;
        }

        editorState.isEditingDraft = true;
    };

    const applyDefinitionAnswerFromMatrix = (
        payload: MatrixAnswerDoubleClickPayload,
    ): void => {
        if (isEditingDisabled.value) {
            return;
        }

        if (!editorState.isEditingDraft) {
            enterEditMode();
        }

        if (!editorState.isEditingDraft) {
            return;
        }

        const targetRow = editableRows.find(
            (row) => row.field === payload.field,
        );
        if (targetRow) {
            targetRow.value = payload.value;
            return;
        }

        editableRows.push({
            field: payload.field,
            label: payload.label,
            value: payload.value,
            companion_of: null,
        });
    };

    const resetDraftToSaved = (): void => {
        applyDraftValues(currentDraft, editorState.draft.saved);
    };

    const cancelEditMode = (): void => {
        resetDraftToSaved();
        editorState.isEditingDraft = false;
    };

    const formattedSignedOffAt = computed(() =>
        options.finalDefinition.value.signed_off_at
            ? new Date(
                  options.finalDefinition.value.signed_off_at,
              ).toLocaleString()
            : null,
    );
    const summaryRows = computed(() =>
        buildSummaryRows({
            definitionFieldRows: definitionFieldRows.value,
            definitionJson: options.finalDefinition.value.definition_json ?? {},
        }),
    );
    const finalDefinitionValuesByField = computed<
        Record<string, string | null>
    >(() =>
        buildFinalDefinitionValuesByField(
            options.finalDefinition.value.definition_json ?? {},
        ),
    );
    const ownerSummaryLabel = computed(() =>
        resolveOwnerSummaryLabel({
            finalDefinition: options.finalDefinition.value,
            ownerOptions: resolvedOwnerOptions.value,
        }),
    );

    const submit = (): void => {
        if (
            isLocked.value ||
            editorState.isSigningOff ||
            editorState.isSavingDraft ||
            !hasDraftChanges.value
        ) {
            return;
        }

        editorState.isSavingDraft = true;

        resolvedRouter.put(
            updateFinalDefinition([
                options.organizationId.value,
                options.metric.value.id,
            ]),
            buildFinalDefinitionDraftPayload(currentDraft),
            {
                preserveScroll: true,
                onFinish: () => {
                    editorState.isSavingDraft = false;
                },
                onSuccess: () => {
                    editorState.draft.saved =
                        cloneFinalDefinitionDraftValues(currentDraft);
                    editorState.isEditingDraft = false;
                    options.toast('success', 'Saved successfully.');
                },
                onError: () => {
                    options.toast(
                        'error',
                        'Unable to save right now. Please try again.',
                    );
                },
            },
        );
    };

    const signOff = (): void => {
        const engagementId = options.engagementId.value;

        if (
            isLocked.value ||
            !options.can.value.sign_off ||
            editorState.isSigningOff ||
            !isReadyForSignOff.value ||
            !engagementId
        ) {
            return;
        }

        editorState.isSigningOff = true;
        editorState.isSignOffDialogOpen = false;

        resolvedRouter.post(
            signOffRoute([
                options.organizationId.value,
                engagementId,
                options.metric.value.id,
            ]),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    editorState.isSigningOff = false;
                },
                onSuccess: () => {
                    void options.onSignOffSuccess?.();
                    options.toast(
                        'success',
                        'Metric signed off. Final definition locked.',
                    );
                },
                onError: () => {
                    options.toast(
                        'error',
                        'Unable to sign off right now. Please try again.',
                    );
                },
            },
        );
    };

    const confirmSignOff = (): void => {
        if (
            isLocked.value ||
            !options.can.value.sign_off ||
            editorState.isSigningOff ||
            !isReadyForSignOff.value ||
            !options.engagementId.value
        ) {
            editorState.isSignOffDialogOpen = false;
            return;
        }

        signOff();
    };

    const isSaveDisabled = computed(
        () => isEditingDisabled.value || !hasDraftChanges.value,
    );

    return {
        editorState,
        definitionFieldRows,
        ownerEligible,
        missingFields,
        isReadyForSignOff,
        isDefinitionFieldMissing,
        editableRows,
        notes,
        alignmentSummary,
        ownerParticipantId,
        isSigningOff,
        isSignOffDialogOpen,
        isSavingDraft,
        resolvedOwnerOptions,
        ownerSelectionRequired,
        updateOwnerParticipant,
        selectedOwnerOption,
        selectedOwnerDetails,
        isLocked,
        showEditDraftButton,
        showSignOffAction,
        signOffReadinessMessage,
        hasDraftChanges,
        isEditingDraft,
        isEditingDisabled,
        formattedSignedOffAt,
        summaryRows,
        finalDefinitionValuesByField,
        ownerSummaryLabel,
        enterEditMode,
        applyDefinitionAnswerFromMatrix,
        cancelEditMode,
        submit,
        confirmSignOff,
        isSaveDisabled,
    };
}
