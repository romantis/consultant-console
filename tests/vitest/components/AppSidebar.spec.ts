import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick, reactive, ref } from 'vue';

import type { AppPageProps, ImpersonationState, Organization } from '@/types';
import { buildImpersonationState } from '../setup/impersonation';

let AppSidebar: ReturnType<typeof defineComponent>;

beforeAll(async () => {
    vi.resetModules();
    ({ default: AppSidebar } = await import('@/components/AppSidebar.vue'));
});

const componentStub = vi.hoisted(
    () => (name: string) =>
        defineComponent({
            name,
            inheritAttrs: false,
            setup(_, { slots }) {
                return () =>
                    h(
                        'div',
                        {
                            'data-stub': name,
                        },
                        slots.default ? slots.default() : [],
                    );
            },
        }),
);

const fieldStub = vi.hoisted(
    () => (name: string) =>
        defineComponent({
            name,
            inheritAttrs: false,
            props: {
                modelValue: {
                    type: [String, Number, Object, Array, Boolean],
                    default: undefined,
                },
            },
            emits: ['update:modelValue'],
            setup(_, { slots }) {
                return () =>
                    h(
                        'div',
                        {
                            'data-field-stub': name,
                        },
                        slots.default ? slots.default() : [],
                    );
            },
        }),
);

const routerPost = vi.fn();
const routerDelete = vi.fn();
const routerVisit = vi.fn();
const urlIsActiveMock = vi.fn(
    (target: string, current: string | undefined) => target === current,
);

let currentPage: { url: string; props: AppPageProps };
const rememberStore: Record<string, ReturnType<typeof ref>> = {};

vi.mock('@inertiajs/vue3', () => {
    const LinkStub = defineComponent({
        name: 'LinkStub',
        inheritAttrs: false,
        props: {
            href: {
                type: [String, Object],
                default: '#',
            },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    'a',
                    {
                        href: typeof props.href === 'string' ? props.href : '#',
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        Link: LinkStub,
        router: {
            post: (...args: unknown[]) => routerPost(...args),
            delete: (...args: unknown[]) => routerDelete(...args),
            visit: (...args: unknown[]) => routerVisit(...args),
        },
        usePage: () => currentPage,
        useRemember: <T>(initialValue: T, key?: string) => {
            if (key) {
                if (!rememberStore[key]) {
                    rememberStore[key] = ref(initialValue);
                }

                return rememberStore[key] as ReturnType<typeof ref>;
            }

            return ref(initialValue);
        },
    };
});

const ownerConsoleRoute = (() => '/owner/console') as (() => string) & {
    readonly can: boolean;
};

Object.defineProperty(ownerConsoleRoute, 'can', {
    get: () => Boolean(currentPage?.props.auth?.user?.can_govern_internal),
});

function buildAuthUser(
    overrides: Partial<NonNullable<AppPageProps['auth']['user']>> = {},
) {
    return {
        id: 'user-1',
        name: 'Consultant',
        email: 'consultant@example.test',
        is_internal_staff: true,
        staff_role: 'owner' as const,
        can_operate_delivery: true,
        has_operator_assignments: false,
        can_impersonate: true,
        can_govern_internal: true,
        ...overrides,
    };
}

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        dashboard: () => '/dashboard',
        owner: {
            console: ownerConsoleRoute,
            impersonation: {
                start: (organizationId: string) =>
                    `/owner/impersonation/${organizationId}`,
                stop: () => '/owner/impersonation',
            },
        },
        metricTemplates: {
            index: () => '/metric-templates',
        },
        metricLibrary: {
            index: () => '/metric-library',
        },
        org: {
            index: () => '/organizations',
            show: (organizationId: string) =>
                `/organizations/${organizationId}`,
            engagements: {
                index: (organizationId: string) =>
                    `/organizations/${organizationId}/engagements`,
            },
            orgMetrics: {
                index: (organizationId: string) =>
                    `/organizations/${organizationId}/org-metrics`,
            },
            handbookExports: {
                index: (organizationId: string) =>
                    `/organizations/${organizationId}/handbook-exports`,
            },
            settings: {
                edit: (organizationId: string) =>
                    `/organizations/${organizationId}/settings`,
            },
        },
    }),
}));

