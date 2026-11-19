<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppShell from '@/layouts/AppShell.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { GitPullRequest, History, PenSquare, UserCheck } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

type Identifier = string | number;

type MetricSummary = {
    id: Identifier;
    name: string;
    status?: string | null;
    slug?: string | null;
};

type DefinitionField = {
    key: string;
    label: string;
    value?: unknown;
    type?: string | null;
};

type FinalDefinition = {
    version?: number | null;
    fields?: DefinitionField[];
};

type ProposalDiff = {
    field: string;
    label: string;
    before?: unknown;
    after?: unknown;
};

type ProposalApproval = {
    id: Identifier;
    name?: string | null;
    status?: string | null;
    decided_at?: string | null;
};

type ProposalActions = {
    decision_required?: boolean;
    can_decide?: boolean;
    can_merge?: boolean;
};

type ChangeProposal = {
    id: string;
    status?: string | null;
    rationale?: string | null;
    opened_at?: string | null;
    opened_by?: {
        name?: string | null;
        role?: string | null;
    } | null;
    diff?: ProposalDiff[];
    approvals?: ProposalApproval[];
    actions?: ProposalActions;
};

type ProposalStats = {
    open?: number;
    approved?: number;
    merged?: number;
    rejected?: number;
};

type Permissions = {
    canOpen?: boolean;
    canMerge?: boolean;
};

type EndpointConfig = {
    store: string;
    decision: string;
    merge: string;
};

type ApproverOption = {
    id: string;
    name?: string | null;
    email?: string | null;
};

const props = withDefaults(
    defineProps<{
        metric: MetricSummary;
        finalDefinition?: FinalDefinition | null;
        changeProposals?: ChangeProposal[];
        stats?: ProposalStats;
        permissions?: Permissions;
        endpoints: EndpointConfig;
        approverOptions?: ApproverOption[];
    }>(),
    {
        finalDefinition: () => ({
            version: 1,
            fields: [],
        }),
        changeProposals: () => [],
        stats: () => ({
            open: 0,
            approved: 0,
            merged: 0,
            rejected: 0,
        }),
        permissions: () => ({
            canOpen: false,
            canMerge: false,
        }),
        approverOptions: () => [],
    },
);

const page = usePage<{
    flags?: {
        change_proposals?: boolean;
    };
}>();
const changeProposalsEnabled = computed(
    () => page.props.flags?.change_proposals === true,
);
const pageTitle = computed(() => 'Change governance');
const pageDescription = computed(() =>
    changeProposalsEnabled.value
        ? 'Propose, review, and merge updates after a metric has been finalized.'
        : 'Change proposals are disabled for this MVP environment.',
);

const headTitle = computed(
    () => `${pageTitle.value} • ${props.metric?.name ?? 'Metric'}`,
);

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const trail: BreadcrumbItemType[] = [
        {
            title: 'Metrics',
            href: '#',
        },
    ];

    if (props.metric?.name) {
        trail.push({
            title: props.metric.name,
            href: '#',
        });
    }

    trail.push({
        title: 'Change governance',
        href: '',
    });

    return trail;
});

const definitionFields = computed(() => props.finalDefinition?.fields ?? []);

const definitionVersion = computed(() => props.finalDefinition?.version ?? 1);

const proposalStats = computed(() =>
    ['open', 'approved', 'merged', 'rejected'].map((key) => ({
        key,
        label: startCase(key),
        count: Number(props.stats?.[key as keyof ProposalStats] ?? 0),
    })),
);

const availableApprovers = computed<ApproverOption[]>(
    () => props.approverOptions ?? [],
);

const sortedProposals = computed(() => {
    const list = Array.isArray(props.changeProposals)
        ? [...props.changeProposals]
        : [];

    return list.sort((a, b) => parseDate(b.opened_at) - parseDate(a.opened_at));
});

const hasProposals = computed(() => sortedProposals.value.length > 0);

const proposalEditorOpen = ref(false);

const proposalDraft = reactive({
    rationale: '',
});

const fieldDrafts = reactive<Record<string, string>>({});
const selectedApproverIds = ref<string[]>([]);

const changedFields = computed<Record<string, string>>(() => {
    const deltas: Record<string, string> = {};
    definitionFields.value.forEach((field) => {
        const draftValue = (fieldDrafts[field.key] ?? '').trim();
        const currentValue = stringifyValue(field.value);

        if (draftValue && draftValue !== currentValue) {
            deltas[field.key] = draftValue;
        }
    });
    return deltas;
});

