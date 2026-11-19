<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    IMPERSONATION_OFF_ID,
    useConsultantNav,
} from '@/composables/useConsultantNav';
import { useEngagementNavigation } from '@/composables/useEngagementNavigation';
import { useImpersonationJustification } from '@/composables/useImpersonationJustification';
import { useWayfinder } from '@/composables/useWayfinder';
import { persistActiveOrganizationSelection } from '@/lib/organizations';
import { urlIsActive } from '@/lib/utils';
import type {
    AppPageProps,
    ImpersonationState,
    NavItem,
    Organization,
} from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertCircle,
    ChevronsUpDown,
    NotebookPen,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<AppPageProps>();
const wayfinder = useWayfinder();
const ORGANIZATION_SWITCH_ERROR_MESSAGE =
    'Could not switch organizations. Please try again.';

const organizations = computed<Organization[]>(
    () => (page.props.organizations as Organization[]) ?? [],
);

const organizationOptions = computed<Organization[]>(() =>
    organizations.value.filter(
        (organization) => organization.id !== IMPERSONATION_OFF_ID,
    ),
);

const impersonation = computed<ImpersonationState | null>(() => {
    const state = page.props.impersonation as
        | ImpersonationState
        | null
        | undefined;
    return state ?? null;
});

const isInternalStaff = computed(
    () => !!page.props.auth?.user?.is_internal_staff,
);
const canOperateDelivery = computed(
    () => !!page.props.auth?.user?.can_operate_delivery,
);
const canImpersonate = computed(() => !!page.props.auth?.user?.can_impersonate);
const canGovernInternal = computed(
    () => !!page.props.auth?.user?.can_govern_internal,
);

const pageActiveOrganizationId = computed(
    () => page.props.activeOrganizationId ?? null,
);
const pendingActiveOrganizationId = ref<string | null>(null);
const organizationSwitchError = ref<string | null>(null);

const activeOrganizationId = computed(() => {
    if (pendingActiveOrganizationId.value) {
        return pendingActiveOrganizationId.value;
    }

    return pageActiveOrganizationId.value ?? null;
});

function clearPendingActiveOrganization() {
    if (!pendingActiveOrganizationId.value) {
        return;
    }

    pendingActiveOrganizationId.value = null;
}

watch(
    () => pageActiveOrganizationId.value,
    () => {
        clearPendingActiveOrganization();
        organizationSwitchError.value = null;
    },
);

const staffWorkspace = computed<Organization | null>(() => {
    const workspace = page.props.staffWorkspace as
        | Organization
        | null
        | undefined;
    return workspace ?? null;
});

const activeOrganizationMatchesStaffWorkspace = computed(() => {
    if (!staffWorkspace.value) {
        return false;
    }

    const targetId = activeOrganizationId.value;
    if (!targetId) {
        return false;
    }

    return String(staffWorkspace.value.id) === String(targetId);
});

const staffWorkspaceHref = computed(() => {
    if (!staffWorkspace.value) {
        return null;
    }

    return wayfinder.org.show(String(staffWorkspace.value.id));
});

const viewingStaffWorkspace = computed(() => {
    if (!staffWorkspaceHref.value) {
        return false;
    }

    const currentUrl = page.url ?? '';
    return urlIsActive(staffWorkspaceHref.value, currentUrl);
});

const resolvedActiveOrganizationId = computed<string | null>(() => {
    return activeOrganizationId.value;
});

