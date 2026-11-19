<script setup lang="ts">
import { computed } from 'vue';

type BannerTone = 'warning' | 'info' | 'success' | 'danger';

type Props = {
    title: string;
    description?: string | null;
    tone?: BannerTone;
};

const props = withDefaults(defineProps<Props>(), {
    description: null,
    tone: 'info',
});

const toneClasses = computed((): string => {
    if (props.tone === 'warning') {
        return 'border-amber-200/70 bg-amber-50/90 text-amber-900 dark:border-amber-500/40 dark:bg-amber-950/50 dark:text-amber-50';
    }

    if (props.tone === 'success') {
        return 'border-emerald-200/70 bg-emerald-50/90 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-950/50 dark:text-emerald-50';
    }

    if (props.tone === 'danger') {
        return 'border-rose-200/70 bg-rose-50/90 text-rose-900 dark:border-rose-500/40 dark:bg-rose-950/50 dark:text-rose-50';
    }

    return 'border-sky-200/70 bg-sky-50/90 text-sky-900 dark:border-sky-500/40 dark:bg-sky-950/50 dark:text-sky-50';
});
</script>

<template>
    <div
        class="rounded-lg border px-4 py-3 shadow-sm"
        :class="toneClasses"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        data-test="status-banner"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <p class="text-sm font-semibold">{{ title }}</p>
                <p v-if="description" class="text-sm">
                    {{ description }}
                </p>
            </div>
            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2"
            >
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
