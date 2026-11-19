<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue';

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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import engagementsRoutes from '@/routes/org/engagements';
import { Loader2, Plus, RotateCcw, Trash2 } from 'lucide-vue-next';

type Responsibility = 'observer' | 'contributor' | 'approver';

type ParticipantOption = {
    id: string;
    name?: string | null;
    email?: string | null;
    affiliation?: string | null;
    type: string;
    is_active: boolean;
    is_pending: boolean;
    is_staff?: boolean;
    membership_role?: string | null;
    stakeholder_role?: string | null;
};

type MetricParticipantAssignee = ParticipantOption | null;

type MetricParticipantRow = {
    id: string;
    responsibility: string;
    role_tags?: string[];
    assignee: MetricParticipantAssignee;
};

type MetricRow = {
    id: string;
    name: string;
    status: string;
    final_definition?: {
        signed_off_at?: string | null;
    } | null;
    participants: MetricParticipantRow[];
};

type DraftParticipantRow = {
    engagement_participant_id: string;
    responsibility: Responsibility;
    role_tags: string[];
};

const props = defineProps<{
    organizationId: string;
    engagementId: string;
    metrics: MetricRow[];
    options: ParticipantOption[];
    canManage: boolean;
}>();

const saveBusy = reactive<Record<string, boolean>>({});
const draftRows = reactive<Record<string, DraftParticipantRow[]>>({});
const originalRows = reactive<Record<string, DraftParticipantRow[]>>({});
const addMenuOpen = reactive<Record<string, boolean>>({});
const saveErrors = reactive<Record<string, string[]>>({});

const closeAllAddMenus = () => {
    Object.keys(addMenuOpen).forEach((metricId) => {
        addMenuOpen[metricId] = false;
    });
};

const onDocumentMouseDown = (event: MouseEvent) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        closeAllAddMenus();

        return;
    }

    if (target.closest('[data-add-participant-menu]')) {
        return;
    }

    closeAllAddMenus();
};

onMounted(() => {
    document.addEventListener('mousedown', onDocumentMouseDown);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentMouseDown);
});

const normalizeResponsibility = (value: string): Responsibility | null => {
    const normalized = value.trim().toLowerCase();

    if (
        normalized === 'observer' ||
        normalized === 'contributor' ||
        normalized === 'approver'
    ) {
        return normalized;
    }

    return null;
};

const responsibilityPriority = (value: Responsibility): number => {
    if (value === 'approver') {
        return 3;
    }

    if (value === 'contributor') {
        return 2;
    }

    return 1;
};

const cloneRows = (rows: DraftParticipantRow[]): DraftParticipantRow[] =>
    rows.map((row) => ({
        engagement_participant_id: row.engagement_participant_id,
        responsibility: row.responsibility,
        role_tags: [...row.role_tags],
    }));

const normalizeRowsForComparison = (rows: DraftParticipantRow[]) =>
    rows
        .map((row) => ({
            engagement_participant_id: row.engagement_participant_id,
            responsibility: row.responsibility,
            role_tags: [...row.role_tags].sort(),
        }))
        .sort((left, right) =>
            left.engagement_participant_id.localeCompare(
                right.engagement_participant_id,
            ),
        );

const isViewerMembershipOption = (option: ParticipantOption | undefined) =>
    option?.type === 'membership' &&
    (option.membership_role ?? '').trim().toLowerCase() === 'viewer';

const isStaffOption = (option: ParticipantOption | undefined) =>
    Boolean(option?.is_staff);

const sortableLabel = (option: ParticipantOption | undefined) =>
    (
        option?.name?.trim() ||
        option?.email?.trim() ||
        option?.id ||
        ''
    ).toLowerCase();

const optionLabel = (option: ParticipantOption | undefined) => {
    if (!option) {
        return 'Unknown participant';
    }

    if (option.name) {
        return option.name;
    }

    if (option.email) {
        return option.email;
    }

    return option.type === 'pending_membership'
        ? 'Pending invite'
        : 'Unknown participant';
};

