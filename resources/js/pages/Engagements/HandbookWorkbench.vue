<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Info,
    Lock,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import HandbookSignalEditor from '@/features/handbook-workbench/HandbookSignalEditor.vue';
import type { SourceContext } from '@/features/handbook-workbench/MetricSourceContext.vue';
import MetricSourceContext from '@/features/handbook-workbench/MetricSourceContext.vue';
import { buildSignalPayload } from '@/features/handbook-workbench/handbook-signals';
import AppLayout from '@/layouts/AppLayout.vue';

import { useToast } from '@/composables/useToast';
import org, { show as orgShow } from '@/routes/org';
import {
    index as orgEngagementsIndex,
    show as orgEngagementsShow,
    workshop as orgEngagementsWorkshop,
} from '@/routes/org/engagements';
import { index as orgHandbookExportsIndex } from '@/routes/org/handbook-exports/index';
import { show as showOrgMetric } from '@/routes/org/org-metrics';
import { update as updateSignals } from '@/routes/org/org-metrics/signals';
import type { BreadcrumbItem } from '@/types';

// Types matching backend payload
interface MetricTemplate {
    id: string;
    key: string;
    name: string;
}

interface ImplementationGaps {
    current_state: string | null;
    gap: string | null;
    owner: string | null;
    suggested_next_step: string | null;
}

interface Signals {
    org_final_definition_id: string;
    version: number | null;
    signed_off_at: string | null;
    signed_off_by: {
        id: string;
        name: string;
    } | null;
    executive_takeaway: string | null;
    alignment_summary: string | null;
    operational_status: string | null;
    operational_status_notes: string | null;
    implementation_gaps: ImplementationGaps | null;
    validation_steps: string | null;
    common_mismatch_causes: string | null;
}

interface ChecklistItem {
    label: string;
    status: 'ready' | 'missing';
}

interface MetricRow {
    id: string;
    org_metric_id: string | null;
    name: string;
    status: string;
    is_signed_off: boolean;
    template: MetricTemplate | null;
    signals: Signals | null;
    source_context: SourceContext | null;
    readiness_checklist: Record<string, ChecklistItem>;
}

interface Organization {
    id: string;
    name: string;
}

interface Engagement {
    id: string;
    title: string;
    status: string;
}

const props = defineProps<{
    organization: Organization;
    engagement: Engagement;
    metrics: MetricRow[];
}>();

// Breadcrumbs matching the app pattern
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    {
        title: 'Engagements',
        href: orgEngagementsIndex(props.organization.id).url,
    },
    {
        title: props.engagement.title,
        href: orgEngagementsShow([props.organization.id, props.engagement.id])
            .url,
    },
    { title: 'Handbook Workbench', href: '' },
];

const selectedMetricId = ref<string | null>(null);

const selectedMetric = computed(() => {
    return props.metrics.find((m) => m.id === selectedMetricId.value) || null;
});

const getMetricReadiness = (metric: MetricRow) => {
    if (!metric.is_signed_off) {
        return {
            label: 'Pending sign-off',
            tone: 'muted',
            missingLabels: [],
        };
    }
    const missing = Object.values(metric.readiness_checklist).filter(
        (item) => item.status === 'missing',
    );
    if (missing.length === 0) {
        return {
            label: 'Ready for export',
            tone: 'ready',
            missingLabels: [],
        };
    }
    return {
        label: `${missing.length} missing`,
        tone: 'missing',
        missingLabels: missing.map((item) => item.label),
    };
};

const readyMetricsCount = computed(() => {
    return props.metrics.filter((m) => getMetricReadiness(m).tone === 'ready')
        .length;
});

