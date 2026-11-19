<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { FieldClassificationRole } from '@/composables/useMetricTemplateForm';
import { useWayfinder } from '@/composables/useWayfinder';
import type { BreadcrumbItem } from '@/types';
import { EllipsisVertical, Eye, PencilLine, Trash } from 'lucide-vue-next';

type FieldDefinition = {
    name: string;
    label: string;
    type: string;
    required?: boolean;
    options?: Array<string | { value: string; label: string }>;
    classification?: {
        role: FieldClassificationRole;
        exclude_from_consensus?: boolean;
        companion_of?: string | null;
    };
};

type TemplateRow = {
    id: string;
    key: string;
    name: string;
    description: string | null;
    form_schema_json: { fields: FieldDefinition[] };
    created_at?: string | null;
    updated_at?: string | null;
    can: {
        update: boolean;
        delete: boolean;
    };
};

type Permissions = {
    create: boolean;
};

const props = defineProps<{
    templates: TemplateRow[];
    can: Permissions;
}>();

const wayfinder = useWayfinder();

const templates = computed(() => props.templates);
const can = computed(() => props.can);

const page = usePage();
const flashMessage = computed(() => page.props.flash?.success ?? null);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Metric Templates',
        href: '',
    },
];

function destroyTemplate(template: TemplateRow) {
    if (!template.can.delete) return;

    if (
        !confirm(
            `Delete the “${template.name}” template? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(wayfinder.metricTemplates.destroy(template.id), {
        preserveScroll: true,
    });
}

const fmtDate = (iso?: string | null) =>
    iso ? new Date(iso).toLocaleString() : '—';
</script>

<template>
    <Head title="Metric Templates" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Metric Templates
                    </h1>
                    <p class="max-w-2xl text-sm text-muted-foreground">
                        Manage reusable templates that power organization forms
                        and metric intake workflows.
                    </p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link :href="wayfinder.metricTemplates.create()"
                        >New template</Link
                    >
                </Button>
            </div>

            <p v-if="flashMessage" class="text-sm text-emerald-700">
                {{ flashMessage }}
            </p>

            <Card>
                <CardHeader>
                    <CardTitle>Templates</CardTitle>
                    <CardDescription
                        >Review existing templates, inspect fields, and adjust
                        as your process evolves.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table class="w-full">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Key</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Fields</TableHead>
                                <TableHead>Updated</TableHead>
                                <TableHead class="text-right"
                                    >Actions</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="template in templates"
                                :key="template.id"
                            >
                                <TableCell class="font-medium">
                                    {{ template.name }}
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                    {{ template.key }}
                                </TableCell>
                                <TableCell class="max-w-sm">
                                    <p
                                        class="text-sm whitespace-pre-line text-muted-foreground"
                                    >
                                        {{ template.description || '—' }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="field in template
                                                .form_schema_json.fields"
                                            :key="`${template.id}-${field.name}`"
                                            variant="secondary"
                                        >
                                            {{ field.label || field.name }}
                                            <span
                                                class="ml-1 text-[10px] tracking-wide uppercase"
                                                >{{ field.type }}</span
                                            >
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>{{
                                    fmtDate(
                                        template.updated_at ??
                                            template.created_at,
                                    )
                                }}</TableCell>
                                <TableCell class="text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="h-8 w-8 transition-transform duration-150 hover:scale-105 focus-visible:scale-105"
                                                :aria-label="`Actions for ${template.name}`"
                                            >
                                                <EllipsisVertical
                                                    class="size-4"
                                                />
                                                <span class="sr-only"
                                                    >Open actions</span
                                                >
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            class="w-44"
                                        >
                                            <DropdownMenuItem :as-child="true">
                                                <Link
                                                    :href="
                                                        wayfinder.metricTemplates.preview(
                                                            template.id,
                                                        )
                                                    "
                                                    class="flex items-center gap-2"
                                                >
                                                    <Eye class="size-4" />
                                                    Preview
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-if="template.can.update"
                                                :as-child="true"
                                            >
                                                <Link
                                                    :href="
                                                        wayfinder.metricTemplates.edit(
                                                            template.id,
                                                        )
                                                    "
                                                    class="flex items-center gap-2"
                                                >
                                                    <PencilLine
                                                        class="size-4"
                                                    />
                                                    Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-else
                                                disabled
                                                class="flex items-center gap-2"
                                            >
                                                <PencilLine class="size-4" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                :disabled="!template.can.delete"
                                                class="flex items-center gap-2"
                                                @select="
                                                    destroyTemplate(template)
                                                "
                                            >
                                                <Trash class="size-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!templates.length">
                                <TableCell
                                    colspan="6"
                                    class="py-10 text-center text-muted-foreground"
                                >
                                    No templates yet. Create your first template
                                    to get started.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