vi.mock('@/components/NavFooter.vue', () => ({
    default: componentStub('NavFooterStub'),
}));
vi.mock('@/components/NavMain.vue', () => ({
    default: componentStub('NavMainStub'),
}));
vi.mock('@/components/NavUser.vue', () => ({
    default: componentStub('NavUserStub'),
}));
vi.mock('@/components/AppLogo.vue', () => ({
    default: componentStub('AppLogoStub'),
}));

vi.mock('@/components/ui/sidebar', () => ({
    Sidebar: componentStub('Sidebar'),
    SidebarContent: componentStub('SidebarContent'),
    SidebarFooter: componentStub('SidebarFooter'),
    SidebarGroup: componentStub('SidebarGroup'),
    SidebarGroupLabel: componentStub('SidebarGroupLabel'),
    SidebarHeader: componentStub('SidebarHeader'),
    SidebarMenu: componentStub('SidebarMenu'),
    SidebarMenuButton: componentStub('SidebarMenuButton'),
    SidebarMenuItem: componentStub('SidebarMenuItem'),
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: componentStub('DropdownMenu'),
    DropdownMenuTrigger: componentStub('DropdownMenuTrigger'),
    DropdownMenuContent: componentStub('DropdownMenuContent'),
    DropdownMenuRadioGroup: componentStub('DropdownMenuRadioGroup'),
    DropdownMenuRadioItem: componentStub('DropdownMenuRadioItem'),
}));

vi.mock('@/components/ui/select', () => ({
    Select: fieldStub('Select'),
    SelectTrigger: componentStub('SelectTrigger'),
    SelectContent: componentStub('SelectContent'),
    SelectGroup: componentStub('SelectGroup'),
    SelectItem: componentStub('SelectItem'),
    SelectLabel: componentStub('SelectLabel'),
    SelectValue: componentStub('SelectValue'),
}));

