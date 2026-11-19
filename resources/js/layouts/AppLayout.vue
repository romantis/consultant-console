<script setup lang="ts">
import ClientLayout from '@/layouts/ClientLayout.vue';
import ConsultantLayout from '@/layouts/ConsultantLayout.vue';
import type { AppPageProps, BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    title?: string;
    description?: string;
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<AppPageProps>();

const shouldUseConsultantLayout = computed(() => {
    const user = page.props.auth?.user;
    const isInternalStaff = Boolean(user?.is_internal_staff);
    const impersonating = Boolean(page.props.impersonation?.organization);

    return isInternalStaff || impersonating;
});
</script>

<template>
    <ConsultantLayout
        v-if="shouldUseConsultantLayout"
        :breadcrumbs="props.breadcrumbs"
    >
        <slot />
    </ConsultantLayout>
    <ClientLayout v-else :title="props.title" :description="props.description">
        <slot />
    </ClientLayout>
</template>