const activeOrganization = computed<Organization | null>(() => {
    if (impersonation.value?.organization) {
        return impersonation.value.organization;
    }

    if (
        isInternalStaff.value &&
        !isImpersonating.value &&
        !resolvedActiveOrganizationId.value
    ) {
        return null;
    }

    if (activeOrganizationMatchesStaffWorkspace.value && staffWorkspace.value) {
        return staffWorkspace.value;
    }

    const resolvedActiveId = resolvedActiveOrganizationId.value;
    const current = organizations.value.find((org) => {
        if (org.id === IMPERSONATION_OFF_ID) {
            return false;
        }

        if (!resolvedActiveId) {
            return false;
        }

        return String(org.id) === String(resolvedActiveId);
    });

    if (current) {
        return current;
    }

    if (isInternalStaff.value && !isImpersonating.value) {
        return null;
    }

    const fallback = organizations.value.find(
        (org) => org.id !== IMPERSONATION_OFF_ID,
    );

    return fallback ?? null;
});

const activeOrganizationValue = computed(() =>
    activeOrganization.value ? String(activeOrganization.value.id) : '',
);

const canManageOrganization = computed(
    () => activeOrganization.value?.can_manage ?? false,
);

const canOperateActiveDelivery = computed(
    () => activeOrganization.value?.can_operate_delivery ?? false,
);

const { mainNavItems, organizationNavItems } = useConsultantNav({
    wayfinder,
    isInternalStaff,
    canOperateDelivery,
    canOperateActiveDelivery,
    canGovernInternal,
    staffWorkspace,
    activeOrganization,
    activeOrganizationValue,
    canManageOrganization,
    activeOrganizationMatchesStaffWorkspace,
});

const activeOrganizationSelectionModel = computed({
    get: () => activeOrganizationValue.value,
    set: (organizationId: string) => {
        handleOrganizationSelect(organizationId);
    },
});

const {
    justification: impersonationJustification,
    justificationField: impersonationJustificationField,
    justificationPayload,
} = useImpersonationJustification();

watch(
    () => impersonation.value?.justification ?? null,
    (value) => {
        impersonationJustification.value = value ?? '';
    },
    { immediate: true },
);

const impersonationTargetOrganizationId = computed(() => {
    if (impersonation.value?.organization?.id) {
        return String(impersonation.value.organization.id);
    }

    if (resolvedActiveOrganizationId.value) {
        return String(resolvedActiveOrganizationId.value);
    }

    if (activeOrganization.value?.id) {
        return String(activeOrganization.value.id);
    }

    const fallbackOrganization = organizations.value.find(
        (organization) => organization.id !== IMPERSONATION_OFF_ID,
    );

    return fallbackOrganization ? String(fallbackOrganization.id) : null;
});

const activeOrganizationIdForEngagement = computed(() => {
    if (!activeOrganization.value) {
        return null;
    }

    if (String(activeOrganization.value.id) === IMPERSONATION_OFF_ID) {
        return null;
    }

    return String(activeOrganization.value.id);
});

const isImpersonating = computed(() => !!impersonation.value?.organization);

type ViewAsMode = 'off' | 'owner' | 'admin' | 'member' | 'viewer';

const viewAsOptions: Array<{ value: ViewAsMode; label: string }> = [
    { value: 'off', label: 'Consultant' },
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Admin' },
    { value: 'member', label: 'Member' },
    { value: 'viewer', label: 'Viewer' },
];

const impersonationMode = ref<ViewAsMode>('off');
const showComplianceNote = ref(false);

const impersonationModeLabel = computed(() => {
    const active = viewAsOptions.find(
        (option) => option.value === impersonationMode.value,
    );
    return active?.label ?? 'Select view mode';
});

const flashError = computed(() => {
    if (!canImpersonate.value) {
        return null;
    }

    const impersonationError = page.props.flash?.impersonation_error;
    if (
        typeof impersonationError === 'string' &&
        impersonationError.trim() !== ''
    ) {
        return impersonationError;
    }

    const message = page.props.flash?.error;
    return typeof message === 'string' && message.trim() !== ''
        ? message
        : null;
});

const {
    engagementOptions,
    activeEngagement: activeEngagementOption,
    metricsHref,
    selectedEngagementId,
    setSelectedEngagement,
    buildEngagementUrlFor,
    rememberSelection,
} = useEngagementNavigation({
    organizationId: activeOrganizationIdForEngagement,
});

