import type { AppPageProps, NavItem, Organization } from '@/types';
import { mount } from '@vue/test-utils';
import { computed, defineComponent, h, reactive, ref } from 'vue';

type TestPageProps = AppPageProps & {
    organizations: Organization[];
};

const navMainTitles: string[] = [];

function buildAuthUser(
    overrides: Partial<NonNullable<AppPageProps['auth']['user']>> = {},
) {
    return {
        id: '1',
        name: 'Test User',
        email: 'test@example.com',
        is_internal_staff: false,
        staff_role: null,
        can_operate_delivery: false,
        has_operator_assignments: false,
        can_impersonate: false,
        can_govern_internal: false,
        ...overrides,
    };
}

const page = reactive({
    url: '/dashboard',
    props: {
        name: 'Console',
        quote: { message: '', author: '' },
        auth: {
            user: buildAuthUser(),
        },
        flash: {
            success: null,
            error: null,
        },
        flags: {
            allow_public_registration: false,
        },
        sidebarOpen: true,
        organizations: [] as Organization[],
        activeOrganizationId: null,
        engagements: [],
        activeEngagementId: null,
        staffWorkspace: null,
        impersonation: null,
        ownerOrganizations: null,
    } satisfies TestPageProps,
});

const routerVisit = vi.hoisted(() => vi.fn());
const routerDelete = vi.hoisted(() => vi.fn());
const routerPost = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => {
    const Link = defineComponent({
        name: 'InertiaLink',
        props: {
            href: { type: [String, Object], required: true },
        },
        setup(props, { slots }) {
            const resolveHref = () =>
                typeof props.href === 'string'
                    ? props.href
                    : ((props.href as { url?: string } | undefined)?.url ?? '');

            return () =>
                h(
                    'a',
                    {
                        'data-href': resolveHref(),
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        Link,
        router: {
            visit: routerVisit,
            delete: routerDelete,
            post: routerPost,
        },
        usePage: () => page,
        useRemember: <T>(initial: T) => ref(initial),
    };
});

vi.mock('@/components/NavFooter.vue', () => ({
    default: defineComponent({
        name: 'NavFooterStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'nav-footer-stub' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/NavUser.vue', () => ({
    default: defineComponent({
        name: 'NavUserStub',
        setup() {
            return () => h('div', { 'data-test': 'nav-user-stub' });
        },
    }),
}));

vi.mock('@/components/NavMain.vue', () => ({
    default: defineComponent({
        name: 'NavMainStub',
        props: {
            items: {
                type: Array as () => NavItem[],
                required: true,
            },
        },
        setup(props) {
            return () => {
                const titles = (props.items as NavItem[]).map(
                    (item) => item.title,
                );
                navMainTitles.splice(0, navMainTitles.length, ...titles);

                return h(
                    'div',
                    { 'data-test': 'nav-main-stub' },
                    titles.join('|'),
                );
            };
        },
    }),
}));

const makeStub = vi.hoisted(
    () => (name: string) =>
        defineComponent({
            name,
            props: {
                class: {
                    type: [String, Object, Array],
                    default: undefined,
                },
                asChild: {
                    type: Boolean,
                    default: false,
                },
                disabled: {
                    type: Boolean,
                    default: false,
                },
                value: {
                    type: [String, Number],
                    default: undefined,
                },
                modelValue: {
                    type: [String, Number, null],
                    default: undefined,
                },
            },
            emits: ['update:modelValue'],
            setup(_, { slots }) {
                return () =>
                    h(
                        'div',
                        { 'data-stub': name },
                        slots.default ? slots.default() : [],
                    );
            },
        }),
);

vi.mock('@/components/ui/sidebar', () => ({
    Sidebar: makeStub('Sidebar'),
    SidebarContent: makeStub('SidebarContent'),
    SidebarFooter: makeStub('SidebarFooter'),
    SidebarGroup: makeStub('SidebarGroup'),
    SidebarGroupLabel: makeStub('SidebarGroupLabel'),
    SidebarHeader: makeStub('SidebarHeader'),
    SidebarMenu: makeStub('SidebarMenu'),
    SidebarMenuButton: makeStub('SidebarMenuButton'),
    SidebarMenuItem: makeStub('SidebarMenuItem'),
    SidebarSeparator: makeStub('SidebarSeparator'),
    useSidebar: () => ({
        isMobile: false,
        state: 'expanded',
        toggleSidebar: vi.fn(),
        openMobile: false,
        setOpenMobile: vi.fn(),
    }),
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: makeStub('DropdownMenu'),
    DropdownMenuTrigger: makeStub('DropdownMenuTrigger'),
    DropdownMenuContent: makeStub('DropdownMenuContent'),
    DropdownMenuRadioGroup: makeStub('DropdownMenuRadioGroup'),
    DropdownMenuRadioItem: makeStub('DropdownMenuRadioItem'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: makeStub('Badge'),
}));

vi.mock('@/components/ui/alert', () => ({
    Alert: makeStub('Alert'),
    AlertTitle: makeStub('AlertTitle'),
    AlertDescription: makeStub('AlertDescription'),
}));

vi.mock('@/components/AppLogo.vue', () => ({
    default: defineComponent({
        name: 'AppLogoStub',
        setup() {
            return () => h('div', { 'data-test': 'app-logo-stub' }, 'Logo');
        },
    }),
}));

vi.mock('@/lib/organizations', () => ({
    persistActiveOrganizationSelection: vi.fn(),
}));

const engagementOptionsRef = ref(
    [] as { id: string; title: string; status?: string }[],
);
const activeEngagementRef = ref(null);

vi.mock('@/composables/useEngagementNavigation', () => ({
    useEngagementNavigation: () => ({
        engagementOptions: computed(() => engagementOptionsRef.value),
        activeEngagement: computed(() => activeEngagementRef.value),
        formsHref: computed(() => null),
        metricsHref: computed(() => null),
        selectedEngagementId: ref(null),
        setSelectedEngagement: vi.fn(),
        buildEngagementUrlFor: vi.fn(() => null),
        buildMetricsUrlFor: vi.fn(() => null),
        rememberSelection: vi.fn(),
    }),
}));

vi.mock('lucide-vue-next', () => {
    const iconStub = (name: string) =>
        defineComponent({
            name,
            setup() {
                return () => h('svg', { 'data-icon': name });
            },
        });

    return {
        Activity: iconStub('Activity'),
        AlertCircle: iconStub('AlertCircle'),
        BarChart3: iconStub('BarChart3'),
        Briefcase: iconStub('Briefcase'),
        Building2: iconStub('Building2'),
        Check: iconStub('Check'),
        ChevronDown: iconStub('ChevronDown'),
        ChevronUp: iconStub('ChevronUp'),
        CheckCircle: iconStub('CheckCircle'),
        ChevronsUpDown: iconStub('ChevronsUpDown'),
        ClipboardList: iconStub('ClipboardList'),
        BookOpen: iconStub('BookOpen'),
        Crown: iconStub('Crown'),
        FileText: iconStub('FileText'),
        LayoutDashboard: iconStub('LayoutDashboard'),
        Settings: iconStub('Settings'),
    };
});

vi.mock('@/composables/useWayfinder', () => {
    const makeRoute = (
        builder: string | ((...args: any[]) => string),
        method = 'get',
        canGetter?: () => boolean,
    ) => {
        const resolver = ((...args: any[]) => {
            const url =
                typeof builder === 'function' ? builder(...args) : builder;
            return { url, method };
        }) as unknown as { (...args: any[]): { url: string; method: string } };

        Object.defineProperty(resolver, 'can', {
            get: () => (canGetter ? canGetter() : true),
        });

        return resolver;
    };

    const staffAbility = () =>
        Boolean(page.props.auth?.user?.can_govern_internal);

    return {
        useWayfinder: () => ({
            can: () => true,
            dashboard: makeRoute('/dashboard'),
            owner: {
                console: makeRoute('/owner/console', 'get', staffAbility),
                impersonation: {
                    start: makeRoute(
                        (organizationId: string | number) =>
                            `/owner/impersonation/${organizationId}`,
                        'post',
                        staffAbility,
                    ),
                    stop: makeRoute(
                        '/owner/impersonation',
                        'delete',
                        staffAbility,
                    ),
                },
            },
            metricTemplates: {
                index: makeRoute('/metric-templates'),
            },
            metricLibrary: {
                index: makeRoute('/metric-library'),
            },
            org: {
                index: makeRoute('/organizations'),
                show: makeRoute(
                    (id: string | number) => `/organizations/${id}`,
                ),
                engagements: {
                    index: makeRoute(
                        (orgId: string | number) =>
                            `/organizations/${orgId}/engagements`,
                    ),
                },
                orgMetrics: {
                    index: makeRoute(
                        (orgId: string | number) =>
                            `/organizations/${orgId}/org-metrics`,
                    ),
                },
                handbookExports: {
                    index: makeRoute(
                        (orgId: string | number) =>
                            `/organizations/${orgId}/handbook-exports`,
                    ),
                },
                settings: {
                    edit: makeRoute(
                        (orgId: string | number) =>
                            `/organizations/${orgId}/settings`,
                    ),
                },
            },
        }),
    };
});

let AppSidebar: ReturnType<typeof defineComponent>;

beforeAll(async () => {
    vi.resetModules();
    ({ default: AppSidebar } = await import('@/components/AppSidebar.vue'));
}, 30000);

function resetPageProps() {
    navMainTitles.splice(0, navMainTitles.length);
    page.url = '/dashboard';
    page.props.auth.user = buildAuthUser();
    page.props.organizations = [];
    page.props.activeOrganizationId = null;
    page.props.staffWorkspace = null;
    page.props.impersonation = null;
    engagementOptionsRef.value = [];
    activeEngagementRef.value = null;
    routerVisit.mockClear();
    routerDelete.mockClear();
    routerPost.mockClear();
}

function mountSidebar(overrides: Partial<TestPageProps> = {}) {
    if (overrides.auth) {
        page.props.auth = {
            ...page.props.auth,
            ...overrides.auth,
        };
        delete overrides.auth;
    }
    Object.assign(page.props, overrides);
    return mount(AppSidebar);
}

describe('AppSidebar', () => {
    beforeEach(() => {
        resetPageProps();
    });

    it('does not render main navigation for non-staff users', () => {
        const wrapper = mountSidebar();

        expect(navMainTitles).toEqual([]);
        expect(wrapper.find('[data-test="nav-main-stub"]').exists()).toBe(
            false,
        );

        wrapper.unmount();
    });

    it('renders staff platform navigation items', () => {
        page.props.auth.user = buildAuthUser({
            is_internal_staff: true,
            staff_role: 'owner',
            can_operate_delivery: true,
            can_impersonate: true,
            can_govern_internal: true,
        });
        page.props.staffWorkspace = {
            id: '99',
            name: 'Consultant Workspace',
            slug: 'consultant-workspace',
        } as unknown as Organization;

        const wrapper = mountSidebar();

        const titles = navMainTitles.slice();
        expect(titles).toEqual([
            'Owner Console',
            'Consultant Workspace',
            'Metric Library',
            'Metric Templates',
            'Organizations',
        ]);

        wrapper.unmount();
    });

    it('includes organization settings for managers', () => {
        page.props.auth.user = buildAuthUser({ is_internal_staff: true });
        page.props.organizations = [
            {
                id: '10',
                name: 'Acme Org',
                slug: 'acme-org',
                owned: true,
                can_manage: true,
            },
        ];
        page.props.activeOrganizationId = '10';

        const wrapper = mountSidebar();

        const organizationSection = wrapper
            .findAll('[data-stub="SidebarGroup"]')
            .find((group) => group.text().includes('Organization'));

        // Staff users don't see 'Organizations' in the Org Nav (it is in the main nav)
        expect(organizationSection?.text()).not.toContain('Organizations');
        expect(organizationSection?.text()).toContain('Settings');
        expect(organizationSection?.text()).toContain('Overview');
        expect(organizationSection?.text()).toContain('Engagements');

        wrapper.unmount();
    });

    it('hides organization settings for members without management rights', () => {
        page.props.auth.user = buildAuthUser({ is_internal_staff: true });
        page.props.organizations = [
            {
                id: '20',
                name: 'Beta Org',
                slug: 'beta-org',
                owned: true,
                can_manage: false,
            },
        ];
        page.props.activeOrganizationId = '20';

        const wrapper = mountSidebar();

        const organizationSection = wrapper
            .findAll('[data-stub="SidebarGroup"]')
            .find((group) => group.text().includes('Organization'));

        expect(organizationSection?.text()).not.toContain('Settings');
        expect(organizationSection?.text()).toContain('Overview');
        expect(organizationSection?.text()).toContain('Engagements');

        wrapper.unmount();
    });

    it('shows org-level items for assigned operators while hiding broad navigation', () => {
        const organizationId = 'org-assigned';
        page.props.auth.user = buildAuthUser({
            is_internal_staff: true,
            staff_role: 'operator',
            can_operate_delivery: false,
            can_impersonate: false,
            can_govern_internal: false,
        });
        page.props.activeOrganizationId = organizationId;
        page.props.organizations = [
            {
                id: organizationId,
                name: 'Assigned Org',
                slug: 'assigned-org',
                can_operate_delivery: true,
            } as Organization,
        ];

        const wrapper = mountSidebar();

        // Main nav should be empty for operator (no broad delivery)
        expect(navMainTitles).toEqual([]);

        const organizationSection = wrapper
            .findAll('[data-stub="SidebarGroup"]')
            .find((group) => group.text().includes('Organization'));

        // Org nav should have Engagements, Org Metrics, and Handbook Exports
        expect(organizationSection?.text()).toContain('Engagements');
        expect(organizationSection?.text()).toContain('Org Metrics');
        expect(organizationSection?.text()).toContain('Handbook Exports');

        wrapper.unmount();
    });

    it('hides delivery navigation for operators with only client membership but no assignment', () => {
        const organizationId = 'org-membership-only';
        page.props.auth.user = buildAuthUser({
            is_internal_staff: true,
            staff_role: 'operator',
            can_operate_delivery: false,
            can_impersonate: false,
            can_govern_internal: false,
        });
        page.props.activeOrganizationId = organizationId;
        page.props.organizations = [
            {
                id: organizationId,
                name: 'Member Org',
                slug: 'member-org',
                can_operate_delivery: false,
            } as Organization,
        ];

        const wrapper = mountSidebar();

        const organizationSection = wrapper
            .findAll('[data-stub="SidebarGroup"]')
            .find((group) => group.text().includes('Organization'));

        expect(organizationSection?.text()).toContain('Overview');
        expect(organizationSection?.text()).toContain('Engagements');

        // SHOULD NOT CONTAIN delivery items
        expect(organizationSection?.text()).not.toContain('Org Metrics');
        expect(organizationSection?.text()).not.toContain('Handbook Exports');

        wrapper.unmount();
    });

    it('does not render legacy organization selector references', () => {
        const wrapper = mountSidebar();

        expect(wrapper.html()).not.toContain('organization-selector');

        wrapper.unmount();
    });
});
