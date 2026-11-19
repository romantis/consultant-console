<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useWayfinder } from '@/composables/useWayfinder';
import org, { show as orgShow } from '@/routes/org';
import type { BreadcrumbItem } from '@/types';

type OrgMetricRow = {
    id: string;
    alias?: string | null;
    canonical_name: string;
    canonical_key?: string | null;
    active_engagement: {
        id: string;
        title: string;
        status?: string | null;
    } | null;
    latest_sign_off: {
        id: string;
        status?: string | null;
        signed_off_at?: string | null;
        signed_off_by?: { id: string; name: string } | null;
    } | null;
    past_engagements_count: number;
};

const props = defineProps<{
    organization: { id: string; name: string };
    metrics: OrgMetricRow[];
}>();

const wayfinder = useWayfinder();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    { title: 'Org Metrics', href: '' },
];

const metrics = computed(() => props.metrics ?? []);

const statusVariant = (status?: string | null) => {
    if (!status) return 'secondary';
    const normalized = status.toLowerCase();
    if (normalized === 'draft') return 'outline';
    if (normalized === 'archived') return 'destructive';
    if (normalized === 'finalized') return 'default';
    return 'secondary';
};

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Org Metrics" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Org Metrics
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Review canonical coverage, active engagements, and the
                    latest org-level sign-off status for this organization.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Metrics</CardTitle>
                    <CardDescription
                        >Alias and governance rollups across
                        engagements.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table class="w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Metric</TableHead>
                                <TableHead>Active engagement</TableHead>
                                <TableHead>Latest sign-off</TableHead>
                                <TableHead class="text-right"
                                    >Past engagements</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="metric in metrics"
                                :key="metric.id"
                            >
                                <TableCell>
                                    <div class="space-y-1">
                                        <Link
                                            :href="
                                                wayfinder.org.orgMetrics.show([
                                                    organization.id,
                                                    metric.id,
                                                ])
                                            "
                                            class="block text-sm font-semibold text-primary underline-offset-2 hover:underline"
                                        >
                                            {{
                                                metric.alias ||
                                                metric.canonical_name
                                            }}
                                        </Link>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                metric.alias
                                                    ? metric.canonical_name
                                                    : metric.canonical_key ||
                                                      '—'
                                            }}
                                        </p>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="metric.active_engagement"
                                        class="space-y-2"
                                    >
                                        <Link
                                            :href="
                                                wayfinder.org.engagements.show([
                                                    organization.id,
                                                    metric.active_engagement.id,
                                                ])
                                            "
                                            class="text-sm font-medium text-primary underline-offset-2 hover:underline"
                                        >
                                            {{ metric.active_engagement.title }}
                                        </Link>
                                        <Badge
                                            :variant="
                                                statusVariant(
                                                    metric.active_engagement
                                                        .status,
                                                )
                                            "
                                        >
                                            {{
                                                metric.active_engagement
                                                    .status || 'Active'
                                            }}
                                        </Badge>
                                    </div>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >No active engagement</span
                                    >
                                </TableCell>
                                <TableCell>
                                    <div
                                        v-if="metric.latest_sign_off"
                                        class="space-y-1"
                                    >
                                        <p class="text-sm font-medium">
                                            {{
                                                metric.latest_sign_off.status ||
                                                'Signed off'
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(
                                                    metric.latest_sign_off
                                                        .signed_off_at,
                                                )
                                            }}
                                            <span
                                                v-if="
                                                    metric.latest_sign_off
                                                        .signed_off_by
                                                "
                                            >
                                                ·
                                                {{
                                                    metric.latest_sign_off
                                                        .signed_off_by.name
                                                }}
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell class="text-right">
                                    {{ metric.past_engagements_count }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!metrics.length">
                                <TableCell
                                    colspan="4"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    No org metrics yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
