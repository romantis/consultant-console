<script setup lang="ts">
import AppStatusBanner from '@/components/AppStatusBanner.vue';
import MatrixAnswersView from '@/components/metrics/MatrixAnswersView.vue';
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import FinalDefinitionPanel from '@/features/workshops/metric-workshop/FinalDefinitionPanel.vue';
import WorkshopHeaderCard from '@/features/workshops/metric-workshop/WorkshopHeaderCard.vue';
import { isValidationGuidanceField } from '@/features/workshops/metric-workshop/final-definition';
import type {
    EngagementSummary,
    FinalDefinition,
    FinalDefinitionRequirements,
    MatrixAnswerDoubleClickPayload,
    MetricView,
    OfficialSignals,
    OrganizationSummary,
    OwnerOption,
    Permissions,
    WorkshopPayload,
    WorkshopSessionContext,
} from '@/features/workshops/metric-workshop/types';
import { useFinalDefinitionEditor } from '@/features/workshops/metric-workshop/useFinalDefinitionEditor';
import { useWorkshopSessions } from '@/features/workshops/metric-workshop/useWorkshopSessions';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as clientHandbookIndex } from '@/routes/client/handbook';
import org, { show as orgShow } from '@/routes/org';
import engagements, {
    index as orgEngagementsIndex,
    show as orgEngagementsShow,
} from '@/routes/org/engagements';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        organization: OrganizationSummary;
        engagement: EngagementSummary;
        metric: MetricView;
        finalDefinition: FinalDefinition;
        officialSignals?: OfficialSignals | null;
        can: Permissions;
        ownerOptions?: OwnerOption[];
        workshop?: WorkshopPayload | null;
        workshop_closed?: boolean;
        final_definition_requirements?: FinalDefinitionRequirements;
    }>(),
    {
        ownerOptions: () => [],
        officialSignals: null,
        final_definition_requirements: () => ({
            required_fields: [],
            missing_keys: [],
            owner_eligible: false,
        }),
    },
);

const { toast } = useToast();
const page = usePage<{
    auth?: {
        user?: {
            is_internal_staff?: boolean;
        };
    };
    flags?: {
        workshop_discrepancies?: boolean;
        workshop_force_start_enabled?: boolean;
        change_proposals?: boolean;
    };
}>();
const isStaff = computed(() =>
    Boolean(page.props.auth?.user?.is_internal_staff),
);

const showWorkshopDiscrepancies = computed(
    () => !!page.props.flags?.workshop_discrepancies,
);
const workshopForceStartEnabled = computed(
    () => !!page.props.flags?.workshop_force_start_enabled,
);
const changeProposalsEnabled = computed(
    () => page.props.flags?.change_proposals === true,
);
const pageTitle = computed(() => {
    const suffix = showWorkshopDiscrepancies.value
        ? 'Discrepancy Review'
        : 'Metric Workshop';

    return `${props.metric.name} • ${suffix}`;
});

const engagementIndexHref = computed(() => {
    const route = orgEngagementsIndex(props.organization.id);
    return typeof route === 'string' ? route : (route?.url ?? '');
});

