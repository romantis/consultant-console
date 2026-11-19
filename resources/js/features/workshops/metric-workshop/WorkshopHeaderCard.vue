<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    MetricView,
    WorkshopMetric,
} from '@/features/workshops/metric-workshop/types';
import {
    resolveWorkshopProgressLabel as workshopProgressLabel,
    resolveWorkshopProgressVariant as workshopProgressVariant,
} from '@/features/workshops/metric-workshop/workshop-session';

const props = defineProps<{
    metric: MetricView;
    hasMetricTitleSelector: boolean;
    workshopMetrics: WorkshopMetric[];
    workshopSessionAttentionBadgeLabel: string | null;
    workshopSessionAttentionBadgeVariant: 'secondary' | 'destructive';
    showWorkshopSessionInProgressText: boolean;
    showScheduledWorkshopTime: boolean;
    activeWorkshopSessionScheduledLabel: string | null;
    showWorkshopStartAction: boolean;
    showWorkshopFinishAction: boolean;
    isWorkshopStartBusy: boolean;
    isWorkshopFinishBusy: boolean;
    canForceStartFromHeaderAction: boolean;
    shouldShowForceStartTooltip: boolean;
    isWorkshopStartButtonDisabled: boolean;
    showLastWorkshopEndedLabel: boolean;
    lastCompletedWorkshopEndedLabel: string | null;
    workshopSessionError: string | null;
    showWorkshopDiscrepancies: boolean;
    recomputeStatusLabel: string | null;
    isForceWorkshopStartDialogOpen: boolean;
    isFinishWorkshopDialogOpen: boolean;
    showMetricOptionLiveIndicator?: (metricId: string) => boolean;
}>();

const emit = defineEmits<{
    (event: 'switch-workshop-metric', metricId: string | null): void;
    (event: 'request-workshop-start'): void;
    (event: 'request-workshop-finish'): void;
    (event: 'confirm-force-workshop-start'): void;
    (event: 'confirm-finish-workshop'): void;
    (event: 'update:isForceWorkshopStartDialogOpen', value: boolean): void;
    (event: 'update:isFinishWorkshopDialogOpen', value: boolean): void;
}>();

const forceStartDialogOpen = computed({
    get: () => props.isForceWorkshopStartDialogOpen,
    set: (value: boolean) =>
        emit('update:isForceWorkshopStartDialogOpen', value),
});

const finishDialogOpen = computed({
    get: () => props.isFinishWorkshopDialogOpen,
    set: (value: boolean) => emit('update:isFinishWorkshopDialogOpen', value),
});

const switchWorkshopMetric = (value: string | number | null): void => {
    emit('switch-workshop-metric', typeof value === 'string' ? value : null);
};

