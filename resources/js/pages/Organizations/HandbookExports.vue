<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, ExternalLink } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useToast } from '@/composables/useToast';
import { useWayfinder } from '@/composables/useWayfinder';
import org, { show as orgShow } from '@/routes/org';
import type { BreadcrumbItem } from '@/types';

type Organization = {
    id: string;
    name: string;
};

type ExportRow = {
    id: string;
    status: string;
    version_label: string;
    queued_at?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    failed_at?: string | null;
    failure_reason?: string | null;
    is_ready: boolean;
    is_published: boolean;
    published_at?: string | null;
    published_by?: { id: string; name: string } | null;
    public_url?: string | null;
    can_publish: boolean;
    can_unpublish: boolean;
};

const props = defineProps<{
    organization: Organization;
    exports: ExportRow[];
}>();

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
        title: 'Handbook Exports',
        href: '',
    },
];

const exportRows = computed(() => props.exports ?? []);
const page = usePage<{
    flags?: {
        public_handbook_links?: boolean;
    };
}>();
const publicLinksEnabled = computed(
    () => page.props.flags?.public_handbook_links === true,
);
const tableColumnCount = computed(() => (publicLinksEnabled.value ? 4 : 3));
const { toast } = useToast();
const wayfinder = useWayfinder();
const { copy, copied } = useClipboard();
const copiedExportId = ref<string | null>(null);
const actionState = ref<{ id: string; action: 'publish' | 'unpublish' } | null>(
    null,
);

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

const startCase = (value: string): string =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) {
        return '--';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '--';
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const exportTimestampLabel = (exportRow: ExportRow): string | null => {
    const timestamp =
        exportRow.completed_at ??
        exportRow.started_at ??
        exportRow.queued_at ??
        exportRow.failed_at ??
        null;

    if (!timestamp) {
        return null;
    }

    return formatDateTime(timestamp);
};

const statusVariant = (status: string) => {
    const normalized = status?.toLowerCase();

    if (normalized === 'ready') {
        return 'success';
    }

    if (normalized === 'failed') {
        return 'destructive';
    }

    if (normalized === 'processing') {
        return 'warning';
    }

    if (normalized === 'queued') {
        return 'secondary';
    }

    return 'outline';
};

const publishBadgeVariant = (isPublished: boolean) =>
    isPublished ? 'success' : 'outline';

const handlePublish = (exportRow: ExportRow) => {
    if (
        !exportRow.can_publish ||
        !exportRow.is_ready ||
        exportRow.is_published
    ) {
        return;
    }

    const route = resolveRouteUrl(
        wayfinder.org.handbookExports.publish({
            organization: props.organization.id,
            handbookExport: exportRow.id,
        }),
    );

    if (!route) {
        toast('error', 'Unable to publish this export right now.');
        return;
    }

    router.post(
        route,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                actionState.value = { id: exportRow.id, action: 'publish' };
            },
            onFinish: () => {
                actionState.value = null;
            },
        },
    );
};

const handleUnpublish = (exportRow: ExportRow) => {
    if (!exportRow.can_unpublish || !exportRow.is_published) {
        return;
    }

    const route = resolveRouteUrl(
        wayfinder.org.handbookExports.unpublish({
            organization: props.organization.id,
            handbookExport: exportRow.id,
        }),
    );

    if (!route) {
        toast('error', 'Unable to unpublish this export right now.');
        return;
    }

    router.delete(route, {
        preserveScroll: true,
        onStart: () => {
            actionState.value = { id: exportRow.id, action: 'unpublish' };
        },
        onFinish: () => {
            actionState.value = null;
        },
    });
};

const downloadUrlFor = (exportRow: ExportRow) => {
    return resolveRouteUrl(
        wayfinder.org.handbookExports.download({
            organization: props.organization.id,
            handbookExport: exportRow.id,
        }),
    );
};

const copyPublicLink = async (exportRow: ExportRow) => {
    if (!exportRow.public_url) {
        return;
    }

    try {
        await copy(exportRow.public_url);
        copiedExportId.value = exportRow.id;
        toast('success', 'Public link copied.');
    } catch {
        toast('error', 'Unable to copy the public link.');
    }
};

const isActionPending = (
    exportRow: ExportRow,
    action: 'publish' | 'unpublish',
) =>
    actionState.value?.id === exportRow.id &&
    actionState.value?.action === action;
</script>

