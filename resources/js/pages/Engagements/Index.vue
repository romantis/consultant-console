<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useWayfinder } from '@/composables/useWayfinder';
import { persistEngagementSelection } from '@/lib/engagements';
import org, { show as orgShow } from '@/routes/org';
import {
    index as orgEngagementsIndex,
    launch as orgEngagementsLaunch,
    show as orgEngagementsShow,
} from '@/routes/org/engagements';
import { BreadcrumbItem } from '@/types';
import {
    CheckCircle2,
    ChevronDown,
    Circle,
    ListFilter,
    Search as SearchIcon,
    Trash2,
} from 'lucide-vue-next';

type StatusOption = { value: string; label: string };
type ChecklistKey =
    | 'metrics_selected'
    | 'stakeholders_invited'
    | 'repository_connected'
    | 'forms_bootstrapped'
    | 'workshop_ready';

type ChecklistState = Partial<Record<ChecklistKey, boolean>>;

type ProgressBreakdown = {
    total: number;
    completion: number;
    finalized?: number;
    open?: number;
    in_progress?: number;
    awaiting_forms?: number;
    in_review?: number;
    in_workshop?: number;
    completed?: number;
};

type EngagementRow = {
    id: string;
    title: string;
    status: string;
    can_delete?: boolean;
    starts_on?: string | null;
    ends_on?: string | null;
    forms: {
        total: number;
        progress?: ProgressBreakdown;
    };
    metrics: {
        total: number;
        progress?: ProgressBreakdown;
    };
    checklist?: ChecklistState | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedEngagements = {
    data: EngagementRow[];
    links: PaginationLink[];
};

type FiltersPayload = {
    available: {
        statuses: StatusOption[];
    };
    active: {
        statuses?: string[] | null;
        status?: string | null;
        starts_on?: string | null;
        ends_on?: string | null;
        search?: string | null;
    };
};

const props = defineProps<{
    organization: {
        id: string;
        name: string;
        is_internal?: boolean;
        can_launch?: boolean;
    };
    engagements: PaginatedEngagements;
    filters: FiltersPayload;
}>();

const wayfinder = useWayfinder();

const isInternal = computed(() => Boolean(props.organization?.is_internal));
const canLaunch = computed(() => Boolean(props.organization?.can_launch));
const ownerConsoleHref = wayfinder.owner.console().url;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    { title: 'Engagements', href: '' },
];

const statusOptions = computed<StatusOption[]>(
    () => props.filters.available?.statuses ?? [],
);

const initialStatusValues = (
    props.filters.active?.statuses ??
    (props.filters.active?.status ? [props.filters.active.status] : []) ??
    []
).filter(
    (value): value is string => typeof value === 'string' && value.length > 0,
);

const selectedStatuses = ref<string[]>([...initialStatusValues]);
const startsOn = ref(props.filters.active?.starts_on ?? '');
const endsOn = ref(props.filters.active?.ends_on ?? '');
const searchTerm = ref(props.filters.active?.search ?? '');

const checklistItems: { key: ChecklistKey; label: string }[] = [
    { key: 'metrics_selected', label: 'Metrics selected' },
    { key: 'stakeholders_invited', label: 'Stakeholders invited' },
    { key: 'repository_connected', label: 'Repository connected' },
    { key: 'forms_bootstrapped', label: 'Forms bootstrapped' },
    { key: 'workshop_ready', label: 'Workshop ready' },
];

const formatStatusLabel = (value: string) =>
    value
        .replace(/_/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());

const statusSummary = computed(() => {
    if (!selectedStatuses.value.length) {
        return 'All statuses';
    }

    if (selectedStatuses.value.length === 1) {
        const match = statusOptions.value.find(
            (option) => option.value === selectedStatuses.value[0],
        );
        const label = match?.label ?? selectedStatuses.value[0];
        return formatStatusLabel(label);
    }

    return `${selectedStatuses.value.length} statuses`;
});

const hasActiveFilters = computed(
    () =>
        selectedStatuses.value.length > 0 ||
        Boolean(startsOn.value) ||
        Boolean(endsOn.value) ||
        Boolean(searchTerm.value.trim()),
);

