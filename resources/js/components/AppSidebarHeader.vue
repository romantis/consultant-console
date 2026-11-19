<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import Button from '@/components/ui/button/Button.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import { useClientNav } from '@/composables/useClientNav';
import { useToast } from '@/composables/useToast';
import { useWayfinder } from '@/composables/useWayfinder';
import { store as clientHandbookExportsStore } from '@/routes/client/handbook/exports';
import type {
    AppPageProps,
    BreadcrumbItemType,
    HandbookExportSummary,
} from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    Download,
    Loader2,
    Monitor,
    Moon,
    Sun,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { appearance, updateAppearance } = useAppearance();
type AppearanceOption = 'light' | 'dark' | 'system';

const appearanceOptions: Array<{
    value: AppearanceOption;
    label: string;
    icon: typeof Sun;
}> = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

const activeAppearance = computed(() => {
    return (
        appearanceOptions.find((option) => option.value === appearance.value) ??
        appearanceOptions[2]
    );
});

const page =
    usePage<AppPageProps<{ handbookExports?: HandbookExportSummary[] }>>();
const { organization } = useClientNav();
const { toast } = useToast();
const wayfinder = useWayfinder();
const handbookExports = computed(() => page.props.handbookExports ?? []);
const latestExport = computed(() => handbookExports.value[0] ?? null);
const isExportReady = (exportRow: HandbookExportSummary | null) => {
    if (!exportRow) {
        return false;
    }

    if (typeof exportRow.is_ready === 'boolean') {
        return exportRow.is_ready;
    }

    return exportRow.status === 'ready';
};
const readyExports = computed(() =>
    handbookExports.value.filter((exportRow) => isExportReady(exportRow)),
);
type ReadyExportLink = {
    row: HandbookExportSummary;
    url: string;
};
const readyExportLinks = computed<ReadyExportLink[]>(() =>
    readyExports.value
        .map((exportRow) => ({
            row: exportRow,
            url: downloadUrlFor(exportRow),
        }))
        .filter((item): item is ReadyExportLink => Boolean(item.url)),
);
const exportReady = computed(() => isExportReady(latestExport.value));
const pastExportLinks = computed<ReadyExportLink[]>(() => {
    if (!readyExportLinks.value.length) {
        return [];
    }

    if (!exportReady.value) {
        return readyExportLinks.value;
    }

    const latestId = latestExport.value?.id;
    if (!latestId) {
        return readyExportLinks.value;
    }

    return readyExportLinks.value.filter((item) => item.row.id !== latestId);
});
const showHandbookHistory = computed(() => pastExportLinks.value.length > 0);
const exportInProgress = computed(() => {
    const status = latestExport.value?.status;
    return status === 'queued' || status === 'processing';
});
const requestingExport = ref(false);
const exportDownloadPending = ref(false);
const exportMenuOpen = ref(false);
const exportButtonBusy = computed(
    () =>
        requestingExport.value ||
        exportInProgress.value ||
        exportDownloadPending.value,
);
const exportButtonLabel = computed(() =>
    exportButtonBusy.value ? 'Generating Handbook...' : 'Download Handbook',
);
const showHandbookDownload = computed(() => Boolean(organization.value?.id));

const resolveRouteUrl = (route: unknown): string | null => {
    if (typeof route === 'string') {
        return route;
    }

    if (route && typeof route === 'object') {
        if ('url' in route && typeof route.url === 'string') {
            return route.url;
        }

        if ('href' in route && typeof route.href === 'string') {
            return route.href;
        }
    }

    return null;
};

const exportTimestampLabel = (exportRow: HandbookExportSummary | null) => {
    if (!exportRow) {
        return null;
    }

    const timestamp =
        exportRow.completed_at ??
        exportRow.started_at ??
        exportRow.queued_at ??
        exportRow.failed_at ??
        null;

    if (!timestamp) {
        return null;
    }

    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const downloadUrlFor = (exportRow: HandbookExportSummary | null) => {
    const organizationId = organization.value?.id;

    if (!organizationId || !exportRow?.id) {
        return null;
    }

    return resolveRouteUrl(
        wayfinder.org.handbookExports.download({
            organization: organizationId,
            handbookExport: exportRow.id,
        }),
    );
};

const triggerExportDownload = (url: string) => {
    if (typeof document === 'undefined') {
        return;
    }

    const link = document.createElement('a');
    link.href = url;
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const maybeDownloadLatestExport = () => {
    if (!exportDownloadPending.value || !exportReady.value) {
        return;
    }

    const url = downloadUrlFor(latestExport.value);
    if (!url) {
        return;
    }

    exportDownloadPending.value = false;
    triggerExportDownload(url);
};

const refreshHandbookExports = () => {
    if (!organization.value?.id) {
        return Promise.resolve();
    }

    return new Promise<void>((resolve) => {
        router.reload({
            only: ['handbookExports'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => resolve(),
        });
    });
};

let exportPollTimer: number | null = null;

const stopExportPolling = () => {
    if (typeof window === 'undefined') {
        return;
    }

    if (exportPollTimer !== null) {
        window.clearTimeout(exportPollTimer);
        exportPollTimer = null;
    }
};

const scheduleExportPolling = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const poll = async () => {
        await refreshHandbookExports();

        if (exportInProgress.value) {
            exportPollTimer = window.setTimeout(() => {
                void poll();
            }, 4000);
        } else {
            stopExportPolling();
        }
    };

    stopExportPolling();

    exportPollTimer = window.setTimeout(() => {
        void poll();
    }, 3000);
};

const handleExportMenuOpen = (value: boolean) => {
    exportMenuOpen.value = value;

    if (value) {
        void refreshHandbookExports();
    }
};

const handleExportClick = () => {
    const organizationId = organization.value?.id;

    if (!organizationId || exportButtonBusy.value) {
        return;
    }

    if (exportReady.value) {
        const url = downloadUrlFor(latestExport.value);
        if (!url) {
            return;
        }

        exportDownloadPending.value = false;
        triggerExportDownload(url);
        return;
    }

    const route = clientHandbookExportsStore(organizationId);

    router.post(
        route.url,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                requestingExport.value = true;
            },
            onSuccess: () => {
                exportDownloadPending.value = true;
                maybeDownloadLatestExport();
                scheduleExportPolling();
            },
            onError: () => {
                exportDownloadPending.value = false;
                toast('error', 'Unable to start the handbook export.');
            },
            onFinish: () => {
                requestingExport.value = false;
            },
        },
    );
};

