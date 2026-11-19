<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronsUpDown } from 'lucide-vue-next';

import org, { show as orgShow } from '@/routes/org';
import {
    index as orgEngagementsIndex,
    launch as orgEngagementsLaunch,
    show as orgEngagementsShow,
} from '@/routes/org/engagements';
import { edit as editOrg } from '@/routes/org/settings';

import { useEngagementNavigation } from '@/composables/useEngagementNavigation';
import { useWayfinder } from '@/composables/useWayfinder';
import type {
    BreadcrumbItem,
    MemberRow,
    Organization,
    TemplateRow,
} from '@/types';

type ActivityItem = {
    id: string;
    action: string;
    created_at: string | null;
    user: { id: string; name: string } | null;
    subject: { type: string | null; id: string | null } | null;
    summary?: string | null;
};

type AssignmentInsight = {
    open: number;
    due_soon: number;
    overdue: number;
};

type Insights = {
    engagements: number;
    open_forms: number;
    assignments: AssignmentInsight;
    members: number;
    templates: number;
};

type OperatorAssignment = {
    id: string;
    granted_at: string | null;
    reason?: string | null;
    notes?: string | null;
    user: { id: string; name: string; email: string } | null;
    granted_by?: { id: string; name: string } | null;
};

type OperatorCandidate = {
    id: string;
    name?: string | null;
    email?: string | null;
    affiliation?: string | null;
};

const props = defineProps<{
    organization: Organization;
    templates: TemplateRow[];
    members: MemberRow[];
    activity: ActivityItem[];
    insights: Insights;
    can_create: boolean;
    can_update: boolean;
    can_manage_operator_assignments?: boolean;
    operator_assignments?: OperatorAssignment[];
    operator_candidates?: OperatorCandidate[];
    activeOrganizationId?: string | null;
}>();

const page = usePage();
const isInternal = computed(() => Boolean(props.organization?.is_internal));
const canGovernInternal = computed(
    () => page.props.auth?.user?.can_govern_internal === true,
);
const wayfinder = useWayfinder();
const ownerConsoleHref = wayfinder.owner.console().url;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organizations',
        href: org.index().url,
    },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    {
        title: 'Overview',
        href: '',
    },
];

const organizationIdRef = computed(() => props.organization.id ?? null);

const { engagementOptions, buildMetricsUrlFor, setSelectedEngagement } =
    useEngagementNavigation({ organizationId: organizationIdRef });

const singleEngagementOption = computed(() =>
    !isInternal.value && engagementOptions.value.length === 1
        ? engagementOptions.value[0]
        : null,
);

const hasEngagementShortcuts = computed(
    () => !isInternal.value && engagementOptions.value.length > 0,
);

const openFormsFor = (engagementId: string) => {
    const organizationId = organizationIdRef.value;
    if (!organizationId) return;

    setSelectedEngagement(engagementId);
    const base = orgEngagementsShow([organizationId, engagementId]);
    const href = typeof base === 'string' ? base : base?.url;

    if (!href) return;

    router.visit(`${href}#forms-panel`);
};

const openMetricsFor = (engagementId: string) => {
    const href = buildMetricsUrlFor(engagementId);
    if (!href) return;

    setSelectedEngagement(engagementId);
    router.visit(href);
};

const memberPreview = computed(() => props.members.slice(0, 5));
const memberOverflow = computed(() =>
    Math.max(
        0,
        (props.insights?.members ?? props.members.length) -
            memberPreview.value.length,
    ),
);

const templatePreview = computed(() => props.templates.slice(0, 3));
const templateOverflow = computed(() =>
    Math.max(
        0,
        (props.insights?.templates ?? props.templates.length) -
            templatePreview.value.length,
    ),
);

const insightCards = computed(() => {
    if (isInternal.value) {
        return [];
    }

    return [
        {
            key: 'engagements',
            title: 'Active engagements',
            value: props.insights?.engagements ?? 0,
            description: 'Projects currently in flight.',
        },
        {
            key: 'open_forms',
            title: 'Open forms',
            value: props.insights?.open_forms ?? 0,
            description: 'Awaiting submissions or review.',
        },
        {
            key: 'due_soon',
            title: 'Assignments due ≤ 48h',
            value: props.insights?.assignments?.due_soon ?? 0,
            description: `${props.insights?.assignments?.open ?? 0} active assignments`,
        },
        {
            key: 'overdue',
            title: 'Overdue assignments',
            value: props.insights?.assignments?.overdue ?? 0,
            description: 'Require immediate follow-up.',
        },
        {
            key: 'members',
            title: 'Members',
            value: props.insights?.members ?? props.members.length,
            description: 'People collaborating in this org.',
        },
    ];
});

