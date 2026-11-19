<script setup lang="ts">
import { computed } from 'vue';

import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    variant?: 'default' | 'client';
    organizationName?: string | null;
    tagline?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'default',
    organizationName: null,
    tagline: null,
});

const organizationLabel = computed(
    () => props.organizationName?.trim() || null,
);
const taglineLabel = computed(() => props.tagline?.trim() || null);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <div
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <AppLogoIcon
                class="size-5 fill-current text-white dark:text-black"
            />
        </div>
        <div v-if="props.variant === 'client'" class="min-w-0 flex-1">
            <p class="truncate text-sm leading-tight font-semibold">
                Metric Harmony
            </p>
            <p class="truncate text-xs leading-tight text-muted-foreground">
                {{ organizationLabel ?? 'Client Console' }}
            </p>
        </div>
        <div v-else class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">
                MH Console
            </span>
            <span
                v-if="taglineLabel"
                class="text-xs leading-tight text-muted-foreground"
            >
                {{ taglineLabel }}
            </span>
        </div>
    </div>
</template>
