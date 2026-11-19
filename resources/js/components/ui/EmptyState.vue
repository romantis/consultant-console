<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';

const sizeClassMap = {
    sm: 'p-4 text-sm',
    md: 'px-6 py-8',
    lg: 'px-10 py-12 text-lg',
} as const;

const alignmentClassMap = {
    start: 'items-start text-left',
    center: 'items-center text-center',
} as const;

const variantClassMap = {
    dashed: 'border border-dashed border-border/70 bg-background/70 dark:bg-background/50',
    card: 'border border-border bg-card text-card-foreground shadow-sm shadow-primary/5',
    ghost: 'border border-transparent bg-transparent',
    soft: 'border border-muted/40 bg-muted/30 dark:bg-muted/20',
} as const;

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component | null;
        align?: keyof typeof alignmentClassMap;
        size?: keyof typeof sizeClassMap;
        variant?: keyof typeof variantClassMap;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: 'Nothing to show just yet.',
        description:
            'Once data is available it will appear here automatically. Check back after completing the required steps.',
        icon: null,
        align: 'center',
        size: 'md',
        variant: 'dashed',
    },
);

const resolvedIcon = computed<Component | null>(() => props.icon ?? null);
const alignmentClasses = computed(() => alignmentClassMap[props.align]);
const paddingClasses = computed(() => sizeClassMap[props.size]);
const variantClasses = computed(() => variantClassMap[props.variant]);

const containerClasses = computed(() =>
    cn(
        'flex w-full max-w-3xl flex-col gap-4 rounded-2xl text-muted-foreground transition-colors',
        alignmentClasses.value,
        paddingClasses.value,
        variantClasses.value,
        props.class,
    ),
);

const actionsAlignment = computed(() =>
    props.align === 'center' ? 'justify-center' : 'justify-start',
);
</script>

<template>
    <div :class="containerClasses">
        <div v-if="$slots.icon || resolvedIcon" class="flex items-center justify-center">
            <slot name="icon">
                <component
                    v-if="resolvedIcon"
                    :is="resolvedIcon"
                    class="size-12 text-muted-foreground/70"
                    aria-hidden="true"
                />
            </slot>
        </div>

        <div class="space-y-2">
            <slot>
                <h2
                    v-if="title"
                    class="text-balance text-2xl font-semibold text-foreground"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="text-pretty text-sm text-muted-foreground/90"
                >
                    {{ description }}
                </p>
            </slot>
        </div>

        <div
            v-if="$slots.actions"
            class="flex flex-wrap gap-2"
            :class="actionsAlignment"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
