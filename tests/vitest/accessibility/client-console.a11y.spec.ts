import { mount } from '@vue/test-utils';
import { axe } from 'vitest-axe';
import { computed, defineComponent, h, ref, type Component } from 'vue';

import ClientSidebar from '@/components/client/ClientSidebar.vue';
import { SidebarProvider } from '@/components/ui/sidebar';

type WorkspaceLinks = Record<
    'metrics' | 'handbook' | 'support',
    string | null
>;

type ClientNavContext = {
    navigation?: Record<string, unknown> | null;
    organization?: { id: string; name: string } | null;
    membership?: { id: string; role?: string | null } | null;
    engagements?: Array<{
        id: string;
        title: string;
        status?: string | null;
        href?: string | null;
    }>;
    activeEngagementId?: string | null;
    workspaceLinks?: Partial<WorkspaceLinks>;
    handbookMetrics?: Array<{
        id: string;
        name: string;
        href?: string | null;
    }>;
};

const routerVisit = vi.fn();

vi.mock('@inertiajs/vue3', () => {
    const LinkStub = defineComponent({
        name: 'InertiaLinkStub',
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
                        'data-link-stub': 'inertia-link',
                    },
                    slots.default ? slots.default() : [],
                );
        },
    });

    return {
        Link: LinkStub,
        usePage: () => ({
            url: '/',
            props: {
                auth: {
                    user: {
                        id: 'user-1',
                        name: 'Test Member',
                        email: 'member@example.com',
                        is_staff: false,
                    },
                },
                abilities: [],
            },
        }),
        router: {
            visit: (...args: unknown[]) => routerVisit(...args),
        },
    };
});

const defaultWorkspaceLinks: WorkspaceLinks = {
    metrics: null,
    handbook: '/',
    support: null,
};

const navState = {
    navigation: ref<Record<string, unknown> | null>(null),
    organization: ref<{ id: string; name: string } | null>(null),
    membership: ref<{ id: string; role?: string | null } | null>(null),
    engagements: ref<ClientNavContext['engagements']>([]),
    activeEngagementId: ref<string | null>(null),
    workspaceLinks: ref<WorkspaceLinks>({ ...defaultWorkspaceLinks }),
    handbookMetrics: ref<ClientNavContext['handbookMetrics']>([]),
};

const resetClientNavContext = () => {
    navState.navigation.value = null;
    navState.organization.value = null;
    navState.membership.value = null;
    navState.engagements.value = [];
    navState.activeEngagementId.value = null;
    navState.workspaceLinks.value = { ...defaultWorkspaceLinks };
    navState.handbookMetrics.value = [];
};

const setClientNavContext = (context: ClientNavContext = {}) => {
    if ('navigation' in context) {
        navState.navigation.value = context.navigation ?? null;
    }

    if ('organization' in context) {
        navState.organization.value = context.organization ?? null;
    }

    if ('membership' in context) {
        navState.membership.value = context.membership ?? null;
    }

    if ('engagements' in context) {
        navState.engagements.value = context.engagements?.map((engagement) => ({
            id: engagement.id,
            title: engagement.title,
            status: engagement.status ?? null,
            href: engagement.href ?? null,
        })) ?? [];
    }

    if ('activeEngagementId' in context) {
        navState.activeEngagementId.value = context.activeEngagementId ?? null;
    }

    if ('workspaceLinks' in context) {
        navState.workspaceLinks.value = {
            ...defaultWorkspaceLinks,
            ...context.workspaceLinks,
        };
    }

    if ('handbookMetrics' in context) {
        navState.handbookMetrics.value = context.handbookMetrics ?? [];
    }
};

vi.mock('@/composables/useClientNav', () => ({
    useClientNav: () => ({
        navigation: computed(() => navState.navigation.value),
        organization: computed(() => navState.organization.value),
        membership: computed(() => navState.membership.value),
        engagements: computed(() => navState.engagements.value ?? []),
        activeEngagementId: computed(() => navState.activeEngagementId.value),
        workspaceLinks: computed(() => navState.workspaceLinks.value),
        handbookMetrics: computed(() => navState.handbookMetrics.value ?? []),
    }),
}));

const axeConfig = {
    rules: {
        'color-contrast': { enabled: false },
    },
};

const withSidebarProvider = (component: Component) =>
    defineComponent({
        name: 'SidebarProviderWrapper',
        setup() {
            return () =>
                h(
                    SidebarProvider,
                    { class: 'min-h-screen' },
                    {
                        default: () => h(component),
                    },
                );
        },
    });

const mountInsideMain = (component: Component) =>
    mount(
        defineComponent({
            name: 'ClientConsoleAxeWrapper',
            setup() {
                return () => h('main', { role: 'main' }, [h(component)]);
            },
        }),
        {
            global: {
                stubs: {
                    teleport: true,
                },
            },
        },
    );

describe('Client console accessibility', () => {
    beforeEach(() => {
        resetClientNavContext();
        routerVisit.mockClear();
    });

    it('keeps ClientSidebar accessible when workspace links + engagements are populated', async () => {
        setClientNavContext({
            navigation: { id: 'nav-context' },
            organization: { id: 'org-1', name: 'Helios Robotics' },
            membership: { id: 'mem-1', role: 'member' },
            activeEngagementId: 'eng-1',
            engagements: [
                {
                    id: 'eng-1',
                    title: 'Helios Pilot',
                    status: 'AwaitingForms',
                    href: '/organizations/org-1/engagements/eng-1',
                },
                {
                    id: 'eng-2',
                    title: 'Northwind Metrics',
                    status: 'Planned',
                    href: '/organizations/org-1/engagements/eng-2',
                },
            ],
            workspaceLinks: {
                handbook: '/',
                support: 'mailto:support@metricharmony.test',
            },
            handbookMetrics: [
                {
                    id: 'metric-1',
                    name: 'Monthly Recurring Revenue',
                    href: '/client/organizations/org-1/engagements/eng-1/metrics/metric-1',
                },
            ],
        });

        const wrapper = mountInsideMain(withSidebarProvider(ClientSidebar));
        const results = await axe(wrapper.element, axeConfig);

        expect(results).toHaveNoViolations();
        wrapper.unmount();
    });
});
