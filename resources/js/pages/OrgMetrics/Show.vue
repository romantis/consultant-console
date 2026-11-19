<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useWayfinder } from '@/composables/useWayfinder';
import AppLayout from '@/layouts/AppLayout.vue';
import org, { show as orgShow } from '@/routes/org';
import type {
    BreadcrumbItem,
    FinalDefinitionProgress,
    OrgMetricDetail,
} from '@/types';

type AliasForm = {
    alias: string | null;
};

type ParticipantsForm = {
    owners: string[];
    approvers: string[];
};

type DefaultTemplateForm = {
    default_metric_template_id: string;
};

const props = defineProps<{
    organization: { id: string; name: string };
    org_metric: OrgMetricDetail['org_metric'];
    active_engagement: OrgMetricDetail['active_engagement'] | null;
    template_variants: OrgMetricDetail['template_variants'];
    participants: OrgMetricDetail['participants'];
    official_definition: OrgMetricDetail['official_definition'] | null;
    official_definition_history: OrgMetricDetail['official_definition_history'];
    can: { update: boolean; manage_participants: boolean };
}>();

const wayfinder = useWayfinder();
const page = usePage<{
    flags?: {
        org_metric_overrides?: boolean;
    };
}>();
const DEFAULT_TEMPLATE_NONE = '__none__';
const PARTICIPANT_NONE = '__none__';

const canonical = computed(() => props.org_metric.canonical);
const displayName = computed(
    () =>
        props.org_metric.alias ||
        canonical.value?.name ||
        props.org_metric.name,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    {
        title: 'Org Metrics',
        href: wayfinder.org.orgMetrics.index(props.organization.id),
    },
    { title: displayName.value, href: '' },
];

const aliasForm = useForm<AliasForm>({
    alias: props.org_metric.alias ?? null,
});

watch(
    () => props.org_metric.alias,
    (alias) => {
        aliasForm.alias = alias ?? null;
    },
);

const selectFirstParticipant = (
    entries?: OrgMetricDetail['participants']['owners'],
) => {
    const first = entries?.find((entry) => entry.participant_id);
    return first?.participant_id ? [first.participant_id] : [];
};

const participantsForm = useForm<ParticipantsForm>({
    owners: selectFirstParticipant(props.participants?.owners),
    approvers: selectFirstParticipant(props.participants?.approvers),
});

watch(
    () => props.participants?.owners,
    (owners) => {
        participantsForm.owners = selectFirstParticipant(owners);
    },
);

const defaultTemplateForm = useForm<DefaultTemplateForm>({
    default_metric_template_id:
        props.org_metric.default_metric_template_id ?? DEFAULT_TEMPLATE_NONE,
});

watch(
    () => props.participants?.approvers,
    (approvers) => {
        participantsForm.approvers = selectFirstParticipant(approvers);
    },
);

watch(
    () => props.org_metric.default_metric_template_id,
    (value) => {
        defaultTemplateForm.default_metric_template_id =
            value ?? DEFAULT_TEMPLATE_NONE;
    },
);

const participantOptions = computed(() => props.participants?.options ?? []);
const templateVariants = computed(() => props.template_variants ?? []);
const activeEngagement = computed(() => props.active_engagement);
const officialDefinition = computed(() => props.official_definition);
const definitionHistory = computed(
    () => props.official_definition_history ?? [],
);
const orgMetricOverridesEnabled = computed(
    () => page.props.flags?.org_metric_overrides === true,
);
const canUpdateAlias = computed(
    () => orgMetricOverridesEnabled.value && (props.can?.update ?? false),
);
const canManageParticipants = computed(
    () =>
        orgMetricOverridesEnabled.value &&
        (props.can?.manage_participants ?? false),
);

function submitDefaultTemplate() {
    defaultTemplateForm.patch(
        wayfinder.org.orgMetrics.defaultTemplate.update([
            props.organization.id,
            props.org_metric.id,
        ]),
        { preserveScroll: true },
    );
}

