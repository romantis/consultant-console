<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import MetricForm from '@/components/MetricLibrary/MetricForm.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useWayfinder } from '@/composables/useWayfinder';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type MetricFormMetric = {
    id: string;
    key: string;
    name: string;
    description: string | null;
    default_metric_template_id?: string | null;
    is_active: boolean;
};

type TemplateOption = {
    id: string;
    key: string;
    name: string;
    description: string | null;
};

const props = defineProps<{
    metric: MetricFormMetric;
    templates: TemplateOption[];
}>();

const wayfinder = useWayfinder();
const metricLibraryIndexHref = wayfinder.metricLibrary.index();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Metric Library', href: metricLibraryIndexHref },
    { title: props.metric.name, href: '' },
];
</script>

<template>
    <Head :title="`Edit ${metric.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Edit {{ metric.name }}
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Update details for this canonical metric.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Metric details</CardTitle>
                    <CardDescription>
                        Adjust the name, description, or status.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <MetricForm
                        mode="edit"
                        :metric="metric"
                        :templates="templates"
                        :cancel-href="metricLibraryIndexHref"
                    />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