const optionMetaLine = (
    option: ParticipantOption | undefined,
): string | null => {
    if (!option) {
        return null;
    }

    const email = option.email?.trim() ?? '';
    const affiliation = option.affiliation?.trim() ?? '';
    const parts = [email, affiliation].filter((value) => value.length > 0);

    return parts.length > 0 ? parts.join(' · ') : null;
};

const participantOptionById = computed(() => {
    const map = new Map<string, ParticipantOption>();

    props.options.forEach((option) => {
        map.set(option.id, option);
    });

    props.metrics.forEach((metric) => {
        metric.participants.forEach((participant) => {
            const assignee = participant.assignee;
            if (!assignee?.id || map.has(assignee.id)) {
                return;
            }

            map.set(assignee.id, assignee);
        });
    });

    return map;
});

const addableOptions = computed(() =>
    props.options
        .filter((option) => option.is_active || option.is_pending)
        .sort((left, right) =>
            sortableLabel(left).localeCompare(sortableLabel(right)),
        ),
);

const metricsWithParticipants = computed(() => props.metrics);
const hasMetrics = computed(() => metricsWithParticipants.value.length > 0);
const defaultMetricTab = computed(() => metricsWithParticipants.value[0]?.id);

const participantOptionFor = (participantId: string) =>
    participantOptionById.value.get(participantId);

const participantBadges = (option: ParticipantOption | undefined) => {
    if (!option) {
        return [] as { text: string; variant: 'secondary' | 'outline' }[];
    }

    const badges: { text: string; variant: 'secondary' | 'outline' }[] = [];

    if (option.is_staff) {
        badges.push({ text: 'Staff', variant: 'secondary' });
    }

    if (option.is_pending) {
        badges.push({ text: 'Pending', variant: 'secondary' });
    }

    if (!option.is_active && !option.is_pending) {
        badges.push({ text: 'Inactive', variant: 'outline' });
    }

    if (isViewerMembershipOption(option)) {
        badges.push({ text: 'Viewer', variant: 'outline' });
    }

    return badges;
};

const roleLabel = (value: Responsibility) => {
    if (value === 'approver') {
        return 'Approver';
    }

    if (value === 'contributor') {
        return 'Contributor';
    }

    return 'Observer';
};

const buildDraftRows = (metric: MetricRow): DraftParticipantRow[] => {
    const byParticipant = new Map<string, DraftParticipantRow>();

    metric.participants.forEach((participant) => {
        const participantId = participant.assignee?.id;
        const responsibility = normalizeResponsibility(
            participant.responsibility,
        );

        if (!participantId || !responsibility) {
            return;
        }

        const existing = byParticipant.get(participantId);
        if (!existing) {
            byParticipant.set(participantId, {
                engagement_participant_id: participantId,
                responsibility,
                role_tags: [...(participant.role_tags ?? [])],
            });
            return;
        }

        if (
            responsibilityPriority(responsibility) >
            responsibilityPriority(existing.responsibility)
        ) {
            existing.responsibility = responsibility;
            existing.role_tags = [...(participant.role_tags ?? [])];
        }
    });

    return [...byParticipant.values()].sort((left, right) =>
        sortableLabel(
            participantOptionFor(left.engagement_participant_id),
        ).localeCompare(
            sortableLabel(
                participantOptionFor(right.engagement_participant_id),
            ),
        ),
    );
};

const initializeDrafts = () => {
    const activeMetricIds = new Set<string>();

    props.metrics.forEach((metric) => {
        activeMetricIds.add(metric.id);

        const rows = buildDraftRows(metric);
        draftRows[metric.id] = cloneRows(rows);
        originalRows[metric.id] = cloneRows(rows);
        addMenuOpen[metric.id] = false;
        saveErrors[metric.id] = [];
    });

    Object.keys(draftRows).forEach((metricId) => {
        if (!activeMetricIds.has(metricId)) {
            delete draftRows[metricId];
            delete originalRows[metricId];
            delete addMenuOpen[metricId];
            delete saveErrors[metricId];
            delete saveBusy[metricId];
        }
    });
};