<template>
    <Head :title="`Handbook Exports - ${organization.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold">Handbook exports</h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        publicLinksEnabled
                            ? 'Review organization-level handbook exports, publish the latest version, and copy public links for sharing.'
                            : 'Review organization-level handbook exports and download generated files. Public links are disabled for MVP mode.'
                    }}
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Export history</CardTitle>
                    <CardDescription>
                        {{
                            publicLinksEnabled
                                ? 'Publishing makes the handbook available at the public slug URL.'
                                : 'Public handbook links are currently disabled.'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Export</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead v-if="publicLinksEnabled"
                                    >Share</TableHead
                                >
                                <TableHead class="text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty
                                v-if="!exportRows.length"
                                :colspan="tableColumnCount"
                                class="text-muted-foreground"
                            >
                                No handbook exports yet.
                            </TableEmpty>
                            <TableRow
                                v-for="exportRow in exportRows"
                                :key="exportRow.id"
                            >
                                <TableCell class="space-y-1">
                                    <div class="text-sm font-medium">
                                        {{ exportRow.version_label }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            exportTimestampLabel(exportRow) ??
                                            'Pending'
                                        }}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="
                                            statusVariant(exportRow.status)
                                        "
                                    >
                                        {{ startCase(exportRow.status) }}
                                    </Badge>
                                    <p
                                        v-if="
                                            exportRow.status === 'failed' &&
                                            exportRow.failure_reason
                                        "
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ exportRow.failure_reason }}
                                    </p>
                                </TableCell>
                                <TableCell
                                    v-if="publicLinksEnabled"
                                    class="space-y-2"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Badge
                                            :variant="
                                                publishBadgeVariant(
                                                    exportRow.is_published,
                                                )
                                            "
                                        >
                                            {{
                                                exportRow.is_published
                                                    ? 'Published'
                                                    : 'Not published'
                                            }}
                                        </Badge>
                                        <span
                                            v-if="exportRow.published_at"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDateTime(
                                                    exportRow.published_at,
                                                )
                                            }}
                                        </span>
                                        <span
                                            v-if="exportRow.published_by?.name"
                                            class="text-xs text-muted-foreground"
                                        >
                                            by {{ exportRow.published_by.name }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :disabled="!exportRow.public_url"
                                            @click="copyPublicLink(exportRow)"
                                        >
                                            <Check
                                                v-if="
                                                    copied &&
                                                    copiedExportId ===
                                                        exportRow.id
                                                "
                                                class="h-4 w-4"
                                            />
                                            <Copy v-else class="h-4 w-4" />
                                            Copy link
                                        </Button>
                                        <Button
                                            v-if="exportRow.public_url"
                                            variant="ghost"
                                            size="icon"
                                            as-child
                                            aria-label="Open public handbook link"
                                        >
                                            <a
                                                :href="exportRow.public_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                            </a>
                                        </Button>
                                    </div>
                                    <p
                                        v-if="!exportRow.public_url"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Publish to enable the public link.
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <div
                                        class="flex flex-wrap items-center justify-end gap-2"
                                    >
                                        <Button
                                            v-if="
                                                publicLinksEnabled &&
                                                exportRow.is_ready &&
                                                !exportRow.is_published &&
                                                exportRow.can_publish
                                            "
                                            size="sm"
                                            :disabled="
                                                isActionPending(
                                                    exportRow,
                                                    'publish',
                                                )
                                            "
                                            @click="handlePublish(exportRow)"
                                        >
                                            {{
                                                isActionPending(
                                                    exportRow,
                                                    'publish',
                                                )
                                                    ? 'Publishing...'
                                                    : 'Publish'
                                            }}
                                        </Button>
                                        <Button
                                            v-else-if="
                                                publicLinksEnabled &&
                                                exportRow.is_published &&
                                                exportRow.can_unpublish
                                            "
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                isActionPending(
                                                    exportRow,
                                                    'unpublish',
                                                )
                                            "
                                            @click="handleUnpublish(exportRow)"
                                        >
                                            {{
                                                isActionPending(
                                                    exportRow,
                                                    'unpublish',
                                                )
                                                    ? 'Unpublishing...'
                                                    : 'Unpublish'
                                            }}
                                        </Button>
                                        <Button
                                            v-if="exportRow.is_ready"
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <a
                                                :href="
                                                    downloadUrlFor(exportRow) ??
                                                    undefined
                                                "
                                            >
                                                Download
                                            </a>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