vi.mock('@/components/ui/tooltip', () => ({
    Tooltip: componentStub('Tooltip'),
    TooltipProvider: componentStub('TooltipProvider'),
    TooltipTrigger: componentStub('TooltipTrigger'),
    TooltipContent: componentStub('TooltipContent'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: componentStub('Button'),
}));

vi.mock('@/components/ui/label', () => ({
    Label: componentStub('Label'),
}));

vi.mock('@/components/ui/textarea', () => ({
    Textarea: fieldStub('Textarea'),
}));

vi.mock('@/lib/utils', () => ({
    urlIsActive: (target: string, current: string) =>
        urlIsActiveMock(target, current),
}));

vi.mock('@/lib/organizations', () => ({
    persistActiveOrganizationSelection: vi.fn(),
}));

vi.mock('@/composables/useEngagementNavigation', () => ({
    useEngagementNavigation: () => ({
        engagementOptions: ref([]),
        activeEngagement: ref(null),
        formsHref: ref(null),
        metricsHref: ref(null),
        selectedEngagementId: ref(null),
        setSelectedEngagement: vi.fn(),
        buildEngagementUrlFor: vi.fn(),
        rememberSelection: vi.fn(),
    }),
}));

vi.mock('lucide-vue-next', () => {
    const IconStub = componentStub('Icon');

    return {
        default: IconStub,
        Activity: IconStub,
        AlertCircle: IconStub,
        Briefcase: IconStub,
        BarChart3: IconStub,
        Building2: IconStub,
        Check: IconStub,
        ChevronDown: IconStub,
        ChevronUp: IconStub,
        ChevronsUpDown: IconStub,
        ClipboardList: IconStub,
        Crown: IconStub,
        FileText: IconStub,
        LayoutDashboard: IconStub,
        Lock: IconStub,
        BookOpen: IconStub,
        NotebookPen: IconStub,
        Settings: IconStub,
    };
});

const buildOrganizations = (): Organization[] => [
    {
        id: 'impersonation-off',
        name: 'Impersonation off',
        slug: 'impersonation-off',
        owned: false,
        can_manage: false,
    } as Organization,
    {
        id: 'org-1',
        name: 'Acme Corp',
        slug: 'acme-corp',
        owned: true,
        role: 'viewer',
        can_manage: true,
    } as Organization,
    {
        id: 'org-2',
        name: 'Globex Corp',
        slug: 'globex-corp',
        owned: true,
        role: 'owner',
        can_manage: true,
    } as Organization,
];

const basePageProps = (
    overrides: Partial<AppPageProps> = {},
): AppPageProps => ({
    name: 'Metric Harmony Console',
    auth: {
        user: buildAuthUser(),
    },
    quote: {
        message: 'Quote',
        author: 'Author',
    },
    flash: {
        success: null,
        error: null,
        impersonation_error: null,
    },
    flags: {},
    organizations: buildOrganizations(),
    ownerOrganizations: [],
    activeOrganizationId: 'org-1',
    engagements: [],
    activeEngagementId: null,
    staffWorkspace: {
        id: 'staff-1',
        name: 'Staff Org',
        slug: 'staff-org',
    },
    impersonation: buildImpersonationState({
        organization: {
            id: 'org-1',
            name: 'Acme Corp',
            slug: 'acme-corp',
        },
        role: 'viewer',
    }),
    sidebarOpen: true,
    ...overrides,
});

const mountSidebar = (overrides: Partial<AppPageProps> = {}) => {
    currentPage = reactive({
        url: '/dashboard',
        props: basePageProps(overrides),
    });

    return mount(AppSidebar);
};

describe('AppSidebar impersonation controls', () => {
    beforeEach(() => {
        routerPost.mockReset();
        routerDelete.mockReset();
        routerVisit.mockReset();
        Object.keys(rememberStore).forEach((key) => delete rememberStore[key]);
    });

    it('syncs the view-as dropdown with server-provided role changes', async () => {
        const wrapper = mountSidebar({
            impersonation: buildImpersonationState({
                organization: {
                    id: 'org-1',
                    name: 'Acme Corp',
                    slug: 'acme-corp',
                },
                role: 'admin',
            }),
        });

        expect(wrapper.vm.impersonationMode).toBe('admin');

        const nextState: ImpersonationState = buildImpersonationState({
            organization: {
                id: 'org-1',
                name: 'Acme Corp',
                slug: 'acme-corp',
            },
            role: 'viewer',
        });

        currentPage.props.impersonation = nextState;
        await nextTick();

        expect(wrapper.vm.impersonationMode).toBe('viewer');
    });

    it('submits the selected role and justification when changing impersonation mode', async () => {
        const wrapper = mountSidebar({
            impersonation: buildImpersonationState({
                organization: {
                    id: 'org-1',
                    name: 'Acme Corp',
                    slug: 'acme-corp',
                },
                role: 'viewer',
            }),
        });

        wrapper.vm.impersonationJustification = '  need audit note  ';

        await wrapper.vm.handleImpersonationModeSelect('owner');

        expect(routerPost).toHaveBeenCalledTimes(1);
        const [url, payload, options] = routerPost.mock.calls[0];
        expect(url).toBe('/owner/impersonation/org-1');
        expect(payload).toEqual({
            role: 'owner',
            justification: 'need audit note',
        });
        expect(options).toMatchObject({
            preserveScroll: true,
        });
    });

    it('sends compliance notes with stop requests and lets the server choose the redirect target', async () => {
        const wrapper = mountSidebar({
            impersonation: buildImpersonationState({
                organization: {
                    id: 'org-1',
                    name: 'Acme Corp',
                    slug: 'acme-corp',
                },
                role: 'admin',
            }),
        });

        wrapper.vm.impersonationJustification = '  shutting down  ';

        await wrapper.vm.stopImpersonationThenVisit(null);

        expect(routerDelete).toHaveBeenCalledTimes(1);
        const [url, options] = routerDelete.mock.calls[0];
        expect(url).toBe('/owner/impersonation');
        expect(options).toMatchObject({
            data: { justification: 'shutting down' },
            preserveScroll: true,
        });

        expect(typeof options.onSuccess).toBe('function');
        options.onSuccess?.();
        expect(routerVisit).not.toHaveBeenCalled();
    });

    it('switches impersonation targets when selecting a new organization from the dropdown', async () => {
        const wrapper = mountSidebar({
            impersonation: buildImpersonationState({
                organization: {
                    id: 'org-1',
                    name: 'Acme Corp',
                    slug: 'acme-corp',
                },
                role: 'viewer',
            }),
        });

        wrapper.vm.impersonationJustification = 'Switch to Globex';

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerDelete).toHaveBeenCalledTimes(1);
        const [stopUrl, stopOptions] = routerDelete.mock.calls[0];
        expect(stopUrl).toBe('/owner/impersonation');
        expect(stopOptions.data).toEqual({ justification: 'Switch to Globex' });

        stopOptions.onSuccess?.();
        expect(routerPost).toHaveBeenCalledWith(
            '/owner/impersonation/org-2',
            {
                justification: 'Switch to Globex',
                role: 'viewer',
            },
            expect.objectContaining({
                preserveScroll: true,
            }),
        );
        expect(routerVisit).not.toHaveBeenCalledWith('/organizations/org-2');
    });

    it('lets staff without org memberships start impersonation', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations().map((organization) => ({
                ...organization,
                owned: false,
            })),
            impersonation: null,
            activeOrganizationId: null,
        });

        expect(wrapper.vm.activeOrganization).toBeNull();
        expect(wrapper.vm.impersonationTargetOrganizationId).toBe('org-1');

        await wrapper.vm.handleImpersonationModeSelect('owner');

        expect(routerPost).toHaveBeenCalledTimes(1);
        const [url, payload] = routerPost.mock.calls[0];
        expect(url).toBe('/owner/impersonation/org-1');
        expect(payload).toEqual({
            role: 'owner',
            justification: '',
        });
    });

    it('keeps staff on their current page when selecting an organization outside impersonation', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        currentPage.url = '/owner/console';

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(
            '/owner/console',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('starts impersonation when selecting an organization without direct membership', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations().map((organization) =>
                organization.id === 'org-2'
                    ? {
                          ...organization,
                          owned: false,
                          role: 'owner',
                          can_manage: false,
                      }
                    : organization,
            ),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerPost).toHaveBeenCalledTimes(1);
        expect(routerPost).toHaveBeenCalledWith(
            '/owner/impersonation/org-2',
            {
                justification: '',
                role: 'owner',
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerVisit).not.toHaveBeenCalled();
    });

    it('navigates staff to the selected organization when already on an organization route', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        currentPage.url = '/organizations/org-1/settings';
        await nextTick();

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(
            '/organizations/org-2/settings',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('prefers impersonation over direct navigation when the target organization is not owned', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations().map((organization) =>
                organization.id === 'org-2'
                    ? {
                          ...organization,
                          owned: false,
                          role: 'admin',
                          can_manage: false,
                      }
                    : organization,
            ),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        currentPage.url = '/organizations/org-1/settings';
        await nextTick();

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerPost).toHaveBeenCalledTimes(1);
        expect(routerPost).toHaveBeenCalledWith(
            '/owner/impersonation/org-2',
            {
                justification: '',
                role: 'admin',
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(routerVisit).not.toHaveBeenCalled();
    });

    it('falls back to the organization overview when the current route targets nested resources', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        currentPage.url = '/organizations/org-1/engagements/eng-1';
        await nextTick();

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(
            '/organizations/org-2',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('preserves static multi-segment routes like the engagement wizard', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        currentPage.url = '/organizations/org-1/engagements/create';
        await nextTick();

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(routerVisit).toHaveBeenCalledTimes(1);
        expect(routerVisit).toHaveBeenCalledWith(
            '/organizations/org-2/engagements/create',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('updates active organization locally for staff selections', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'org-1',
        });

        await wrapper.vm.handleOrganizationSelect('org-2');

        expect(wrapper.vm.activeOrganization?.id).toBe('org-2');
        expect(wrapper.vm.impersonationTargetOrganizationId).toBe('org-2');

        currentPage.props.activeOrganizationId = 'org-1';
        await nextTick();
        expect(wrapper.vm.activeOrganization?.id).toBe('org-2');

        currentPage.props.activeOrganizationId = 'org-2';
        await nextTick();

        expect(wrapper.vm.activeOrganization?.id).toBe('org-2');
    });

    it('releases consultant workspace lock after navigating away', async () => {
        const wrapper = mountSidebar({
            organizations: buildOrganizations(),
            impersonation: null,
            activeOrganizationId: 'staff-1',
        });

        currentPage.url = '/organizations/staff-1';
        await nextTick();
        expect(wrapper.vm.showingStaffWorkspace).toBe(true);

        currentPage.url = '/owner/console';
        await nextTick();

        expect(wrapper.vm.showingStaffWorkspace).toBe(false);
        expect(wrapper.vm.activeOrganization?.id).toBe('staff-1');
        expect(wrapper.vm.impersonationTargetOrganizationId).toBe('staff-1');
    });

    it('shows owner-only main navigation when internal governance is allowed', () => {
        const wrapper = mountSidebar();

        expect(
            wrapper.vm.mainNavItems.map(
                (item: { title: string }) => item.title,
            ),
        ).toEqual([
            'Owner Console',
            'Consultant Workspace',
            'Metric Library',
            'Metric Templates',
            'Organizations',
        ]);
        expect(wrapper.text()).toContain('Membership Role');

        wrapper.unmount();
    });

    it('hides owner-only navigation while keeping impersonation controls for admins', () => {
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'admin',
                    can_govern_internal: false,
                }),
            },
        });

        expect(
            wrapper.vm.mainNavItems.map(
                (item: { title: string }) => item.title,
            ),
        ).toEqual([
            'Consultant Workspace',
            'Metric Library',
            'Metric Templates',
            'Organizations',
        ]);
        expect(wrapper.text()).not.toContain('Owner Console');
        expect(wrapper.text()).toContain('Membership Role');

        wrapper.unmount();
    });

    it('hides impersonation and broad consultant navigation for operators', () => {
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'operator',
                    can_operate_delivery: false,
                    can_impersonate: false,
                    can_govern_internal: false,
                }),
            },
            organizations: [],
            staffWorkspace: null,
            impersonation: null,
            activeOrganizationId: null,
        });

        expect(wrapper.vm.mainNavItems).toEqual([]);
        expect(wrapper.text()).not.toContain('Owner Console');
        expect(wrapper.text()).not.toContain('Membership Role');
        expect(wrapper.text()).toContain('No organizations available.');

        wrapper.unmount();
    });

    it('shows org-level items for assigned operators while hiding broad navigation', () => {
        const organizationId = 'org-assigned';
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'operator',
                    can_operate_delivery: false,
                    can_impersonate: false,
                    can_govern_internal: false,
                }),
            },
            activeOrganizationId: organizationId,
            organizations: [
                {
                    id: organizationId,
                    name: 'Assigned Org',
                    slug: 'assigned-org',
                    can_operate_delivery: true,
                } as Organization,
            ],
            impersonation: null,
        });

        // Main nav should be empty for operator (no broad delivery)
        expect(wrapper.vm.mainNavItems).toEqual([]);

        // Org nav should have Engagements, Org Metrics, and Handbook Exports
        const titles = wrapper.vm.organizationNavItems.map(
            (item: { title: string }) => item.title,
        );
        expect(titles).toContain('Engagements');
        expect(titles).toContain('Org Metrics');
        expect(titles).toContain('Handbook Exports');

        wrapper.unmount();
    });

    it('shows organization settings for staff admins without direct client membership', () => {
        const organizationId = 'org-visible';
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'admin',
                    can_govern_internal: false,
                }),
            },
            activeOrganizationId: organizationId,
            organizations: [
                {
                    id: 'impersonation-off',
                    name: 'Impersonation off',
                    slug: 'impersonation-off',
                    owned: false,
                    can_manage: false,
                } as Organization,
                {
                    id: organizationId,
                    name: 'Visible Client',
                    slug: 'visible-client',
                    owned: false,
                    can_manage: true,
                    can_operate_delivery: true,
                } as Organization,
            ],
            impersonation: null,
        });

        const titles = wrapper.vm.organizationNavItems.map(
            (item: { title: string }) => item.title,
        );

        expect(titles).toContain('Settings');

        wrapper.unmount();
    });

    it('hides delivery navigation for operators with only client membership but no assignment', () => {
        const organizationId = 'org-membership-only';
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'operator',
                    can_operate_delivery: false,
                    can_impersonate: false,
                    can_govern_internal: false,
                }),
            },
            activeOrganizationId: organizationId,
            organizations: [
                {
                    id: organizationId,
                    name: 'Member Org',
                    slug: 'member-org',
                    can_operate_delivery: false,
                } as Organization,
            ],
        });

        const titles = wrapper.vm.organizationNavItems.map(
            (item: { title: string }) => item.title,
        );

        expect(titles).toContain('Overview');
        expect(titles).toContain('Engagements');

        // SHOULD NOT CONTAIN delivery items
        expect(titles).not.toContain('Org Metrics');
        expect(titles).not.toContain('Handbook Exports');

        wrapper.unmount();
    });

    it('uses the selected organization delivery permission during optimistic switching', async () => {
        const assignedOrganizationId = 'org-assigned';
        const memberOrganizationId = 'org-membership-only';
        const wrapper = mountSidebar({
            auth: {
                user: buildAuthUser({
                    staff_role: 'operator',
                    can_operate_delivery: false,
                    can_impersonate: false,
                    can_govern_internal: false,
                }),
            },
            activeOrganizationId: assignedOrganizationId,
            organizations: [
                {
                    id: assignedOrganizationId,
                    name: 'Assigned Org',
                    slug: 'assigned-org',
                    can_operate_delivery: true,
                } as Organization,
                {
                    id: memberOrganizationId,
                    name: 'Member Org',
                    slug: 'member-org',
                    can_operate_delivery: false,
                } as Organization,
            ],
        });

        await (
            wrapper.vm as unknown as {
                handleOrganizationSelect: (
                    organizationId: string,
                ) => Promise<void>;
            }
        ).handleOrganizationSelect(memberOrganizationId);
        await nextTick();

        const titles = wrapper.vm.organizationNavItems.map(
            (item: { title: string }) => item.title,
        );
        expect(titles).toContain('Overview');
        expect(titles).toContain('Engagements');
        expect(titles).not.toContain('Org Metrics');
        expect(titles).not.toContain('Handbook Exports');

        wrapper.unmount();
    });

    it('hides consultant navigation entirely for non-staff users', () => {
        const wrapper = mountSidebar({
            auth: {
                user: {
                    id: 'user-client',
                    name: 'Client User',
                    email: 'client@example.test',
                    is_internal_staff: false,
                },
            },
        });

        expect(wrapper.vm.mainNavItems).toEqual([]);
        expect(wrapper.vm.organizationNavItems).toEqual([]);

        wrapper.unmount();
    });
});
