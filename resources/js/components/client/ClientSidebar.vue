<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useClientNav } from '@/composables/useClientNav';
import { useWayfinder } from '@/composables/useWayfinder';
import { urlIsActive } from '@/lib/utils';
import type { AppPageProps, NavItem } from '@/types';
import type { LucideIcon } from 'lucide-vue-next';
import { LayoutDashboard, NotebookPen } from 'lucide-vue-next';

const wayfinder = useWayfinder();
const { navigation, organization, workspaceLinks, handbookMetrics } =
    useClientNav();
const page = usePage<AppPageProps>();

type ShortcutKey = 'handbook';

const shortcutConfig: Array<{
    key: ShortcutKey;
    label: string;
    description: string;
    icon: LucideIcon;
}> = [
    {
        key: 'handbook',
        label: 'Handbook',
        description: 'Browse metric definitions',
        icon: NotebookPen,
    },
];

const shortcutItems = computed(() =>
    shortcutConfig.map((item) => ({
        ...item,
        href: workspaceLinks.value[item.key],
    })),
);

const hasNavigation = computed(() => Boolean(navigation.value));
const handbookMetricItems = computed(() => handbookMetrics.value ?? []);
const hasHandbookMetrics = computed(() => handbookMetricItems.value.length > 0);
const showHandbookEmptyState = computed(
    () => hasNavigation.value && !hasHandbookMetrics.value,
);
const footerNavItems: NavItem[] = [];
const logoHref = computed(
    () => workspaceLinks.value.handbook ?? wayfinder.client.handbook.index(),
);
const isInternalStaff = computed(
    () => !!page.props.auth?.user?.is_internal_staff,
);
const showStaffReturnLink = computed(
    () => isInternalStaff.value && organization.value,
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border/80 pb-4">
            <SidebarMenu
                class="group-data-[collapsible=icon]/sidebar-wrapper:mb-0"
            >
                <SidebarMenuItem>
                    <SidebarMenuButton as="div" size="lg">
                        <Link :href="logoHref">
                            <AppLogo
                                variant="client"
                                :organization-name="organization?.name ?? null"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-if="showStaffReturnLink">
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                as-child
                                class="h-auto bg-amber-50/50 hover:bg-amber-100/50 dark:bg-amber-900/10 dark:hover:bg-amber-900/20"
                            >
                                <Link
                                    :href="wayfinder.org.show(organization!.id)"
                                    class="flex w-full flex-col items-start gap-1 py-2"
                                >
                                    <span
                                        class="flex items-center gap-2 text-xs font-semibold text-amber-700 dark:text-amber-400"
                                    >
                                        <LayoutDashboard class="h-3 w-3" />
                                        Staff Preview
                                    </span>
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Return to Consultant Console
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
            <SidebarSeparator v-if="showStaffReturnLink" />

            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in shortcutItems"
                            :key="item.key"
                        >
                            <SidebarMenuButton
                                v-if="item.href"
                                :is-active="urlIsActive(item.href, page.url)"
                                as-child
                                class="h-auto items-start gap-3 rounded-lg py-2 text-sm"
                            >
                                <Link
                                    :href="item.href"
                                    class="flex w-full items-start gap-3 text-left"
                                >
                                    <span
                                        class="flex h-8 w-8 items-center justify-center rounded-md border border-border/60 bg-muted text-muted-foreground"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="h-4 w-4"
                                        />
                                    </span>
                                    <span class="flex flex-col">
                                        <span
                                            class="font-medium text-sidebar-foreground"
                                        >
                                            {{ item.label }}
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ item.description }}
                                        </span>
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                            <SidebarMenuButton
                                v-else
                                class="h-auto items-start gap-3 rounded-lg py-2 text-sm opacity-60"
                                disabled
                            >
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-md border border-border/60 bg-muted text-muted-foreground"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <span class="flex flex-col">
                                    <span
                                        class="font-medium text-sidebar-foreground"
                                    >
                                        {{ item.label }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ item.description }}
                                    </span>
                                    <span
                                        class="text-[11px] font-medium text-muted-foreground"
                                    >
                                        Check back soon
                                    </span>
                                </span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    <div class="mt-4 space-y-2">
                        <p
                            class="text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            Metrics
                        </p>
                        <div class="space-y-1 pl-1">
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="metric in handbookMetricItems"
                                    :key="metric.id"
                                >
                                    <SidebarMenuButton
                                        v-if="metric.href"
                                        :is-active="
                                            urlIsActive(metric.href, page.url)
                                        "
                                        as-child
                                        size="sm"
                                        class="h-auto items-start rounded-md px-2 py-1.5 leading-snug"
                                    >
                                        <Link :href="metric.href">
                                            <span class="min-w-0 truncate">
                                                {{ metric.name }}
                                            </span>
                                        </Link>
                                    </SidebarMenuButton>
                                    <SidebarMenuButton
                                        v-else
                                        class="h-auto items-start rounded-md px-2 py-1.5 leading-snug opacity-60"
                                        size="sm"
                                        disabled
                                    >
                                        <span class="min-w-0 truncate">
                                            {{ metric.name }}
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                            <p
                                v-if="showHandbookEmptyState"
                                class="text-xs text-muted-foreground"
                            >
                                No finalized metrics yet.
                            </p>
                        </div>
                    </div>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/60 pt-4">
            <div
                v-if="!hasNavigation"
                class="rounded-lg border border-dashed border-sidebar-border/60 bg-muted/40 p-3 text-xs text-muted-foreground"
            >
                <p class="font-semibold text-sidebar-foreground">
                    Client navigation loading
                </p>
                <p>
                    Workspace links will appear once your membership is scoped
                    to an organization.
                </p>
            </div>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
