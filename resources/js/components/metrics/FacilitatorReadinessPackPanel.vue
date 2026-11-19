<script setup lang="ts">
import GeneratedMarker from '@/components/metrics/GeneratedMarker.vue';
import type {
    FacilitatorReadinessPack,
    ReadinessDecision,
    ReadinessState,
} from '@/types';
import { ChevronRight, Lock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pack: FacilitatorReadinessPack;
}>();

const STATE_LABELS: Record<ReadinessState, string> = {
    blocked: 'Blocked',
    needs_review: 'Needs review',
    ready: 'Ready',
    in_progress: 'In progress',
    no_prep_needed: 'No prep needed',
};

const STATE_CLASSES: Record<ReadinessState, string> = {
    blocked: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    needs_review: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    ready: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    in_progress: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    no_prep_needed: 'bg-muted text-muted-foreground',
};

const TIER_LABELS: Record<ReadinessDecision['attention_tier'], string> = {
    must_resolve: 'Must resolve',
    missing_evidence: 'Missing evidence',
    needs_confirmation: 'Needs confirmation',
};

const TIER_CLASSES: Record<ReadinessDecision['attention_tier'], string> = {
    must_resolve: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    missing_evidence: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    needs_confirmation: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
};

const readiness = computed(() => props.pack.readiness);

// Workshop-level reminders only. Per-decision capture and watch-outs render
// inside their Run of Show segment (Task 5A), so the script keeps just the
// items that have no decision home. A readiness gap is shown once as an entry
// blocker (FX04), not as a capture line, so in practice only the
// unresolved-evidence risk lands here.
const workshopCaptureItems = computed(() =>
    props.pack.capture_checklist.filter((item) => item.decision_id === null),
);

const workshopRisks = computed(() =>
    props.pack.facilitation_risks.filter((risk) => risk.decision_id === null),
);

// Anchor into the matching Run of Show segment. Reuses the existing
// #field-detail-{key} navigation so clicking a decision expands, scrolls to, and
// pulses its segment — the full per-decision detail lives there, not here.
const segmentAnchor = (decision: ReadinessDecision): string =>
    `#field-detail-${decision.field_key}`;
</script>