watch(
    () => props.metrics,
    () => {
        initializeDrafts();
    },
    { immediate: true },
);

const roleCounts = (metricId: string) => {
    const rows = draftRows[metricId] ?? [];

    return {
        observers: rows.filter((row) => row.responsibility === 'observer')
            .length,
        contributors: rows.filter((row) => row.responsibility === 'contributor')
            .length,
        approvers: rows.filter((row) => row.responsibility === 'approver')
            .length,
    };
};

const blockingIssuesForMetric = (metric: MetricRow): string[] => {
    const metricId = metric.id;
    const rows = draftRows[metricId] ?? [];
    const counts = roleCounts(metricId);
    const issues: string[] = [];

    if (counts.contributors < 2) {
        issues.push('At least two contributors are required.');
    }

    if (counts.approvers !== 1) {
        issues.push('Exactly one approver is required.');
    }

    if (counts.observers > 5) {
        issues.push('No more than five observers are allowed.');
    }

    const unavailableRows = rows.filter(
        (row) =>
            !participantOptionById.value.has(row.engagement_participant_id),
    );
    if (unavailableRows.length > 0) {
        issues.push(
            'One or more selected participants are no longer available.',
        );
    }

    const inactiveRows = rows.filter((row) => {
        const option = participantOptionFor(row.engagement_participant_id);

        return Boolean(option) && !option?.is_active && !option?.is_pending;
    });
    if (inactiveRows.length > 0) {
        issues.push('Inactive participants must be removed or replaced.');
    }

    const invalidViewerRoles = rows.filter((row) => {
        if (row.responsibility === 'observer') {
            return false;
        }

        return isViewerMembershipOption(
            participantOptionFor(row.engagement_participant_id),
        );
    });

    if (invalidViewerRoles.length > 0) {
        issues.push('View-only participants can only be observers.');
    }

    const invalidStaffRoles = rows.filter((row) => {
        if (row.responsibility !== 'contributor') {
            return false;
        }

        return isStaffOption(
            participantOptionFor(row.engagement_participant_id),
        );
    });

    if (invalidStaffRoles.length > 0) {
        issues.push('Staff participants can only be observers or approvers.');
    }

    return issues;
};

const advisoryWarningsForMetric = (metricId: string): string[] => {
    const rows = draftRows[metricId] ?? [];
    const approver = rows.find((row) => row.responsibility === 'approver');

    if (!approver) {
        return [];
    }

    const option = participantOptionFor(approver.engagement_participant_id);

    if (!option || option.is_staff) {
        return [];
    }

    return [
        'Internal-first warning: approver is not staff. Confirm this is intentional.',
    ];
};

const isMetricLocked = (metric: MetricRow) =>
    !props.canManage || Boolean(metric.final_definition?.signed_off_at);

const metricLockReason = (metric: MetricRow) => {
    if (!metric.final_definition?.signed_off_at) {
        return null;
    }

    const signedOffDate = new Date(metric.final_definition.signed_off_at);

    if (Number.isNaN(signedOffDate.getTime())) {
        return 'Signed off. Participant assignments are read-only.';
    }

    return `Signed off on ${signedOffDate.toLocaleString()}. Participant assignments are read-only.`;
};

const isMetricDirty = (metricId: string) => {
    const current = normalizeRowsForComparison(draftRows[metricId] ?? []);
    const baseline = normalizeRowsForComparison(originalRows[metricId] ?? []);

    return JSON.stringify(current) !== JSON.stringify(baseline);
};

const availableAddOptionsForMetric = (metricId: string) => {
    const assignedIds = new Set(
        (draftRows[metricId] ?? []).map((row) => row.engagement_participant_id),
    );

    return addableOptions.value.filter((option) => !assignedIds.has(option.id));
};

const isNewDraftParticipant = (
    metricId: string,
    participantId: string,
): boolean =>
    !(originalRows[metricId] ?? []).some(
        (row) => row.engagement_participant_id === participantId,
    );

