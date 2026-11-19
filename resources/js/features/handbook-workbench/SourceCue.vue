<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next';

const props = defineProps<{
    sourceName: string;
    sourceDescription: string;
    showWarning?: boolean;
    warningMessage?: string;
    dataTestCue?: string;
    dataTestWarning?: string;
}>();
</script>

<template>
    <div class="mt-2 space-y-1.5">
        <div
            class="flex items-center gap-1.5 rounded border border-border/40 bg-muted/20 px-2.5 py-1.5 text-[10px] text-muted-foreground/90"
            :data-test="props.dataTestCue"
        >
            <span
                class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-[9px] font-semibold tracking-wider text-primary uppercase"
                >Source Context</span
            >
            <span
                >Refer to <strong>{{ props.sourceName }}</strong> ({{
                    props.sourceDescription
                }}).</span
            >
        </div>
        <div
            v-if="props.showWarning"
            class="flex items-center gap-1.5 rounded border border-amber-500/20 bg-amber-500/10 px-2.5 py-1.5 text-[10px] text-amber-700 dark:text-amber-400"
            :data-test="props.dataTestWarning"
        >
            <AlertTriangle class="size-3.5 shrink-0 text-amber-500" />
            <span>{{ props.warningMessage }}</span>
        </div>
    </div>
</template>
