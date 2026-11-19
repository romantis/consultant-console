<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { useImpersonationJustification } from '@/composables/useImpersonationJustification';
import { useWayfinder } from '@/composables/useWayfinder';
import type {
    AppPageProps,
    BreadcrumbItemType,
    ImpersonationState,
} from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<AppPageProps>();
const wayfinder = useWayfinder();
const { justificationPayload } = useImpersonationJustification();

const impersonation = computed<ImpersonationState | null>(() => {
    const state = page.props.impersonation as
        | ImpersonationState
        | null
        | undefined;
    return state ?? null;
});

const hasImpersonation = computed(() => Boolean(impersonation.value));

const impersonationError = computed(() => {
    const message = page.props.flash?.impersonation_error;

    if (typeof message !== 'string') {
        return null;
    }

    const trimmed = message.trim();

    return trimmed !== '' ? trimmed : null;
});

function handleStopImpersonation() {
    if (!impersonation.value) {
        return;
    }

    router.delete(wayfinder.owner.impersonation.stop(), {
        data: {
            justification: justificationPayload.value,
        },
        preserveScroll: true,
    });
}
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="props.breadcrumbs">
                <template #status>
                    <ImpersonationBanner
                        :impersonation="impersonation"
                        :error="impersonationError"
                        :show-stop-action="hasImpersonation"
                        @stop="handleStopImpersonation"
                    />
                </template>
            </AppSidebarHeader>
            <slot />
        </AppContent>
    </AppShell>
</template>