const toggleAddMenu = (metric: MetricRow) => {
    if (isMetricLocked(metric) || saveBusy[metric.id]) {
        return;
    }

    addMenuOpen[metric.id] = !addMenuOpen[metric.id];
};

const addParticipant = (metric: MetricRow, participantId: string) => {
    if (isMetricLocked(metric) || saveBusy[metric.id]) {
        return;
    }

    const availableOptions = availableAddOptionsForMetric(metric.id);
    if (!availableOptions.some((option) => option.id === participantId)) {
        return;
    }

    const rows = draftRows[metric.id] ?? [];
    rows.push({
        engagement_participant_id: participantId,
        responsibility: 'observer',
        role_tags: [],
    });

    rows.sort((left, right) =>
        sortableLabel(
            participantOptionFor(left.engagement_participant_id),
        ).localeCompare(
            sortableLabel(
                participantOptionFor(right.engagement_participant_id),
            ),
        ),
    );

    draftRows[metric.id] = rows;
    addMenuOpen[metric.id] = false;
    saveErrors[metric.id] = [];
};

const removeParticipant = (metric: MetricRow, participantId: string) => {
    if (isMetricLocked(metric) || saveBusy[metric.id]) {
        return;
    }

    draftRows[metric.id] = (draftRows[metric.id] ?? []).filter(
        (row) => row.engagement_participant_id !== participantId,
    );

    saveErrors[metric.id] = [];
};

const setParticipantRole = (
    metric: MetricRow,
    participantId: string,
    responsibilityValue: string,
) => {
    if (isMetricLocked(metric) || saveBusy[metric.id]) {
        return;
    }

    const responsibility = normalizeResponsibility(responsibilityValue);
    if (!responsibility) {
        return;
    }

    const option = participantOptionFor(participantId);
    if (isViewerMembershipOption(option) && responsibility !== 'observer') {
        return;
    }
    if (isStaffOption(option) && responsibility === 'contributor') {
        return;
    }

    const rows = draftRows[metric.id] ?? [];

    if (responsibility === 'approver') {
        rows.forEach((row) => {
            if (
                row.engagement_participant_id !== participantId &&
                row.responsibility === 'approver'
            ) {
                row.responsibility = 'observer';
            }
        });
    }

    const row = rows.find(
        (item) => item.engagement_participant_id === participantId,
    );

    if (!row) {
        return;
    }

    row.responsibility = responsibility;
    saveErrors[metric.id] = [];
};

const resetMetric = (metric: MetricRow) => {
    if (saveBusy[metric.id]) {
        return;
    }

    draftRows[metric.id] = cloneRows(originalRows[metric.id] ?? []);
    addMenuOpen[metric.id] = false;
    saveErrors[metric.id] = [];
};

const flattenErrors = (errors: Record<string, string | string[]>) => {
    const messages = Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .map((value) => String(value).trim())
        .filter((value) => value.length > 0);

    return [...new Set(messages)];
};

const saveMetric = (metric: MetricRow) => {
    if (isMetricLocked(metric) || saveBusy[metric.id]) {
        return;
    }

    const blockingIssues = blockingIssuesForMetric(metric);
    if (blockingIssues.length > 0) {
        saveErrors[metric.id] = blockingIssues;

        return;
    }

    const route = engagementsRoutes.metrics.participants.update([
        props.organizationId,
        props.engagementId,
        metric.id,
    ]);

    saveBusy[metric.id] = true;
    saveErrors[metric.id] = [];

    router.patch(
        route.url,
        {
            participants: (draftRows[metric.id] ?? []).map((row) => ({
                engagement_participant_id: row.engagement_participant_id,
                responsibility: row.responsibility,
                role_tags: row.role_tags,
            })),
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                const messages = flattenErrors(
                    errors as Record<string, string | string[]>,
                );
                saveErrors[metric.id] =
                    messages.length > 0
                        ? messages
                        : ['Unable to save participant assignments.'];
            },
            onSuccess: () => {
                originalRows[metric.id] = cloneRows(draftRows[metric.id] ?? []);
                saveErrors[metric.id] = [];
            },
            onFinish: () => {
                saveBusy[metric.id] = false;
            },
        },
    );
};
</script>

