<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import ClientLayout from '@/layouts/ClientLayout.vue';
import { show as formsShow } from '@/routes/forms';

type Assignment = {
    id: string;
    form: {
        id: string;
        title: string;
        organization?: { id: string; name: string } | null;
    };
    metric: { id: string; name: string } | null;
    engagement: { id: string; title: string } | null;
    status: 'Submitted' | 'In Progress' | 'Overdue';
    due_at: string | null;
    submitted_at: string | null;
    is_due_soon: boolean;
    is_overdue: boolean;
};

type Summary = {
    total: number;
    open: number;
    due_soon: number;
    overdue: number;
    next_due_at: string | null;
};

const props = defineProps<{
    summary: Summary;
    assignments: Assignment[];
}>();

const summaryStats = computed(() => props.summary);
const assignmentList = computed(() => props.assignments);

const nextDueLabel = computed(() =>
    formatDueDate(summaryStats.value.next_due_at),
);
const hasAssignments = computed(() => assignmentList.value.length > 0);

const statusVariant = (status: Assignment['status']) => {
    if (status === 'Overdue') {
        return 'destructive';
    }

    if (status === 'Submitted') {
        return 'secondary';
    }

    return 'outline';
};

function formatDueDate(value: string | null) {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Forms" />

    <ClientLayout
        title="Forms"
        description="Track the forms you need to complete across your engagements."
    >
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-6">
                <section
                    class="rounded-xl border border-border/70 bg-card/80 p-4 shadow-sm sm:flex sm:items-center sm:gap-6 sm:p-6"
                >
                    <div
                        class="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            class="rounded-lg border border-border/70 bg-background/80 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Open assignments
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-foreground"
                            >
                                {{ summaryStats.open }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-background/80 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Due soon
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-foreground"
                            >
                                {{ summaryStats.due_soon }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-background/80 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Overdue
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-foreground"
                            >
                                {{ summaryStats.overdue }}
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-background/80 p-4"
                        >
                            <p
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Next due date
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-foreground"
                            >
                                {{ nextDueLabel ?? 'No upcoming due dates' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-border/70 bg-background/80 p-4"
                    >
                        <p
                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Total assignments:
                        </p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                            {{ summaryStats.total }}
                        </p>
                    </div>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Your contributor forms</CardTitle>
                        <CardDescription>
                            Only assignments where you are the contributor are
                            listed here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="hasAssignments"
                            class="divide-y divide-border/70"
                        >
                            <article
                                v-for="assignment in assignmentList"
                                :key="assignment.id"
                                class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-base font-semibold text-foreground"
                                    >
                                        {{ assignment.form.title }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            assignment.metric?.name ??
                                            'Metric pending'
                                        }}
                                        <span
                                            v-if="assignment.engagement"
                                            class="text-muted-foreground/80"
                                        >
                                            • {{ assignment.engagement.title }}
                                        </span>
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap gap-3 text-xs text-muted-foreground"
                                    >
                                        <span
                                            v-if="
                                                assignment.form.organization
                                                    ?.name
                                            "
                                        >
                                            {{
                                                assignment.form.organization
                                                    .name
                                            }}
                                        </span>
                                        <span v-if="assignment.due_at">
                                            Due
                                            {{
                                                formatDueDate(assignment.due_at)
                                            }}
                                        </span>
                                        <span
                                            v-if="assignment.is_overdue"
                                            class="font-semibold text-rose-600 dark:text-rose-300"
                                        >
                                            Overdue
                                        </span>
                                        <span
                                            v-else-if="assignment.is_due_soon"
                                            class="font-medium text-amber-600 dark:text-amber-300"
                                        >
                                            Due soon
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Badge
                                        :variant="
                                            statusVariant(assignment.status)
                                        "
                                    >
                                        {{ assignment.status }}
                                    </Badge>
                                    <Button
                                        v-if="assignment.form.id"
                                        size="sm"
                                        variant="outline"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                formsShow(assignment.form.id)
                                            "
                                            >Open form</Link
                                        >
                                    </Button>
                                </div>
                            </article>
                        </div>
                        <p
                            v-else
                            class="py-10 text-center text-sm text-muted-foreground"
                        >
                            You're caught up—new forms will appear here when
                            your consultant assigns them.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