const shouldShowMetricOptionLiveIndicator = (metricId: string): boolean =>
    props.showMetricOptionLiveIndicator?.(metricId) ?? false;
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex flex-wrap items-start gap-2">
                <div class="min-w-0 flex-1">
                    <Select
                        v-if="hasMetricTitleSelector"
                        :model-value="metric.id"
                        @update:model-value="switchWorkshopMetric"
                    >
                        <SelectTrigger
                            data-test="metric-title-selector"
                            class="h-auto w-auto max-w-full border-0 bg-transparent px-0 py-0 text-left text-lg leading-tight font-semibold shadow-none hover:bg-transparent focus:ring-0"
                        >
                            <span class="truncate">{{ metric.name }}</span>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="item in workshopMetrics"
                                :key="item.id"
                                :value="item.id"
                                :text-value="item.name"
                                data-test="metric-title-selector-option"
                            >
                                <div class="flex w-70 items-start gap-2">
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <span class="truncate">
                                            {{ item.name }}
                                        </span>
                                        <span
                                            v-if="
                                                shouldShowMetricOptionLiveIndicator(
                                                    item.id,
                                                )
                                            "
                                            data-test="metric-title-option-live-indicator"
                                            class="inline-flex items-center gap-1 text-[11px] text-muted-foreground"
                                        >
                                            <span
                                                data-test="metric-title-option-live-dot"
                                                class="relative flex size-2"
                                                aria-hidden="true"
                                            >
                                                <span
                                                    class="absolute inline-flex h-full w-full rounded-full bg-red-500/60 motion-safe:animate-ping"
                                                />
                                                <span
                                                    class="relative inline-flex size-2 rounded-full bg-red-500"
                                                />
                                            </span>
                                            <span>Workshop live</span>
                                        </span>
                                    </div>
                                    <i class="grow"></i>
                                    <Badge
                                        data-test="metric-title-option-status-badge"
                                        variant="outline"
                                    >
                                        {{ item.status ?? 'Unknown' }}
                                    </Badge>
                                </div>
                                <Badge
                                    v-if="
                                        item.final_definition_progress
                                            ?.state === 'missing'
                                    "
                                    data-test="metric-title-option-progress-badge"
                                    class="mt-1"
                                    :variant="
                                        workshopProgressVariant(
                                            item.final_definition_progress,
                                        )
                                    "
                                >
                                    {{
                                        workshopProgressLabel(
                                            item.final_definition_progress,
                                        )
                                    }}
                                </Badge>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <span v-else>{{ metric.name }}</span>
                </div>
                <div
                    class="ml-auto flex flex-wrap items-center justify-end gap-2"
                >
                    <Badge
                        v-if="workshopSessionAttentionBadgeLabel"
                        data-test="workshop-session-status-badge"
                        :variant="workshopSessionAttentionBadgeVariant"
                    >
                        {{ workshopSessionAttentionBadgeLabel }}
                    </Badge>
                    <p
                        v-if="showWorkshopSessionInProgressText"
                        data-test="workshop-session-status-text"
                        class="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground"
                    >
                        <span
                            data-test="workshop-session-status-live-dot"
                            class="relative flex size-2"
                            aria-hidden="true"
                        >
                            <span
                                class="absolute inline-flex h-full w-full rounded-full bg-red-500/60 motion-safe:animate-ping"
                            />
                            <span
                                class="relative inline-flex size-2 rounded-full bg-red-500"
                            />
                        </span>
                        <span>Workshop live</span>
                    </p>
                    <p
                        v-if="showScheduledWorkshopTime"
                        data-test="workshop-session-scheduled-time"
                        class="text-xs font-normal text-muted-foreground"
                    >
                        Scheduled:
                        {{ activeWorkshopSessionScheduledLabel }}
                    </p>
                    <TooltipProvider
                        v-if="showWorkshopStartAction"
                        :delay-duration="100"
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <span class="inline-flex">
                                    <Button
                                        size="sm"
                                        data-test="workshop-session-start"
                                        :disabled="
                                            isWorkshopStartButtonDisabled
                                        "
                                        :aria-disabled="
                                            canForceStartFromHeaderAction ||
                                            undefined
                                        "
                                        :aria-busy="
                                            isWorkshopStartBusy || undefined
                                        "
                                        :class="
                                            canForceStartFromHeaderAction
                                                ? 'cursor-not-allowed opacity-60'
                                                : ''
                                        "
                                        @click="emit('request-workshop-start')"
                                    >
                                        <span
                                            class="flex items-center justify-center gap-2"
                                        >
                                            <Loader2
                                                v-if="isWorkshopStartBusy"
                                                class="size-4 animate-spin"
                                            />
                                            <span>Start workshop</span>
                                        </span>
                                    </Button>
                                </span>
                            </TooltipTrigger>
                            <TooltipContent v-if="shouldShowForceStartTooltip">
                                Click to force start
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <Button
                        v-if="showWorkshopFinishAction"
                        size="sm"
                        variant="outline"
                        data-test="workshop-session-finish"
                        :disabled="isWorkshopFinishBusy || isWorkshopStartBusy"
                        :aria-busy="isWorkshopFinishBusy || undefined"
                        @click="emit('request-workshop-finish')"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <Loader2
                                v-if="isWorkshopFinishBusy"
                                class="size-4 animate-spin"
                            />
                            <span>
                                {{
                                    isWorkshopFinishBusy
                                        ? 'Finishing...'
                                        : 'Finish workshop'
                                }}
                            </span>
                        </span>
                    </Button>
                </div>
            </CardTitle>
            <CardDescription class="space-y-2">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-muted-foreground">Metric status</span>
                    <Badge variant="outline">
                        {{ metric.status }}
                    </Badge>
                    <template
                        v-if="showWorkshopDiscrepancies && recomputeStatusLabel"
                    >
                        <span class="text-muted-foreground">Recompute</span>
                        <Badge variant="outline">
                            {{ recomputeStatusLabel }}
                        </Badge>
                    </template>
                </div>
                <p
                    v-if="showLastWorkshopEndedLabel"
                    data-test="workshop-session-last-ended"
                    class="text-xs text-muted-foreground"
                >
                    Last workshop ended:
                    {{ lastCompletedWorkshopEndedLabel }}
                </p>
                <p v-if="workshopSessionError" class="text-xs text-destructive">
                    {{ workshopSessionError }}
                </p>
            </CardDescription>
        </CardHeader>
        <Dialog v-model:open="forceStartDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-2">
                    <DialogTitle>Force start workshop?</DialogTitle>
                    <DialogDescription>
                        This session is outside the start window. Continue with
                        local force start?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        data-test="workshop-session-force-confirm"
                        :disabled="isWorkshopStartBusy"
                        :aria-busy="isWorkshopStartBusy || undefined"
                        @click="emit('confirm-force-workshop-start')"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <Loader2
                                v-if="isWorkshopStartBusy"
                                class="size-4 animate-spin"
                            />
                            <span>Confirm force start</span>
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="finishDialogOpen">
            <DialogContent>
                <DialogHeader class="space-y-2">
                    <DialogTitle>Finish workshop?</DialogTitle>
                    <DialogDescription>
                        This will complete the active workshop session for all
                        linked metrics.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        data-test="workshop-session-finish-confirm"
                        :disabled="isWorkshopFinishBusy || isWorkshopStartBusy"
                        :aria-busy="isWorkshopFinishBusy || undefined"
                        @click="emit('confirm-finish-workshop')"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <Loader2
                                v-if="isWorkshopFinishBusy"
                                class="size-4 animate-spin"
                            />
                            <span>Confirm finish</span>
                        </span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </Card>
</template>