const engagementSelectionModel = computed<string | undefined>({
    get: () => selectedEngagementId.value ?? undefined,
    set: (value) => {
        if (!value || value === selectedEngagementId.value) {
            return;
        }

        setSelectedEngagement(value);

        const href = buildEngagementUrlFor(value);
        if (href && !urlIsActive(href, page.url)) {
            router.visit(href);
        }
    },
});

const hasEngagements = computed(() => engagementOptions.value.length > 0);
const showingStaffWorkspace = computed(
    () =>
        activeOrganizationMatchesStaffWorkspace.value &&
        viewingStaffWorkspace.value,
);

const metricsIsActive = computed(
    () => !!metricsHref.value && urlIsActive(metricsHref.value, page.url),
);

function navigateToMetrics() {
    if (!metricsHref.value) {
        return;
    }

    rememberSelection();
    router.visit(metricsHref.value);
}

watch(
    () => impersonation.value?.organization?.id ?? null,
    (organizationId) => {
        if (!organizationId) {
            impersonationMode.value = 'off';
            return;
        }

        const matchingOrganization = organizations.value.find(
            (organization) =>
                String(organization.id) === String(organizationId),
        );

        if (matchingOrganization?.role) {
            impersonationMode.value = matchingOrganization.role as ViewAsMode;
            return;
        }

        if (impersonationMode.value === 'off') {
            impersonationMode.value = 'viewer';
        }
    },
    { immediate: true },
);

watch(
    () => impersonation.value?.role ?? null,
    (role) => {
        if (!role || !isViewAsMode(role)) {
            if (!impersonation.value?.organization) {
                impersonationMode.value = 'off';
            }

            return;
        }

        impersonationMode.value = role;
    },
    { immediate: true },
);

const footerNavItems: NavItem[] = [];

type VisitOrganizationOptions = {
    bypassStaffOwnerRedirect?: boolean;
};

type ResumeImpersonationOptions = {
    organizationId: string;
    role: ViewAsMode;
};

function submitImpersonationStart(
    organizationId: string,
    role: ViewAsMode,
    options?: { onError?: () => void },
) {
    router.post(
        wayfinder.owner.impersonation.start(organizationId),
        { role, justification: justificationPayload.value },
        {
            preserveScroll: true,
            onError: () => {
                options?.onError?.();
            },
        },
    );
}

async function visitOrganization(
    organizationId: string,
    options?: VisitOrganizationOptions,
) {
    if (organizationId && organizationId !== IMPERSONATION_OFF_ID) {
        try {
            await persistActiveOrganizationSelection(organizationId);
        } catch (error) {
            console.error(
                'Failed to persist active organization selection',
                error,
            );
            organizationSwitchError.value = ORGANIZATION_SWITCH_ERROR_MESSAGE;
            clearPendingActiveOrganization();
            return;
        }
    }

    organizationSwitchError.value = null;

    const bypassStaffOwnerRedirect = options?.bypassStaffOwnerRedirect ?? false;

    if (
        !bypassStaffOwnerRedirect &&
        canOperateDelivery.value &&
        !isImpersonating.value
    ) {
        const destination =
            buildOrganizationScopedDestinationForStaff(organizationId) ??
            page.url ??
            wayfinder.owner.console();
        router.visit(destination, {
            preserveScroll: true,
            onSuccess: () => {
                organizationSwitchError.value = null;
                clearPendingActiveOrganization();
            },
            onError: () => {
                organizationSwitchError.value =
                    ORGANIZATION_SWITCH_ERROR_MESSAGE;
                clearPendingActiveOrganization();
            },
            onCancel: clearPendingActiveOrganization,
            onFinish: clearPendingActiveOrganization,
        });
        return;
    }

    router.visit(wayfinder.org.show(organizationId), {
        onSuccess: () => {
            organizationSwitchError.value = null;
            clearPendingActiveOrganization();
        },
        onError: () => {
            organizationSwitchError.value = ORGANIZATION_SWITCH_ERROR_MESSAGE;
            clearPendingActiveOrganization();
        },
        onCancel: clearPendingActiveOrganization,
        onFinish: clearPendingActiveOrganization,
    });
}

