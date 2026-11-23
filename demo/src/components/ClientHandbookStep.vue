<script setup lang="ts">
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Download,
    ExternalLink,
    FileText,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import {
    useDemoState,
    type HandbookStatusFilter,
} from '../composables/useDemoState';
import {
    GOLDEN_PATH_ENGAGEMENT,
    GOLDEN_PATH_ORG,
    MRR_SNAPSHOT_OPTIONS,
} from '../data/goldenPath';

const {
    mrrChoice,
    mrrApproved,
    metrics,
    handbookMetricKey,
    handbookStatusFilter,
    operationalReadyCount,
    needsFollowUpCount,
    implementationGapCount,
    pdfInfo,
    goToStep,
} = useDemoState();

const historyExpanded = ref(false);

const statusFilters: Array<{ key: HandbookStatusFilter; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'needs_attention', label: 'Needs attention' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'finalized', label: 'Finalized' },
];

const statusCounts = computed<Record<HandbookStatusFilter, number>>(() => {
    const counts: Record<HandbookStatusFilter, number> = {
        all: metrics.value.length,
        needs_attention: 0,
        in_progress: 0,
        finalized: 0,
    };
    for (const metric of metrics.value) {
        counts[metric.urgency] += 1;
    }
    return counts;
});

const filteredMetrics = computed(() => {
    if (handbookStatusFilter.value === 'all') {
        return metrics.value;
    }
    return metrics.value.filter(
        (metric) => metric.urgency === handbookStatusFilter.value,
    );
});

const selectedMetric = computed(
    () =>
        metrics.value.find((m) => m.key === handbookMetricKey.value) ??
        metrics.value[0],
);

const isSelectedMrr = computed(() => selectedMetric.value.key === 'golden-mrr');
</script>