const activityItems = computed(() => props.activity ?? []);
const canManageOperatorAssignments = computed(
    () => props.can_manage_operator_assignments === true,
);
const operatorAssignments = computed(() => props.operator_assignments ?? []);
const operatorCandidates = computed(() => props.operator_candidates ?? []);
const operatorAssignmentForm = useForm({
    user_id: '',
});
const operatorAssignmentError = ref<string | null>(null);

const fmtRelative = (iso: string | null) => {
    if (!iso) return '—';
    const date = new Date(iso);
    const diffSeconds = Math.round((date.getTime() - Date.now()) / 1000);
    const absSeconds = Math.abs(diffSeconds);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    if (absSeconds < 60) return rtf.format(diffSeconds, 'second');
    if (absSeconds < 3600)
        return rtf.format(Math.round(diffSeconds / 60), 'minute');
    if (absSeconds < 86400)
        return rtf.format(Math.round(diffSeconds / 3600), 'hour');
    if (absSeconds < 604800)
        return rtf.format(Math.round(diffSeconds / 86400), 'day');
    return rtf.format(Math.round(diffSeconds / 604800), 'week');
};

const fmtDateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString() : '—';

const formatActionLabel = (action: string) =>
    action.replace(/[._]/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const submitOperatorAssignment = () => {
    operatorAssignmentError.value = null;

    if (operatorAssignmentForm.user_id.trim() === '') {
        operatorAssignmentError.value = 'Select an operator.';
        return;
    }

    operatorAssignmentForm.post(
        org.staffOperatorAssignments.store(props.organization.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                operatorAssignmentForm.reset();
                operatorAssignmentError.value = null;
            },
        },
    );
};