<template>
    <section
        class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/30 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/10"
        data-test="facilitator-readiness-pack"
        aria-labelledby="readiness-pack-heading"
    >
        <!-- Header: clearly private, internal, generated preparation support. -->
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="readiness-pack-heading"
                        class="text-base font-bold text-foreground"
                    >
                        Facilitator readiness pack
                    </h2>
                    <GeneratedMarker />
                </div>
                <p
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-indigo-700 dark:text-indigo-300"
                    data-test="readiness-pack-private"
                >
                    <Lock class="size-3" aria-hidden="true" />
                    Private · internal preparation support — not shared with
                    clients.
                </p>
            </div>
            <span
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="STATE_CLASSES[readiness.state]"
                data-test="readiness-pack-state"
            >
                {{ STATE_LABELS[readiness.state] }}
            </span>
        </div>

        <!-- Readiness overview: the 60-second "can I run this?" answer. -->
        <div class="space-y-2">
            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                <span data-test="readiness-pack-decision-count">
                    <span class="font-semibold text-foreground">{{
                        readiness.decision_count
                    }}</span>
                    decision{{ readiness.decision_count === 1 ? '' : 's' }}
                </span>
                <span
                    v-if="readiness.blocker_count > 0"
                    data-test="readiness-pack-blocker-count"
                >
                    <span class="font-semibold text-foreground">{{
                        readiness.blocker_count
                    }}</span>
                    blocker{{ readiness.blocker_count === 1 ? '' : 's' }}
                </span>
                <span
                    v-if="readiness.planned_minutes > 0"
                    data-test="readiness-pack-minutes"
                >
                    <span class="font-semibold text-foreground">{{
                        readiness.planned_minutes
                    }}</span>
                    min planned
                </span>
            </div>
            <p
                class="text-sm font-medium text-foreground"
                data-test="readiness-pack-next-action"
            >
                {{ readiness.next_action }}
            </p>
            <ul
                v-if="readiness.blocker_summary.length > 0"
                class="space-y-1 text-xs text-muted-foreground"
                data-test="readiness-pack-blockers"
            >
                <li
                    v-for="(blocker, index) in readiness.blocker_summary"
                    :key="index"
                    class="border-l-2 border-amber-400 pl-2"
                    data-test="readiness-pack-blocker"
                >
                    {{ blocker.text }}
                </li>
            </ul>
        </div>

        <!-- Optional workshop thesis (empty in deterministic Phase 1). -->
        <p
            v-if="pack.workshop_thesis"
            class="rounded-md border-l-2 border-indigo-300 bg-background/50 px-3 py-2 text-sm text-foreground dark:border-indigo-800"
            data-test="readiness-pack-thesis"
        >
            {{ pack.workshop_thesis.text }}
        </p>

        <!-- Opening plan: the first move. -->
        <div
            v-if="pack.opening_plan"
            class="rounded-md border border-indigo-200 bg-background/60 p-3 dark:border-indigo-900/50"
            data-test="readiness-pack-opening"
        >
            <p
                class="text-[10px] font-bold tracking-wide text-indigo-700 uppercase dark:text-indigo-300"
            >
                Open with
            </p>
            <p
                class="mt-1 text-sm font-medium text-foreground"
                data-test="readiness-pack-opening-question"
            >
                {{ pack.opening_plan.opening_question.text }}
            </p>
            <p
                v-if="pack.opening_plan.timebox_note"
                class="mt-1 text-xs text-muted-foreground"
                data-test="readiness-pack-opening-timebox"
            >
                {{ pack.opening_plan.timebox_note }}
            </p>
            <p
                v-if="pack.opening_plan.fallback_question"
                class="mt-1 text-xs text-muted-foreground"
                data-test="readiness-pack-opening-fallback"
            >
                Fallback: {{ pack.opening_plan.fallback_question.text }}
            </p>
        </div>

        <!-- Compact ordered decision index. Each row links into the matching Run
        of Show segment, where the source positions, facilitation assist, and
        capture checklist live — the script does not re-render that detail. -->
        <div class="space-y-1.5">
            <p
                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
            >
                Decisions to facilitate
            </p>
            <ol class="space-y-1.5" data-test="readiness-decision-index">
                <li
                    v-for="(decision, index) in pack.decisions"
                    :key="decision.id"
                    data-test="readiness-decision-index-item"
                    :data-test-id="decision.id"
                >
                    <a
                        :href="segmentAnchor(decision)"
                        class="group flex items-start gap-2 rounded-md border border-transparent bg-background/50 px-2 py-1.5 hover:border-indigo-200 hover:bg-background dark:hover:border-indigo-900/50"
                        data-test="readiness-decision-link"
                    >
                        <span
                            class="mt-0.5 text-xs font-semibold text-muted-foreground tabular-nums"
                            >{{ index + 1 }}.</span
                        >
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-semibold text-foreground"
                                    data-test="readiness-decision-index-label"
                                >
                                    {{ decision.field_label }}
                                </span>
                                <span
                                    class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="
                                        TIER_CLASSES[decision.attention_tier]
                                    "
                                    data-test="readiness-decision-index-tier"
                                >
                                    {{ TIER_LABELS[decision.attention_tier] }}
                                </span>
                            </span>
                            <span
                                class="mt-0.5 block text-xs text-muted-foreground"
                                data-test="readiness-decision-index-question"
                            >
                                {{ decision.decision_question.text }}
                            </span>
                        </span>
                        <ChevronRight
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </a>
                </li>
            </ol>
        </div>

        <!-- Workshop-level closing reminders (only non-decision-bound items). -->
        <div
            v-if="workshopCaptureItems.length > 0 || workshopRisks.length > 0"
            class="space-y-2 border-t border-indigo-200 pt-3 dark:border-indigo-900/50"
            data-test="readiness-workshop-notes"
        >
            <p
                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
            >
                Before you close
            </p>
            <ul class="space-y-1 text-xs text-muted-foreground">
                <li
                    v-for="item in workshopCaptureItems"
                    :key="item.id"
                    data-test="readiness-workshop-capture"
                >
                    {{ item.label }}
                </li>
                <li
                    v-for="risk in workshopRisks"
                    :key="risk.id"
                    data-test="readiness-workshop-risk"
                >
                    {{ risk.label }}
                </li>
            </ul>
        </div>
    </section>
</template>