watch(
    () => exportInProgress.value,
    (next) => {
        if (next) {
            scheduleExportPolling();
        } else {
            stopExportPolling();
        }
    },
    { immediate: true },
);

watch(
    () => [latestExport.value?.status, latestExport.value?.is_ready],
    ([status]) => {
        if (exportReady.value) {
            maybeDownloadLatestExport();
            return;
        }

        if (status === 'failed' && exportDownloadPending.value) {
            exportDownloadPending.value = false;
            toast('error', 'Handbook export failed. Try again.');
        }
    },
);

watch(
    () => organization.value?.id,
    () => {
        exportDownloadPending.value = false;
        requestingExport.value = false;
        exportMenuOpen.value = false;
        stopExportPolling();
    },
);

onBeforeUnmount(() => {
    stopExportPolling();
});

function handleAppearanceChange(value: string) {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}
</script>

<template>
    <header
        class="flex h-auto min-h-16 flex-col gap-2 border-b border-sidebar-border/70 px-6 py-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-auto md:px-4"
    >
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>

            <div class="grow">
                <slot name="heading" />
            </div>

            <div v-if="showHandbookDownload" class="flex items-center gap-1">
                <!-- TODO: When client-facing sharing is approved, add publish/unpublish controls and public link copy here.
                     Requires publish state + public URL in handbookExports payload. -->
                <Button
                    variant="outline"
                    size="sm"
                    class="inline-flex items-center gap-2"
                    :disabled="exportButtonBusy"
                    @click="handleExportClick"
                >
                    <Loader2
                        v-if="exportButtonBusy"
                        class="h-4 w-4 animate-spin"
                    />
                    <Download v-else class="h-4 w-4" />
                    <span>{{ exportButtonLabel }}</span>
                </Button>
                <DropdownMenu
                    v-if="showHandbookHistory"
                    :open="exportMenuOpen"
                    @update:open="handleExportMenuOpen"
                >
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="inline-flex items-center px-2"
                            aria-label="Handbook download history"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-64">
                        <DropdownMenuLabel>Handbook history</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-for="item in pastExportLinks"
                            :key="item.row.id"
                            :as-child="true"
                        >
                            <a :href="item.url" class="flex flex-col gap-0.5">
                                <span class="text-sm font-medium">
                                    {{ item.row.version_label }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        exportTimestampLabel(item.row) ??
                                        'Ready'
                                    }}
                                </span>
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <div
                v-if="showHandbookDownload"
                aria-hidden="true"
                class="mx-1 h-6 w-px bg-border/70"
            />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="inline-flex items-center gap-2 px-2.5 text-sm font-medium text-sidebar-foreground hover:bg-sidebar-accent/60"
                    >
                        <component
                            :is="activeAppearance.icon"
                            class="h-4 w-4"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuLabel>Appearance</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuRadioGroup
                        :model-value="appearance"
                        @update:model-value="handleAppearanceChange"
                    >
                        <DropdownMenuRadioItem
                            v-for="option in appearanceOptions"
                            :key="option.value"
                            :value="option.value"
                            class="flex items-center gap-2 pl-3 data-[state=checked]:bg-accent data-[state=checked]:text-foreground data-[state=checked]:shadow-inner [&>span:first-child]:hidden"
                        >
                            <component
                                :is="option.icon"
                                class="h-4 w-4 text-muted-foreground"
                            />
                            <span class="text-sm">{{ option.label }}</span>
                        </DropdownMenuRadioItem>
                    </DropdownMenuRadioGroup>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <slot name="status" />
    </header>
</template>
