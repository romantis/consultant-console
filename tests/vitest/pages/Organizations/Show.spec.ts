import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import OrganizationShow from '@/pages/Organizations/Show.vue';

const page = {
    props: {
        auth: {
            user: {
                can_govern_internal: true,
            },
        },
    },
};

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@inertiajs/vue3', async () => {
    const actual =
        await vi.importActual<typeof import('@inertiajs/vue3')>(
            '@inertiajs/vue3',
        );

    const Link = defineComponent({
        name: 'InertiaLinkStub',
        props: {
            href: {
                type: [String, Object],
                default: '',
            },
        },
        setup(props, { slots }) {
            const href = typeof props.href === 'string' ? props.href : '';

            return () =>
                h(
                    'a',
                    { href, 'data-href': href },
                    slots.default ? slots.default() : [],
                );
        },
    });

    const form = {
        user_id: '',
        processing: false,
        errors: {} as Record<string, string>,
        post: vi.fn(),
        reset: vi.fn(),
    };

    return {
        ...actual,
        Head: basicWrapper('div'),
        Link,
        useForm: () => form,
        usePage: () => page,
        router: {
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            patch: vi.fn(),
            delete: vi.fn(),
            reload: vi.fn(),
            visit: vi.fn(),
            remember: vi.fn(),
        },
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: basicWrapper('div'),
}));

vi.mock('@/components/ui/avatar', () => ({
    Avatar: basicWrapper('div'),
    AvatarFallback: basicWrapper('div'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: basicWrapper('span'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: basicWrapper('button'),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardContent: basicWrapper('div'),
    CardDescription: basicWrapper('p'),
    CardFooter: basicWrapper('footer'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
}));

vi.mock('@/components/ui/dropdown-menu', () => {
    const Wrapper = basicWrapper('div');
    return {
        DropdownMenu: Wrapper,
        DropdownMenuContent: Wrapper,
        DropdownMenuItem: Wrapper,
        DropdownMenuLabel: Wrapper,
        DropdownMenuSeparator: Wrapper,
        DropdownMenuTrigger: Wrapper,
    };
});

vi.mock('@/composables/useEngagementNavigation', () => ({
    useEngagementNavigation: () => ({
        engagementOptions: { value: [] },
        buildMetricsUrlFor: vi.fn(),
        setSelectedEngagement: vi.fn(),
    }),
}));

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        owner: {
            console: () => ({ url: '/owner/console' }),
        },
    }),
}));

function baseProps() {
    return {
        organization: {
            id: 'org-1',
            name: 'Northwind Analytics',
            slug: 'northwind-analytics',
            is_internal: false,
        },
        templates: [],
        members: [],
        activity: [],
        insights: {
            engagements: 0,
            open_forms: 0,
            assignments: {
                open: 0,
                due_soon: 0,
                overdue: 0,
            },
            members: 0,
            templates: 0,
        },
        can_create: true,
        can_update: true,
        can_manage_operator_assignments: true,
        operator_assignments: [
            {
                id: 'assign-1',
                granted_at: '2026-05-07T10:00:00Z',
                reason: 'coverage',
                notes: null,
                user: {
                    id: 'user-1',
                    name: 'Taylor Operator',
                    email: 'taylor@example.com',
                },
                granted_by: {
                    id: 'user-admin',
                    name: 'Alex Admin',
                },
            },
        ],
        operator_candidates: [
            {
                id: 'user-2',
                name: 'Jordan Operator',
                email: 'jordan@example.com',
                affiliation: 'QA',
            },
        ],
    };
}

describe('Organizations/Show operator staffing', () => {
    beforeEach(() => {
        page.props.auth.user.can_govern_internal = true;
    });

    it('renders the staff operators section for delivery staff on client organizations', () => {
        const wrapper = mount(OrganizationShow, {
            props: baseProps(),
        });

        expect(wrapper.text()).toContain('Settings');
        expect(wrapper.text()).toContain('Staff Operators');
        expect(wrapper.text()).toContain('Assign operator');
        expect(wrapper.text()).toContain('Taylor Operator');
        expect(wrapper.text()).toContain('Jordan Operator');
        expect(wrapper.text()).toContain('Revoke');

        wrapper.unmount();
    });

    it('hides the staff operators section when the viewer cannot manage assignments', () => {
        const wrapper = mount(OrganizationShow, {
            props: {
                ...baseProps(),
                can_manage_operator_assignments: false,
                operator_assignments: [],
                operator_candidates: [],
            },
        });

        expect(wrapper.text()).not.toContain('Staff Operators');
        expect(wrapper.text()).not.toContain('Assign operator');
        expect(wrapper.text()).not.toContain('Revoke');

        wrapper.unmount();
    });

    it('hides internal governance actions for non-owner staff on the staff workspace', () => {
        page.props.auth.user.can_govern_internal = false;

        const wrapper = mount(OrganizationShow, {
            props: {
                ...baseProps(),
                organization: {
                    ...baseProps().organization,
                    is_internal: true,
                },
                can_update: false,
                can_create: false,
                can_manage_operator_assignments: false,
                operator_assignments: [],
                operator_candidates: [],
            },
        });

        expect(wrapper.text()).not.toContain('Settings');
        expect(wrapper.text()).not.toContain('Manage members');
        expect(wrapper.text()).not.toContain('Owner Console');
        expect(wrapper.text()).not.toContain('Staff Operators');

        wrapper.unmount();
    });

    it('shows internal governance actions for staff owners on the staff workspace', () => {
        const wrapper = mount(OrganizationShow, {
            props: {
                ...baseProps(),
                organization: {
                    ...baseProps().organization,
                    is_internal: true,
                },
                can_manage_operator_assignments: false,
                operator_assignments: [],
                operator_candidates: [],
            },
        });

        expect(wrapper.text()).toContain('Settings');
        expect(wrapper.text()).toContain('Manage members');
        expect(wrapper.text()).toContain('Owner Console');
        expect(wrapper.text()).not.toContain('Staff Operators');

        wrapper.unmount();
    });
});
