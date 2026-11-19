<script setup lang="ts">
import ToastHub from '@/components/ToastHub.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const page = usePage();

const isOpen = computed(() => {
    const sidebarOpen = page.props.sidebarOpen as boolean | undefined;
    return typeof sidebarOpen === 'boolean' ? sidebarOpen : true;
});
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <ToastHub />
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen">
        <ToastHub />
        <slot />
    </SidebarProvider>
</template>
