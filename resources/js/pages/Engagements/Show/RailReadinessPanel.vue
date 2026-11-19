<script setup lang="ts">
import { computed } from 'vue';

type RailReadiness = {
    intake_ready: boolean;
    forms_ready: boolean;
    review_ready: boolean;
    sign_off_ready: boolean;
    handbook_ready: boolean;
    blockers: string[];
};

const props = defineProps<{
    readiness: RailReadiness;
}>();

const items = computed(() => [
    {
        key: 'intake',
        label: 'Launch',
        ready: props.readiness.intake_ready,
    },
    {
        key: 'forms',
        label: 'Forms',
        ready: props.readiness.forms_ready,
    },
    {
        key: 'review',
        label: 'Review / Workshop',
        ready: props.readiness.review_ready,
    },
    {
        key: 'sign-off',
        label: 'Sign-off',
        ready: props.readiness.sign_off_ready,
    },
    {
        key: 'handbook',
        label: 'Handbook',
        ready: props.readiness.handbook_ready,
    },
]);

const blockers = computed(() =>
    (props.readiness.blockers ?? []).filter((value) => value?.trim() !== ''),
);

const readyCount = computed(
    () => items.value.filter((item) => item.ready).length,
);
</script>

<template>
    <section
        class="rounded-xl border border-border/70 bg-gradient-to-br from-background to-muted/30 p-4 shadow-sm"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="space-y-0.5">
                <p
                    class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Rail readiness
                </p>
                <p class="text-xs text-muted-foreground">
                    Clear the first blocked stage to keep momentum.
                </p>
            </div>
            <p
                data-test="rail-readiness-summary"
                class="rounded-full border border-border/80 bg-background/80 px-2.5 py-1 text-[11px] font-medium text-muted-foreground dark:border-border/60 dark:bg-background/60"
            >
                {{ readyCount }}/{{ items.length }} ready
            </p>
        </div>

        <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="item in items"
                :key="item.key"
                :data-test="`rail-readiness-${item.key}`"
                class="rounded-lg border px-3 py-2.5 text-xs transition-colors"
                :class="
                    item.ready
                        ? 'border-emerald-200/70 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950/50 dark:text-emerald-50'
                        : 'border-amber-200/80 bg-amber-50/90 text-amber-900 dark:border-amber-500/40 dark:bg-amber-950/50 dark:text-amber-50'
                "
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="flex items-center gap-2 font-medium">
                        <span
                            class="inline-flex size-2 rounded-full"
                            :class="
                                item.ready
                                    ? 'bg-emerald-500 dark:bg-emerald-300'
                                    : 'bg-amber-500 dark:bg-amber-300'
                            "
                        />
                        {{ item.label }}
                    </p>
                    <p class="font-semibold tracking-wide uppercase">
                        {{ item.ready ? 'Ready' : 'Blocked' }}
                    </p>
                </div>
            </article>
        </div>

        <div
            class="mt-3 rounded-lg border border-border/70 bg-background/70 p-3 dark:bg-background/30"
        >
            <p
                v-if="blockers.length === 0"
                data-test="rail-readiness-clear"
                class="text-xs text-emerald-700 dark:text-emerald-300"
            >
                No blockers on the current rail.
            </p>
            <ul
                v-else
                data-test="rail-readiness-blockers"
                class="space-y-1.5 text-xs text-muted-foreground xl:grid xl:grid-cols-2 xl:space-y-0 xl:gap-x-4 xl:gap-y-2"
            >
                <li
                    v-for="(blocker, index) in blockers"
                    :key="`rail-blocker-${index}`"
                    class="flex items-start gap-2"
                >
                    <span
                        class="mt-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-amber-100 px-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
                    >
                        {{ index + 1 }}
                    </span>
                    <span>{{ blocker }}</span>
                </li>
            </ul>
        </div>
    </section>
</template>
