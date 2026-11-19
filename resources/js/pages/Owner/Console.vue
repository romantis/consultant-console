<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useWayfinder } from '@/composables/useWayfinder';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type StatusBadge = {
    label: string;
    variant: 'success' | 'warning' | 'muted' | 'outline';
    count?: number | null;
};

type OwnerOrganization = {
    id: string;
    name: string;
    slug: string;
    statusBadges: StatusBadge[];
    engagementsCount: number;
    activeEngagementsCount: number;
    draftEngagementsCount: number;
    completedEngagementsCount: number;
    metricsCount: number;
    membershipsCount: number;
    pendingMembershipsCount: number;
};

type Filters = {
    search?: string | null;
    status?: string | null;
};

type StatusOption = {
    value: string;
    label: string;
    count: number;
};

type Summary = {
    organizations: number;
    activeEngagements: number;
    metrics: number;
    pendingInvites: number;
};

const props = defineProps<{
    heading?: string;
    introduction?: string;
    consoleOrganizations: OwnerOrganization[];
    filters: Filters;
    statusOptions: StatusOption[];
    summary: Summary;
}>();

const wayfinder = useWayfinder();
const ownerConsoleRoute = wayfinder.owner.console();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Owner console',
        href: ownerConsoleRoute.url,
    },
];

const heading = computed(() => props.heading ?? 'Owner console');
const searchTerm = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? '');

const organizations = computed(() => props.consoleOrganizations ?? []);
const statusOptions = computed(() => props.statusOptions ?? []);

const summaryCards = computed(() => [
    { label: 'Organizations', value: props.summary?.organizations ?? 0 },
    {
        label: 'Active engagements',
        value: props.summary?.activeEngagements ?? 0,
    },
    { label: 'Metrics', value: props.summary?.metrics ?? 0 },
    { label: 'Pending invites', value: props.summary?.pendingInvites ?? 0 },
]);

const hasActiveFilters = computed(() => {
    return Boolean(searchTerm.value.trim().length || statusFilter.value);
});

const formatNumber = (value: number | null | undefined) => {
    return (value ?? 0).toLocaleString();
};

const statusBadgeClass = (variant: StatusBadge['variant']) => {
    switch (variant) {
        case 'success':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';
        case 'warning':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
        case 'muted':
            return 'bg-muted text-muted-foreground';
        default:
            return 'border border-border text-muted-foreground';
    }
};

const applyFilters = () => {
    const query: Record<string, string> = {};
    const trimmedSearch = searchTerm.value.trim();

    if (trimmedSearch.length) {
        query.search = trimmedSearch;
    }

    if (statusFilter.value) {
        query.status = statusFilter.value;
    }

    searchTerm.value = trimmedSearch;

    router.get(ownerConsoleRoute, query, {
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    searchTerm.value = '';
    statusFilter.value = '';

    router.get(
        ownerConsoleRoute,
        {},
        {
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    () => props.filters,
    (filters) => {
        searchTerm.value = filters?.search ?? '';
        statusFilter.value = filters?.status ?? '';
    },
    { deep: true },
);
</script>

<template>
    <Head :title="heading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-10 px-6 py-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="rounded-lg border bg-card p-6 text-card-foreground shadow-sm"
                >
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ formatNumber(card.value) }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-lg border bg-card text-card-foreground shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 border-b border-border px-6 py-6 md:flex-row md:items-end md:justify-between"
                >
                    <form
                        class="flex flex-col gap-4 md:flex-row md:items-end"
                        @submit.prevent="applyFilters"
                    >
                        <div class="flex flex-col gap-2">
                            <label
                                for="owner-search"
                                class="text-sm font-medium text-foreground"
                                >Search organizations</label
                            >
                            <Input
                                id="owner-search"
                                v-model="searchTerm"
                                name="search"
                                type="search"
                                placeholder="Search by name or slug"
                                class="md:w-64"
                            />
                        </div>

                        <div class="flex flex-col gap-2">
                            <label
                                for="owner-status"
                                class="text-sm font-medium text-foreground"
                                >Status filter</label
                            >
                            <select
                                id="owner-status"
                                v-model="statusFilter"
                                name="status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option value="">All statuses</option>
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }} ({{
                                        formatNumber(option.count)
                                    }})
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-3">
                            <Button type="submit">Apply</Button>
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-sm font-medium text-muted-foreground hover:text-foreground"
                                :disabled="!hasActiveFilters"
                                @click="resetFilters"
                            >
                                Reset
                            </Button>
                        </div>
                    </form>
                </div>

                <div class="divide-y divide-border">
                    <template v-if="organizations.length">
                        <div
                            v-for="organization in organizations"
                            :key="organization.id"
                            class="flex flex-col gap-4 px-6 py-6 md:flex-row md:items-start md:justify-between"
                        >
                            <div class="space-y-4">
                                <div>
                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <h2
                                            class="text-lg leading-tight font-semibold"
                                        >
                                            {{ organization.name }}
                                        </h2>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="badge in organization.statusBadges"
                                                :key="`${badge.label}-${badge.variant}`"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                                                :class="
                                                    statusBadgeClass(
                                                        badge.variant,
                                                    )
                                                "
                                            >
                                                <span>{{ badge.label }}</span>
                                                <template
                                                    v-if="
                                                        badge.count !==
                                                            undefined &&
                                                        badge.count !== null
                                                    "
                                                >
                                                    <span
                                                        class="text-muted-foreground"
                                                        >•</span
                                                    >
                                                    <span>{{
                                                        formatNumber(
                                                            badge.count,
                                                        )
                                                    }}</span>
                                                </template>
                                            </span>
                                        </div>
                                    </div>
                                    <p
                                        class="mt-2 text-sm text-muted-foreground"
                                    >
                                        Slug: {{ organization.slug }}
                                    </p>
                                </div>

                                <div
                                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <div
                                        class="rounded-lg border border-dashed border-border/70 p-4"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Engagements
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            {{
                                                formatNumber(
                                                    organization.engagementsCount,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg border border-dashed border-border/70 p-4"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Active engagements
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            {{
                                                formatNumber(
                                                    organization.activeEngagementsCount,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg border border-dashed border-border/70 p-4"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Metrics
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            {{
                                                formatNumber(
                                                    organization.metricsCount,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-lg border border-dashed border-border/70 p-4"
                                    >
                                        <p
                                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            Members
                                        </p>
                                        <p class="mt-2 text-xl font-semibold">
                                            {{
                                                formatNumber(
                                                    organization.membershipsCount,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatNumber(
                                                    organization.pendingMembershipsCount,
                                                )
                                            }}
                                            pending invites
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div
                        v-else
                        class="px-6 py-12 text-center text-sm text-muted-foreground"
                    >
                        No organizations match your filters yet. Try updating
                        the search or status filter.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