const definitionFieldLookup = computed<Record<string, DefinitionField>>(() => {
    return Object.fromEntries(
        definitionFields.value.map((field) => [field.key, field]),
    );
});

const buildProposalPayload = () => {
    const payload: Record<string, { label: string; value: string }> = {};
    Object.entries(changedFields.value).forEach(([fieldKey, draft]) => {
        payload[fieldKey] = {
            label:
                definitionFieldLookup.value[fieldKey]?.label ??
                startCase(fieldKey),
            value: draft,
        };
    });

    return payload;
};

const hasApproverSelection = computed(
    () => selectedApproverIds.value.length > 0,
);
const canSelectApprovers = computed(() => availableApprovers.value.length > 0);

const toggleApproverSelection = (id: string, checked: boolean) => {
    if (checked) {
        if (!selectedApproverIds.value.includes(id)) {
            selectedApproverIds.value = [...selectedApproverIds.value, id];
        }
        return;
    }

    selectedApproverIds.value = selectedApproverIds.value.filter(
        (entry) => entry !== id,
    );
};

watch(
    () => availableApprovers.value.map((approver) => String(approver.id)),
    (validIds) => {
        selectedApproverIds.value = selectedApproverIds.value.filter((id) =>
            validIds.includes(id),
        );
    },
    { immediate: true },
);

const canSubmitProposal = computed(
    () =>
        changeProposalsEnabled.value &&
        Boolean(props.permissions?.canOpen) &&
        proposalDraft.rationale.trim().length > 0 &&
        Object.keys(changedFields.value).length > 0 &&
        hasApproverSelection.value,
);

const decisionDrafts = reactive<Record<string, string>>({});

const toggleProposalEditor = () => {
    if (!changeProposalsEnabled.value) {
        proposalEditorOpen.value = false;
        return;
    }

    proposalEditorOpen.value = !proposalEditorOpen.value;
    if (!proposalEditorOpen.value) {
        resetProposalDraft();
    }
};

const resetProposalDraft = () => {
    proposalDraft.rationale = '';
    Object.keys(fieldDrafts).forEach((key) => {
        delete fieldDrafts[key];
    });
    selectedApproverIds.value = [];
};

const closeProposalEditor = () => {
    proposalEditorOpen.value = false;
    resetProposalDraft();
};

const updateDecisionComment = (id: string, value: string) => {
    decisionDrafts[id] = value;
};

const decisionComment = (id: string) => decisionDrafts[id] ?? '';

const submitProposal = () => {
    if (!changeProposalsEnabled.value || !canSubmitProposal.value) {
        return;
    }

    const payload = buildProposalPayload();

    if (!Object.keys(payload).length) {
        return;
    }

    const body = {
        rationale: proposalDraft.rationale.trim(),
        payload,
        approver_ids: [...selectedApproverIds.value],
    };

    router.post(props.endpoints.store, body, {
        preserveScroll: true,
    });

    closeProposalEditor();
};

const PROPOSAL_PLACEHOLDER = '__PROPOSAL__';

const resolveEndpoint = (template: string, id: string) =>
    template.replace(PROPOSAL_PLACEHOLDER, id);

const submitDecision = (
    proposal: ChangeProposal,
    decision: 'approve' | 'reject',
) => {
    if (!changeProposalsEnabled.value || !proposal.actions?.can_decide) {
        return;
    }

    const url = resolveEndpoint(props.endpoints.decision, proposal.id);
    const comment = decisionComment(proposal.id).trim();

    router.post(
        url,
        {
            decision,
            comment: comment.length ? comment : undefined,
        },
        {
            preserveScroll: true,
        },
    );
};

const mergeProposal = (proposal: ChangeProposal) => {
    if (!changeProposalsEnabled.value || !proposal.actions?.can_merge) {
        return;
    }

    const url = resolveEndpoint(props.endpoints.merge, proposal.id);
    router.post(
        url,
        {},
        {
            preserveScroll: true,
        },
    );
};

const parseDate = (value?: string | null) => {
    if (!value) {
        return 0;
    }

    const timestamp = Date.parse(value);
    return Number.isNaN(timestamp) ? 0 : timestamp;
};

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString();
};

