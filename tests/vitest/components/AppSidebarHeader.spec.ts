import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick, ref } from 'vue';

let AppSidebarHeader: ReturnType<typeof defineComponent>;

beforeAll(async () => {
    vi.resetModules();
    ({ default: AppSidebarHeader } = await import('@/components/AppSidebarHeader.vue'));
});

const routerPost = vi.hoisted(() => vi.fn());
const routerReload = vi.hoisted(() => vi.fn());
const pageProps = vi.hoisted(() => ({
    value: {
        handbookExports: [],
    } as Record<string, unknown>,
}));
const organizationRef = vi.hoisted(() => ({
    value: {
        id: 'org-1',
        name: 'Acme Corp',
    } as { id: string; name: string } | null,
}));

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>(
        '@inertiajs/vue3',
    );

    return {
        ...actual,
        router: {
            get: vi.fn(),
            post: routerPost,
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            visit: vi.fn(),
            reload: routerReload,
            remember: vi.fn(),
        },
        usePage: () => ({ props: pageProps.value }),
    };
});

const basicWrapper = vi.hoisted(
    () => (tag: string) =>
        defineComponent({
            name: `${tag}-stub`,
            inheritAttrs: false,
            setup(_, { slots, attrs }) {
                return () =>
                    h(tag, attrs, slots.default ? slots.default() : []);
            },
        }),
);

vi.mock('lucide-vue-next', () => {
    const IconStub = basicWrapper('svg');

    return {
        Check: IconStub,
        ChevronDown: IconStub,
        ChevronUp: IconStub,
        Download: IconStub,
        Loader2: IconStub,
        Monitor: IconStub,
        Moon: IconStub,
        Sun: IconStub,
    };
});

vi.mock('@/components/Breadcrumbs.vue', () => ({
    default: basicWrapper('nav'),
}));

vi.mock('@/components/ui/sidebar', () => ({
    SidebarTrigger: basicWrapper('button'),
}));

vi.mock('@/components/ui/button/Button.vue', () => ({
    default: defineComponent({
        name: 'ButtonStub',
        inheritAttrs: false,
        props: {
            type: {
                type: String,
                default: 'button',
            },
            variant: {
                type: String,
                default: 'default',
            },
            size: {
                type: String,
                default: 'md',
            },
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'button',
                    {
                        type: props.type,
                        disabled: props.disabled,
                        'data-variant': props.variant,
                        'data-size': props.size,
                        ...attrs,
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/ui/dropdown-menu', () => ({
    DropdownMenu: basicWrapper('div'),
    DropdownMenuContent: basicWrapper('div'),
    DropdownMenuItem: basicWrapper('div'),
    DropdownMenuLabel: basicWrapper('div'),
    DropdownMenuRadioGroup: basicWrapper('div'),
    DropdownMenuRadioItem: basicWrapper('div'),
    DropdownMenuSeparator: basicWrapper('div'),
    DropdownMenuTrigger: basicWrapper('div'),
}));

vi.mock('@/composables/useAppearance', () => ({
    useAppearance: () => ({
        appearance: ref('system'),
        updateAppearance: vi.fn(),
    }),
}));

vi.mock('@/composables/useClientNav', () => ({
    useClientNav: () => ({
        organization: organizationRef,
    }),
}));

vi.mock('@/composables/useToast', () => ({
    useToast: () => ({
        toast: vi.fn(),
    }),
}));

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        org: {
            handbookExports: {
                download: (
                    args:
                        | { organization: string; handbookExport: string }
                        | [string, string],
                ) => {
                    const payload = Array.isArray(args)
                        ? { organization: args[0], handbookExport: args[1] }
                        : args;

                    return {
                        url: `/organizations/${payload.organization}/handbook-exports/${payload.handbookExport}/download`,
                    };
                },
            },
        },
    }),
}));

describe('AppSidebarHeader', () => {
    beforeEach(() => {
        routerPost.mockReset();
        routerReload.mockReset();
        organizationRef.value = {
            id: 'org-1',
            name: 'Acme Corp',
        };
        pageProps.value = {
            handbookExports: [],
        };
    });

    it('renders download history items for ready exports', async () => {
        pageProps.value = {
            handbookExports: [
                {
                    id: 'export-2',
                    status: 'ready',
                    is_ready: true,
                    version_label: 'v2025-01-08',
                    completed_at: '2025-01-08T09:15:00Z',
                },
                {
                    id: 'export-1',
                    status: 'ready',
                    is_ready: true,
                    version_label: 'v2025-01-07',
                    completed_at: '2025-01-07T09:15:00Z',
                },
            ],
        };

        const wrapper = mount(AppSidebarHeader);

        await nextTick();

        expect(wrapper.text()).toContain('Download Handbook');
        expect(wrapper.text()).toContain('v2025-01-07');
        expect(
            wrapper
                .find(
                    'a[href="/organizations/org-1/handbook-exports/export-1/download"]',
                )
                .exists(),
        ).toBe(true);

        wrapper.unmount();
    });

    it('hides download history when only the latest export is ready', async () => {
        pageProps.value = {
            handbookExports: [
                {
                    id: 'export-1',
                    status: 'ready',
                    is_ready: true,
                    version_label: 'v2025-01-07',
                    completed_at: '2025-01-07T09:15:00Z',
                },
            ],
        };

        const wrapper = mount(AppSidebarHeader);

        await nextTick();

        expect(
            wrapper
                .find(
                    'a[href="/organizations/org-1/handbook-exports/export-1/download"]',
                )
                .exists(),
        ).toBe(false);

        wrapper.unmount();
    });

    it('queues a handbook export when none are ready', async () => {
        const wrapper = mount(AppSidebarHeader);

        await nextTick();

        const exportButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Download Handbook'));

        expect(exportButton).toBeTruthy();
        await exportButton?.trigger('click');

        const call = routerPost.mock.calls[0];
        expect(call?.[0]).toContain('/client/organizations/org-1/handbook/exports');

        wrapper.unmount();
    });
});