const statusVariant = (status?: string | null) => {
    if (!status) return 'secondary';
    const normalized = status.toLowerCase();
    if (normalized === 'draft') return 'outline';
    if (normalized === 'archived') return 'destructive';
    if (normalized === 'active') return 'default';
    return 'secondary';
};

const formatStatusLabel = (value?: string | null) => {
    if (!value) return '—';
    return value.replace(/([A-Z])/g, ' $1').trim();
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

const finalDefinitionProgressLabel = (
    progress?: FinalDefinitionProgress | null,
) => {
    if (!progress) {
        return 'Missing 0';
    }

    if (progress.state === 'signed_off') {
        return 'Signed off';
    }

    if (progress.state === 'ready') {
        return 'Ready';
    }

    return `Missing ${progress.missing_count}`;
};

const participantErrors = computed(() =>
    Object.values(participantsForm.errors ?? {}).filter((value) =>
        Boolean(value),
    ),
);

const updateParticipantSelection = (
    field: keyof ParticipantsForm,
    value: unknown,
) => {
    const normalized =
        value === null || value === undefined
            ? PARTICIPANT_NONE
            : String(value);

    participantsForm[field] =
        normalized === PARTICIPANT_NONE ? [] : [normalized];
};

const submitAlias = () => {
    aliasForm.transform((data) => {
        const value = typeof data.alias === 'string' ? data.alias.trim() : '';

        return {
            ...data,
            alias: value === '' ? null : value,
        };
    });

    aliasForm.patch(
        wayfinder.org.orgMetrics.update([
            props.organization.id,
            props.org_metric.id,
        ]),
        {
            preserveScroll: true,
        },
    );
};

const submitParticipants = () => {
    participantsForm.patch(
        wayfinder.org.orgMetrics.participants.update([
            props.organization.id,
            props.org_metric.id,
        ]),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="displayName" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ displayName }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Canonical: {{ canonical?.name ?? org_metric.name }}
                        <span class="text-xs tracking-wide uppercase">
                            · {{ canonical?.key ?? org_metric.key }}
                        </span>
                    </p>
                </div>
                <Badge
                    v-if="canonical && !canonical.is_active"
                    variant="destructive"
                >
                    Archived
                </Badge>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Canonical metric</CardTitle>
                        <CardDescription>
                            Source metadata from the global library.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div>
                            <p class="font-medium text-foreground">Name</p>
                            <p class="text-muted-foreground">
                                {{ canonical?.name ?? org_metric.name }}
                            </p>
                        </div>
                        <div>
                            <p class="font-medium text-foreground">Key</p>
                            <p class="text-muted-foreground">
                                {{ canonical?.key ?? org_metric.key }}
                            </p>
                        </div>
                        <div>
                            <p class="font-medium text-foreground">
                                Description
                            </p>
                            <p
                                class="whitespace-pre-line text-muted-foreground"
                            >
                                {{
                                    canonical?.description ||
                                    'No description provided.'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="orgMetricOverridesEnabled && canUpdateAlias">
                    <CardHeader>
                        <CardTitle>Org alias</CardTitle>
                        <CardDescription>
                            The display name used in org and client views.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-3" @submit.prevent="submitAlias">
                            <div class="space-y-2">
                                <Label for="org-metric-alias">Alias</Label>
                                <Input
                                    id="org-metric-alias"
                                    v-model="aliasForm.alias"
                                    :disabled="!canUpdateAlias"
                                    placeholder="Optional alias"
                                />
                                <p
                                    v-if="aliasForm.errors.alias"
                                    class="text-xs text-red-600"
                                >
                                    {{ aliasForm.errors.alias }}
                                </p>
                            </div>
                            <Button
                                type="submit"
                                :disabled="
                                    aliasForm.processing || !canUpdateAlias
                                "
                            >
                                Save alias
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Active engagement</CardTitle>
                        <CardDescription>
                            Current engagement context for this org metric.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="activeEngagement" class="space-y-3 text-sm">
                            <div class="space-y-1">
                                <Link
                                    :href="
                                        wayfinder.org.engagements.show([
                                            organization.id,
                                            activeEngagement.id,
                                        ])
                                    "
                                    class="font-medium text-primary underline-offset-2 hover:underline"
                                >
                                    {{ activeEngagement.title }}
                                </Link>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        :variant="
                                            statusVariant(
                                                activeEngagement.status,
                                            )
                                        "
                                    >
                                        {{
                                            formatStatusLabel(
                                                activeEngagement.status,
                                            )
                                        }}
                                    </Badge>
                                    <Badge variant="outline">
                                        {{
                                            formatStatusLabel(
                                                activeEngagement.metric.status,
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </div>
                            <div
                                class="grid gap-2 text-xs text-muted-foreground"
                            >
                                <p>
                                    Last submitted:
                                    {{
                                        formatDate(
                                            activeEngagement.metric
                                                .last_submitted_at,
                                        )
                                    }}
                                </p>
                                <p>
                                    Final definition progress:
                                    {{
                                        finalDefinitionProgressLabel(
                                            activeEngagement.metric
                                                .final_definition_progress,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No active engagement
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Template variants</CardTitle>
                    <CardDescription>
                        Canonical templates used across engagements for this
                        metric.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="orgMetricOverridesEnabled && canUpdateAlias"
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="space-y-1">
                            <Label
                                for="org-metric-default-template"
                                class="text-sm font-medium"
                            >
                                Default template
                            </Label>
                            <p class="text-xs text-muted-foreground">
                                Used to preselect variants when launching new
                                engagements.
                            </p>
                        </div>
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Select
                                :model-value="
                                    defaultTemplateForm.default_metric_template_id
                                "
                                :disabled="
                                    !canUpdateAlias || !templateVariants.length
                                "
                                @update:model-value="
                                    (value) =>
                                        (defaultTemplateForm.default_metric_template_id =
                                            String(value))
                                "
                            >
                                <SelectTrigger
                                    id="org-metric-default-template"
                                    class="sm:min-w-[220px]"
                                >
                                    <SelectValue
                                        placeholder="Select a default template"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="DEFAULT_TEMPLATE_NONE">
                                        No default
                                    </SelectItem>
                                    <SelectItem
                                        v-for="variant in templateVariants"
                                        :key="variant.id"
                                        :value="variant.id"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">
                                                {{ variant.name }}
                                            </span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ variant.key }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                size="sm"
                                :disabled="
                                    defaultTemplateForm.processing ||
                                    !canUpdateAlias ||
                                    !templateVariants.length
                                "
                                @click="submitDefaultTemplate"
                            >
                                {{
                                    defaultTemplateForm.processing
                                        ? 'Saving...'
                                        : 'Save default'
                                }}
                            </Button>
                        </div>
                    </div>
                    <p
                        v-else-if="!orgMetricOverridesEnabled"
                        data-test="org-metric-overrides-disabled"
                        class="mb-4 rounded-md border border-dashed border-muted-foreground/30 p-3 text-xs text-muted-foreground"
                    >
                        Org metric overrides are disabled for MVP mode.
                    </p>
                    <p
                        v-if="
                            orgMetricOverridesEnabled &&
                            defaultTemplateForm.errors
                                .default_metric_template_id
                        "
                        class="mb-4 text-xs text-red-600"
                    >
                        {{
                            defaultTemplateForm.errors
                                .default_metric_template_id
                        }}
                    </p>
                    <Table class="w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Variant</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="text-right"
                                    >Engagements</TableHead
                                >
                                <TableHead>Last used</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="variant in templateVariants"
                                :key="variant.id"
                            >
                                <TableCell>
                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ variant.name }}
                                            <Badge
                                                v-if="variant.is_default"
                                                variant="secondary"
                                                class="ml-2"
                                            >
                                                Default
                                            </Badge>
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ variant.key }}
                                        </p>
                                    </div>
                                </TableCell>
                                <TableCell
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ variant.description || '—' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    {{ variant.engagements_count }}
                                </TableCell>
                                <TableCell
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ formatDate(variant.last_used_at) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!templateVariants.length">
                                <TableCell
                                    colspan="4"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    No template variants yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card v-if="orgMetricOverridesEnabled && canManageParticipants">
                <CardHeader>
                    <CardTitle>Org-level participants</CardTitle>
                    <CardDescription>
                        Assign one owner and one approver for org-level sign-off
                        governance.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        v-if="participantOptions.length"
                        class="grid gap-6 md:grid-cols-2"
                    >
                        <div class="space-y-3">
                            <Label class="text-sm font-medium">Owner</Label>
                            <Select
                                :model-value="
                                    participantsForm.owners[0] ??
                                    PARTICIPANT_NONE
                                "
                                :disabled="!canManageParticipants"
                                @update:model-value="
                                    (value) =>
                                        updateParticipantSelection(
                                            'owners',
                                            value,
                                        )
                                "
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select an owner"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="PARTICIPANT_NONE">
                                        No owner
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in participantOptions"
                                        :key="`owner-${option.participant_id}`"
                                        :value="option.participant_id"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">
                                                {{ option.label }}
                                            </span>
                                            <span
                                                v-if="option.is_pending"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Pending
                                            </span>
                                            <span
                                                v-else-if="option.email"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ option.email }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-3">
                            <Label class="text-sm font-medium">
                                Approver
                            </Label>
                            <Select
                                :model-value="
                                    participantsForm.approvers[0] ??
                                    PARTICIPANT_NONE
                                "
                                :disabled="!canManageParticipants"
                                @update:model-value="
                                    (value) =>
                                        updateParticipantSelection(
                                            'approvers',
                                            value,
                                        )
                                "
                            >
                                <SelectTrigger class="w-full">
                                    <SelectValue
                                        placeholder="Select an approver"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="PARTICIPANT_NONE">
                                        No approver
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in participantOptions"
                                        :key="`approver-${option.participant_id}`"
                                        :value="option.participant_id"
                                    >
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">
                                                {{ option.label }}
                                            </span>
                                            <span
                                                v-if="option.is_pending"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Pending
                                            </span>
                                            <span
                                                v-else-if="option.email"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ option.email }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        No participants available yet.
                    </p>

                    <div
                        v-if="participantErrors.length"
                        class="text-xs text-red-600"
                    >
                        {{ participantErrors[0] }}
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="button"
                            :disabled="
                                participantsForm.processing ||
                                !canManageParticipants
                            "
                            @click="submitParticipants"
                        >
                            Save participants
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Org-level definitions</CardTitle>
                    <CardDescription>
                        Latest sign-off and historical approvals.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="officialDefinition" class="space-y-1 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {{
                                    formatStatusLabel(officialDefinition.status)
                                }}
                            </Badge>
                            <span class="text-muted-foreground">
                                Signed off
                                {{
                                    formatDate(officialDefinition.signed_off_at)
                                }}
                            </span>
                        </div>
                        <p class="text-muted-foreground">
                            by
                            {{
                                officialDefinition.signed_off_by?.name ??
                                'Unknown'
                            }}
                            <span v-if="officialDefinition.metric?.engagement">
                                ·
                                {{ officialDefinition.metric.engagement.title }}
                            </span>
                        </p>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No org-level definitions yet.
                    </p>

                    <Table class="w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Version</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Signed off</TableHead>
                                <TableHead>Engagement</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="definition in definitionHistory"
                                :key="definition.id"
                            >
                                <TableCell>{{
                                    definition.version ?? '—'
                                }}</TableCell>
                                <TableCell>{{
                                    formatStatusLabel(definition.status)
                                }}</TableCell>
                                <TableCell>{{
                                    formatDate(definition.signed_off_at)
                                }}</TableCell>
                                <TableCell
                                    class="text-sm text-muted-foreground"
                                >
                                    {{
                                        definition.metric?.engagement?.title ??
                                        '—'
                                    }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!definitionHistory.length">
                                <TableCell
                                    colspan="4"
                                    class="py-8 text-center text-muted-foreground"
                                >
                                    No org-level definitions yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
