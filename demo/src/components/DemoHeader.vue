<script setup lang="ts">
import { Check, RotateCcw } from 'lucide-vue-next';

import {
    useDemoState,
    type DemoStep,
} from '../composables/useDemoState';
import {
    GOLDEN_PATH_ENGAGEMENT,
    GOLDEN_PATH_ORG,
} from '../data/goldenPath';

const { step, mrrChoice, mrrApproved, goToStep, startOver } = useDemoState();

interface StepConfig {
    id: DemoStep;
    number: number;
    label: string;
    subtitle: string;
}

const steps: StepConfig[] = [
    {
        id: 'workshop',
        number: 1,
        label: 'Workshop',
        subtitle: 'Resolve MRR discrepancy',
    },
    {
        id: 'workbench',
        number: 2,
        label: 'Handbook Workbench',
        subtitle: 'Approve & publish',
    },
    {
        id: 'handbook',
        number: 3,
        label: 'Client Handbook',
        subtitle: 'Published definition & PDF',
    },
];

function isStepComplete(id: DemoStep): boolean {
    if (id === 'workshop') {
        return mrrChoice.value !== null;
    }
    if (id === 'workbench') {
        return mrrApproved.value;
    }
    return false;
}
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b border-border bg-card/95 backdrop-blur supports-[backdrop-filter]:bg-card/85"
    >
        <div class="mx-auto max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
            <div
                class="flex flex-wrap items-center justify-between gap-3"
            >
                <!-- Brand & Engagement Context -->
                <div class="flex min-w-0 items-center gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-950 p-1.5 shadow-sm"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 200 200"
                            xmlns="http://www.w3.org/2000/svg"
                            class="size-full"
                        >
                            <rect
                                x="30"
                                y="120"
                                width="35"
                                height="50"
                                fill="#0280be"
                            />
                            <rect
                                x="85"
                                y="90"
                                width="35"
                                height="80"
                                fill="#0280be"
                            />
                            <rect
                                x="140"
                                y="70"
                                width="35"
                                height="100"
                                fill="#0280be"
                            />
                            <path
                                d="M47 90 C 70 17, 155 42, 155 42"
                                stroke="#0280be"
                                stroke-width="20"
                                fill="none"
                            />
                            <circle cx="47" cy="90" r="18" fill="#0280be" />
                            <circle cx="155" cy="42" r="18" fill="#0280be" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="text-sm font-bold tracking-tight text-foreground"
                            >
                                Metric Harmony Console
                            </span>
                            <span
                                class="rounded-full border border-brand-200 bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700"
                            >
                                Interactive Demo
                            </span>
                        </div>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ GOLDEN_PATH_ORG.name }} ·
                            <span class="font-medium text-foreground/80">
                                {{ GOLDEN_PATH_ENGAGEMENT.title }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Start Over Action -->
                <button
                    type="button"
                    data-test="start-over-btn"
                    @click="startOver"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-semibold text-foreground shadow-xs transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <RotateCcw class="size-3.5 text-muted-foreground" />
                    Start over
                </button>
            </div>

            <!-- 3-Step Guided Journey -->
            <nav
                aria-label="Demo steps"
                class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3"
            >
                <button
                    v-for="item in steps"
                    :key="item.id"
                    type="button"
                    :data-test="`nav-step-${item.id}`"
                    :aria-current="step === item.id ? 'step' : undefined"
                    @click="goToStep(item.id)"
                    class="flex items-center gap-3 rounded-lg border px-3 py-2 text-left transition-all"
                    :class="
                        step === item.id
                            ? 'border-primary bg-brand-50/70 shadow-xs'
                            : isStepComplete(item.id)
                              ? 'border-emerald-200 bg-emerald-50/40 hover:bg-emerald-50/70'
                              : 'border-border/80 bg-background/60 hover:bg-muted/60'
                    "
                >
                    <span
                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                        :class="
                            step === item.id
                                ? 'bg-primary text-primary-foreground'
                                : isStepComplete(item.id)
                                  ? 'bg-emerald-600 text-white'
                                  : 'bg-muted text-muted-foreground'
                        "
                    >
                        <Check
                            v-if="isStepComplete(item.id) && step !== item.id"
                            class="size-3.5"
                        />
                        <template v-else>{{ item.number }}</template>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-xs font-semibold"
                            :class="
                                step === item.id
                                    ? 'text-brand-800'
                                    : 'text-foreground'
                            "
                        >
                            {{ item.label }}
                        </span>
                        <span
                            class="block truncate text-[11px] text-muted-foreground"
                        >
                            {{ item.subtitle }}
                        </span>
                    </span>
                </button>
            </nav>
        </div>
    </header>
</template>