const revokeOperatorAssignment = (assignmentId: string) => {
    router.delete(
        org.staffOperatorAssignments.destroy({
            organization: props.organization.id,
            assignment: assignmentId,
        }).url,
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="`Overview — ${organization.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full space-y-8 p-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-3xl font-semibold tracking-tight">
                            {{ organization.name }}
                        </h1>
                        <Badge
                            v-if="organization.slug"
                            variant="outline"
                            class="font-mono uppercase"
                            >{{ organization.slug }}</Badge
                        >
                        <Badge
                            v-if="isInternal"
                            variant="secondary"
                            class="font-mono uppercase"
                            >Staff workspace</Badge
                        >
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="can_update" variant="outline" as-child>
                        <Link :href="editOrg(organization.id)">Settings</Link>
                    </Button>
                    <Button
                        v-if="can_create && !isInternal"
                        variant="outline"
                        as-child
                    >
                        <Link :href="orgEngagementsLaunch(organization.id)"
                            >New engagement</Link
                        >
                    </Button>
                </div>
            </div>
            <div
                v-if="!isInternal"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-5"
            >
                <Card v-for="card in insightCards" :key="card.key">
                    <CardHeader>
                        <CardTitle>{{ card.title }}</CardTitle>
                        <CardDescription>{{
                            card.description
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ card.value }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                        <CardDescription>
                            <template v-if="isInternal">
                                Latest updates for staff-only changes and
                                workspace configuration.
                            </template>
                            <template v-else>
                                Latest updates captured across forms, metrics,
                                and engagements.
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="activityItems.length" class="space-y-4">
                            <li
                                v-for="item in activityItems"
                                :key="item.id"
                                class="flex flex-col gap-1 border-b pb-4 last:border-0 last:pb-0"
                            >
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div class="space-y-1">
                                        <p class="leading-tight font-medium">
                                            {{ formatActionLabel(item.action) }}
                                        </p>
                                        <p
                                            class="text-sm text-muted-foreground"
                                        >
                                            <span v-if="item.user"
                                                >{{ item.user.name }} ·
                                            </span>
                                            <span>{{
                                                fmtRelative(item.created_at)
                                            }}</span>
                                        </p>
                                        <p
                                            v-if="item.summary"
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ item.summary }}
                                        </p>
                                    </div>
                                    <span
                                        class="text-xs whitespace-nowrap text-muted-foreground"
                                        >{{
                                            fmtDateTime(item.created_at)
                                        }}</span
                                    >
                                </div>
                            </li>
                        </ul>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center gap-2 py-12 text-center text-sm text-muted-foreground"
                        >
                            <p v-if="isInternal">
                                <template v-if="canGovernInternal">
                                    No internal activity recorded yet. Visit the
                                    Owner Console to review client engagements
                                    and recent delivery updates.
                                </template>
                                <template v-else>
                                    No internal activity recorded yet. Delivery
                                    tools and operator staffing remain available
                                    from client organization workspaces.
                                </template>
                            </p>
                            <p v-else>
                                No activity recorded yet. Create forms or
                                engagements to get things moving.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Team snapshot</CardTitle>
                            <CardDescription
                                >{{
                                    props.insights?.members ?? members.length
                                }}
                                total members</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <ul v-if="memberPreview.length" class="space-y-3">
                                <li
                                    v-for="member in memberPreview"
                                    :key="member.id"
                                    class="flex items-center gap-3"
                                >
                                    <Avatar class="h-9 w-9">
                                        <AvatarFallback>{{
                                            member.name
                                                .slice(0, 2)
                                                .toUpperCase()
                                        }}</AvatarFallback>
                                    </Avatar>
                                    <span class="text-sm font-medium">{{
                                        member.name
                                    }}</span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">
                                No members added yet.
                            </p>

                            <p
                                v-if="memberOverflow > 0"
                                class="text-xs text-muted-foreground"
                            >
                                +{{ memberOverflow }} more teammates.
                            </p>
                        </CardContent>
                        <CardFooter>
                            <Button
                                v-if="can_update"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="editOrg(organization.id)"
                                    >Manage members</Link
                                >
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card v-if="canManageOperatorAssignments">
                        <CardHeader>
                            <CardTitle>Staff Operators</CardTitle>
                            <CardDescription>
                                Grant or revoke delegated operator access for
                                this client organization.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div
                                class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]"
                            >
                                <div class="space-y-2">
                                    <label
                                        for="operator-candidate"
                                        class="text-sm font-medium"
                                    >
                                        Internal operator
                                    </label>
                                    <select
                                        id="operator-candidate"
                                        v-model="operatorAssignmentForm.user_id"
                                        class="w-full rounded border px-3 py-2"
                                    >
                                        <option value="">
                                            Select operator
                                        </option>
                                        <option
                                            v-for="candidate in operatorCandidates"
                                            :key="candidate.id"
                                            :value="candidate.id"
                                        >
                                            {{
                                                candidate.name ||
                                                candidate.email ||
                                                candidate.id
                                            }}
                                            <template v-if="candidate.email">
                                                · {{ candidate.email }}
                                            </template>
                                        </option>
                                    </select>
                                    <p
                                        v-if="operatorAssignmentError"
                                        class="text-sm text-red-600"
                                    >
                                        {{ operatorAssignmentError }}
                                    </p>
                                    <p
                                        v-else-if="
                                            operatorAssignmentForm.errors
                                                .user_id
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            operatorAssignmentForm.errors
                                                .user_id
                                        }}
                                    </p>
                                </div>
                                <div class="flex items-end">
                                    <Button
                                        type="button"
                                        :disabled="
                                            operatorAssignmentForm.processing ||
                                            operatorCandidates.length === 0
                                        "
                                        @click="submitOperatorAssignment"
                                    >
                                        <span
                                            v-if="
                                                operatorAssignmentForm.processing
                                            "
                                        >
                                            Assigning...
                                        </span>
                                        <span v-else>Assign operator</span>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="operatorCandidates.length === 0"
                                class="rounded border border-dashed p-4 text-sm text-muted-foreground"
                            >
                                No unassigned internal operators available.
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-if="operatorAssignments.length === 0"
                                    class="rounded border border-dashed p-4 text-sm text-muted-foreground"
                                >
                                    No active operator assignments for this
                                    organization.
                                </div>

                                <ul v-else class="space-y-3">
                                    <li
                                        v-for="assignment in operatorAssignments"
                                        :key="assignment.id"
                                        class="flex flex-col gap-3 rounded border p-4 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div class="space-y-1">
                                            <p class="font-medium">
                                                {{
                                                    assignment.user?.name ||
                                                    assignment.user?.email ||
                                                    'Operator'
                                                }}
                                            </p>
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    assignment.user?.email ||
                                                    'No email available'
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    assignment.granted_by
                                                        ?.name ||
                                                    assignment.granted_at
                                                "
                                                class="text-xs text-muted-foreground"
                                            >
                                                Assigned
                                                <template
                                                    v-if="
                                                        assignment.granted_by
                                                            ?.name
                                                    "
                                                >
                                                    by
                                                    {{
                                                        assignment.granted_by
                                                            .name
                                                    }}
                                                </template>
                                                <template
                                                    v-if="assignment.granted_at"
                                                >
                                                    ·
                                                    {{
                                                        fmtDateTime(
                                                            assignment.granted_at,
                                                        )
                                                    }}
                                                </template>
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="
                                                revokeOperatorAssignment(
                                                    assignment.id,
                                                )
                                            "
                                        >
                                            Revoke
                                        </Button>
                                    </li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Shortcuts</CardTitle>
                            <CardDescription>
                                {{
                                    isInternal
                                        ? 'Staff quick links and go-to tools.'
                                        : 'Jump into focused workflows.'
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <template v-if="isInternal">
                                <div
                                    class="space-y-2 text-sm text-muted-foreground"
                                >
                                    <p>
                                        The staff workspace surfaces shared
                                        resources and internal tooling.
                                    </p>
                                    <p v-if="canGovernInternal">
                                        Use the Owner Console to browse client
                                        engagements, launch impersonation, and
                                        monitor delivery health.
                                    </p>
                                    <p v-else>
                                        Use the organizations directory and
                                        delegated staffing tools to manage
                                        delivery work.
                                    </p>
                                </div>
                                <Button
                                    v-if="canGovernInternal"
                                    variant="secondary"
                                    size="sm"
                                    class="w-full justify-between"
                                    as-child
                                >
                                    <Link :href="ownerConsoleHref">
                                        Owner Console
                                        <span aria-hidden="true">→</span>
                                    </Link>
                                </Button>
                            </template>
                            <template v-else>
                                <div class="space-y-3 text-sm">
                                    <div>
                                        <p class="font-medium">
                                            Metric templates
                                        </p>
                                        <p class="text-muted-foreground">
                                            {{
                                                templatePreview.length
                                                    ? 'Recently used templates:'
                                                    : 'No templates available yet.'
                                            }}
                                        </p>
                                        <ul
                                            v-if="templatePreview.length"
                                            class="mt-2 space-y-1 text-muted-foreground"
                                        >
                                            <li
                                                v-for="template in templatePreview"
                                                :key="template.key"
                                            >
                                                {{ template.name }}
                                            </li>
                                        </ul>
                                        <p
                                            v-if="templateOverflow > 0"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            +{{ templateOverflow }} more
                                            templates
                                        </p>
                                    </div>

                                    <div
                                        v-if="singleEngagementOption"
                                        class="flex flex-col gap-2"
                                    >
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            class="w-full justify-between"
                                            @click="
                                                openFormsFor(
                                                    singleEngagementOption.id,
                                                )
                                            "
                                        >
                                            <span
                                                >Open forms —
                                                {{
                                                    singleEngagementOption.title
                                                }}</span
                                            >
                                            <span aria-hidden="true">→</span>
                                        </Button>
                                    </div>
                                    <DropdownMenu v-else>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                class="w-full justify-between"
                                            >
                                                <span>
                                                    {{
                                                        hasEngagementShortcuts
                                                            ? 'Browse forms by engagement'
                                                            : 'No engagements available'
                                                    }}
                                                </span>
                                                <ChevronsUpDown
                                                    class="size-4"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="start"
                                            class="min-w-64 rounded-lg"
                                        >
                                            <DropdownMenuLabel
                                                >Select
                                                engagement</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-for="engagement in engagementOptions"
                                                :key="engagement.id"
                                                class="flex flex-col items-start gap-0.5"
                                                @select="
                                                    openFormsFor(engagement.id)
                                                "
                                            >
                                                <span class="font-medium">{{
                                                    engagement.title
                                                }}</span>
                                                <span
                                                    v-if="engagement.status"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ engagement.status }}
                                                </span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="!engagementOptions.length"
                                                disabled
                                            >
                                                No engagements available yet
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div class="space-y-2 text-sm">
                                    <div
                                        v-if="singleEngagementOption"
                                        class="flex flex-col gap-2"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="w-full justify-between"
                                            @click="
                                                openMetricsFor(
                                                    singleEngagementOption.id,
                                                )
                                            "
                                        >
                                            <span
                                                >Metrics dashboard —
                                                {{
                                                    singleEngagementOption.title
                                                }}</span
                                            >
                                            <span aria-hidden="true">→</span>
                                        </Button>
                                    </div>
                                    <DropdownMenu v-else>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="w-full justify-between"
                                            >
                                                <span>
                                                    {{
                                                        hasEngagementShortcuts
                                                            ? 'Metrics dashboard'
                                                            : 'No engagements available'
                                                    }}
                                                </span>
                                                <ChevronsUpDown
                                                    class="size-4"
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="start"
                                            class="min-w-64 rounded-lg"
                                        >
                                            <DropdownMenuLabel
                                                >Choose an
                                                engagement</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-for="engagement in engagementOptions"
                                                :key="engagement.id"
                                                class="flex flex-col items-start gap-0.5"
                                                @select="
                                                    openMetricsFor(
                                                        engagement.id,
                                                    )
                                                "
                                            >
                                                <span class="font-medium">{{
                                                    engagement.title
                                                }}</span>
                                                <span
                                                    v-if="engagement.status"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ engagement.status }}
                                                </span>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="!engagementOptions.length"
                                                disabled
                                            >
                                                No engagements available yet
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="w-full justify-between"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                orgEngagementsIndex(
                                                    organization.id,
                                                )
                                            "
                                        >
                                            View engagements
                                            <span aria-hidden="true">→</span>
                                        </Link>
                                    </Button>
                                </div>
                            </template>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
