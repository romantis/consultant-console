<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { AlertOctagon, Loader2 } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';

const toneClassMap = {
    subtle: 'border border-destructive/20 bg-destructive/5 text-muted-foreground',
    surface:
        'border border-destructive/30 bg-background text-muted-foreground shadow-xs shadow-destructive/10',
    solid: 'border border-destructive bg-destructive/90 text-destructive-foreground',
} as const;

const alignmentClassMap = {
    start: 'items-start text-left',
    center: 'items-center text-center',
} as const;

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component | null;
        align?: keyof typeof alignmentClassMap;
        tone?: keyof typeof toneClassMap;
        retryText?: string;
        isRetrying?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: 'Something went wrong.',
        description:
            'An unexpected error blocked this surface. Try the action again or refresh the page once you have network connectivity.',
        icon: null,
        align: 'center',
        tone: 'subtle',
        retryText: undefined,
        isRetrying: false,
    },
);

const emit = defineEmits<{
    (event: 'retry'): void;
}>();

const resolvedIcon = computed<Component>(() => props.icon ?? AlertOctagon);
const toneClasses = computed(() => toneClassMap[props.tone]);
const alignmentClasses = computed(() => alignmentClassMap[props.align]);
const actionsAlignment = computed(() =>
    props.align === 'center' ? 'justify-center text-center' : 'justify-start text-left',
);

const containerClasses = computed(() =>
    cn(
        'flex w-full flex-col gap-4 rounded-2xl px-6 py-8 text-base',
        toneClasses.value,
        alignmentClasses.value,
        props.class,
    ),
);

const titleClasses = computed(() =>
    cn(
        'text-balance text-lg font-semibold',
        props.tone === 'solid' ? 'text-destructive-foreground' : 'text-destructive',
    ),
);

const showRetry = computed(() => Boolean(props.retryText));
const handleRetry = () => emit('retry');
</script>

<template>
    <div :class="containerClasses" role="alert">
        <div class="flex flex-col gap-3" :class="props.align === 'center' ? 'items-center' : 'items-start'">
            <div class="flex items-center justify-center">
                <component
                    :is="resolvedIcon"
                    class="size-12 text-destructive"
                    aria-hidden="true"
                />
            </div>
            <div class="space-y-2">
                <slot>
                    <h2 :class="titleClasses">
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="text-pretty text-sm text-muted-foreground"
                        :class="props.tone === 'solid' && 'text-destructive-foreground/80'"
                    >
                        {{ description }}
                    </p>
                </slot>
            </div>
        </div>

        <div
            v-if="$slots.details"
            class="w-full rounded-xl border border-destructive/20 bg-destructive/5 p-4 text-left text-xs text-muted-foreground/90"
        >
            <slot name="details" />
        </div>

        <div
            v-if="showRetry || $slots.actions"
            class="flex flex-wrap items-center gap-3"
            :class="actionsAlignment"
        >
            <Button
                v-if="showRetry"
                :disabled="isRetrying"
                variant="destructive"
                @click="handleRetry"
            >
                <Loader2
                    v-if="isRetrying"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                <span>{{ retryText }}</span>
            </Button>
            <slot name="actions" />
        </div>
    </div>
</template>
