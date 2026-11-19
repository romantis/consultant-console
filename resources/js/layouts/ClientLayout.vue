<script setup lang="ts">
import { computed } from 'vue';

import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ClientSidebar from '@/components/client/ClientSidebar.vue';
import { useClientNav } from '@/composables/useClientNav';

interface Props {
    title?: string;
    description?: string;
}

const props = defineProps<Props>();

const { navigation, organization } = useClientNav();

const headerTitle = computed(
    () => props.title ?? organization.value?.name ?? 'Client Console',
);
const headerDescription = computed(() => {
    if (props.description) {
        return props.description;
    }

    if (organization.value) {
        if (navigation.value?.membership?.role) {
            return `Signed in as ${navigation.value.membership.role}`;
        }

        return 'You are viewing this organization in Staff Preview mode.';
    }

    return 'Member-specific navigation loads once your organization is selected.';
});
</script>

<template>
    <AppShell variant="sidebar">
        <slot name="sidebar">
            <ClientSidebar />
        </slot>

        <AppContent variant="sidebar" class="overflow-x-clip">
            <AppSidebarHeader>
                <template #heading>
                    <hgroup>
                        <h1 class="text-2xl font-semibold text-foreground">
                            {{ headerTitle }}
                        </h1>
                        <h2 class="-mt-1 text-sm text-muted-foreground">
                            {{ headerDescription }}
                        </h2>
                    </hgroup>
                </template>
            </AppSidebarHeader>

            <slot>
                <div
                    class="rounded-xl border border-dashed border-border/70 bg-card/80 p-8 text-sm text-muted-foreground"
                >
                    Client content placeholder
                </div>
            </slot>
        </AppContent>
    </AppShell>
</template>