<template>
    <Card v-if="hasMetrics" class="space-y-0">
        <CardHeader>
            <CardTitle>Participant assignments</CardTitle>
            <CardDescription>
                Manage metric participants first, then assign one role per
                participant. Changes apply only when saved.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <Tabs :default-value="defaultMetricTab">
                <TabsList class="w-full justify-start gap-1 overflow-x-auto">
                    <TabsTrigger
                        v-for="metric in metricsWithParticipants"
                        :key="`metric-tab-trigger-${metric.id}`"
                        :value="metric.id"
                        class="max-w-[280px]"
                    >
                        {{ metric.name }} ·
                        {{ draftRows[metric.id]?.length ?? 0 }}
                    </TabsTrigger>
                </TabsList>
                <TabsContent
                    v-for="metric in metricsWithParticipants"
                    :key="`metric-tab-content-${metric.id}`"
                    :value="metric.id"
                    class="space-y-4"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-border pb-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-medium">{{ metric.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ draftRows[metric.id]?.length ?? 0 }}
                                participant roles in draft
                            </p>
                            <p
                                v-if="metricLockReason(metric)"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ metricLockReason(metric) }}
                            </p>
                        </div>

                        <div
                            v-if="!isMetricLocked(metric)"
                            data-add-participant-menu
                            class="relative w-full sm:w-auto"
                        >
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="saveBusy[metric.id]"
                                class="inline-flex items-center gap-2"
                                @click="toggleAddMenu(metric)"
                            >
                                <Plus class="size-4" />
                                Add participant
                            </Button>

                            <div
                                v-if="addMenuOpen[metric.id]"
                                class="absolute right-0 z-30 mt-2 w-full min-w-64 rounded-md border border-border bg-background p-1 shadow-lg"
                            >
                                <template
                                    v-if="
                                        availableAddOptionsForMetric(metric.id)
                                            .length > 0
                                    "
                                >
                                    <button
                                        v-for="option in availableAddOptionsForMetric(
                                            metric.id,
                                        )"
                                        :key="`add-option-${metric.id}-${option.id}`"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-sm px-2 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                        @click="
                                            addParticipant(metric, option.id)
                                        "
                                    >
                                        <span class="flex min-w-0 flex-col">
                                            <span class="truncate font-medium">
                                                {{ optionLabel(option) }}
                                            </span>
                                            <span
                                                v-if="optionMetaLine(option)"
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ optionMetaLine(option) }}
                                            </span>
                                        </span>
                                        <span
                                            v-if="option.is_staff"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Staff
                                        </span>
                                    </button>
                                </template>
                                <p
                                    v-else
                                    class="px-2 py-2 text-sm text-muted-foreground"
                                >
                                    All available participants are already
                                    assigned.
                                </p>
                            </div>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Participant</TableHead>
                                <TableHead class="w-[180px]">Role</TableHead>
                                <TableHead>Badges</TableHead>
                                <TableHead class="w-[120px] text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in draftRows[metric.id] ?? []"
                                :key="`${metric.id}-${row.engagement_participant_id}`"
                                :class="
                                    isNewDraftParticipant(
                                        metric.id,
                                        row.engagement_participant_id,
                                    )
                                        ? 'bg-emerald-50/40'
                                        : ''
                                "
                            >
                                <TableCell
                                    :class="
                                        isNewDraftParticipant(
                                            metric.id,
                                            row.engagement_participant_id,
                                        )
                                            ? 'border-l-4 border-l-emerald-500'
                                            : ''
                                    "
                                >
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{
                                                optionLabel(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                )
                                            }}</span>
                                            <Badge
                                                v-if="
                                                    isNewDraftParticipant(
                                                        metric.id,
                                                        row.engagement_participant_id,
                                                    )
                                                "
                                                variant="secondary"
                                                class="w-fit"
                                            >
                                                New
                                            </Badge>
                                        </div>
                                        <span
                                            v-if="
                                                optionMetaLine(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                )
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                optionMetaLine(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                )
                                            }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <select
                                        :value="row.responsibility"
                                        :disabled="
                                            isMetricLocked(metric) ||
                                            saveBusy[metric.id]
                                        "
                                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
                                        @change="
                                            setParticipantRole(
                                                metric,
                                                row.engagement_participant_id,
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="observer">
                                            {{ roleLabel('observer') }}
                                        </option>
                                        <option
                                            value="contributor"
                                            :disabled="
                                                isViewerMembershipOption(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                ) ||
                                                isStaffOption(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                )
                                            "
                                        >
                                            {{ roleLabel('contributor') }}
                                        </option>
                                        <option
                                            value="approver"
                                            :disabled="
                                                isViewerMembershipOption(
                                                    participantOptionFor(
                                                        row.engagement_participant_id,
                                                    ),
                                                )
                                            "
                                        >
                                            {{ roleLabel('approver') }}
                                        </option>
                                    </select>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="badge in participantBadges(
                                                participantOptionFor(
                                                    row.engagement_participant_id,
                                                ),
                                            )"
                                            :key="badge.text"
                                            :variant="badge.variant"
                                            class="w-fit"
                                        >
                                            {{ badge.text }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        :disabled="
                                            isMetricLocked(metric) ||
                                            saveBusy[metric.id]
                                        "
                                        class="inline-flex items-center gap-2"
                                        @click="
                                            removeParticipant(
                                                metric,
                                                row.engagement_participant_id,
                                            )
                                        "
                                    >
                                        <Trash2 class="size-4" />
                                        Remove
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-if="(draftRows[metric.id] ?? []).length === 0"
                            >
                                <TableCell
                                    colspan="4"
                                    class="text-sm text-muted-foreground"
                                >
                                    No participants assigned.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div
                        v-if="blockingIssuesForMetric(metric).length"
                        class="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm"
                    >
                        <p class="font-medium text-destructive">
                            Save is blocked until these rules are satisfied:
                        </p>
                        <ul class="list-disc space-y-1 pl-5 text-destructive">
                            <li
                                v-for="(
                                    issue, index
                                ) in blockingIssuesForMetric(metric)"
                                :key="`blocking-${metric.id}-${index}`"
                            >
                                {{ issue }}
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="advisoryWarningsForMetric(metric.id).length"
                        class="space-y-1 rounded-md border border-amber-300/60 bg-amber-50 p-3 text-sm"
                    >
                        <p class="font-medium text-amber-800">Warning</p>
                        <ul class="list-disc space-y-1 pl-5 text-amber-800">
                            <li
                                v-for="(
                                    warning, index
                                ) in advisoryWarningsForMetric(metric.id)"
                                :key="`warning-${metric.id}-${index}`"
                            >
                                {{ warning }}
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="saveErrors[metric.id]?.length"
                        class="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm"
                    >
                        <p class="font-medium text-destructive">
                            Unable to save
                        </p>
                        <ul class="list-disc space-y-1 pl-5 text-destructive">
                            <li
                                v-for="(error, index) in saveErrors[metric.id]"
                                :key="`save-error-${metric.id}-${index}`"
                            >
                                {{ error }}
                            </li>
                        </ul>
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t border-border pt-3"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="
                                saveBusy[metric.id] || !isMetricDirty(metric.id)
                            "
                            class="inline-flex items-center gap-2"
                            @click="resetMetric(metric)"
                        >
                            <RotateCcw class="size-4" />
                            Reset
                        </Button>
                        <Button
                            size="sm"
                            :disabled="
                                isMetricLocked(metric) ||
                                saveBusy[metric.id] ||
                                !isMetricDirty(metric.id) ||
                                blockingIssuesForMetric(metric).length > 0
                            "
                            class="inline-flex items-center gap-2"
                            @click="saveMetric(metric)"
                        >
                            <Loader2
                                v-if="saveBusy[metric.id]"
                                class="size-4 animate-spin"
                            />
                            Save
                        </Button>
                    </div>
                </TabsContent>
            </Tabs>
        </CardContent>
    </Card>
</template>
