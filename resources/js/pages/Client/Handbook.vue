<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { home } from '@/routes';
import { index as clientHandbookIndex } from '@/routes/client/handbook';
import { page as clientHandbookMetricPage } from '@/routes/client/handbook/metrics';

type Organization = {
    id: string;
    name: string;
};

type EngagementOption = {
    id: string;
    title: string;
    status?: string | null;
};

type ActionLink = {
    key: string;
    label: string;
    href: string;
    disabled?: boolean;
    disabled_reason?: string | null;
    workshop_live?: boolean;
};

type MetricPayload = {
    id: string;
    engagement_metric_id?: string | null;
    alias?: string | null;
    name: string;
    status?: string | null;
    urgency?: 'needs_attention' | 'in_progress' | 'finalized' | null;
    needs_attention?: boolean;
    stage_label?: string | null;
    engagement?: { id: string; title: string } | null;
    owner?: { key: string; label: string; value: string | null } | null;
    signed_off_at?: string | null;
    actions?: ActionLink[];
};

type SelectedEngagementSummary = {
    engagement_id: string;
    engagement_title?: string | null;
    next_review_on?: string | null;
    review_cadence?: string | null;
    operational_ready_count: number;
    needs_follow_up_count: number;
    implementation_gap_count: number;
};

type StatusFilterKey = 'all' | 'needs_attention' | 'in_progress' | 'finalized';

const props = defineProps<{
    organization: Organization;
    engagements: EngagementOption[];
    activeEngagementId?: string | null;
    selected_engagement_summary?: SelectedEngagementSummary | null;
    metrics: MetricPayload[];
}>();

const page = usePage();

const allEngagementValue = 'all';
const statusFilters: Array<{ key: StatusFilterKey; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'needs_attention', label: 'Needs attention' },
    { key: 'in_progress', label: 'In progress' },
    { key: 'finalized', label: 'Finalized' },
];
const hiddenClientActionKeys = new Set([
    'reassign_owner',
    'request_sign_off',
    'approve_sign_off',
    'request_changes',
    'sign_off_now',
]);

const organization = computed(() => props.organization);
const metrics = computed(() => props.metrics);
const selectedEngagementSummary = computed(
    () => props.selected_engagement_summary ?? null,
);
const handbookIndex = computed(() =>
    page.props.auth?.user?.is_internal_staff ? clientHandbookIndex : home,
);

const selectedEngagementId = ref(props.activeEngagementId ?? null);
const activeStatusFilter = ref<StatusFilterKey>('all');

watch(
    () => props.activeEngagementId,
    (next) => {
        selectedEngagementId.value = next ?? null;
    },
);

const filteredMetrics = computed(() => {
    if (activeStatusFilter.value === 'all') {
        return metrics.value;
    }

    return metrics.value.filter(
        (metric) => metric.urgency === activeStatusFilter.value,
    );
});

const statusCounts = computed<Record<StatusFilterKey, number>>(() => {
    const counts: Record<StatusFilterKey, number> = {
        all: metrics.value.length,
        needs_attention: 0,
        in_progress: 0,
        finalized: 0,
    };

    metrics.value.forEach((metric) => {
        if (metric.urgency === 'needs_attention') {
            counts.needs_attention += 1;
            return;
        }

        if (metric.urgency === 'in_progress') {
            counts.in_progress += 1;
            return;
        }

        if (metric.urgency === 'finalized') {
            counts.finalized += 1;
        }
    });

    return counts;
});

const hasMetrics = computed(() => metrics.value.length > 0);
const readinessSummary = computed(() => {
    if (!selectedEngagementSummary.value) {
        return null;
    }

    return `${selectedEngagementSummary.value.operational_ready_count} ready / ${selectedEngagementSummary.value.needs_follow_up_count} needs follow-up`;
});
const implementationGapSummary = computed(() => {
    if (!selectedEngagementSummary.value) {
        return null;
    }

    const label =
        selectedEngagementSummary.value.implementation_gap_count === 1
            ? 'metric'
            : 'metrics';

    return `${selectedEngagementSummary.value.implementation_gap_count} ${label}`;
});
const reviewCadenceLabel = computed(() => {
    const cadence = selectedEngagementSummary.value?.review_cadence?.trim();

    return cadence ? `${cadence} cadence` : null;
});
const nextReviewLabel = computed(() =>
    formatDate(selectedEngagementSummary.value?.next_review_on ?? null),
);

const startCase = (value: string): string =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase()) || value;

