<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import BaseAppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import type { BreadcrumbItemType } from '@/types';
import { computed, useSlots, type Component } from 'vue';

interface EmptyStateConfig {
    show?: boolean;
    title?: string;
    description?: string;
    icon?: Component;
}

interface Props {
    title?: string;
    description?: string;
    breadcrumbs?: BreadcrumbItemType[];
    isEmpty?: boolean;
    emptyState?: EmptyStateConfig;
    padded?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    description: '',
    breadcrumbs: () => [],
    isEmpty: false,
    emptyState: () => ({ show: false }),
    padded: true,
});

const slots = useSlots();

const showEmptyState = computed(() => {
    if (typeof props.emptyState?.show === 'boolean') {
        return props.emptyState.show;
    }

    return props.isEmpty;
});

const emptyStateTitle = computed(
    () => props.emptyState?.title ?? 'Nothing to show just yet.',
);

const emptyStateDescription = computed(
    () =>
        props.emptyState?.description ??
        'Once data is available it will appear here automatically. In the meantime, start a new engagement or import existing records.',
);

const emptyStateIcon = computed<Component | null>(
    () => props.emptyState?.icon ?? null,
);

const hasHeader = computed(
    () =>
        Boolean(props.title?.length) ||
        Boolean(props.description?.length) ||
        Boolean(slots.actions),
);

const hasToolbar = computed(() => Boolean(slots.toolbar));

const contentPadding = computed(() =>
    props.padded ? 'px-6 pb-10 pt-6 md:px-8 lg:px-10' : '',
);
</script>

<template>
    <BaseAppShell variant="sidebar">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="relative flex min-h-screen flex-1 flex-col overflow-x-clip bg-muted/20"
        >
            <AppSidebarHeader :breadcrumbs="props.breadcrumbs" />

            <div class="flex flex-1 flex-col gap-6" :class="contentPadding">
                <header
                    v-if="hasHeader"
                    class="flex flex-col gap-4 border-b border-muted/50 pb-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            <slot name="title">
                                {{ props.title }}
                            </slot>
                        </h1>
                        <p
                            v-if="props.description"
                            class="max-w-2xl text-sm text-muted-foreground"
                        >
                            {{ props.description }}
                        </p>
                    </div>
                    <div
                        v-if="slots.actions"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <slot name="actions" />
                    </div>
                </header>

                <section v-if="hasToolbar" class="flex flex-col gap-3">
                    <slot name="toolbar" />
                </section>

                <section class="relative flex flex-1 flex-col">
                    <template v-if="showEmptyState">
                        <div class="flex flex-1 items-center justify-center">
                            <slot name="empty">
                                <EmptyState
                                    class="w-full max-w-xl"
                                    :title="emptyStateTitle"
                                    :description="emptyStateDescription"
                                    :icon="emptyStateIcon ?? undefined"
                                    size="lg"
                                    variant="dashed"
                                >
                                    <template
                                        v-if="slots['empty-actions']"
                                        #actions
                                    >
                                        <slot name="empty-actions" />
                                    </template>
                                </EmptyState>
                            </slot>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex h-full flex-col gap-6">
                            <slot />
                        </div>
                    </template>
                </section>
            </div>
        </AppContent>
    </BaseAppShell>
</template>
