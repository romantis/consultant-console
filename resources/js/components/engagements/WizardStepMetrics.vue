<template>
    <Card>
        <CardHeader>
            <CardTitle>Select canonical metrics</CardTitle>
            <CardDescription>
                Choose between {{ minMetrics }} and {{ maxMetrics }} metrics,
                then pick a template variant.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <p v-if="hiddenMetricsCount" class="text-sm text-muted-foreground">
                {{ hiddenMetricsCount }} metric{{
                    hiddenMetricsCount === 1 ? '' : 's'
                }}
                hidden because no template variants exist yet.
            </p>
            <div v-if="visibleMetrics.length" class="grid gap-3">
                <label
                    v-for="metric in visibleMetrics"
                    :key="metric.key"
                    class="flex items-start gap-3 rounded-lg border p-4 transition hover:border-primary/50"
                >
                    <Checkbox
                        :id="`wizard-metric-${metric.key}`"
                        class="mt-1"
                        :model-value="isSelected(metric.key)"
                        :disabled="
                            isMetricLocked(metric) ||
                            (!isSelected(metric.key) && !canSelectMore)
                        "
                        @update:model-value="
                            (checked) =>
                                toggleMetric(metric.key, Boolean(checked))
                        "
                    />
                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="leading-tight font-medium">
                                    {{ metric.name }}
                                </p>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ metric.key }}
                                </p>
                            </div>
                            <Badge
                                v-if="
                                    activeTemplate(metric)?.required_departments
                                        ?.length
                                "
                                variant="outline"
                            >
                                {{
                                    activeTemplate(
                                        metric,
                                    )?.required_departments?.join(', ')
                                }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ resolveDescription(metric) }}
                        </p>
                        <div
                            v-if="metric.templates.length"
                            class="mt-3 space-y-2"
                        >
                            <template v-if="metric.templates.length > 1">
                                <Label
                                    :for="`wizard-metric-template-${metric.key}`"
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Template variant
                                </Label>
                                <Select
                                    :model-value="selectedTemplateId(metric)"
                                    :disabled="
                                        !isSelected(metric.key) ||
                                        isMetricLocked(metric)
                                    "
                                    @update:model-value="
                                        (value) =>
                                            setTemplate(
                                                metric.key,
                                                String(value),
                                            )
                                    "
                                >
                                    <SelectTrigger
                                        :id="`wizard-metric-template-${metric.key}`"
                                    >
                                        <SelectValue
                                            placeholder="Select a template"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="template in metric.templates"
                                            :key="template.id"
                                            :value="template.id"
                                        >
                                            {{ template.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="activeTemplate(metric)?.key"
                                    class="text-xs text-muted-foreground"
                                >
                                    Template key:
                                    {{ activeTemplate(metric)?.key }}
                                </p>
                            </template>
                            <p v-else class="text-xs text-muted-foreground">
                                Template: {{ metric.templates[0]?.name }}
                            </p>
                        </div>
                        <div
                            v-if="isSelected(metric.key)"
                            class="mt-3 space-y-2"
                        >
                            <Label
                                :for="`wizard-metric-due-date-${metric.key}`"
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Due date
                            </Label>
                            <input
                                :id="`wizard-metric-due-date-${metric.key}`"
                                type="date"
                                :value="dueDateFor(metric.key)"
                                :disabled="isMetricLocked(metric)"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                @input="
                                    (event) =>
                                        updateDueDate(
                                            metric.key,
                                            (event.target as HTMLInputElement)
                                                .value,
                                        )
                                "
                            />
                            <p class="text-xs text-muted-foreground">
                                Reminders are scheduled relative to this date.
                            </p>
                        </div>
                        <p
                            v-if="availabilityMessage(metric)"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            {{ availabilityMessage(metric) }}
                        </p>
                    </div>
                </label>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No metrics with templates yet. Add a template to make metrics
                available.
            </p>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    EngagementWizardMetric,
    EngagementWizardTemplateVariant,
} from '@/types';
import { computed, toRefs } from 'vue';

const props = withDefaults(
    defineProps<{
        metrics: EngagementWizardMetric[];
        selectedKeys: string[];
        selectedTemplates?: Record<string, string | null>;
        dueDates?: Record<string, string | null>;
        canSelectMore?: boolean;
        minMetrics?: number;
        maxMetrics?: number;
    }>(),
    {
        canSelectMore: true,
        dueDates: () => ({}),
        selectedTemplates: () => ({}),
        minMetrics: 3,
        maxMetrics: 10,
    },
);

const emit = defineEmits<{
    (event: 'toggle', payload: { key: string; selected: boolean }): void;
    (
        event: 'select-template',
        payload: { key: string; templateId: string },
    ): void;
    (
        event: 'set-due-date',
        payload: { key: string; dueDate: string | null },
    ): void;
}>();

const {
    metrics,
    selectedKeys,
    selectedTemplates,
    dueDates,
    canSelectMore,
    minMetrics,
    maxMetrics,
} = toRefs(props);

const visibleMetrics = computed(() =>
    metrics.value.filter((metric) => metric.templates.length > 0),
);
const hiddenMetricsCount = computed(
    () => metrics.value.length - visibleMetrics.value.length,
);

function isSelected(key: string) {
    return selectedKeys.value.includes(key);
}

function toggleMetric(key: string, selected: boolean) {
    emit('toggle', { key, selected });
}

function setTemplate(key: string, templateId: string) {
    emit('select-template', { key, templateId });
}

function dueDateFor(key: string) {
    if (Object.prototype.hasOwnProperty.call(dueDates.value, key)) {
        return dueDates.value[key] ?? '';
    }

    return '';
}

function updateDueDate(key: string, dueDate: string) {
    emit('set-due-date', {
        key,
        dueDate: dueDate.trim() === '' ? null : dueDate.trim(),
    });
}

function selectedTemplateId(metric: EngagementWizardMetric) {
    return (
        selectedTemplates.value?.[metric.key] ??
        metric.default_metric_template_id ??
        metric.templates[0]?.id ??
        ''
    );
}

function activeTemplate(
    metric: EngagementWizardMetric,
): EngagementWizardTemplateVariant | null {
    const selectedId = selectedTemplateId(metric);
    return (
        metric.templates.find((template) => template.id === selectedId) ??
        metric.templates.find(
            (template) => template.id === metric.default_metric_template_id,
        ) ??
        metric.templates[0] ??
        null
    );
}

function resolveDescription(metric: EngagementWizardMetric) {
    const template = activeTemplate(metric);

    if (template?.description) {
        return template.description;
    }

    if (metric.description) {
        return metric.description;
    }

    return 'No description provided for this metric.';
}

function isMetricLocked(metric: EngagementWizardMetric) {
    return (
        !metric.is_active ||
        metric.templates.length === 0 ||
        Boolean(metric.active_engagement)
    );
}

function availabilityMessage(metric: EngagementWizardMetric) {
    if (metric.active_engagement) {
        return `In ${formatStatusLabel(metric.active_engagement.status)} engagement: ${metric.active_engagement.title}`;
    }

    if (!metric.is_active) {
        return 'This metric is archived in the library.';
    }

    if (metric.templates.length === 0) {
        return 'No template variants are available yet.';
    }

    return null;
}

function formatStatusLabel(value: string) {
    return value.replace(/([A-Z])/g, ' $1').trim();
}
</script>