function buildOrganizationScopedDestinationForStaff(
    organizationId: string,
): string | null {
    const currentUrl = page.url ?? '';
    if (!currentUrl) {
        return null;
    }

    const context = extractOrganizationRouteContext(currentUrl);
    if (!context) {
        return null;
    }

    const { organizationId: currentRouteOrganizationId, suffix } = context;
    if (!isKnownOrganizationRouteId(currentRouteOrganizationId)) {
        return null;
    }

    if (organizationRouteHasNestedResource(suffix)) {
        return (
            resolveRouteUrl(wayfinder.org.show(organizationId)) ??
            `/organizations/${organizationId}`
        );
    }

    const escapedOrganizationId = escapeRegExp(currentRouteOrganizationId);
    const pattern = new RegExp(
        `(/organizations/)${escapedOrganizationId}(?=[/?#]|$)`,
    );

    return currentUrl.replace(pattern, `/organizations/${organizationId}`);
}

function extractOrganizationRouteContext(
    url: string,
): { organizationId: string; suffix: string } | null {
    const match = url.match(/\/organizations\/([^/?#]+)([^?#]*)/);
    if (!match) {
        return null;
    }

    return {
        organizationId: match[1],
        suffix: match[2] ?? '',
    };
}

function isKnownOrganizationRouteId(organizationId: string): boolean {
    if (!organizationId) {
        return false;
    }

    const staffWorkspaceId = staffWorkspace.value?.id
        ? String(staffWorkspace.value.id)
        : null;
    if (staffWorkspaceId && organizationId === staffWorkspaceId) {
        return true;
    }

    return organizations.value.some(
        (organization) => String(organization.id) === organizationId,
    );
}

function organizationRouteHasNestedResource(suffix: string): boolean {
    if (!suffix || suffix === '/' || suffix === '') {
        return false;
    }

    const [path] = suffix.split(/[?#]/, 1);
    const trimmed = path.replace(/\/+$/, '');
    if (!trimmed || trimmed === '/') {
        return false;
    }

    const normalized = trimmed.replace(/^\/+/, '');
    if (normalized.length === 0) {
        return false;
    }

    const segments = normalized.split('/').filter(Boolean);
    if (segments.length <= 1) {
        return false;
    }

    const multiSegmentPath = `/${segments.join('/')}`;
    if (ALLOWED_MULTI_SEGMENT_SUFFIXES.has(multiSegmentPath)) {
        return false;
    }

    return true;
}

const ALLOWED_MULTI_SEGMENT_SUFFIXES = new Set([
    '/engagements/create',
    '/engagements/launch',
]);

function organizationRequiresImpersonation(
    organization: Organization | undefined | null,
): boolean {
    if (!organization) {
        return true;
    }

    if (!canImpersonate.value) {
        return false;
    }

    if (organization.owned) {
        return false;
    }

    if (
        staffWorkspace.value &&
        String(staffWorkspace.value.id) === String(organization.id)
    ) {
        return false;
    }

    return true;
}

function findOrganizationById(
    organizationId: string,
): Organization | undefined {
    if (!organizationId) {
        return undefined;
    }

    return organizations.value.find(
        (organization) => String(organization.id) === String(organizationId),
    );
}

function resolveImpersonationRoleForOrganization(
    organization: Organization | undefined | null,
): ViewAsMode {
    if (organization?.role && isViewAsMode(organization.role)) {
        return organization.role;
    }

    return 'viewer';
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function resolveRouteUrl(href: unknown): string | null {
    if (typeof href === 'string') {
        return href;
    }

    if (
        href &&
        typeof href === 'object' &&
        'url' in href &&
        typeof (href as { url?: string }).url === 'string'
    ) {
        return (href as { url: string }).url;
    }

    return null;
}

function stopImpersonationThenVisit(
    organizationId: string | null,
    options?: { resume?: ResumeImpersonationOptions },
) {
    const targetOrganizationId = organizationId ? String(organizationId) : null;
    const resume = options?.resume;

    router.delete(wayfinder.owner.impersonation.stop(), {
        data: {
            justification: justificationPayload.value,
        },
        preserveScroll: true,
        onSuccess: () => {
            if (resume) {
                submitImpersonationStart(resume.organizationId, resume.role);
                return;
            }

            if (targetOrganizationId) {
                visitOrganization(targetOrganizationId, {
                    bypassStaffOwnerRedirect: true,
                });
            }
        },
    });
}

async function handleOrganizationSelect(organizationId: string) {
    organizationSwitchError.value = null;

    if (
        !organizationId ||
        organizationId === IMPERSONATION_OFF_ID ||
        organizationId === activeOrganizationValue.value
    ) {
        return;
    }

    pendingActiveOrganizationId.value = organizationId;

    const selectedOrganization = findOrganizationById(organizationId);
    const requiresImpersonationSelection =
        !isImpersonating.value &&
        organizationRequiresImpersonation(selectedOrganization);

    if (requiresImpersonationSelection) {
        const targetRole =
            resolveImpersonationRoleForOrganization(selectedOrganization);
        handleImpersonationModeSelect(targetRole);
        clearPendingActiveOrganization();
        return;
    }

    if (
        isImpersonating.value &&
        isViewAsMode(impersonationMode.value) &&
        impersonationMode.value !== 'off'
    ) {
        stopImpersonationThenVisit(organizationId, {
            resume: {
                organizationId,
                role: impersonationMode.value,
            },
        });
        return;
    }

    await visitOrganization(organizationId);
}

function toggleComplianceNote() {
    if (!canImpersonate.value) {
        return;
    }

    showComplianceNote.value = !showComplianceNote.value;
}

type SelectAcceptableValue =
    | string
    | number
    | bigint
    | Record<string, any>
    | null;

function isViewAsMode(value: string): value is ViewAsMode {
    return ['off', 'owner', 'admin', 'member', 'viewer'].includes(
        value as ViewAsMode,
    );
}

function handleImpersonationModeSelect(value: SelectAcceptableValue) {
    if (typeof value !== 'string' || !isViewAsMode(value)) {
        return;
    }

    const mode = value;

    if (!canImpersonate.value && mode !== 'off') {
        return;
    }
    if (mode === impersonationMode.value) {
        const impersonatingWithSelectedRole =
            isImpersonating.value && impersonation.value?.role === mode;
        const viewingAsConsultant = !isImpersonating.value && mode === 'off';

        if (impersonatingWithSelectedRole || viewingAsConsultant) {
            return;
        }
    }

    const previousMode = impersonationMode.value;
    impersonationMode.value = mode;

    const organizationId = impersonationTargetOrganizationId.value;

    if (mode === 'off') {
        if (!isImpersonating.value) {
            return;
        }

        stopImpersonationThenVisit(organizationId);
        return;
    }

    if (!organizationId || organizationId === IMPERSONATION_OFF_ID) {
        impersonationMode.value = previousMode;
        return;
    }

    const impersonationTargetId = impersonation.value?.organization
        ? String(impersonation.value.organization.id)
        : null;
    const alreadyImpersonatingTarget =
        isImpersonating.value && impersonationTargetId === organizationId;

    if (alreadyImpersonatingTarget && impersonation.value?.role === mode) {
        return;
    }

    submitImpersonationStart(organizationId, mode, {
        onError: () => {
            impersonationMode.value = previousMode;
        },
    });
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="wayfinder.dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div
                v-if="isInternalStaff && organizationOptions.length"
                class="px-2 pb-2"
            >
                <div class="space-y-2">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <SidebarMenuButton
                                        size="lg"
                                        class="flex h-auto flex-col items-start gap-2 rounded-lg px-3 py-3 text-left data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                        :class="{
                                            'cursor-not-allowed opacity-60 ring-1 ring-border/70 ring-inset dark:ring-white/10':
                                                showingStaffWorkspace,
                                        }"
                                        :disabled="showingStaffWorkspace"
                                        :tabindex="
                                            showingStaffWorkspace
                                                ? -1
                                                : undefined
                                        "
                                        aria-label="Select organization"
                                    >
                                        <div
                                            class="flex w-full items-center gap-2"
                                        >
                                            <div class="flex-1">
                                                <p
                                                    class="text-xs font-medium text-muted-foreground uppercase"
                                                >
                                                    Organization
                                                </p>
                                                <p
                                                    class="truncate text-sm leading-tight font-semibold"
                                                >
                                                    {{
                                                        activeOrganization?.name ??
                                                        'Select organization'
                                                    }}
                                                </p>
                                            </div>
                                            <ChevronsUpDown
                                                class="ml-auto size-4 shrink-0 opacity-60"
                                            />
                                        </div>
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                >
                                    <DropdownMenuRadioGroup
                                        v-model="
                                            activeOrganizationSelectionModel
                                        "
                                    >
                                        <DropdownMenuRadioItem
                                            v-for="organization in organizationOptions"
                                            :key="organization.id"
                                            :value="String(organization.id)"
                                            class="flex items-center gap-2"
                                        >
                                            <div
                                                class="flex w-full flex-col text-left"
                                            >
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="flex-1 truncate"
                                                        >{{
                                                            organization.name
                                                        }}</span
                                                    >
                                                    <span
                                                        class="text-[0.65rem] font-semibold tracking-wide uppercase"
                                                        :class="[
                                                            organizationRequiresImpersonation(
                                                                organization,
                                                            )
                                                                ? 'text-amber-600 dark:text-amber-400'
                                                                : 'text-muted-foreground',
                                                        ]"
                                                    >
                                                        {{
                                                            organizationRequiresImpersonation(
                                                                organization,
                                                            )
                                                                ? 'View as'
                                                                : 'Member'
                                                        }}
                                                    </span>
                                                </div>
                                                <span
                                                    v-if="
                                                        organizationRequiresImpersonation(
                                                            organization,
                                                        )
                                                    "
                                                    class="text-[0.65rem] leading-tight text-muted-foreground"
                                                >
                                                    Requires impersonation
                                                </span>
                                            </div>
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <div
                                v-if="organizationSwitchError"
                                class="flex items-center gap-1 px-3 pt-2 text-xs font-medium text-destructive"
                                role="alert"
                                aria-live="assertive"
                                aria-atomic="true"
                                data-test="organization-switch-error"
                            >
                                <AlertCircle class="size-3" />
                                <span class="truncate">{{
                                    organizationSwitchError
                                }}</span>
                            </div>
                        </SidebarMenuItem>

                        <SidebarMenuItem v-if="canImpersonate">
                            <div class="w-full space-y-2">
                                <div
                                    class="mb-0 flex items-center justify-between px-1"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Impersonation
                                    </p>
                                    <TooltipProvider
                                        v-if="canImpersonate"
                                        :delay-duration="0"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-transparent text-muted-foreground transition hover:border-border hover:bg-muted/70 hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-60 dark:hover:bg-muted/50"
                                                    :aria-expanded="
                                                        showComplianceNote
                                                    "
                                                    :aria-pressed="
                                                        showComplianceNote
                                                    "
                                                    aria-label="Toggle compliance note"
                                                    @click="
                                                        toggleComplianceNote
                                                    "
                                                    :disabled="
                                                        showingStaffWorkspace
                                                    "
                                                >
                                                    <NotebookPen
                                                        class="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    <span class="sr-only">
                                                        {{
                                                            showComplianceNote
                                                                ? 'Hide compliance note'
                                                                : 'Show compliance note'
                                                        }}
                                                    </span>
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                align="end"
                                                side="left"
                                            >
                                                {{
                                                    showComplianceNote
                                                        ? 'Hide note'
                                                        : 'Add compliance note'
                                                }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                <Select
                                    v-if="canImpersonate"
                                    :model-value="impersonationMode"
                                    :disabled="showingStaffWorkspace"
                                    @update:modelValue="
                                        handleImpersonationModeSelect
                                    "
                                >
                                    <SelectTrigger
                                        class="flex h-auto items-start gap-1 rounded-lg bg-transparent px-3 py-3 text-left shadow-none ring-0 focus:ring-0 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                        :class="[
                                            {
                                                'border-2 border-blue-500 bg-blue-100 dark:bg-blue-950':
                                                    isImpersonating,
                                            },
                                            showingStaffWorkspace
                                                ? 'cursor-not-allowed opacity-60 ring-1 ring-border/70 ring-inset dark:ring-white/10'
                                                : '',
                                        ]"
                                    >
                                        <div
                                            class="flex w-full items-start gap-2"
                                        >
                                            <div class="flex-1">
                                                <p
                                                    class="text-xs font-semibold text-muted-foreground uppercase"
                                                >
                                                    View as
                                                </p>
                                                <p
                                                    class="truncate text-sm leading-tight font-semibold"
                                                >
                                                    {{ impersonationModeLabel }}
                                                </p>
                                            </div>
                                        </div>

                                        <SelectValue
                                            class="sr-only"
                                            placeholder="Select view mode"
                                        />
                                    </SelectTrigger>
                                    <SelectContent class="min-w-56 rounded-lg">
                                        <SelectGroup>
                                            <SelectLabel
                                                >Membership Role</SelectLabel
                                            >
                                            <SelectItem
                                                v-for="option in viewAsOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <div
                                    v-if="flashError"
                                    class="flex items-center gap-1 px-3 text-xs font-medium text-destructive"
                                    role="alert"
                                    aria-live="assertive"
                                    aria-atomic="true"
                                    data-test="impersonation-flash-error"
                                >
                                    <AlertCircle class="size-3" />
                                    <span class="truncate">{{
                                        flashError
                                    }}</span>
                                </div>
                                <Button
                                    v-if="canImpersonate && isImpersonating"
                                    type="button"
                                    variant="outline"
                                    class="w-full justify-center text-xs font-semibold uppercase"
                                    data-test="stop-impersonation"
                                    @click="stopImpersonationThenVisit(null)"
                                    :disabled="showingStaffWorkspace"
                                >
                                    Stop impersonating
                                </Button>
                                <div
                                    v-if="
                                        showComplianceNote &&
                                        !showingStaffWorkspace
                                    "
                                    class="space-y-2 px-1"
                                >
                                    <Label
                                        for="sidebar-impersonation-justification"
                                        class="text-xs font-semibold text-muted-foreground uppercase"
                                    >
                                        Compliance note
                                    </Label>
                                    <Textarea
                                        id="sidebar-impersonation-justification"
                                        v-model="
                                            impersonationJustificationField
                                        "
                                        rows="3"
                                        placeholder="Add context for impersonation audit logs (optional)"
                                    />
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Included when logging impersonation
                                        start/stop events.
                                    </p>
                                </div>
                            </div>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </div>
            </div>
            <div
                v-else
                class="space-y-2 px-4 pb-4 text-sm text-muted-foreground"
            >
                <p class="leading-tight">No organizations available.</p>
                <p
                    v-if="staffWorkspace"
                    class="text-xs font-medium text-muted-foreground/80 uppercase"
                >
                    Staff Access
                </p>
                <Link
                    v-if="staffWorkspace"
                    :href="wayfinder.org.show(String(staffWorkspace.id))"
                    class="inline-flex items-center text-sm font-semibold text-sidebar-accent hover:underline"
                >
                    Go to Consultant Workspace
                </Link>
            </div>

            <NavMain v-if="mainNavItems.length" :items="mainNavItems" />

            <SidebarGroup
                v-if="organizationNavItems.length && !showingStaffWorkspace"
                class="px-2 py-0"
                data-test="sidebar-group"
            >
                <SidebarGroupLabel>Organization</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in organizationNavItems"
                        :key="item.title"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="urlIsActive(item.href, page.url)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup
                v-if="
                    activeOrganization &&
                    !showingStaffWorkspace &&
                    (isImpersonating ||
                        activeOrganizationMatchesStaffWorkspace ||
                        !canOperateDelivery)
                "
                class="px-2 py-0"
                data-test="sidebar-group"
            >
                <!-- <SidebarGroup v-if=" activeOrganization && !showingStaffWorkspace && (!isStaff || isImpersonating || activeOrganizationMatchesStaffWorkspace) " class="px-2 py-0" > -->
                <SidebarGroupLabel>Engagement</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <SidebarMenuButton
                                    size="lg"
                                    class="flex h-auto flex-col items-start gap-1 rounded-lg px-3 py-3 text-left"
                                >
                                    <div class="flex w-full items-center gap-2">
                                        <div class="flex-1">
                                            <p
                                                class="text-xs font-medium text-muted-foreground uppercase"
                                            >
                                                Engagement
                                            </p>
                                            <p
                                                class="truncate text-sm leading-tight font-semibold"
                                            >
                                                {{
                                                    hasEngagements
                                                        ? (activeEngagementOption?.title ??
                                                          'Select engagement')
                                                        : 'No engagements'
                                                }}
                                            </p>
                                        </div>
                                        <ChevronsUpDown
                                            class="ml-auto size-4 shrink-0 opacity-60"
                                        />
                                    </div>
                                    <p
                                        v-if="
                                            hasEngagements &&
                                            activeEngagementOption?.status
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        Status:
                                        {{ activeEngagementOption.status }}
                                    </p>
                                    <p
                                        v-else-if="!hasEngagements"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            showingStaffWorkspace
                                                ? 'Staff workspace metrics are available from the Owner Console.'
                                                : 'Create an engagement to unlock metrics.'
                                        }}
                                    </p>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="start"
                                class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            >
                                <template v-if="hasEngagements">
                                    <DropdownMenuRadioGroup
                                        v-model="engagementSelectionModel"
                                    >
                                        <DropdownMenuRadioItem
                                            v-for="engagement in engagementOptions"
                                            :key="engagement.id"
                                            :value="engagement.id"
                                            class="flex flex-col items-start gap-0.5"
                                        >
                                            <span class="font-medium">{{
                                                engagement.title
                                            }}</span>
                                            <span
                                                v-if="engagement.status"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ engagement.status }}
                                            </span>
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </template>
                                <div
                                    v-else
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    <template v-if="showingStaffWorkspace">
                                        Manage client engagements from the
                                        <Link
                                            :href="wayfinder.owner.console()"
                                            class="font-medium text-sidebar-accent hover:underline"
                                        >
                                            Owner Console
                                        </Link>
                                        .
                                    </template>
                                    <template v-else>
                                        No engagements available yet.
                                    </template>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                    <SidebarMenuItem v-if="hasEngagements">
                        <SidebarMenuButton
                            variant="ghost"
                            :disabled="!metricsHref"
                            :is-active="metricsIsActive"
                            @click="navigateToMetrics"
                        >
                            <Activity />
                            <span>Metrics</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