const engagementShowHref = computed(() => {
    if (!props.engagement) {
        return '';
    }

    const route = orgEngagementsShow([
        props.organization.id,
        props.engagement.id,
    ]);
    const href = typeof route === 'string' ? route : (route?.url ?? '');
    return href ? `${href}#metrics-panel` : '';
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    { title: 'Engagements', href: engagementIndexHref.value },
    ...(props.engagement
        ? [{ title: props.engagement.title, href: engagementShowHref.value }]
        : []),
    { title: props.metric.name, href: '' },
];

const finalDefinitionRequirements = computed(
    () =>
        props.final_definition_requirements ?? {
            required_fields: [],
            missing_keys: [],
            owner_eligible: false,
        },
);

const matrixDownloadFilename = computed(() => {
    const toSlug = (val: string) =>
        val
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
    const orgSlug = toSlug(props.organization.name);
    const engagementSlug = props.engagement?.title
        ? toSlug(props.engagement.title)
        : 'no-engagement';
    const metricSlug = toSlug(props.metric.name);
    const dateStr = new Date().toISOString().split('T')[0];
    return `${orgSlug}_${engagementSlug}_${metricSlug}_matrix_${dateStr}`;
});

const workshopMetrics = computed(() => props.workshop?.metrics ?? []);
const workshopBaseUrl = computed(() => props.workshop?.base_url ?? '');
const hasMetricTitleSelector = computed(
    () =>
        workshopMetrics.value.length > 0 && workshopBaseUrl.value.trim() !== '',
);
const workshopSessionContext = computed<WorkshopSessionContext>(() => ({
    selected_session_id: props.workshop?.session_context?.selected_session_id,
    sessions: props.workshop?.session_context?.sessions ?? [],
}));
const canManageWorkshopSessions = computed(
    () => props.can.manage_workshop_sessions === true,
);
const workshopSessionsRouteApi = computed(() => engagements?.workshopSessions);

const workshopMetricHref = (metricId: string): string => {
    const baseUrl = workshopBaseUrl.value;
    if (!baseUrl) {
        return '';
    }

    const separator = baseUrl.includes('?') ? '&' : '?';
    return `${baseUrl}${separator}metric=${encodeURIComponent(metricId)}`;
};

const switchWorkshopMetric = (metricId: string | null): void => {
    if (!metricId || metricId === props.metric.id) {
        return;
    }

    const href = workshopMetricHref(metricId);
    if (!href) {
        return;
    }

    router.visit(href, { preserveScroll: true });
};

const statusProbeUrl = (): string => {
    if (typeof window === 'undefined') {
        return '';
    }

    const url = new URL(window.location.href);
    url.searchParams.set('probe', 'status');
    return url.toString();
};
const {
    workshopClosed,
    workshopSessionError,
    isForceWorkshopStartDialogOpen,
    isFinishWorkshopDialogOpen,
    workshopSessionAttentionBadgeLabel,
    workshopSessionAttentionBadgeVariant,
    showWorkshopSessionInProgressText,
    showScheduledWorkshopTime,
    activeWorkshopSessionScheduledLabel,
    showWorkshopStartAction,
    showWorkshopFinishAction,
    isWorkshopStartBusy,
    isWorkshopFinishBusy,
    canForceStartFromHeaderAction,
    shouldShowForceStartTooltip,
    isWorkshopStartButtonDisabled,
    showLastWorkshopEndedLabel,
    lastCompletedWorkshopEndedLabel,
    requestWorkshopStart,
    requestWorkshopFinish,
    confirmForceWorkshopStart,
    confirmFinishWorkshop,
    isWorkshopMetricLive,
    pollEngagementStatus,
} = useWorkshopSessions({
    organizationId: computed(() => props.organization.id),
    engagementId: computed(() => props.engagement?.id ?? null),
    engagementStatus: computed(() => props.engagement?.status),
    metricId: computed(() => props.metric.id),
    workshopClosedProp: computed(() => props.workshop_closed),
    workshopSessionContext,
    isStaff,
    canManageWorkshopSessions,
    workshopForceStartEnabled,
    workshopSessionsRouteApi,
    statusProbeUrl,
    toast,
});

const shouldShowMetricOptionLiveIndicator = (metricId: string): boolean =>
    hasMetricTitleSelector.value && isWorkshopMetricLive(metricId);

const updateDraftNotes = (value: string): void => {
    notes.value = value;
};

const updateDraftAlignmentSummary = (value: string): void => {
    alignmentSummary.value = value;
};

const setSignOffDialogOpen = (value: boolean): void => {
    isSignOffDialogOpen.value = value;
};

const setForceWorkshopStartDialogOpen = (value: boolean): void => {
    isForceWorkshopStartDialogOpen.value = value;
};

const setFinishWorkshopDialogOpen = (value: boolean): void => {
    isFinishWorkshopDialogOpen.value = value;
};

const layoutClasses = 'grid gap-6 p-6 lg:grid-cols-12';
const mainColumnClasses = 'space-y-4 lg:col-span-9';
const sideColumnClasses = 'space-y-4 lg:col-span-3';
const focusedDefinitionField = ref<string | null>(null);
const focusDefinitionFieldRequest = ref(0);
const isMatrixComparisonField = (field: string): boolean =>
    !isValidationGuidanceField(field);
const matrixComparisonMetric = computed<MetricView>(() => ({
    ...props.metric,
    matrix: props.metric.matrix.filter((row) =>
        isMatrixComparisonField(row.field),
    ),
    context_fields: (props.metric.context_fields ?? []).filter((row) =>
        isMatrixComparisonField(row.field),
    ),
    field_roles: props.metric.field_roles.filter((entry) =>
        isMatrixComparisonField(entry.key),
    ),
    unclassified: props.metric.unclassified.filter(isMatrixComparisonField),
}));

const {
    ownerEligible,
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
} = useFinalDefinitionEditor({
    organizationId: computed(() => props.organization.id),
    engagementId: computed(() => props.engagement?.id ?? null),
    metric: computed(() => props.metric),
    finalDefinition: computed(() => props.finalDefinition),
    can: computed(() => props.can),
    ownerOptionsProp: computed(() => props.ownerOptions),
    finalDefinitionRequirements,
    toast,
    onSignOffSuccess: () => void pollEngagementStatus(),
});

const applyAndFocusDefinitionAnswerFromMatrix = (
    payload: MatrixAnswerDoubleClickPayload,
): void => {
    applyDefinitionAnswerFromMatrix(payload);

    if (!isEditingDraft.value) {
        focusedDefinitionField.value = null;
        return;
    }

    focusedDefinitionField.value = payload.field;
    focusDefinitionFieldRequest.value += 1;
};

const recomputeStatusMeta = computed(() => props.workshop?.recompute ?? null);
const recomputeStatusValue = computed(() =>
    (recomputeStatusMeta.value?.status ?? 'idle').toString().toLowerCase(),
);
const recomputePendingChanges = computed(() => {
    const value = recomputeStatusMeta.value?.pending_changes;
    return typeof value === 'boolean' ? value : null;
});
const recomputeInProgress = computed(() =>
    ['queued', 'running'].includes(recomputeStatusValue.value),
);
const recomputeStatusLabel = computed(() => {
    if (!recomputeStatusMeta.value) {
        return null;
    }

    if (recomputeInProgress.value) {
        return recomputeStatusValue.value === 'running'
            ? 'Recompute running'
            : 'Recompute queued';
    }

    if (recomputePendingChanges.value === false) {
        return 'Up to date';
    }

    return 'Pending changes';
});

const governanceHref = computed(() => {
    if (!props.organization?.id || !props.metric?.id) {
        return '';
    }

    return `/organizations/${props.organization.id}/metrics/${props.metric.id}/governance`;
});
const showGovernanceLink = computed(() => {
    const status = (props.metric.status ?? '').toString().toLowerCase();

    return (
        changeProposalsEnabled.value &&
        governanceHref.value.length > 0 &&
        status === 'finalized'
    );
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="layoutClasses">
            <AppStatusBanner
                v-if="workshopClosed && !isStaff"
                class="lg:col-span-12"
                tone="warning"
                title="Workshop closed"
                description="This engagement is finalized. Review the deliverables below."
            >
                <template #actions>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="clientHandbookIndex().url">
                            Open handbook
                        </Link>
                    </Button>
                </template>
            </AppStatusBanner>

            <div :class="mainColumnClasses">
                <WorkshopHeaderCard
                    :metric="metric"
                    :has-metric-title-selector="hasMetricTitleSelector"
                    :workshop-metrics="workshopMetrics"
                    :workshop-session-attention-badge-label="
                        workshopSessionAttentionBadgeLabel
                    "
                    :workshop-session-attention-badge-variant="
                        workshopSessionAttentionBadgeVariant
                    "
                    :show-workshop-session-in-progress-text="
                        showWorkshopSessionInProgressText
                    "
                    :show-scheduled-workshop-time="showScheduledWorkshopTime"
                    :active-workshop-session-scheduled-label="
                        activeWorkshopSessionScheduledLabel
                    "
                    :show-workshop-start-action="showWorkshopStartAction"
                    :show-workshop-finish-action="showWorkshopFinishAction"
                    :is-workshop-start-busy="isWorkshopStartBusy"
                    :is-workshop-finish-busy="isWorkshopFinishBusy"
                    :can-force-start-from-header-action="
                        canForceStartFromHeaderAction
                    "
                    :should-show-force-start-tooltip="
                        shouldShowForceStartTooltip
                    "
                    :is-workshop-start-button-disabled="
                        isWorkshopStartButtonDisabled
                    "
                    :show-last-workshop-ended-label="showLastWorkshopEndedLabel"
                    :last-completed-workshop-ended-label="
                        lastCompletedWorkshopEndedLabel
                    "
                    :workshop-session-error="workshopSessionError"
                    :show-workshop-discrepancies="showWorkshopDiscrepancies"
                    :recompute-status-label="recomputeStatusLabel"
                    :is-force-workshop-start-dialog-open="
                        isForceWorkshopStartDialogOpen
                    "
                    :is-finish-workshop-dialog-open="isFinishWorkshopDialogOpen"
                    :show-metric-option-live-indicator="
                        shouldShowMetricOptionLiveIndicator
                    "
                    @switch-workshop-metric="switchWorkshopMetric"
                    @request-workshop-start="requestWorkshopStart"
                    @request-workshop-finish="requestWorkshopFinish"
                    @confirm-force-workshop-start="confirmForceWorkshopStart"
                    @confirm-finish-workshop="confirmFinishWorkshop"
                    @update:is-force-workshop-start-dialog-open="
                        setForceWorkshopStartDialogOpen
                    "
                    @update:is-finish-workshop-dialog-open="
                        setFinishWorkshopDialogOpen
                    "
                />

                <MatrixAnswersView
                    :metric="matrixComparisonMetric"
                    :final-definition-values="finalDefinitionValuesByField"
                    :download-filename="matrixDownloadFilename"
                    @definition-answer-dblclick="
                        applyAndFocusDefinitionAnswerFromMatrix
                    "
                />
            </div>

            <div :class="sideColumnClasses">
                <FinalDefinitionPanel
                    :final-definition="finalDefinition"
                    :show-governance-link="showGovernanceLink"
                    :governance-href="governanceHref"
                    :show-edit-draft-button="showEditDraftButton"
                    :owner-eligible="ownerEligible"
                    :is-ready-for-sign-off="isReadyForSignOff"
                    :is-definition-field-missing="isDefinitionFieldMissing"
                    :editable-rows="editableRows"
                    :notes="notes"
                    :alignment-summary="alignmentSummary"
                    :owner-participant-id="ownerParticipantId"
                    :is-signing-off="isSigningOff"
                    :is-sign-off-dialog-open="isSignOffDialogOpen"
                    :is-saving-draft="isSavingDraft"
                    :resolved-owner-options="resolvedOwnerOptions"
                    :owner-selection-required="ownerSelectionRequired"
                    :selected-owner-option="selectedOwnerOption"
                    :selected-owner-details="selectedOwnerDetails"
                    :is-locked="isLocked"
                    :show-sign-off-action="showSignOffAction"
                    :sign-off-readiness-message="signOffReadinessMessage"
                    :is-editing-draft="isEditingDraft"
                    :is-editing-disabled="isEditingDisabled"
                    :formatted-signed-off-at="formattedSignedOffAt"
                    :summary-rows="summaryRows"
                    :owner-summary-label="ownerSummaryLabel"
                    :is-save-disabled="isSaveDisabled"
                    :focused-definition-field="focusedDefinitionField"
                    :focus-definition-field-request="
                        focusDefinitionFieldRequest
                    "
                    @edit-draft="enterEditMode"
                    @cancel-draft="cancelEditMode"
                    @save-draft="submit"
                    @confirm-sign-off="confirmSignOff"
                    @set-owner-participant="updateOwnerParticipant"
                    @set-notes="updateDraftNotes"
                    @set-alignment-summary="updateDraftAlignmentSummary"
                    @set-sign-off-dialog-open="setSignOffDialogOpen"
                />
            </div>
        </div>
    </AppLayout>
</template>