const checkIsSaveRequest = (visit: any) => {
    if (!isSaving.value || visit.method !== 'patch') return false;

    const saveUrl = selectedMetric.value?.org_metric_id
        ? updateSignals({
              organization: props.organization.id,
              orgMetric: selectedMetric.value.org_metric_id,
          }).url
        : null;

    return saveUrl ? visit.url?.toString().includes(saveUrl) : false;
};

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (isDirty.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

let unsubscribeRouter: (() => void) | null = null;

// Auto-select first metric on load & register page-leave listeners
onMounted(() => {
    if (props.metrics.length > 0) {
        selectedMetricId.value = props.metrics[0].id;
    }

    window.addEventListener('beforeunload', handleBeforeUnload);

    unsubscribeRouter = router.on('before', (event) => {
        // Skip prefetch requests
        if (event.detail.visit.prefetch) {
            return;
        }

        if (isDirty.value) {
            // Do not block save requests
            if (checkIsSaveRequest(event.detail.visit)) {
                return;
            }

            event.preventDefault();
            pendingAction.value = {
                type: 'inertia_visit',
                visit: event.detail.visit,
            };
            showDiscardDialog.value = true;
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    if (unsubscribeRouter) {
        unsubscribeRouter();
    }
});

const showDiscardDialog = ref(false);
const pendingAction = ref<{
    type: 'switch_metric' | 'back_navigation' | 'inertia_visit';
    targetId?: string;
    visit?: any;
} | null>(null);

const selectMetric = (id: string) => {
    if (selectedMetricId.value === id) return;
    if (isDirty.value) {
        pendingAction.value = { type: 'switch_metric', targetId: id };
        showDiscardDialog.value = true;
    } else {
        selectedMetricId.value = id;
    }
};

const goBack = () => {
    if (isDirty.value) {
        pendingAction.value = { type: 'back_navigation' };
        showDiscardDialog.value = true;
    } else {
        router.visit(
            orgEngagementsShow([props.organization.id, props.engagement.id])
                .url,
        );
    }
};

const handleDialogUpdate = (open: boolean) => {
    showDiscardDialog.value = open;
    if (!open) {
        pendingAction.value = null;
    }
};

const closeDiscardDialog = () => {
    handleDialogUpdate(false);
};

const confirmDiscard = () => {
    const action = pendingAction.value;
    showDiscardDialog.value = false;
    if (action) {
        if (action.type === 'switch_metric' && action.targetId) {
            editorRef.value?.reset();
            isEditing.value = false;
            selectedMetricId.value = action.targetId;
        } else if (action.type === 'back_navigation') {
            editorRef.value?.reset();
            isEditing.value = false;
            router.visit(
                orgEngagementsShow([props.organization.id, props.engagement.id])
                    .url,
            );
        } else if (action.type === 'inertia_visit' && action.visit) {
            editorRef.value?.reset();
            isEditing.value = false;
            router.visit(action.visit.url, action.visit);
        }
    }
    pendingAction.value = null;
};

// Edit state management for signals editor component
const isEditing = ref(false);
const isSaving = ref(false);
const { toast } = useToast();
const isListCollapsed = ref(false);

const editorRef = ref<InstanceType<typeof HandbookSignalEditor> | null>(null);

const isDirty = computed(() => {
    return editorRef.value?.isDirty ?? false;
});

const startEditing = () => {
    isEditing.value = true;
};

const cancelEditing = () => {
    editorRef.value?.reset();
    isEditing.value = false;
};

const saveSignals = () => {
    if (
        !selectedMetric.value ||
        !selectedMetric.value.org_metric_id ||
        !editorRef.value
    )
        return;
    isSaving.value = true;

    router.patch(
        updateSignals({
            organization: props.organization.id,
            orgMetric: selectedMetric.value.org_metric_id,
        }).url,
        buildSignalPayload(editorRef.value.draft),
        {
            onSuccess: () => {
                isEditing.value = false;
                toast('success', 'Official metric signals updated.');
            },
            onError: (errors) => {
                const message = Object.values(errors).join(' ');
                toast('error', message || 'Failed to update signals.');
            },
            onFinish: () => {
                isSaving.value = false;
            },
            preserveScroll: true,
        },
    );
};

// Reset edit state when selected metric changes
watch(selectedMetricId, () => {
    isEditing.value = false;
});
</script>

<template>
    <Head title="Handbook Workbench" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <header
                class="flex flex-col gap-4 border-b border-border/60 pb-0 md:flex-row md:items-center md:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="goBack"
                            class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-background text-muted-foreground transition-colors hover:text-foreground"
                            aria-label="Back to engagement"
                            data-test="back-link"
                        >
                            <ArrowLeft class="size-4" />
                        </button>
                        <h1
                            class="text-2xl font-bold tracking-tight text-foreground"
                        >
                            Handbook Workbench
                        </h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Inspect metrics alignment and consultant-authored
                        takeaways for the executive handbook.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs text-muted-foreground"
                            >Engagement Status:</span
                        >
                        <Badge
                            variant="outline"
                            class="px-2.5 py-1 text-xs font-semibold capitalize"
                        >
                            {{ props.engagement.status.replaceAll('_', ' ') }}
                        </Badge>
                    </div>
                    <Link
                        :href="
                            orgHandbookExportsIndex(props.organization.id).url
                        "
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow transition-colors hover:bg-primary/90"
                        data-test="handbook-exports-link"
                    >
                        Go to Handbook Exports
                        <ExternalLink class="size-3.5" />
                    </Link>
                </div>
            </header>

            <!-- Dual-pane Layout -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch">
                <!-- Left Pane: Metric List -->
                <div
                    v-if="!isListCollapsed"
                    class="flex w-full flex-col overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm lg:sticky lg:top-4 lg:max-h-[calc(100vh-120px)] lg:w-[380px] lg:self-start lg:overflow-y-auto"
                >
                    <!-- Header with Collapse Trigger -->
                    <div
                        class="flex items-center justify-between border-b border-border/60 bg-muted/10 p-3 px-3.5"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Metrics</span
                            >
                            <span
                                class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                data-test="page-readiness-summary"
                            >
                                {{ readyMetricsCount }}/{{
                                    props.metrics.length
                                }}
                                Ready
                            </span>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            @click="isListCollapsed = true"
                            class="h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                            title="Collapse metric list"
                            data-test="collapse-metric-list-btn"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>
                    </div>

                    <!-- List of Metrics -->
                    <div class="divide-y divide-border/40">
                        <template v-if="props.metrics.length > 0">
                            <button
                                v-for="metric in props.metrics"
                                :key="metric.id"
                                @click="selectMetric(metric.id)"
                                class="flex w-full flex-col gap-2 p-3.5 text-left transition-all hover:bg-muted/30 focus:outline-none"
                                :class="
                                    selectedMetricId === metric.id
                                        ? 'border-l-2 border-primary bg-primary/5 hover:bg-primary/5'
                                        : 'border-l-2 border-transparent'
                                "
                            >
                                <div
                                    class="flex w-full items-start justify-between gap-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-sm leading-tight font-semibold"
                                            :class="
                                                selectedMetricId === metric.id
                                                    ? 'text-primary'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ metric.name }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="metric.template"
                                        class="shrink-0 rounded border border-border/40 bg-muted/60 px-1 py-0.5 font-mono text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        {{ metric.template.key }}
                                    </span>
                                </div>

                                <!-- Composite Readiness Indicator -->
                                <div
                                    class="flex w-full flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-semibold"
                                        :class="
                                            getMetricReadiness(metric).tone ===
                                            'ready'
                                                ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                : getMetricReadiness(metric)
                                                        .tone === 'missing'
                                                  ? 'border-amber-200/60 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400'
                                                  : 'border-border bg-muted text-muted-foreground'
                                        "
                                        data-test="composite-readiness-indicator"
                                    >
                                        {{ getMetricReadiness(metric).label }}
                                    </span>
                                </div>

                                <!-- Secondary missing detail -->
                                <p
                                    v-if="
                                        getMetricReadiness(metric).missingLabels
                                            .length > 0
                                    "
                                    class="text-[11px] leading-normal text-muted-foreground"
                                    data-test="secondary-missing-detail"
                                >
                                    <span class="font-medium">Missing:</span>
                                    {{
                                        getMetricReadiness(
                                            metric,
                                        ).missingLabels.join(', ')
                                    }}
                                </p>
                            </button>
                        </template>
                        <div
                            v-else
                            class="p-8 text-center text-xs text-muted-foreground"
                        >
                            No metrics available.
                        </div>
                    </div>
                </div>

                <!-- Collapsed Left Pane: Metric Rail -->
                <div
                    v-else
                    class="hidden w-16 shrink-0 flex-col items-center gap-4 rounded-xl border border-border/80 bg-card py-4 shadow-sm lg:sticky lg:top-4 lg:flex lg:max-h-[calc(100vh-120px)] lg:self-start"
                    data-test="collapsed-metric-rail"
                >
                    <!-- Expand Button -->
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="isListCollapsed = false"
                        class="h-10 w-10 p-0 text-muted-foreground hover:text-foreground"
                        title="Expand metric list"
                        data-test="expand-metric-list-rail-btn"
                    >
                        <ChevronRight class="size-5" />
                    </Button>

                    <div class="w-full border-t border-border/60"></div>

                    <!-- Metric Count Indicator (Ready / Total) -->
                    <div
                        class="cursor-default rounded bg-muted px-1 py-0.5 text-[9px] font-bold whitespace-nowrap text-muted-foreground select-none"
                        :title="`${readyMetricsCount} of ${props.metrics.length} metrics ready`"
                        data-test="collapsed-metric-count"
                    >
                        {{ readyMetricsCount }}/{{ props.metrics.length }}
                    </div>

                    <div class="w-full border-t border-border/60"></div>

                    <!-- List of Metric Indices -->
                    <div
                        class="flex w-full flex-col items-center gap-3 overflow-y-auto px-2"
                    >
                        <button
                            v-for="(metric, index) in props.metrics"
                            :key="metric.id"
                            @click="selectMetric(metric.id)"
                            class="relative flex size-10 items-center justify-center rounded-lg border text-sm font-semibold transition-all focus:outline-none"
                            :class="
                                selectedMetricId === metric.id
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border/60 bg-muted/20 text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                            "
                            :title="`${metric.name} (${getMetricReadiness(metric).label})`"
                            :aria-label="`${metric.name} (${getMetricReadiness(metric).label})`"
                            :data-test="`rail-metric-btn-${metric.id}`"
                        >
                            {{ index + 1 }}

                            <!-- Readiness Dot -->
                            <span
                                class="absolute top-1 right-1 size-2 rounded-full ring-1 ring-background"
                                :class="
                                    getMetricReadiness(metric).tone === 'ready'
                                        ? 'bg-emerald-500'
                                        : getMetricReadiness(metric).tone ===
                                            'missing'
                                          ? 'bg-amber-500'
                                          : 'bg-muted-foreground/45'
                                "
                                data-test="rail-metric-readiness-dot"
                            ></span>
                        </button>
                    </div>
                </div>

                <!-- Right Pane: Details -->
                <div
                    class="flex flex-1 flex-col overflow-hidden rounded-xl border border-border/80 bg-card shadow-sm"
                >
                    <template v-if="selectedMetric">
                        <!-- Detail Header -->
                        <div
                            class="flex flex-wrap items-center justify-between gap-4 border-b border-border/60 bg-muted/10 p-4 sm:p-5"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <Button
                                    v-if="isListCollapsed"
                                    variant="outline"
                                    size="sm"
                                    @click="isListCollapsed = false"
                                    class="flex h-8 shrink-0 items-center gap-1.5 px-2 text-xs lg:hidden"
                                    data-test="expand-metric-list-btn"
                                    title="Expand metric list"
                                >
                                    <ChevronRight class="size-4" />
                                    Metrics
                                </Button>
                                <div class="min-w-0 space-y-1">
                                    <h2
                                        class="truncate text-lg font-bold text-foreground"
                                    >
                                        {{ selectedMetric.name }}
                                    </h2>
                                    <div
                                        class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground"
                                        data-test="selected-metric-metadata"
                                    >
                                        <span
                                            v-if="selectedMetric.template"
                                            class="rounded-md border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{ selectedMetric.template.key }}
                                        </span>
                                        <span>•</span>
                                        <span class="capitalize"
                                            >Status:
                                            {{
                                                selectedMetric.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )
                                            }}</span
                                        >
                                        <!-- Deep link to Org Metric details -->
                                        <template
                                            v-if="selectedMetric.org_metric_id"
                                        >
                                            <span>•</span>
                                            <Link
                                                :href="
                                                    showOrgMetric({
                                                        organization:
                                                            props.organization
                                                                .id,
                                                        orgMetric:
                                                            selectedMetric.org_metric_id,
                                                    }).url
                                                "
                                                class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                                                data-test="org-metric-details-link"
                                            >
                                                View Org Metric
                                                <ExternalLink class="size-3" />
                                            </Link>
                                        </template>
                                        <template
                                            v-if="
                                                selectedMetric.is_signed_off &&
                                                selectedMetric.signals
                                            "
                                        >
                                            <span>•</span>
                                            <span
                                                v-if="
                                                    selectedMetric.signals
                                                        .version
                                                "
                                                data-test="selected-metric-version"
                                                >v{{
                                                    selectedMetric.signals
                                                        .version
                                                }}</span
                                            >
                                            <span
                                                v-if="
                                                    selectedMetric.signals
                                                        .signed_off_at
                                                "
                                                data-test="selected-metric-signed-at"
                                            >
                                                Signed
                                                {{
                                                    new Date(
                                                        selectedMetric.signals
                                                            .signed_off_at,
                                                    ).toLocaleDateString()
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    selectedMetric.signals
                                                        .signed_off_by
                                                "
                                                data-test="selected-metric-signed-by"
                                            >
                                                by
                                                {{
                                                    selectedMetric.signals
                                                        .signed_off_by.name
                                                }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <template v-if="selectedMetric.is_signed_off">
                                    <!-- Responsive Source Context Sheet Button -->
                                    <Sheet>
                                        <SheetTrigger as-child>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-8 gap-1.5 text-xs"
                                                :class="
                                                    isListCollapsed
                                                        ? 'lg:hidden'
                                                        : 'xl:hidden'
                                                "
                                                data-test="open-source-context-sheet-btn"
                                            >
                                                <BookOpen class="size-3.5" />
                                                Source Context
                                            </Button>
                                        </SheetTrigger>
                                        <SheetContent
                                            class="w-full overflow-y-auto sm:max-w-md"
                                        >
                                            <SheetHeader class="pb-4">
                                                <SheetTitle
                                                    >Source Context</SheetTitle
                                                >
                                                <SheetDescription>
                                                    Reference definition fields
                                                    and workshop evidence for
                                                    {{ selectedMetric.name }}.
                                                </SheetDescription>
                                            </SheetHeader>
                                            <MetricSourceContext
                                                :source-context="
                                                    selectedMetric.source_context
                                                "
                                            />
                                        </SheetContent>
                                    </Sheet>

                                    <template v-if="isEditing">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="cancelEditing"
                                            :disabled="isSaving"
                                            class="h-8 px-3 text-xs"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            size="sm"
                                            @click="saveSignals"
                                            :disabled="isSaving || !isDirty"
                                            class="h-8 px-3 text-xs"
                                        >
                                            {{
                                                isSaving ? 'Saving...' : 'Save'
                                            }}
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="startEditing"
                                            class="h-8 px-3 text-xs"
                                        >
                                            Edit
                                        </Button>
                                    </template>
                                </template>
                            </div>
                        </div>

                        <!-- Detail Content -->
                        <div class="space-y-6 p-5 sm:p-6">
                            <!-- Signed Off States -->
                            <template
                                v-if="
                                    selectedMetric.is_signed_off &&
                                    selectedMetric.signals
                                "
                            >
                                <div
                                    class="grid grid-cols-1 gap-6"
                                    :class="
                                        isListCollapsed
                                            ? 'lg:grid-cols-[1fr_320px] xl:grid-cols-2'
                                            : 'xl:grid-cols-[minmax(0,1fr)_360px]'
                                    "
                                >
                                    <!-- Signal Editor (primary) -->
                                    <div class="space-y-4">
                                        <HandbookSignalEditor
                                            ref="editorRef"
                                            :signals="selectedMetric.signals"
                                            :checklist="
                                                selectedMetric.readiness_checklist
                                            "
                                            :source-context="
                                                selectedMetric.source_context
                                            "
                                            :is-editing="isEditing"
                                            :is-saving="isSaving"
                                            @save="saveSignals"
                                        />
                                    </div>

                                    <!-- Source Context Panel (sidebar) -->
                                    <aside
                                        class="min-w-0"
                                        :class="
                                            isListCollapsed
                                                ? 'hidden lg:block'
                                                : 'hidden xl:block'
                                        "
                                        data-test="source-context-panel"
                                    >
                                        <div class="sticky top-4 space-y-3">
                                            <h3
                                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                            >
                                                Source Context
                                            </h3>
                                            <MetricSourceContext
                                                :source-context="
                                                    selectedMetric.source_context
                                                "
                                            />
                                        </div>
                                    </aside>
                                </div>
                            </template>

                            <!-- Unsigned State -->
                            <template v-else>
                                <div class="space-y-6">
                                    <!-- Compact Not-Signed Banner -->
                                    <div
                                        class="rounded-xl border border-amber-200/60 bg-amber-50/50 p-4 backdrop-blur-sm dark:border-amber-900/30 dark:bg-amber-950/10"
                                        data-test="unsigned-metric-banner"
                                    >
                                        <div
                                            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                                        >
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-amber-200/50 bg-amber-50 dark:border-amber-950 dark:bg-amber-950/40"
                                                >
                                                    <Lock
                                                        class="size-4 text-amber-600 dark:text-amber-400"
                                                    />
                                                </div>
                                                <div>
                                                    <h4
                                                        class="text-sm font-semibold text-foreground"
                                                    >
                                                        Consultant Signals
                                                        Unsigned
                                                    </h4>
                                                    <p
                                                        class="mt-0.5 text-xs leading-normal text-muted-foreground"
                                                    >
                                                        Official consultant
                                                        signals, takeaways, and
                                                        operational notes will
                                                        become editable once the
                                                        metric has been
                                                        reconciled and signed
                                                        off in the workshop.
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="shrink-0">
                                                <Link
                                                    :href="
                                                        orgEngagementsWorkshop(
                                                            [
                                                                props
                                                                    .organization
                                                                    .id,
                                                                props.engagement
                                                                    .id,
                                                            ],
                                                            {
                                                                query: {
                                                                    metric: selectedMetric.id,
                                                                },
                                                            },
                                                        ).url
                                                    "
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200/50 bg-background px-3 py-1.5 text-xs font-semibold text-foreground shadow-sm transition-colors hover:bg-muted/50 dark:border-amber-900/30"
                                                    data-test="unsigned-workshop-link"
                                                >
                                                    Go to Workshop Alignment
                                                    <ExternalLink
                                                        class="size-3"
                                                    />
                                                </Link>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Disabled/Skeleton Signal Editor -->
                                    <div
                                        class="pointer-events-none opacity-60 select-none"
                                        data-test="unsigned-signal-editor-wrapper"
                                    >
                                        <HandbookSignalEditor
                                            :signals="null"
                                            :checklist="
                                                selectedMetric.readiness_checklist
                                            "
                                            :source-context="null"
                                            :is-editing="false"
                                            :is-saving="false"
                                            is-unsigned
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Empty State (No selection) -->
                    <div
                        v-else
                        class="flex flex-1 flex-col items-center justify-center p-8 text-center"
                    >
                        <div
                            class="mb-4 flex size-12 items-center justify-center rounded-full bg-muted"
                        >
                            <Info class="size-6 text-muted-foreground/80" />
                        </div>
                        <h3 class="mb-1 text-base font-bold text-foreground">
                            No Metric Selected
                        </h3>
                        <p
                            class="max-w-xs text-xs leading-relaxed text-muted-foreground"
                        >
                            Select a metric from the sidebar list to view its
                            consultant sign-off details and alignment signals.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="showDiscardDialog" @update:open="handleDialogUpdate">
            <DialogContent
                class="max-w-md"
                data-test="discard-confirmation-dialog"
            >
                <DialogHeader>
                    <DialogTitle>Unsaved Changes</DialogTitle>
                    <DialogDescription>
                        You have unsaved changes. Are you sure you want to
                        discard them?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="closeDiscardDialog"
                        data-test="stay-btn"
                    >
                        Stay
                    </Button>
                    <Button
                        variant="destructive"
                        @click="confirmDiscard"
                        data-test="discard-btn"
                    >
                        Discard
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