const startCase = (value?: string | null) => {
    if (!value) {
        return '';
    }

    return value
        .toString()
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const stringifyValue = (value: unknown): string => {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.map(stringifyValue).join(', ');
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return '';
};

const proposalStatusVariant = (status?: string | null) => {
    switch ((status ?? '').toLowerCase()) {
        case 'approved':
            return 'secondary';
        case 'merged':
            return 'outline';
        case 'rejected':
            return 'destructive';
        default:
            return 'default';
    }
};

const approvalStatusVariant = (status?: string | null) => {
    switch ((status ?? '').toLowerCase()) {
        case 'approved':
            return 'secondary';
        case 'pending':
            return 'outline';
        default:
            return 'default';
    }
};
</script>

<template>
    <AppShell
        :title="pageTitle"
        :description="pageDescription"
        :breadcrumbs="breadcrumbs"
    >
        <Head :title="headTitle" />

        <div
            class="mb-6 flex flex-col gap-3 rounded-lg border border-muted/40 bg-card/40 p-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p
                    data-test="governance-metric-title"
                    class="text-lg font-semibold text-foreground"
                >
                    {{ props.metric.name ?? 'Metric' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Governance applies to the currently finalized definition and
                    any pending change proposals.
                </p>
            </div>
            <Badge
                class="self-start"
                :variant="
                    proposalStatusVariant(props.metric.status ?? 'Finalized')
                "
            >
                {{ startCase(props.metric.status ?? 'Finalized') }}
            </Badge>
        </div>

        <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
            <section class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <GitPullRequest
                                class="size-4 text-muted-foreground"
                            />
                            Final definition
                        </CardTitle>
                        <CardDescription>
                            Source of truth for the finalized metric values and
                            context.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            class="flex items-center justify-between text-xs text-muted-foreground"
                        >
                            <span>Version {{ definitionVersion }}</span>
                            <Badge variant="outline">{{
                                startCase(props.metric.status ?? 'Finalized')
                            }}</Badge>
                        </div>
                        <Separator />
                        <dl class="space-y-4">
                            <div
                                v-for="field in definitionFields"
                                :key="field.key"
                                class="space-y-1 rounded-lg border border-muted/40 p-3"
                            >
                                <dt
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ field.label }}
                                </dt>
                                <dd
                                    class="text-sm leading-relaxed text-foreground"
                                >
                                    {{ stringifyValue(field.value) || '—' }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <History class="size-4 text-muted-foreground" />
                            Proposal status
                        </CardTitle>
                        <CardDescription
                            >Track each proposal as it moves from open to
                            merged.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                v-for="stat in proposalStats"
                                :key="stat.key"
                                class="rounded-lg bg-muted/40 p-3"
                                :data-test="`proposal-stat-${stat.key}`"
                                :data-count="stat.count.toString()"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ stat.label }}
                                </p>
                                <p
                                    class="text-2xl font-semibold text-foreground"
                                >
                                    {{ stat.count }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    v-if="changeProposalsEnabled && props.permissions?.canOpen"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <PenSquare class="size-4 text-muted-foreground" />
                            Open a change proposal
                        </CardTitle>
                        <CardDescription>
                            Capture rationale, assign approvers, and describe
                            the revisions you need.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button
                            data-test="open-proposal-trigger"
                            class="w-full justify-center"
                            :disabled="!props.permissions?.canOpen"
                            @click="toggleProposalEditor"
                        >
                            {{
                                proposalEditorOpen
                                    ? 'Hide proposal form'
                                    : 'Start change proposal'
                            }}
                        </Button>

                        <div
                            v-if="proposalEditorOpen"
                            data-test="proposal-editor"
                            class="space-y-4 rounded-lg border border-dashed border-primary/40 p-4"
                        >
                            <div class="space-y-2">
                                <label
                                    class="text-sm font-medium text-foreground"
                                >
                                    Rationale
                                </label>
                                <Textarea
                                    v-model="proposalDraft.rationale"
                                    data-test="proposal-rationale-input"
                                    placeholder="Explain why the finalized definition needs an update…"
                                    rows="4"
                                />
                            </div>
                            <div class="space-y-2">
                                <label
                                    class="text-sm font-medium text-foreground"
                                >
                                    Approvers
                                </label>
                                <p class="text-xs text-muted-foreground">
                                    Select the approvers responsible for
                                    reviewing this proposal.
                                </p>
                                <div
                                    v-if="canSelectApprovers"
                                    class="space-y-2 rounded-lg border border-dashed border-muted/40 p-3"
                                >
                                    <label
                                        v-for="approver in availableApprovers"
                                        :key="approver.id"
                                        class="flex items-start gap-3 text-sm"
                                        :for="`approver-${approver.id}`"
                                    >
                                        <Checkbox
                                            :id="`approver-${approver.id}`"
                                            :model-value="
                                                selectedApproverIds.includes(
                                                    String(approver.id),
                                                )
                                            "
                                            @update:model-value="
                                                (checked: boolean) =>
                                                    toggleApproverSelection(
                                                        String(approver.id),
                                                        Boolean(checked),
                                                    )
                                            "
                                        />
                                        <div>
                                            <p
                                                class="font-medium text-foreground"
                                            >
                                                {{
                                                    approver.name ?? 'Approver'
                                                }}
                                            </p>
                                            <p
                                                v-if="approver.email"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ approver.email }}
                                            </p>
                                        </div>
                                    </label>
                                </div>
                                <p
                                    v-else
                                    class="rounded-lg border border-dashed border-muted/40 p-3 text-xs text-muted-foreground"
                                >
                                    Add at least one approver to the metric
                                    before opening proposals.
                                </p>
                                <p
                                    v-if="!hasApproverSelection"
                                    class="text-xs text-destructive"
                                >
                                    Select at least one approver.
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div
                                    v-for="field in definitionFields"
                                    :key="`editor-${field.key}`"
                                    class="space-y-2 rounded-lg border border-muted/40 p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            {{ field.label }}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="text-[11px] tracking-wide uppercase"
                                        >
                                            {{ field.type ?? 'text' }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Current value:
                                        {{ stringifyValue(field.value) || '—' }}
                                    </p>
                                    <component
                                        :is="
                                            (field.type ?? '').toLowerCase() ===
                                            'markdown'
                                                ? Textarea
                                                : Input
                                        "
                                        v-model="fieldDrafts[field.key]"
                                        :data-test="'proposal-field-input'"
                                        :data-field="field.key"
                                        :rows="
                                            (field.type ?? '').toLowerCase() ===
                                            'markdown'
                                                ? 3
                                                : undefined
                                        "
                                        placeholder="Proposed update"
                                    />
                                    <div
                                        v-if="changedFields[field.key]"
                                        class="rounded-md bg-muted/40 p-2 text-xs text-muted-foreground"
                                        :data-test="'proposal-change-preview'"
                                        :data-field="field.key"
                                    >
                                        Proposed value:
                                        <span
                                            class="font-medium text-foreground"
                                            >{{
                                                changedFields[field.key]
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <CardFooter class="flex flex-wrap gap-3 pt-2">
                                <Button
                                    variant="outline"
                                    type="button"
                                    @click="closeProposalEditor"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    data-test="submit-proposal-button"
                                    type="button"
                                    :disabled="!canSubmitProposal"
                                    @click="submitProposal"
                                >
                                    Submit for approval
                                </Button>
                            </CardFooter>
                        </div>
                    </CardContent>
                </Card>
                <Card
                    v-else-if="!changeProposalsEnabled"
                    data-test="change-proposals-disabled"
                >
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <PenSquare class="size-4 text-muted-foreground" />
                            Change proposals disabled
                        </CardTitle>
                        <CardDescription>
                            This MVP rail keeps finalized metric governance in
                            read-only mode.
                        </CardDescription>
                    </CardHeader>
                </Card>
            </section>

            <section class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <History class="size-4 text-muted-foreground" />
                            Proposal timeline
                        </CardTitle>
                        <CardDescription
                            >Ordered by the time each proposal was
                            opened.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <ul v-if="hasProposals" class="space-y-3">
                            <li
                                v-for="proposal in sortedProposals"
                                :key="proposal.id"
                                class="flex items-center justify-between gap-4 rounded-lg border border-muted/50 p-3"
                                data-test="proposal-row"
                                :data-id="proposal.id"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            proposal.rationale ??
                                            'Change proposal'
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            formatDateTime(
                                                proposal.opened_at,
                                            ) ?? 'Pending'
                                        }}
                                        <span v-if="proposal.opened_by?.name">
                                            •
                                            {{ proposal.opened_by?.name }}</span
                                        >
                                    </p>
                                </div>
                                <Badge
                                    data-test="proposal-status-badge"
                                    :variant="
                                        proposalStatusVariant(proposal.status)
                                    "
                                >
                                    {{ startCase(proposal.status ?? 'Open') }}
                                </Badge>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="rounded-lg border border-dashed border-muted-foreground/40 p-4 text-center text-sm text-muted-foreground"
                        >
                            No change proposals have been opened yet.
                        </p>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card
                        v-for="proposal in sortedProposals"
                        :key="`card-${proposal.id}`"
                        class="space-y-4"
                        data-test="proposal-card"
                        :data-id="proposal.id"
                    >
                        <CardHeader>
                            <CardTitle
                                class="flex flex-wrap items-center gap-2 text-base"
                            >
                                <UserCheck
                                    class="size-4 text-muted-foreground"
                                />
                                {{ proposal.rationale ?? 'Change proposal' }}
                                <Badge
                                    :variant="
                                        proposalStatusVariant(proposal.status)
                                    "
                                >
                                    {{ startCase(proposal.status ?? 'Open') }}
                                </Badge>
                            </CardTitle>
                            <CardDescription
                                class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground"
                            >
                                <span
                                    >Opened
                                    {{
                                        formatDateTime(proposal.opened_at) ??
                                        'Pending'
                                    }}</span
                                >
                                <span v-if="proposal.opened_by?.name"
                                    >• {{ proposal.opened_by?.name }}</span
                                >
                                <span
                                    v-if="
                                        changeProposalsEnabled &&
                                        proposal.actions?.decision_required
                                    "
                                    class="text-destructive"
                                >
                                    • Decision required
                                </span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div v-if="proposal.diff?.length" class="space-y-4">
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Requested changes
                                </h3>
                                <div
                                    v-for="diff in proposal.diff"
                                    :key="`${proposal.id}-${diff.field}`"
                                    class="space-y-2 rounded-lg border border-muted/40 p-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <p
                                            class="text-sm font-medium text-foreground"
                                        >
                                            {{ diff.label }}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="text-[11px] tracking-wide uppercase"
                                        >
                                            Field
                                        </Badge>
                                    </div>
                                    <div
                                        class="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2"
                                    >
                                        <div>
                                            <p
                                                class="font-medium tracking-wide text-muted-foreground/80 uppercase"
                                            >
                                                Current
                                            </p>
                                            <p class="text-sm text-foreground">
                                                {{
                                                    stringifyValue(
                                                        diff.before,
                                                    ) || '—'
                                                }}
                                            </p>
                                        </div>
                                        <div>
                                            <p
                                                class="font-medium tracking-wide text-muted-foreground/80 uppercase"
                                            >
                                                Proposed
                                            </p>
                                            <p class="text-sm text-foreground">
                                                {{
                                                    stringifyValue(
                                                        diff.after,
                                                    ) || '—'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="proposal.approvals?.length"
                                class="space-y-3"
                            >
                                <h3
                                    class="text-sm font-semibold text-foreground"
                                >
                                    Approvals
                                </h3>
                                <ul class="space-y-2">
                                    <li
                                        v-for="approval in proposal.approvals"
                                        :key="approval.id"
                                        class="flex flex-col gap-1 rounded-md border border-muted/30 p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <p
                                                class="text-sm font-medium text-foreground"
                                            >
                                                {{
                                                    approval.name ?? 'Approver'
                                                }}
                                            </p>
                                            <Badge
                                                :variant="
                                                    approvalStatusVariant(
                                                        approval.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    startCase(
                                                        approval.status ??
                                                            'Pending',
                                                    )
                                                }}
                                            </Badge>
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                approval.decided_at
                                                    ? formatDateTime(
                                                          approval.decided_at,
                                                      )
                                                    : 'Awaiting response'
                                            }}
                                        </p>
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                        <CardFooter
                            v-if="
                                changeProposalsEnabled &&
                                (proposal.actions?.can_decide ||
                                    proposal.actions?.can_merge)
                            "
                            class="flex flex-col gap-3"
                        >
                            <div
                                v-if="proposal.actions?.can_decide"
                                class="w-full space-y-3 rounded-lg border border-muted/30 p-3"
                            >
                                <label
                                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Decision comment
                                </label>
                                <Textarea
                                    :model-value="decisionComment(proposal.id)"
                                    rows="3"
                                    data-test="proposal-decision-comment"
                                    placeholder="Capture context for the approval or rejection…"
                                    @update:model-value="
                                        (value: string) =>
                                            updateDecisionComment(
                                                proposal.id,
                                                value,
                                            )
                                    "
                                />
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        data-test="proposal-approve"
                                        size="sm"
                                        type="button"
                                        @click="
                                            submitDecision(proposal, 'approve')
                                        "
                                    >
                                        Approve
                                    </Button>
                                    <Button
                                        data-test="proposal-reject"
                                        size="sm"
                                        type="button"
                                        variant="destructive"
                                        @click="
                                            submitDecision(proposal, 'reject')
                                        "
                                    >
                                        Request changes
                                    </Button>
                                </div>
                            </div>

                            <Button
                                v-if="proposal.actions?.can_merge"
                                data-test="proposal-merge"
                                type="button"
                                variant="secondary"
                                @click="mergeProposal(proposal)"
                            >
                                Merge into final definition
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </section>
        </div>
    </AppShell>
</template>
