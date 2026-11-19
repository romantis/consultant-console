<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useWayfinder } from '@/composables/useWayfinder';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { X } from 'lucide-vue-next';

type MetricRow = {
    id: string;
    key: string;
    name: string;
    description: string | null;
    is_active: boolean;
    templates_count: number;
    created_at?: string | null;
    updated_at?: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Permissions = {
    create: boolean;
};

type MetricStatusFilter = 'active' | 'archived' | 'all';

const props = defineProps<{
    metrics: MetricRow[];
    can: Permissions;
}>();

const wayfinder = useWayfinder();

const metrics = computed(() => props.metrics);
const can = computed(() => props.can);
const page = usePage();
const flashMessage = computed(() => page.props.flash?.success ?? null);
const statusFilter = ref<MetricStatusFilter>('active');
const searchQuery = ref('');

const statusOptions: Array<{ id: MetricStatusFilter; label: string }> = [
    { id: 'active', label: 'Active' },
    { id: 'archived', label: 'Archived' },
    { id: 'all', label: 'All' },
];

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Metric Library',
        href: '',
    },
];

const fmtDate = (iso?: string | null) =>
    iso ? new Date(iso).toLocaleString() : '--';

const searchTerm = computed(() => searchQuery.value.trim().toLowerCase());
const filteredMetrics = computed(() => {
    let entries = metrics.value;

    if (statusFilter.value === 'active') {
        entries = entries.filter((metric) => metric.is_active);
    } else if (statusFilter.value === 'archived') {
        entries = entries.filter((metric) => !metric.is_active);
    }

    if (!searchTerm.value) {
        return entries;
    }

    return entries.filter((metric) => {
        const description = metric.description ?? '';
        return (
            metric.name.toLowerCase().includes(searchTerm.value) ||
            metric.key.toLowerCase().includes(searchTerm.value) ||
            description.toLowerCase().includes(searchTerm.value)
        );
    });
});

const emptyStateMessage = computed(() => {
    if (!metrics.value.length) {
        return 'No canonical metrics yet. Add one to get started.';
    }

    return 'No metrics match your filters.';
});

const clearSearch = () => {
    searchQuery.value = '';
};

const archiveMetric = (metric: MetricRow) => {
    if (!metric.can.delete) return;
    if (!confirm(`Archive the "${metric.name}" canonical metric?`)) {
        return;
    }

    router.delete(wayfinder.metricLibrary.destroy(metric.id), {
        preserveScroll: true,
    });
};

const unarchiveMetric = (metric: MetricRow) => {
    if (!metric.can.update) return;
    if (!confirm(`Unarchive the "${metric.name}" canonical metric?`)) {
        return;
    }

    router.patch(
        wayfinder.metricLibrary.update(metric.id),
        { is_active: true },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Metric Library" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Metric Library
                    </h1>
                    <p class="max-w-2xl text-sm text-muted-foreground">
                        Canonical metrics catalog that powers template variants
                        and org-level governance.
                    </p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link :href="wayfinder.metricLibrary.create()"
                        >New metric</Link
                    >
                </Button>
            </div>

            <p v-if="flashMessage" class="text-sm text-emerald-700">
                {{ flashMessage }}
            </p>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-for="option in statusOptions"
                                :key="option.id"
                                type="button"
                                size="sm"
                                :variant="
                                    statusFilter === option.id
                                        ? 'secondary'
                                        : 'outline'
                                "
                                :aria-pressed="statusFilter === option.id"
                                @click="statusFilter = option.id"
                            >
                                {{ option.label }}
                            </Button>
                        </div>

                        <div class="relative w-full sm:w-72">
                            <Label for="metric-search" class="sr-only"
                                >Search metrics</Label
                            >
                            <Input
                                id="metric-search"
                                v-model="searchQuery"
                                type="search"
                                class="metric-search pr-10"
                                placeholder="Search metrics"
                            />
                            <Button
                                v-if="searchTerm"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="absolute top-1/2 right-1 -translate-y-1/2"
                                @click="clearSearch"
                            >
                                <X class="h-4 w-4" />
                                <span class="sr-only">Clear search</span>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table class="w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Key</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead class="text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="metric in filteredMetrics"
                                :key="metric.id"
                            >
                                <TableCell class="font-medium">{{
                                    metric.name
                                }}</TableCell>
                                <TableCell class="font-mono text-xs">{{
                                    metric.key
                                }}</TableCell>
                                <TableCell class="max-w-sm">
                                    <p
                                        class="text-sm whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ metric.description || '--' }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            :variant="
                                                metric.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                        >
                                            {{
                                                metric.is_active
                                                    ? 'Active'
                                                    : 'Archived'
                                            }}
                                        </Badge>
                                        <Badge
                                            :variant="
                                                metric.templates_count === 0
                                                    ? 'warning'
                                                    : 'outline'
                                            "
                                        >
                                            {{ metric.templates_count }}
                                            template{{
                                                metric.templates_count === 1
                                                    ? ''
                                                    : 's'
                                            }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>{{
                                    fmtDate(
                                        metric.updated_at ?? metric.created_at,
                                    )
                                }}</TableCell>
                                <TableCell class="space-x-2 text-right">
                                    <Button
                                        v-if="metric.can.update"
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                wayfinder.metricLibrary.edit(
                                                    metric.id,
                                                )
                                            "
                                        >
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="sm"
                                        disabled
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="metric.is_active"
                                        variant="ghost"
                                        size="sm"
                                        :disabled="!metric.can.delete"
                                        @click="archiveMetric(metric)"
                                    >
                                        Archive
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="sm"
                                        :disabled="!metric.can.update"
                                        @click="unarchiveMetric(metric)"
                                    >
                                        Unarchive
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!filteredMetrics.length">
                                <TableCell
                                    colspan="6"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    {{ emptyStateMessage }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.metric-search::-webkit-search-decoration,
.metric-search::-webkit-search-cancel-button,
.metric-search::-webkit-search-results-button,
.metric-search::-webkit-search-results-decoration {
    -webkit-appearance: none;
}

.metric-search::-ms-clear,
.metric-search::-ms-reveal {
    display: none;
    width: 0;
    height: 0;
}
</style>
