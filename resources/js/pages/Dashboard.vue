<script setup lang="ts">
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { show as formsShow } from '@/routes/forms';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Assignment = {
    id: string;
    form: {
        id: string;
        title: string;
        organization?: { id: string; name: string } | null;
    };
    status: 'Submitted' | 'In Progress' | 'Overdue';
    due_at: string | null;
    submitted_at: string | null;
    is_due_soon: boolean;
    is_overdue: boolean;
};

type Tiles = {
    assigned: number;
    in_progress: number;
    due_soon: number;
    overdue: number;
    submitted: number;
};

type MetricSummary = {
    total: number;
    by_status: Record<string, number>;
};

const props = defineProps<{
    tiles: Tiles;
    assignments: Assignment[];
    metrics: MetricSummary;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const fmtDateTime = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const statusVariant = (status: Assignment['status']) =>
    status === 'Submitted'
        ? 'secondary'
        : status === 'Overdue'
          ? 'destructive'
          : 'outline';

const formatStatusLabel = (value: string) =>
    value.replace(/([A-Z])/g, ' $1').trim();

const metricStatusRows = computed(() =>
    Object.entries(props.metrics.by_status ?? {}).map(([key, count]) => ({
        key,
        label: formatStatusLabel(key),
        count: count as number,
    })),
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Overdue forms</CardTitle>
                        <CardDescription
                            >Needing immediate follow-up.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ tiles.overdue }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>In progress</CardTitle>
                        <CardDescription
                            >Drafts without submissions yet.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ tiles.in_progress }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Due ≤ 48h</CardTitle>
                        <CardDescription
                            >Assignments approaching their
                            deadline.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ tiles.due_soon }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Submitted</CardTitle>
                        <CardDescription
                            >Forms you've already submitted.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ tiles.submitted }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Assigned form status</CardTitle>
                            <CardDescription
                                >{{ tiles.assigned }} active assignments across
                                your organizations.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <Table v-if="assignments.length">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Form</TableHead>
                                        <TableHead class="hidden lg:table-cell"
                                            >Organization</TableHead
                                        >
                                        <TableHead>Due</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right"
                                            >Actions</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="assignment in assignments"
                                        :key="assignment.id"
                                    >
                                        <TableCell>
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{
                                                    assignment.form.title
                                                }}</span>
                                                <span
                                                    v-if="
                                                        assignment.submitted_at
                                                    "
                                                    class="text-xs text-muted-foreground"
                                                    >Submitted
                                                    {{
                                                        fmtDateTime(
                                                            assignment.submitted_at,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </TableCell>
                                        <TableCell class="hidden lg:table-cell">
                                            {{
                                                assignment.form.organization
                                                    ?.name ?? '—'
                                            }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col gap-1">
                                                <span>{{
                                                    fmtDateTime(
                                                        assignment.due_at,
                                                    )
                                                }}</span>
                                                <Badge
                                                    v-if="assignment.is_overdue"
                                                    variant="destructive"
                                                    >Overdue</Badge
                                                >
                                                <Badge
                                                    v-else-if="
                                                        assignment.is_due_soon
                                                    "
                                                    variant="secondary"
                                                    >Due soon</Badge
                                                >
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    statusVariant(
                                                        assignment.status,
                                                    )
                                                "
                                                >{{ assignment.status }}</Badge
                                            >
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                as-child
                                            >
                                                <Link
                                                    :href="
                                                        formsShow(
                                                            assignment.form.id,
                                                        )
                                                    "
                                                    >Open</Link
                                                >
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <div
                                v-else
                                class="flex flex-col items-center justify-center gap-2 py-10 text-center"
                            >
                                <p class="text-sm text-muted-foreground">
                                    You're not assigned to any forms right now.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Metric status mix</CardTitle>
                            <CardDescription
                                >Total metrics tracked:
                                {{ metrics.total }}</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div v-if="metrics.total" class="space-y-3">
                                <div
                                    v-for="item in metricStatusRows"
                                    :key="item.key"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-sm font-medium">{{
                                        item.label
                                    }}</span>
                                    <span
                                        class="text-sm text-muted-foreground"
                                        >{{ item.count }}</span
                                    >
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No metrics are being tracked yet.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Overdue forms</CardTitle>
                            <CardDescription
                                >Items needing immediate
                                follow-up.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <ul v-if="tiles.overdue" class="space-y-2 text-sm">
                                <li
                                    v-for="assignment in assignments.filter(
                                        (a) => a.is_overdue,
                                    )"
                                    :key="assignment.id"
                                    class="flex justify-between gap-2"
                                >
                                    <span class="font-medium">{{
                                        assignment.form.title
                                    }}</span>
                                    <span class="text-muted-foreground">{{
                                        fmtDateTime(assignment.due_at)
                                    }}</span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">
                                Great news—nothing is overdue.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