const formatDate = (value: string | null | undefined) => {
    if (!value) {
        return null;
    }

    const date = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? new Date(
              Number.parseInt(value.slice(0, 4), 10),
              Number.parseInt(value.slice(5, 7), 10) - 1,
              Number.parseInt(value.slice(8, 10), 10),
          )
        : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const metricStatusLabel = (metric: MetricPayload) => {
    switch (metric.urgency) {
        case 'needs_attention':
            return 'Needs attention';
        case 'in_progress':
            return 'In progress';
        case 'finalized':
            return 'Finalized';
        default:
            return metric.status ? startCase(metric.status) : 'Unknown';
    }
};

const metricStatusVariant = (metric: MetricPayload) => {
    switch (metric.urgency) {
        case 'needs_attention':
            return 'warning';
        case 'in_progress':
            return 'secondary';
        case 'finalized':
            return 'success';
        default:
            return 'outline';
    }
};

const metricOwnerLabel = (metric: MetricPayload) =>
    metric.owner?.value || 'Not assigned';
const metricEngagementLabel = (metric: MetricPayload) =>
    metric.engagement?.title ?? 'Engagement pending';
const metricActions = (metric: MetricPayload) =>
    (metric.actions ?? []).filter(
        (action) => !hiddenClientActionKeys.has(action.key),
    );
const metricDisplayName = (metric: MetricPayload) =>
    metric.alias?.trim() ? metric.alias : metric.name;
const isLiveWorkshopAction = (action: ActionLink) =>
    action.key === 'workshop' && action.workshop_live === true;
const actionLabel = (action: ActionLink) =>
    isLiveWorkshopAction(action) ? 'Workshop live' : action.label;

const updateEngagement = (value: string | null) => {
    const normalizedValue = value === allEngagementValue ? null : value;
    selectedEngagementId.value = normalizedValue;

    const destination = normalizedValue
        ? handbookIndex.value({ query: { engagement: normalizedValue } })
        : handbookIndex.value();

    router.get(destination.url, {}, { preserveScroll: true });
};

const visitMetric = (metric: MetricPayload) => {
    const engagementId = metric.engagement?.id ?? null;

    if (!engagementId) {
        return;
    }

    router.visit(
        clientHandbookMetricPage({
            organization: props.organization.id,
            engagement: engagementId,
            orgMetric: metric.id,
        }),
    );
};
</script>

<template>
    <Head title="Handbook" />
    <ClientLayout
        title="Handbook"
        description="Browse metric definitions for your team."
    >
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <Card
                    v-if="selectedEngagementSummary"
                    data-test="selected-engagement-summary"
                    class="border-border/60 bg-card/70"
                >
                    <CardHeader class="space-y-2">
                        <CardTitle>Executive brief</CardTitle>
                        <CardDescription>
                            {{ selectedEngagementSummary.engagement_title }}
                            <span v-if="reviewCadenceLabel">
                                · {{ reviewCadenceLabel }}
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-if="nextReviewLabel"
                                class="rounded-lg border border-border/60 bg-muted/30 px-4 py-3"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    Next review
                                </p>
                                <p
                                    data-test="selected-engagement-summary-next-review"
                                    class="mt-2 text-base font-semibold text-foreground"
                                >
                                    {{ nextReviewLabel }}
                                </p>
                            </div>

                            <div
                                class="rounded-lg border border-border/60 bg-muted/30 px-4 py-3"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    Metric readiness
                                </p>
                                <p
                                    data-test="selected-engagement-summary-readiness"
                                    class="mt-2 text-base font-semibold text-foreground"
                                >
                                    {{ readinessSummary }}
                                </p>
                            </div>

                            <div
                                class="rounded-lg border border-border/60 bg-muted/30 px-4 py-3"
                            >
                                <p
                                    class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    Implementation gaps
                                </p>
                                <p
                                    data-test="selected-engagement-summary-implementation-gaps"
                                    class="mt-2 text-base font-semibold text-foreground"
                                >
                                    {{ implementationGapSummary }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/60 bg-card/60">
                    <CardHeader
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="space-y-1">
                            <CardTitle>Handbook metrics</CardTitle>
                            <CardDescription>
                                All org-level metrics across
                                {{ organization.name }} engagements, sorted by
                                urgency.
                            </CardDescription>
                        </div>
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Select
                                :model-value="
                                    selectedEngagementId ?? allEngagementValue
                                "
                                @update:model-value="
                                    (value) =>
                                        updateEngagement(value as string | null)
                                "
                            >
                                <SelectTrigger class="min-w-[240px]">
                                    <SelectValue
                                        placeholder="All engagements"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="allEngagementValue">
                                        All engagements
                                    </SelectItem>
                                    <SelectItem
                                        v-for="engagement in props.engagements"
                                        :key="engagement.id"
                                        :value="engagement.id"
                                    >
                                        {{ engagement.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                        >
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-for="filter in statusFilters"
                                    :key="filter.key"
                                    type="button"
                                    size="sm"
                                    :variant="
                                        filter.key === activeStatusFilter
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                    @click="activeStatusFilter = filter.key"
                                >
                                    <span>{{ filter.label }}</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            filter.key === activeStatusFilter
                                                ? 'bg-background/70 text-foreground'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ statusCounts[filter.key] }}
                                    </span>
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ filteredMetrics.length }} of
                                {{ metrics.length }} metrics
                            </p>
                        </div>

                        <div
                            class="overflow-hidden rounded-lg border border-border/60"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Metric</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>Engagement</TableHead>
                                        <TableHead class="text-right">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableEmpty
                                        v-if="!filteredMetrics.length"
                                        :colspan="5"
                                        class="text-muted-foreground"
                                    >
                                        {{
                                            hasMetrics
                                                ? 'No metrics match the selected filters.'
                                                : 'Metrics will appear here once they are created.'
                                        }}
                                    </TableEmpty>
                                    <TableRow
                                        v-for="metric in filteredMetrics"
                                        :key="metric.id"
                                        class="cursor-pointer"
                                        @click="visitMetric(metric)"
                                    >
                                        <TableCell class="font-medium">
                                            <span class="text-foreground">
                                                {{ metricDisplayName(metric) }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="space-y-1">
                                                <Badge
                                                    :variant="
                                                        metricStatusVariant(
                                                            metric,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        metricStatusLabel(
                                                            metric,
                                                        )
                                                    }}
                                                </Badge>
                                                <p
                                                    v-if="metric.stage_label"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ metric.stage_label }}
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ metricOwnerLabel(metric) }}
                                        </TableCell>
                                        <TableCell
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ metricEngagementLabel(metric) }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div
                                                v-if="
                                                    metricActions(metric).length
                                                "
                                                class="flex flex-col flex-wrap justify-end gap-2"
                                            >
                                                <div
                                                    v-for="action in metricActions(
                                                        metric,
                                                    )"
                                                    :key="action.key"
                                                    class="flex flex-col items-end gap-1"
                                                    @click.stop
                                                >
                                                    <Button
                                                        v-if="action.disabled"
                                                        size="sm"
                                                        variant="link"
                                                        class="h-auto px-0 text-xs font-semibold"
                                                        disabled
                                                    >
                                                        <span
                                                            class="inline-flex items-center gap-1"
                                                            :data-test="
                                                                isLiveWorkshopAction(
                                                                    action,
                                                                )
                                                                    ? 'handbook-workshop-live-link'
                                                                    : undefined
                                                            "
                                                        >
                                                            <span
                                                                v-if="
                                                                    isLiveWorkshopAction(
                                                                        action,
                                                                    )
                                                                "
                                                                data-test="handbook-workshop-live-dot"
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
                                                            <span>{{
                                                                actionLabel(
                                                                    action,
                                                                )
                                                            }}</span>
                                                        </span>
                                                    </Button>
                                                    <Button
                                                        v-else
                                                        size="sm"
                                                        variant="link"
                                                        class="h-auto px-0 text-xs font-semibold"
                                                        as-child
                                                    >
                                                        <Link
                                                            :href="action.href"
                                                            @click.stop
                                                        >
                                                            <span
                                                                class="inline-flex items-center gap-1"
                                                                :data-test="
                                                                    isLiveWorkshopAction(
                                                                        action,
                                                                    )
                                                                        ? 'handbook-workshop-live-link'
                                                                        : undefined
                                                                "
                                                            >
                                                                <span
                                                                    v-if="
                                                                        isLiveWorkshopAction(
                                                                            action,
                                                                        )
                                                                    "
                                                                    data-test="handbook-workshop-live-dot"
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
                                                                <span>{{
                                                                    actionLabel(
                                                                        action,
                                                                    )
                                                                }}</span>
                                                            </span>
                                                        </Link>
                                                    </Button>
                                                    <span
                                                        v-if="
                                                            action.disabled &&
                                                            action.disabled_reason
                                                        "
                                                        class="text-[10px] text-muted-foreground"
                                                    >
                                                        {{
                                                            action.disabled_reason
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
