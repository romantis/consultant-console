<script setup lang="ts">
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle2,
    ClipboardCheck,
    FileCheck2,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

import { useDemoState } from '../composables/useDemoState';
import {
    GOLDEN_PATH_ENGAGEMENT,
    MRR_SNAPSHOT_OPTIONS,
} from '../data/goldenPath';

const {
    mrrChoice,
    mrrApproved,
    metrics,
    readyMetricsCount,
    workbenchMetricKey,
    approveMrrInWorkbench,
    goToStep,
} = useDemoState();

const selectedMetric = computed(
    () =>
        metrics.value.find((m) => m.key === workbenchMetricKey.value) ??
        metrics.value[0],
);

const isSelectedMrr = computed(() => selectedMetric.value.key === 'golden-mrr');
</script>

<template>
    <div class="space-y-6" data-test="workbench-view">
        <!-- Workbench Header -->
        <header
            class="flex flex-col gap-4 border-b border-border/70 pb-4 md:flex-row md:items-center md:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="goToStep('workshop')"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground transition-colors hover:text-foreground"
                        aria-label="Back to Workshop"
                    >
                        <ArrowLeft class="size-4" />
                    </button>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Handbook Workbench
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Inspect metrics alignment, consultant-authored takeaways,
                    and approve MRR for the executive handbook.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">
                        Engagement Status:
                    </span>
                    <span
                        data-test="workbench-engagement-status"
                        class="rounded-md border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground"
                    >
                        {{ mrrApproved ? 'Finalized' : 'Awaiting MRR sign-off' }}
                    </span>
                </div>

                <button
                    type="button"
                    data-test="open-handbook-btn"
                    @click="goToStep('handbook')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold shadow-xs transition-colors"
                    :class="
                        mrrApproved
                            ? 'bg-primary text-primary-foreground hover:bg-brand-600'
                            : 'border border-border bg-card text-foreground hover:bg-muted'
                    "
                >
                    Open Client Handbook
                    <ArrowRight class="size-3.5" />
                </button>
            </div>
        </header>

        <!-- Dual-pane Workbench Layout -->
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <!-- Left Pane: Metric List -->
            <aside
                class="w-full overflow-hidden rounded-xl border border-border bg-card shadow-xs lg:w-[320px] lg:shrink-0"
            >
                <div
                    class="flex items-center justify-between border-b border-border/70 bg-muted/30 px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Metrics
                        </span>
                        <span
                            data-test="workbench-readiness-count"
                            class="rounded bg-muted px-1.5 py-0.5 text-[11px] font-semibold text-muted-foreground"
                        >
                            {{ readyMetricsCount }}/{{ metrics.length }} Ready
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-border/60">
                    <button
                        v-for="metric in metrics"
                        :key="metric.key"
                        type="button"
                        :data-test="`workbench-metric-${metric.key}`"
                        @click="workbenchMetricKey = metric.key"
                        class="flex w-full flex-col gap-2 border-l-3 p-4 text-left transition-colors"
                        :class="
                            selectedMetric.key === metric.key
                                ? 'border-l-primary bg-brand-50/50'
                                : 'border-l-transparent hover:bg-muted/30'
                        "
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span
                                class="text-sm leading-tight font-semibold"
                                :class="
                                    selectedMetric.key === metric.key
                                        ? 'text-brand-700'
                                        : 'text-foreground'
                                "
                            >
                                {{ metric.name }}
                            </span>
                            <span
                                class="shrink-0 rounded border border-border bg-muted/70 px-1.5 py-0.5 font-mono text-[10px] font-bold text-muted-foreground"
                            >
                                {{ metric.templateKey }}
                            </span>
                        </div>
                        <div>
                            <span
                                class="inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-semibold"
                                :class="
                                    metric.isSignedOff
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-800'
                                "
                            >
                                {{
                                    metric.isSignedOff
                                        ? 'Ready for export'
                                        : 'Pending sign-off'
                                }}
                            </span>
                        </div>
                    </button>
                </div>
            </aside>

            <!-- Right Pane: Selected Metric Details -->
            <section
                class="min-w-0 flex-1 overflow-hidden rounded-xl border border-border bg-card shadow-xs"
            >
                <!-- Detail Header -->
                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-border/70 bg-muted/20 p-5"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="text-lg font-bold text-foreground">
                            {{ selectedMetric.name }}
                        </h2>
                        <div
                            class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                class="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-bold"
                            >
                                {{ selectedMetric.templateKey }}
                            </span>
                            <span>•</span>
                            <span>Status: {{ selectedMetric.statusLabel }}</span>
                            <span>•</span>
                            <span>v{{ selectedMetric.version }}</span>
                            <template v-if="selectedMetric.isSignedOff">
                                <span>•</span>
                                <span>
                                    Signed {{ selectedMetric.signedOffAt }} by
                                    {{ selectedMetric.signedOffBy }}
                                </span>
                            </template>
                        </div>
                    </div>

                    <div v-if="isSelectedMrr" class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            @click="goToStep('workshop')"
                            class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted"
                        >
                            Change choice in Workshop
                        </button>
                    </div>
                </div>

                <!-- MRR Approval Banner (when pending or just approved) -->
                <div
                    v-if="isSelectedMrr && !mrrApproved"
                    data-test="mrr-approval-panel"
                    class="border-b border-amber-200 bg-amber-50/70 p-5"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <AlertCircle class="size-4 text-amber-700" />
                                <h3 class="text-sm font-bold text-slate-900">
                                    Ready for Sign-off Approval
                                </h3>
                            </div>
                            <p
                                v-if="mrrChoice"
                                class="text-xs leading-relaxed text-slate-700"
                            >
                                Workshop draft selects
                                <strong>
                                    {{
                                        MRR_SNAPSHOT_OPTIONS[mrrChoice]
                                            .canonicalLabel
                                    }}
                                </strong>
                                ({{ MRR_SNAPSHOT_OPTIONS[mrrChoice].team }}).
                                Approve as
                                <strong>{{ selectedMetric.approver.name }}</strong>
                                to publish v2 to the Client Handbook.
                            </p>
                            <p
                                v-else
                                class="text-xs leading-relaxed text-slate-700"
                            >
                                No snapshot timing was chosen yet in Workshop.
                                Return to Workshop to pick Finance or RevOps, or
                                approve with the default Month-end close
                                snapshot.
                            </p>
                        </div>

                        <button
                            type="button"
                            data-test="approve-mrr-btn"
                            @click="approveMrrInWorkbench"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-emerald-700"
                        >
                            <ShieldCheck class="size-4" />
                            Approve &amp; Publish MRR
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="isSelectedMrr && mrrApproved"
                    data-test="mrr-approved-banner"
                    class="border-b border-emerald-200 bg-emerald-50/70 p-4 px-5"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-2.5">
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-700"
                            />
                            <div class="text-xs text-emerald-950">
                                <p class="font-bold">
                                    MRR approved and published to Client Handbook
                                </p>
                                <p class="mt-0.5 text-emerald-900/80">
                                    Official snapshot timing:
                                    <strong>
                                        {{
                                            mrrChoice
                                                ? MRR_SNAPSHOT_OPTIONS[
                                                      mrrChoice
                                                  ].canonicalLabel
                                                : ''
                                        }}
                                    </strong>
                                    · All 3 metrics in
                                    {{ GOLDEN_PATH_ENGAGEMENT.title }} are now
                                    ready.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            data-test="workbench-next-handbook-btn"
                            @click="goToStep('handbook')"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-brand-600"
                        >
                            View in Client Handbook
                            <ArrowRight class="size-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Signals + Source Context Grid -->
                <div class="grid gap-6 p-5 xl:grid-cols-12">
                    <!-- Consultant Signals Column -->
                    <div class="space-y-4 xl:col-span-7">
                        <!-- Executive Takeaway -->
                        <article
                            class="rounded-xl border border-border/80 bg-card p-4"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Executive Takeaway
                                </h3>
                                <span
                                    class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                                    :class="
                                        selectedMetric.isSignedOff
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-800'
                                    "
                                >
                                    {{
                                        selectedMetric.isSignedOff
                                            ? 'Ready'
                                            : 'Draft'
                                    }}
                                </span>
                            </div>
                            <p
                                data-test="workbench-executive-takeaway"
                                class="mt-2 text-sm leading-relaxed text-foreground"
                            >
                                {{ selectedMetric.executiveTakeaway }}
                            </p>
                        </article>

                        <!-- Executive Alignment -->
                        <article
                            class="rounded-xl border border-border/80 bg-card p-4"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Executive Alignment
                                </h3>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                >
                                    <FileCheck2 class="size-3" />
                                    Workshop Decision
                                </span>
                            </div>
                            <p
                                class="mt-2 text-sm leading-relaxed text-foreground"
                            >
                                {{ selectedMetric.alignmentSummary }}
                            </p>
                        </article>

                        <!-- Implementation Status & Follow-up -->
                        <article
                            class="rounded-xl border border-border/80 bg-card p-4"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
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
                            <p
                                class="mt-2 text-sm leading-relaxed text-foreground"
                            >
                                {{ selectedMetric.operationalStatusNotes }}
                            </p>

                            <dl
                                v-if="selectedMetric.implementationGaps"
                                class="mt-3 space-y-2 rounded-lg border border-amber-200/80 bg-amber-50/50 p-3 text-xs"
                            >
                                <div>
                                    <dt class="font-semibold text-amber-950">
                                        Gap
                                    </dt>
                                    <dd class="text-amber-900/90">
                                        {{
                                            selectedMetric.implementationGaps
                                                .gap
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-amber-950">
                                        Owner
                                    </dt>
                                    <dd class="text-amber-900/90">
                                        {{
                                            selectedMetric.implementationGaps
                                                .owner
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-amber-950">
                                        Suggested Next Step
                                    </dt>
                                    <dd class="text-amber-900/90">
                                        {{
                                            selectedMetric.implementationGaps
                                                .suggestedNextStep
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </article>

                        <!-- Validation Steps -->
                        <article
                            class="rounded-xl border border-border/80 bg-card p-4"
                        >
                            <div class="flex items-center gap-1.5">
                                <ClipboardCheck
                                    class="size-3.5 text-muted-foreground"
                                />
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    Operational Validation Checklist
                                </h3>
                            </div>
                            <p
                                class="mt-2 text-xs leading-relaxed text-foreground/90"
                            >
                                {{ selectedMetric.validationSteps }}
                            </p>
                        </article>
                    </div>

                    <!-- Source Context Column -->
                    <div class="space-y-4 xl:col-span-5">
                        <div
                            class="rounded-xl border border-border/80 bg-muted/20 p-4"
                        >
                            <div class="flex items-center gap-1.5">
                                <BookOpen
                                    class="size-3.5 text-muted-foreground"
                                />
                                <h3
                                    class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        selectedMetric.isSignedOff
                                            ? 'Signed Definition'
                                            : 'Draft Definition'
                                    }}
                                </h3>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Metric Owner:
                                <strong class="text-foreground">
                                    {{ selectedMetric.owner.name }}
                                </strong>
                            </p>
                            <p
                                class="mt-2.5 text-xs leading-relaxed text-foreground/90"
                            >
                                {{ selectedMetric.notes }}
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-border/80 bg-card p-4"
                        >
                            <h3
                                class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Official Definition Fields
                            </h3>
                            <dl class="mt-3 space-y-3 text-xs">
                                <div
                                    v-for="field in selectedMetric.definitionFields"
                                    :key="field.key"
                                    class="rounded-lg p-2"
                                    :class="
                                        field.isInteractiveDecision
                                            ? 'border border-brand-200 bg-brand-50/50'
                                            : ''
                                    "
                                >
                                    <dt class="font-medium text-muted-foreground">
                                        {{ field.label }}
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-foreground"
                                    >
                                        {{ field.value }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
