<script setup lang="ts">
import ClientHandbookStep from './components/ClientHandbookStep.vue';
import DemoHeader from './components/DemoHeader.vue';
import WorkbenchStep from './components/WorkbenchStep.vue';
import WorkshopStep from './components/WorkshopStep.vue';
import { useDemoState } from './composables/useDemoState';

const { step } = useDemoState();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <DemoHeader />

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <WorkshopStep v-if="step === 'workshop'" />
            <WorkbenchStep v-else-if="step === 'workbench'" />
            <ClientHandbookStep v-else />
        </main>

        <footer class="border-t border-border/70 bg-card/60">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <p>
                    Metric Harmony Console · Static Demo (no backend, database,
                    or login required)
                </p>
                <p>
                    Synthetic engagement data from
                    <code class="rounded bg-muted px-1 py-0.5 text-[11px]">
                        GoldenPathExecutiveArtifactStorySeeder
                    </code>
                </p>
            </div>
        </footer>
    </div>
</template>
