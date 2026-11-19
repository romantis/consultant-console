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

const wayfinder = useWayfinder();
const metricLibraryIndexHref = wayfinder.metricLibrary.index();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Metric Library', href: metricLibraryIndexHref },
    { title: 'New Metric', href: '' },
];
</script>

<template>
    <Head title="New Metric" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    New canonical metric
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Create a reusable metric entry for staff and org rollups.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Metric details</CardTitle>
                    <CardDescription>
                        Add a canonical metric to the shared library.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <MetricForm mode="create" />
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
