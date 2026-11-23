<script setup lang="ts">
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    FileSpreadsheet,
    Users,
} from 'lucide-vue-next';

import { useDemoState } from '../composables/useDemoState';
import {
    GOLDEN_PATH_ENGAGEMENT,
    MEMBERS,
    MRR_SNAPSHOT_OPTIONS,
    MRR_WORKSHOP_MATRIX,
    type MrrSnapshotChoice,
} from '../data/goldenPath';

const { mrrChoice, mrrApproved, mrrMetric, selectMrrChoice, goToStep } =
    useDemoState();

const snapshotChoices: MrrSnapshotChoice[] = [
    'last_calendar_day',
    'rolling_daily',
];

function proceedToWorkbench() {
    if (!mrrChoice.value) {
        return;
    }
    goToStep('workbench');
}
</script>

<template>
    <div class="space-y-6" data-test="workshop-view">
        <!-- Active Discrepancy Banner -->
        <section
            class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-xs sm:p-5"
            data-test="mrr-discrepancy-banner"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800"
                >
                    <AlertTriangle class="size-5" />
                </div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded bg-amber-200/80 px-2 py-0.5 text-[11px] font-bold tracking-wide text-amber-900 uppercase"
                        >
                            Open Discrepancy · MRR
                        </span>
                        <span class="text-xs font-medium text-amber-900/80">
                            {{ GOLDEN_PATH_ENGAGEMENT.title }}
                        </span>
                    </div>
                    <h1
                        class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl"
                    >
                        Finance and RevOps disagree on Official MRR Snapshot Timing
                    </h1>
                    <p class="text-sm leading-relaxed text-slate-700">
                        Before publishing the executive handbook, resolve how
                        <strong>Monthly Recurring Revenue (MRR)</strong> is cut
                        for the board baseline. Finance locks MRR on the
                        <strong>month-end close</strong>, while Revenue
                        Operations tracks a <strong>rolling daily view</strong>.
                    </p>
                </div>
            </div>
        </section>

        <!-- Main Workshop Grid -->
        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Left 8 columns: Decision Cards + Intake Matrix -->
            <div class="space-y-6 lg:col-span-8">
                <!-- Interactive Decision Selection -->
                <section
                    class="rounded-xl border border-border bg-card p-5 shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 pb-4"
                    >
                        <div>
                            <h2 class="text-base font-bold text-foreground">
                                Choose the Official MRR Snapshot Timing
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Select which team's snapshot rule becomes the
                                draft definition for Handbook Workbench approval.
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/60 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <Users class="size-3.5" />
                            Nora Kim · Lia Price · Anthony Reyes
                        </span>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <button
                            v-for="choiceId in snapshotChoices"
                            :key="choiceId"
                            type="button"
                            :data-test="`mrr-choice-${choiceId}`"
                            :aria-pressed="mrrChoice === choiceId"
                            @click="selectMrrChoice(choiceId)"
                            class="group flex flex-col justify-between rounded-xl border-2 p-4 text-left transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                            :class="
                                mrrChoice === choiceId
                                    ? 'border-primary bg-brand-50/60 shadow-sm'
                                    : 'border-border bg-card hover:border-brand-300 hover:bg-muted/30'
                            "
                        >
                            <div class="space-y-3">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div>
                                        <span
                                            class="inline-block rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                            :class="
                                                choiceId === 'last_calendar_day'
                                                    ? 'bg-sky-100 text-sky-800'
                                                    : 'bg-violet-100 text-violet-800'
                                            "
                                        >
                                            {{
                                                MRR_SNAPSHOT_OPTIONS[choiceId]
                                                    .team
                                            }}
                                            ·
                                            {{
                                                MRR_SNAPSHOT_OPTIONS[choiceId]
                                                    .contributor
                                            }}
                                        </span>
                                        <h3
                                            class="mt-2 text-base font-bold text-foreground"
                                        >
                                            {{
                                                MRR_SNAPSHOT_OPTIONS[choiceId]
                                                    .shortTitle
                                            }}
                                        </h3>
                                    </div>
                                    <span
                                        class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border"
                                        :class="
                                            mrrChoice === choiceId
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-slate-300 bg-white'
                                        "
                                    >
                                        <CheckCircle2
                                            v-if="mrrChoice === choiceId"
                                            class="size-3.5"
                                        />
                                    </span>
                                </div>

                                <p class="text-sm text-foreground/90">
                                    {{
                                        MRR_SNAPSHOT_OPTIONS[choiceId].summary
                                    }}
                                </p>

                                <blockquote
                                    class="rounded-lg border border-border/80 bg-background/80 p-2.5 text-xs italic text-muted-foreground"
                                >
                                    "{{
                                        MRR_SNAPSHOT_OPTIONS[choiceId]
                                            .intakeRationale
                                    }}"
                                </blockquote>
                            </div>

                            <div class="mt-4 border-t border-border/60 pt-3">
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    <span class="font-semibold text-foreground">
                                        Board impact:
                                    </span>
                                    {{
                                        MRR_SNAPSHOT_OPTIONS[choiceId]
                                            .boardImpact
                                    }}
                                </p>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- Intake Comparison Matrix -->
                <section
                    class="overflow-hidden rounded-xl border border-border bg-card shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-border/70 bg-muted/30 px-5 py-3.5"
                    >
                        <div class="flex items-center gap-2">
                            <FileSpreadsheet
                                class="size-4 text-muted-foreground"
                            />
                            <h2 class="text-sm font-bold text-foreground">
                                Stakeholder Intake Comparison Matrix
                            </h2>
                        </div>
                        <span class="text-xs text-muted-foreground">
                            Monthly Recurring Revenue Measurement Form
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[600px] text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-border/70 bg-muted/20 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    <th class="px-4 py-3">Definition Field</th>
                                    <th class="px-4 py-3">
                                        Finance ({{ MEMBERS.finance.name }})
                                    </th>
                                    <th class="px-4 py-3">
                                        RevOps ({{ MEMBERS.revops.name }})
                                    </th>
                                    <th class="px-4 py-3">Workshop Consensus</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="row in MRR_WORKSHOP_MATRIX"
                                    :key="row.fieldKey"
                                    :class="
                                        row.isPrimaryDecision
                                            ? 'bg-amber-50/50'
                                            : ''
                                    "
                                >
                                    <td class="px-4 py-3 align-top">
                                        <div
                                            class="font-semibold text-foreground"
                                        >
                                            {{ row.label }}
                                        </div>
                                        <span
                                            v-if="row.isPrimaryDecision"
                                            class="mt-1 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900"
                                        >
                                            Active decision
                                        </span>
                                        <span
                                            v-else-if="row.hasDiscrepancy"
                                            class="mt-1 inline-block rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                                        >
                                            Reconciled
                                        </span>
                                    </td>
                                    <td
                                        class="px-4 py-3 align-top text-foreground/90"
                                    >
                                        <div class="font-medium">
                                            {{ row.financeAnswer }}
                                        </div>
                                        <p
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            {{ row.financeNotes }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-3 align-top text-foreground/90"
                                    >
                                        <div class="font-medium">
                                            {{ row.revopsAnswer }}
                                        </div>
                                        <p
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            {{ row.revopsNotes }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <template v-if="row.isPrimaryDecision">
                                            <span
                                                v-if="mrrChoice"
                                                class="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50 px-2 py-1 font-semibold text-brand-800"
                                            >
                                                {{
                                                    MRR_SNAPSHOT_OPTIONS[
                                                        mrrChoice
                                                    ].canonicalLabel
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center rounded-md border border-amber-300 bg-amber-100/70 px-2 py-1 font-semibold text-amber-900"
                                            >
                                                Select above
                                            </span>
                                        </template>
                                        <template v-else>
                                            <div
                                                class="font-medium text-foreground"
                                            >
                                                {{ row.resolvedLabel }}
                                            </div>
                                            <p
                                                class="mt-1 text-[11px] text-muted-foreground"
                                            >
                                                {{ row.resolutionNotes }}
                                            </p>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Right 4 columns: Final Definition Draft Panel -->
            <aside class="lg:col-span-4">
                <div
                    class="sticky top-32 rounded-xl border border-border bg-card p-5 shadow-xs"
                >
                    <div
                        class="flex items-center justify-between gap-2 border-b border-border/70 pb-3"
                    >
                        <div>
                            <p
                                class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Final Definition Draft
                            </p>
                            <h2 class="text-base font-bold text-foreground">
                                {{ mrrMetric.name }}
                            </h2>
                        </div>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                            :class="
                                mrrApproved
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : mrrChoice
                                      ? 'border-brand-200 bg-brand-50 text-brand-700'
                                      : 'border-amber-200 bg-amber-50 text-amber-800'
                            "
                        >
                            {{
                                mrrApproved
                                    ? 'Approved'
                                    : mrrChoice
                                      ? 'Draft ready'
                                      : 'Needs choice'
                            }}
                        </span>
                    </div>

                    <dl class="mt-4 space-y-3 text-xs">
                        <div>
                            <dt
                                class="font-semibold text-muted-foreground uppercase"
                            >
                                Metric Owner
                            </dt>
                            <dd class="mt-0.5 text-sm font-medium text-foreground">
                                {{ MEMBERS.cfo.name }} ({{
                                    MEMBERS.cfo.affiliation
                                }})
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="font-semibold text-muted-foreground uppercase"
                            >
                                Sign-off Approver
                            </dt>
                            <dd class="mt-0.5 text-sm font-medium text-foreground">
                                {{ MEMBERS.revops.name }} ({{
                                    MEMBERS.revops.affiliation
                                }})
                            </dd>
                        </div>

                        <div
                            class="rounded-lg border p-3"
                            :class="
                                mrrChoice
                                    ? 'border-brand-200 bg-brand-50/50'
                                    : 'border-dashed border-amber-300 bg-amber-50/50'
                            "
                        >
                            <dt
                                class="text-[11px] font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                Official MRR snapshot timing
                            </dt>
                            <dd
                                data-test="workshop-selected-snapshot"
                                class="mt-1 text-sm font-bold text-foreground"
                            >
                                {{
                                    mrrChoice
                                        ? MRR_SNAPSHOT_OPTIONS[mrrChoice]
                                              .canonicalLabel
                                        : 'Not selected yet'
                                }}
                            </dd>
                            <p
                                v-if="mrrChoice"
                                class="mt-1.5 text-xs leading-relaxed text-muted-foreground"
                            >
                                {{ MRR_SNAPSHOT_OPTIONS[mrrChoice].summary }}
                            </p>
                        </div>

                        <div>
                            <dt
                                class="font-semibold text-muted-foreground uppercase"
                            >
                                Other Aligned Rules
                            </dt>
                            <dd class="mt-1 space-y-1 text-muted-foreground">
                                <p>• Annual deals: Prorated evenly</p>
                                <p>• Discounts: Net recurring price</p>
                                <p>• Churn removal: Paid-through date</p>
                                <p>• Usage &amp; overages: Excluded</p>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 space-y-2 border-t border-border/70 pt-4">
                        <button
                            type="button"
                            data-test="continue-to-workbench-btn"
                            :disabled="!mrrChoice"
                            @click="proceedToWorkbench"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Continue to Handbook Workbench
                            <ArrowRight class="size-4" />
                        </button>
                        <p
                            v-if="!mrrChoice"
                            class="text-center text-[11px] text-muted-foreground"
                        >
                            Pick Finance or RevOps snapshot timing above to
                            continue.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
