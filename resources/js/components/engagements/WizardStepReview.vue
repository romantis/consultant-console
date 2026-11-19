<template>
    <Card>
        <CardHeader>
            <CardTitle>Review engagement configuration</CardTitle>
            <CardDescription
                >Confirm everything looks correct before
                launching.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-6 text-sm">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-lg border p-4">
                    <p
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        Overview
                    </p>
                    <p class="mt-2 text-base font-semibold">
                        {{ overview.name }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ overview.timelineStart }} →
                        {{ overview.timelineEnd }}
                    </p>
                </div>
                <div class="rounded-lg border p-4">
                    <p
                        class="text-xs tracking-wide text-muted-foreground uppercase"
                    >
                        Handbook repository
                    </p>
                    <p class="mt-2 font-medium">
                        {{ repositoryDriverLabel(overview.repository.driver) }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ overview.repository.identifier }}
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-muted-foreground">
                    Metrics · {{ selectedMetricsCount }}
                </p>
                <ul class="space-y-3">
                    <li
                        v-for="summary in summaryAssignments"
                        :key="`summary-${summary.template.key}`"
                        class="rounded-lg border p-3"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-medium">
                                {{ summary.template.name }}
                            </p>
                            <Badge variant="outline">{{
                                summary.template.key
                            }}</Badge>
                        </div>
                        <div
                            class="mt-3 grid gap-2 text-sm text-muted-foreground md:grid-cols-3"
                        >
                            <div>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Contributors
                                </p>
                                <ul class="mt-1 space-y-1">
                                    <li
                                        v-for="item in summary.contributors"
                                        :key="item.id"
                                    >
                                        {{ item.label }}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Approvers
                                </p>
                                <ul class="mt-1 space-y-1">
                                    <li
                                        v-for="item in summary.approvers"
                                        :key="item.id"
                                    >
                                        {{ item.label }}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Observers
                                </p>
                                <ul class="mt-1 space-y-1">
                                    <li
                                        v-for="item in summary.observers"
                                        :key="item.id"
                                    >
                                        {{ item.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="space-y-2">
                <p class="text-sm font-medium text-muted-foreground">
                    Stakeholders · {{ summaryStakeholders.length }}
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="stakeholder in summaryStakeholders"
                        :key="stakeholder.id"
                    >
                        <span
                            class="mr-2 inline-flex items-center gap-2 font-medium"
                        >
                            <span>{{ stakeholder.name }}</span>
                            <Badge
                                v-if="stakeholder.badge"
                                variant="secondary"
                                class="text-[10px] tracking-wide uppercase"
                            >
                                {{ stakeholder.badge }}
                            </Badge>
                        </span>
                        <span class="text-muted-foreground">
                            · {{ stakeholder.email }} ·
                            {{ resolveStakeholderRoleLabel(stakeholder.role) }}
                        </span>
                    </li>
                </ul>
            </div>
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
import { toRefs } from 'vue';

type WizardOverviewState = {
    name: string;
    timelineStart: string;
    timelineEnd: string;
    repository: {
        driver: string;
        identifier: string;
        notes?: string | null;
    };
};

type SummaryParticipant = {
    id: string;
    label: string;
};

type SummaryAssignment = {
    template: {
        key: string;
        name: string;
    };
    contributors: SummaryParticipant[];
    approvers: SummaryParticipant[];
    observers: SummaryParticipant[];
};

type SummaryStakeholder = {
    id: string;
    name: string;
    email: string;
    role: string;
    badge?: string;
};

const props = defineProps<{
    overview: WizardOverviewState;
    selectedMetricsCount: number;
    summaryAssignments: SummaryAssignment[];
    summaryStakeholders: SummaryStakeholder[];
    repositoryDriverLabel: (driver: string) => string;
    resolveStakeholderRoleLabel: (role?: string) => string;
}>();

const {
    overview,
    selectedMetricsCount,
    summaryAssignments,
    summaryStakeholders,
} = toRefs(props);

const repositoryDriverLabel = props.repositoryDriverLabel;
const resolveStakeholderRoleLabel = props.resolveStakeholderRoleLabel;
</script>