const engagementListEmpty = computed(() => props.engagements.data.length === 0);

const statusVariant = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed':
        case 'finalized':
            return 'default';
        case 'draft':
            return 'outline';
        default:
            return 'secondary';
    }
};

const formatDate = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTimeline = (engagement: EngagementRow) => {
    const start = formatDate(engagement.starts_on ?? null);
    const end = formatDate(engagement.ends_on ?? null);

    if (start === '—' && end === '—') {
        return '—';
    }

    if (start !== '—' && end !== '—') {
        return `${start} → ${end}`;
    }

    if (start !== '—') {
        return `Starts ${start}`;
    }

    return `Ends ${end}`;
};

const toggleStatus = (value: string, checked: boolean | 'indeterminate') => {
    const next = new Set(selectedStatuses.value);
    if (checked === true) {
        next.add(value);
    } else {
        next.delete(value);
    }

    selectedStatuses.value = Array.from(next);
};

const applyFilters = () => {
    const query: Record<string, unknown> = {};

    if (selectedStatuses.value.length === 1) {
        query.status = selectedStatuses.value[0];
    } else if (selectedStatuses.value.length > 1) {
        query.status = selectedStatuses.value;
    }

    if (startsOn.value) {
        query.starts_on = startsOn.value;
    }

    if (endsOn.value) {
        query.ends_on = endsOn.value;
    }

    const trimmedSearch = searchTerm.value.trim();
    if (trimmedSearch.length) {
        query.search = trimmedSearch;
    }

    router.get(orgEngagementsIndex(props.organization.id), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    selectedStatuses.value = [];
    startsOn.value = '';
    endsOn.value = '';
    searchTerm.value = '';

    applyFilters();
};

const resolveEngagementUrl = (engagementId: string): string | null => {
    const base = orgEngagementsShow([props.organization.id, engagementId]);

    return typeof base === 'string' ? base : (base?.url ?? null);
};

const canDeleteDraft = (engagement: EngagementRow): boolean =>
    (engagement.can_delete ?? false) &&
    engagement.status.trim().toLowerCase() === 'draft';

const deleteDraft = (engagement: EngagementRow): void => {
    if (!canDeleteDraft(engagement)) {
        return;
    }

    const href = resolveEngagementUrl(engagement.id);
    if (!href) {
        return;
    }

    if (
        !window.confirm(
            `Delete "${engagement.title}"? This draft will be permanently removed.`,
        )
    ) {
        return;
    }

    router.delete(href, {
        preserveScroll: true,
    });
};

const goToEngagement = (engagementId: string) => {
    const href = resolveEngagementUrl(engagementId);
    if (!href) return;

    persistEngagementSelection(props.organization.id, engagementId);
    router.visit(href);
};
</script>

<template>
    <Head :title="`${organization.name} • Engagements`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <h1 class="text-3xl font-semibold">Engagements</h1>
                    <p v-if="isInternal" class="text-sm text-muted-foreground">
                        The Metric Harmony staff workspace uses the Owner
                        Console to browse, launch, and monitor client
                        engagements.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Track every collaboration, monitor delivery progress,
                        and confirm onboarding is complete before launch.
                    </p>
                </div>

                <Button v-if="!isInternal && canLaunch" as-child>
                    <Link :href="orgEngagementsLaunch(organization.id)"
                        >Create engagement</Link
                    >
                </Button>
            </div>

            <div
                v-if="isInternal"
                class="space-y-4 rounded-lg border bg-card p-10 text-center shadow-sm"
            >
                <h2 class="text-lg font-semibold">
                    Client engagements live in the Owner Console
                </h2>
                <p class="mx-auto max-w-2xl text-sm text-muted-foreground">
                    Search and filter organizations, review delivery health, and
                    start impersonation sessions from the Owner Console. The
                    staff workspace keeps shared resources together without
                    duplicating client handbooks.
                </p>
                <div class="flex justify-center">
                    <Button as-child>
                        <Link :href="ownerConsoleHref">Open Owner Console</Link>
                    </Button>
                </div>
            </div>

            <template v-else>
                <form
                    class="space-y-4 rounded-lg border bg-card p-4 shadow-sm"
                    @submit.prevent="applyFilters"
                >
                    <div
                        class="grid gap-3 lg:grid-cols-[auto_auto_auto_1fr] lg:items-center"
                    >
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="mt-5 min-w-[200px] justify-between"
                                >
                                    <span>{{ statusSummary }}</span>
                                    <ChevronDown class="h-4 w-4 opacity-60" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-60">
                                <DropdownMenuLabel>Status</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuCheckboxItem
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :checked="
                                        selectedStatuses.includes(option.value)
                                    "
                                    @update:checked="
                                        (checked) =>
                                            toggleStatus(option.value, checked)
                                    "
                                >
                                    {{ formatStatusLabel(option.label) }}
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <div class="flex flex-col gap-1">
                            <span
                                class="text-xs font-medium text-muted-foreground uppercase"
                                >Starts on</span
                            >
                            <Input type="date" v-model="startsOn" />
                        </div>

                        <div class="flex flex-col gap-1">
                            <span
                                class="text-xs font-medium text-muted-foreground uppercase"
                                >Ends on</span
                            >
                            <Input type="date" v-model="endsOn" />
                        </div>

                        <div class="flex flex-col gap-1">
                            <span
                                class="text-xs font-medium text-muted-foreground uppercase"
                                >Search</span
                            >
                            <div class="relative">
                                <SearchIcon
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    type="search"
                                    v-model="searchTerm"
                                    placeholder="Search engagements"
                                    class="pl-9"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="!hasActiveFilters"
                            @click="resetFilters"
                        >
                            Clear filters
                        </Button>
                        <Button type="submit" class="flex items-center gap-2">
                            <ListFilter class="h-4 w-4" />
                            Apply filters
                        </Button>
                    </div>
                </form>

                <div
                    v-if="engagementListEmpty"
                    class="rounded-lg border bg-card p-10 text-center shadow-sm"
                >
                    <h2 class="text-lg font-semibold">No engagements yet</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Launch your first engagement to begin capturing metrics,
                        onboarding stakeholders, and building shared context.
                    </p>
                    <div v-if="canLaunch" class="mt-4 flex justify-center">
                        <Button as-child>
                            <Link :href="orgEngagementsLaunch(organization.id)"
                                >Create engagement</Link
                            >
                        </Button>
                    </div>
                </div>

                <template v-else>
                    <Card class="hidden lg:block">
                        <CardHeader>
                            <CardTitle class="text-xl"
                                >Engagement overview</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[280px]"
                                            >Engagement</TableHead
                                        >
                                        <TableHead>Status</TableHead>
                                        <TableHead>Timeline</TableHead>
                                        <TableHead>Metrics</TableHead>
                                        <TableHead class="w-[140px]"
                                            >Actions</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="engagement in engagements.data"
                                        :key="engagement.id"
                                        class="cursor-pointer transition-colors hover:bg-muted/60"
                                        @click="goToEngagement(engagement.id)"
                                    >
                                        <TableCell class="space-y-1">
                                            <div class="text-base font-medium">
                                                {{ engagement.title }}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="
                                                    statusVariant(
                                                        engagement.status,
                                                    )
                                                "
                                                >{{
                                                    formatStatusLabel(
                                                        engagement.status,
                                                    )
                                                }}</Badge
                                            >
                                        </TableCell>
                                        <TableCell
                                            class="text-sm text-muted-foreground"
                                            >{{
                                                formatTimeline(engagement)
                                            }}</TableCell
                                        >
                                        <TableCell class="text-sm">
                                            <div class="space-y-1">
                                                <p class="font-medium">
                                                    {{
                                                        engagement.metrics.total
                                                    }}
                                                    total
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Finalized:
                                                    {{
                                                        engagement.metrics
                                                            .progress
                                                            ?.finalized ?? 0
                                                    }}
                                                    /
                                                    {{
                                                        engagement.metrics
                                                            .progress?.total ??
                                                        0
                                                    }}
                                                    ({{
                                                        engagement.metrics
                                                            .progress
                                                            ?.completion ?? 0
                                                    }}%)
                                                </p>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                v-if="
                                                    canDeleteDraft(engagement)
                                                "
                                                size="sm"
                                                variant="destructive"
                                                class="shrink-0"
                                                @click.stop="
                                                    deleteDraft(engagement)
                                                "
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                Delete draft
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <div class="grid gap-4 lg:hidden">
                        <Card
                            v-for="engagement in engagements.data"
                            :key="engagement.id"
                            class="transition-shadow hover:shadow-md"
                        >
                            <CardHeader class="space-y-2">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <CardTitle class="text-lg">{{
                                        engagement.title
                                    }}</CardTitle>
                                    <Badge
                                        :variant="
                                            statusVariant(engagement.status)
                                        "
                                        >{{
                                            formatStatusLabel(engagement.status)
                                        }}</Badge
                                    >
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{ engagement.metrics.total }} metrics •
                                    {{ engagement.forms.total }} forms
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <h3
                                        class="text-sm font-medium text-muted-foreground uppercase"
                                    >
                                        Timeline
                                    </h3>
                                    <p class="mt-1 text-sm">
                                        {{ formatTimeline(engagement) }}
                                    </p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-lg border p-3">
                                        <h3 class="text-sm font-medium">
                                            Metrics
                                        </h3>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Finalized:
                                            {{
                                                engagement.metrics.progress
                                                    ?.finalized ?? 0
                                            }}
                                            /
                                            {{
                                                engagement.metrics.progress
                                                    ?.total ?? 0
                                            }}
                                            ({{
                                                engagement.metrics.progress
                                                    ?.completion ?? 0
                                            }}%)
                                        </p>
                                    </div>
                                    <div class="rounded-lg border p-3">
                                        <h3 class="text-sm font-medium">
                                            Forms
                                        </h3>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Completed:
                                            {{
                                                engagement.forms.progress
                                                    ?.completed ?? 0
                                            }}
                                            /
                                            {{
                                                engagement.forms.progress
                                                    ?.total ?? 0
                                            }}
                                            ({{
                                                engagement.forms.progress
                                                    ?.completion ?? 0
                                            }}%)
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <h3
                                        class="text-sm font-medium text-muted-foreground uppercase"
                                    >
                                        Onboarding
                                    </h3>
                                    <div
                                        v-if="engagement.checklist"
                                        class="mt-2 grid gap-2"
                                    >
                                        <div
                                            v-for="item in checklistItems"
                                            :key="item.key"
                                            class="flex items-center gap-2"
                                            :class="
                                                engagement.checklist?.[item.key]
                                                    ? 'text-emerald-600'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            <component
                                                :is="
                                                    engagement.checklist?.[
                                                        item.key
                                                    ]
                                                        ? CheckCircle2
                                                        : Circle
                                                "
                                                class="h-4 w-4"
                                            />
                                            <span class="text-sm">{{
                                                item.label
                                            }}</span>
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Checklist details unavailable
                                    </p>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button
                                        v-if="canDeleteDraft(engagement)"
                                        size="sm"
                                        variant="destructive"
                                        @click="deleteDraft(engagement)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Delete draft
                                    </Button>
                                    <Button as-child size="sm">
                                        <Link
                                            :href="
                                                orgEngagementsShow([
                                                    organization.id,
                                                    engagement.id,
                                                ])
                                            "
                                            >Manage engagement</Link
                                        >
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </template>

                <nav
                    v-if="engagements.links.length > 1"
                    class="flex flex-wrap items-center justify-center gap-2"
                    aria-label="Pagination"
                >
                    <component
                        v-for="link in engagements.links"
                        :key="`${link.label}-${link.url ?? 'null'}`"
                        :is="link.url ? Link : 'span'"
                        :href="link.url ?? undefined"
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="[
                            link.active
                                ? 'border-primary bg-primary/10 font-medium text-primary'
                                : 'bg-background text-muted-foreground',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                    >
                        <span v-html="link.label" />
                    </component>
                </nav>
            </template>
        </div>
    </AppLayout>
</template>
