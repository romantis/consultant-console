import type { NavItem, Organization } from '@/types';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Briefcase,
    Building2,
    ClipboardList,
    Crown,
    FileText,
    LayoutDashboard,
    Settings,
} from 'lucide-vue-next';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

export const IMPERSONATION_OFF_ID = 'impersonation-off';

type Wayfinder = {
    owner: {
        console: (() => NonNullable<InertiaLinkProps['href']>) & {
            readonly can: boolean;
        };
    };
    metricTemplates: {
        index: () => NonNullable<InertiaLinkProps['href']>;
    };
    metricLibrary: {
        index: () => NonNullable<InertiaLinkProps['href']>;
    };
    org: {
        index: () => NonNullable<InertiaLinkProps['href']>;
        show: (organization: string) => NonNullable<InertiaLinkProps['href']>;
        engagements: {
            index: (
                organization: string,
            ) => NonNullable<InertiaLinkProps['href']>;
        };
        orgMetrics: {
            index: (
                organization: string,
            ) => NonNullable<InertiaLinkProps['href']>;
        };
        handbookExports: {
            index: (
                organization: string,
            ) => NonNullable<InertiaLinkProps['href']>;
        };
        settings: {
            edit: (
                organization: string,
            ) => NonNullable<InertiaLinkProps['href']>;
        };
    };
};

type UseConsultantNavOptions = {
    wayfinder: Wayfinder;
    isInternalStaff: ComputedRef<boolean>;
    canOperateDelivery: ComputedRef<boolean>;
    canOperateActiveDelivery: ComputedRef<boolean>;
    canGovernInternal: ComputedRef<boolean>;
    staffWorkspace: ComputedRef<Organization | null>;
    activeOrganization: ComputedRef<Organization | null>;
    activeOrganizationValue: ComputedRef<string>;
    canManageOrganization: ComputedRef<boolean>;
    activeOrganizationMatchesStaffWorkspace: ComputedRef<boolean>;
};

export function useConsultantNav(options: UseConsultantNavOptions) {
    const mainNavItems = computed<NavItem[]>(() => {
        if (!options.isInternalStaff.value) {
            return [];
        }

        const items: NavItem[] = [];

        if (
            options.canGovernInternal.value &&
            options.wayfinder.owner.console.can
        ) {
            items.push({
                title: 'Owner Console',
                href: options.wayfinder.owner.console(),
                icon: Crown,
            });
        }

        if (!options.canOperateDelivery.value) {
            return items;
        }

        if (options.staffWorkspace.value) {
            items.push({
                title: 'Consultant Workspace',
                href: options.wayfinder.org.show(
                    String(options.staffWorkspace.value.id),
                ),
                icon: LayoutDashboard,
            });
        }

        items.push(
            {
                title: 'Metric Library',
                href: options.wayfinder.metricLibrary.index(),
                icon: BookOpen,
            },
            {
                title: 'Metric Templates',
                href: options.wayfinder.metricTemplates.index(),
                icon: ClipboardList,
            },
            {
                title: 'Organizations',
                href: options.wayfinder.org.index(),
                icon: Building2,
            },
        );

        return items;
    });

    const organizationNavItems = computed<NavItem[]>(() => {
        if (
            !options.isInternalStaff.value ||
            options.activeOrganizationMatchesStaffWorkspace.value
        ) {
            return [];
        }

        const items: NavItem[] = [];

        if (!options.isInternalStaff.value) {
            items.push({
                title: 'Organizations',
                href: options.wayfinder.org.index(),
                icon: Building2,
            });
        }

        const organizationId = options.activeOrganizationValue.value;

        if (!organizationId || organizationId === IMPERSONATION_OFF_ID) {
            return items;
        }

        items.push(
            {
                title: 'Overview',
                href: options.wayfinder.org.show(organizationId),
                icon: LayoutDashboard,
            },
            {
                title: 'Engagements',
                href: options.wayfinder.org.engagements.index(organizationId),
                icon: Briefcase,
            },
        );

        if (options.canOperateActiveDelivery.value) {
            items.push({
                title: 'Org Metrics',
                href: options.wayfinder.org.orgMetrics.index(organizationId),
                icon: BarChart3,
            });

            items.push({
                title: 'Handbook Exports',
                href: options.wayfinder.org.handbookExports.index(
                    organizationId,
                ),
                icon: FileText,
            });
        }

        if (options.canManageOrganization.value) {
            items.push({
                title: 'Settings',
                href: options.wayfinder.org.settings.edit(organizationId),
                icon: Settings,
            });
        }

        return items;
    });

    return {
        mainNavItems,
        organizationNavItems,
    };
}
