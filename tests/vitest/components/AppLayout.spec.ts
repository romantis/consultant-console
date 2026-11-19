import type { AppPageProps } from '@/types';
import { mount } from '@vue/test-utils';
import { defineComponent, h, reactive } from 'vue';

let currentPage: { props: AppPageProps };

function buildAuthUser(
    overrides: Partial<NonNullable<AppPageProps['auth']['user']>> = {},
) {
    return {
        id: 'user-1',
        name: 'Consultant',
        email: 'consultant@example.test',
        is_staff: false,
        is_internal_staff: false,
        staff_role: null,
        can_operate_delivery: false,
        has_operator_assignments: false,
        can_impersonate: false,
        can_govern_internal: false,
        ...overrides,
    };
}

const defaultProps = (): AppPageProps => ({
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
    flags: {
        allow_public_registration: false,
    },
    sidebarOpen: true,
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => currentPage,
}));

vi.mock('@/layouts/ConsultantLayout.vue', () => ({
    default: defineComponent({
        name: 'ConsultantLayoutStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'section',
                    { 'data-test': 'consultant-layout' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/layouts/ClientLayout.vue', () => ({
    default: defineComponent({
        name: 'ClientLayoutStub',
        props: {
            title: {
                type: String,
                default: undefined,
            },
            description: {
                type: String,
                default: undefined,
            },
        },
        setup(_, { slots }) {
            return () =>
                h(
                    'section',
                    { 'data-test': 'client-layout' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

let AppLayout: ReturnType<typeof defineComponent>;

beforeAll(async () => {
    vi.resetModules();
    ({ default: AppLayout } = await import('@/layouts/AppLayout.vue'));
});

function mountLayout(props: Partial<AppPageProps> = {}) {
    currentPage = reactive({
        props: {
            ...defaultProps(),
            ...props,
        },
    });

    return mount(AppLayout, {
        props: {
            title: 'Page title',
            description: 'Page description',
        },
        slots: {
            default: () => 'Layout content',
        },
    });
}

describe('AppLayout', () => {
    it('uses the consultant layout for internal staff operators even when is_staff is false', () => {
        const wrapper = mountLayout({
            auth: {
                user: buildAuthUser({
                    is_internal_staff: true,
                    staff_role: 'operator',
                }),
            },
        });

        expect(wrapper.find('[data-test="consultant-layout"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="client-layout"]').exists()).toBe(false);
    });

    it('uses the client layout for non-staff users without impersonation', () => {
        const wrapper = mountLayout();

        expect(wrapper.find('[data-test="client-layout"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="consultant-layout"]').exists()).toBe(
            false,
        );
    });
});