<template>
    <div class="space-y-6" data-test="handbook-view">
        <!-- Client Handbook Header + PDF Action -->
        <header
            class="flex flex-col gap-4 border-b border-border/70 pb-4 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    Client Handbook
                </h1>
                <p class="text-sm text-muted-foreground">
                    Approved metric definitions for {{ GOLDEN_PATH_ORG.name }}.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <span
                    data-test="pdf-status-badge"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold"
                    :class="
                        pdfInfo.isSample
                            ? 'border-amber-300 bg-amber-50 text-amber-900'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    "
                >
                    <FileText class="size-3.5" />
                    {{ pdfInfo.badgeLabel }}
                </span>

                <a
                    :href="pdfInfo.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-test="pdf-download-link"
                    class="inline-flex items-center gap-2 rounded-lg border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground shadow-xs transition-colors hover:bg-muted"
                >
                    <Download class="size-3.5" />
                    {{ pdfInfo.buttonLabel }}
                    <ExternalLink class="size-3 text-muted-foreground" />
                </a>
            </div>
        </header>

        <!-- Sample PDF Notice whenever bundled PDF differs from chosen/approved definition -->
        <section
            v-if="pdfInfo.isSample"
            data-test="sample-pdf-notice"
            class="rounded-xl border border-amber-200 bg-amber-50/80 p-4"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0 text-amber-700"
                />
                <div class="space-y-1 text-xs leading-relaxed text-amber-950">
                    <p class="font-bold">
                        Bundled PDF is a Sample PDF (Month-end close baseline)
                    </p>
                    <p>{{ pdfInfo.reason }}</p>
                </div>
            </div>
        </section>

        <section
            v-else
            data-test="matched-pdf-notice"
            class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4"
        >
            <div class="flex items-start gap-2.5 text-xs text-emerald-950">
                <CheckCircle2
                    class="mt-0.5 size-4 shrink-0 text-emerald-700"
                />
                <p>{{ pdfInfo.reason }}</p>
            </div>
        </section>

        <!-- Executive Brief Card -->
        <section
            class="rounded-xl border border-border bg-card p-5 shadow-xs"
            data-test="executive-brief"
        >
            <div class="space-y-1">
                <h2 class="text-base font-bold text-foreground">
                    Executive brief
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ GOLDEN_PATH_ENGAGEMENT.title }} ·
                    {{ GOLDEN_PATH_ENGAGEMENT.reviewCadence }} cadence
                </p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-lg border border-border/70 bg-muted/30 px-4 py-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Next review
                    </p>
                    <p class="mt-1.5 text-base font-semibold text-foreground">
                        {{ GOLDEN_PATH_ENGAGEMENT.nextReviewLabel }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-border/70 bg-muted/30 px-4 py-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Metric readiness
                    </p>
                    <p
                        data-test="handbook-readiness-summary"
                        class="mt-1.5 text-base font-semibold text-foreground"
                    >
                        {{ operationalReadyCount }} ready /
                        {{ needsFollowUpCount }} needs follow-up
                    </p>
                </div>

                <div
                    class="rounded-lg border border-border/70 bg-muted/30 px-4 py-3"
                >
                    <p
                        class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Implementation gaps
                    </p>
                    <p
                        data-test="handbook-gap-summary"
                        class="mt-1.5 text-base font-semibold text-foreground"
                    >
                        {{ implementationGapCount }}
                        {{
                            implementationGapCount === 1 ? 'metric' : 'metrics'
                        }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Handbook Metrics Table -->
        <section class="rounded-xl border border-border bg-card p-5 shadow-xs">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-base font-bold text-foreground">
                        Handbook metrics
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Select any metric below to inspect its official
                        definition and approval record.
                    </p>
                </div>

                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="filter in statusFilters"
                        :key="filter.key"
                        type="button"
                        @click="handbookStatusFilter = filter.key"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors"
                        :class="
                            handbookStatusFilter === filter.key
                                ? 'border-brand-200 bg-brand-50 text-brand-800'
                                : 'border-border bg-card text-muted-foreground hover:bg-muted/50'
                        "
                    >
                        <span>{{ filter.label }}</span>
                        <span
                            class="rounded-full bg-background/80 px-1.5 py-0.5 text-[10px] font-semibold text-foreground"
                        >
                            {{ statusCounts[filter.key] }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                class="mt-4 overflow-x-auto rounded-lg border border-border/80"
            >
                <table class="w-full min-w-[540px] text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-border/70 bg-muted/20 text-xs font-semibold text-muted-foreground"
                        >
                            <th class="px-4 py-2.5">Metric</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Owner</th>
                            <th class="px-4 py-2.5">Engagement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="metric in filteredMetrics"
                            :key="metric.key"
                            :data-test="`handbook-row-${metric.key}`"
                            @click="handbookMetricKey = metric.key"
                            class="cursor-pointer transition-colors"
                            :class="
                                selectedMetric.key === metric.key
                                    ? 'bg-brand-50/50'
                                    : 'hover:bg-muted/30'
                            "
                        >
                            <td class="px-4 py-3 font-semibold text-foreground">
                                {{ metric.name }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        metric.isSignedOff
                                            ? 'bg-emerald-600 text-white'
                                            : 'bg-amber-100 text-amber-900'
                                    "
                                >
                                    {{ metric.statusLabel }}
                                </span>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">
                                    {{ metric.stageLabel }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ metric.owner.name }}
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{ GOLDEN_PATH_ENGAGEMENT.title }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Selected Metric Official Definition Detail -->
        <section
            class="rounded-xl border border-border bg-card p-5 shadow-xs sm:p-6"
            data-test="handbook-metric-detail"
        >
            <!-- Pending Approval Warning if user jumped straight to Client Handbook -->
            <div
                v-if="isSelectedMrr && !mrrApproved"
                data-test="handbook-mrr-unapproved-notice"
                class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="text-xs text-amber-950">
                    <p class="font-bold">
                        MRR has not been approved in Handbook Workbench yet
                    </p>
                    <p class="mt-0.5">
                        Approve the selected MRR definition in Handbook
                        Workbench to publish v2 to this Client Handbook.
                    </p>
                </div>
                <button
                    type="button"
                    @click="goToStep('workbench')"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground hover:bg-brand-600"
                >
                    <ArrowLeft class="size-3.5" />
                    Go to Handbook Workbench
                </button>
            </div>

            <div
                class="flex flex-wrap items-start justify-between gap-3 border-b border-border/70 pb-4"
            >
                <div>
                    <p
                        class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        {{
                            selectedMetric.isSignedOff
                                ? 'Official Definition'
                                : 'Draft Preview (Unapproved)'
                        }}
                    </p>
                    <h2 class="mt-0.5 text-xl font-bold text-foreground">
                        {{ selectedMetric.name }}
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <template v-if="selectedMetric.isSignedOff">
                            Version {{ selectedMetric.version }} · Signed off
                            {{ selectedMetric.signedOffAt }} by
                            {{ selectedMetric.signedOffBy }}
                        </template>
                        <template v-else>
                            Awaiting sign-off by
                            {{ selectedMetric.approver.name }}
                        </template>
                    </p>
                </div>

                <!-- Highlight Badge for Approved MRR Snapshot Choice -->
                <div
                    v-if="isSelectedMrr && mrrChoice"
                    data-test="handbook-approved-mrr-choice"
                    class="rounded-lg border px-3 py-2 text-right"
                    :class="
                        mrrApproved
                            ? 'border-brand-200 bg-brand-50/70'
                            : 'border-amber-200 bg-amber-50/60'
                    "
                >
                    <p
                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        {{
                            mrrApproved
                                ? 'Approved Snapshot Timing'
                                : 'Draft Snapshot Timing'
                        }}
                    </p>
                    <p class="text-sm font-bold text-brand-800">
                        {{ MRR_SNAPSHOT_OPTIONS[mrrChoice].canonicalLabel }}
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <!-- Executive Alignment -->
                <div>
                    <h3
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        Executive Alignment
                    </h3>
                    <p
                        data-test="handbook-alignment-summary"
                        class="mt-2 text-sm leading-relaxed text-foreground"
                    >
                        {{ selectedMetric.alignmentSummary }}
                    </p>
                </div>

                <!-- Official Definition Fields -->
                <div>
                    <h3
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        Official Definition &amp; Rules
                    </h3>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="field in selectedMetric.definitionFields"
                            :key="field.key"
                            :data-test="`handbook-field-${field.key}`"
                            class="rounded-lg border p-3.5"
                            :class="
                                field.isInteractiveDecision
                                    ? 'border-brand-300 bg-brand-50/50 sm:col-span-2'
                                    : 'border-border/70 bg-muted/20'
                            "
                        >
                            <dt class="text-xs font-semibold text-muted-foreground">
                                {{ field.label }}
                            </dt>
                            <dd
                                class="mt-1 text-sm font-semibold text-foreground"
                            >
                                {{ field.value }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Implementation Status & Follow-up -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <div
                        class="rounded-lg border border-border/70 bg-muted/20 p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <h3
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Implementation Status
                            </h3>
                            <span
                                class="rounded border px-2 py-0.5 text-[10px] font-bold uppercase"
                                :class="
                                    selectedMetric.operationalStatus ===
                                    'operationally_ready'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-800'
                                "
                            >
                                {{
                                    selectedMetric.operationalStatus ===
                                    'operationally_ready'
                                        ? 'Ready for Use'
                                        : 'Implementation Follow-up Needed'
                                }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-foreground">
                            {{ selectedMetric.operationalStatusNotes }}
                        </p>

                        <dl
                            v-if="selectedMetric.implementationGaps"
                            class="mt-3 space-y-1.5 border-t border-border/60 pt-3 text-xs"
                        >
                            <div>
                                <dt class="font-semibold text-foreground">
                                    Gap:
                                </dt>
                                <dd class="text-muted-foreground">
                                    {{ selectedMetric.implementationGaps.gap }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-foreground">
                                    Next step ({{
                                        selectedMetric.implementationGaps.owner
                                    }}):
                                </dt>
                                <dd class="text-muted-foreground">
                                    {{
                                        selectedMetric.implementationGaps
                                            .suggestedNextStep
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div
                        class="rounded-lg border border-border/70 bg-muted/20 p-4"
                    >
                        <h3
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Operational Validation Checklist
                        </h3>
                        <p class="mt-2 text-xs leading-relaxed text-foreground">
                            {{ selectedMetric.validationSteps }}
                        </p>
                        <p
                            class="mt-3 text-xs leading-relaxed text-muted-foreground"
                        >
                            <strong class="text-foreground">
                                Common mismatch causes:
                            </strong>
                            {{ selectedMetric.commonMismatchCauses }}
                        </p>
                    </div>
                </div>

                <!-- Revision History -->
                <div class="border-t border-border/70 pt-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h3
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Revision history
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Signed versions for {{ selectedMetric.name }}.
                            </p>
                        </div>
                        <button
                            v-if="selectedMetric.revisionHistory.length > 1"
                            type="button"
                            @click="historyExpanded = !historyExpanded"
                            class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground hover:bg-muted"
                        >
                            {{
                                historyExpanded
                                    ? 'Hide history'
                                    : `View history (${selectedMetric.revisionHistory.length})`
                            }}
                            <ChevronUp
                                v-if="historyExpanded"
                                class="size-3.5"
                            />
                            <ChevronDown v-else class="size-3.5" />
                        </button>
                    </div>

                    <ol
                        v-if="
                            selectedMetric.revisionHistory.length === 1 ||
                            historyExpanded
                        "
                        class="mt-3 space-y-2.5"
                    >
                        <li
                            v-for="entry in selectedMetric.revisionHistory"
                            :key="entry.version"
                            class="rounded-lg border border-border/70 bg-muted/20 p-3 text-xs"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span class="font-bold text-foreground">
                                    Version {{ entry.version }}
                                    <span
                                        v-if="entry.snapshotTiming"
                                        class="font-normal text-muted-foreground"
                                    >
                                        — {{ entry.snapshotTiming }}
                                    </span>
                                </span>
                                <span class="text-muted-foreground">
                                    Signed off {{ entry.signedOffAt }} by
                                    {{ entry.signedOffBy }}
                                </span>
                            </div>
                            <p class="mt-1 text-muted-foreground">
                                {{ entry.notes }}
                            </p>
                        </li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
</template>
