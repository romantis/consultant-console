import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import OrganizationSettings from '@/pages/Organizations/Settings.vue';

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

    return {
        ...actual,
        Link,
        useForm: () => ({
            name: 'Northwind Analytics',
            settings_json: {
                locale: 'en',
                currency: 'USD',
                timezone: 'UTC',
            },
            processing: false,
            errors: {} as Record<string, string>,
            transform: vi.fn(),
            put: vi.fn(),
        }),
        usePage: () => ({
            props: {
                flash: {},
                auth: {
                    user: {
                        can_impersonate: true,
                    },
                },
            },
        }),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: basicWrapper('div'),
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

vi.mock('@/components/ui/input', () => ({
    Input: basicWrapper('input'),
}));

vi.mock('@/components/ui/label', () => ({
    Label: basicWrapper('label'),
}));

function baseProps() {
    return {
        organization: {
            id: 'org-1',
            name: 'Northwind Analytics',
            slug: 'northwind-analytics',
            settings_json: {
                locale: 'en',
                currency: 'USD',
                timezone: 'UTC',
            },
        },
        members: [],
        pending_members: [],
        roles: [
            { value: 'owner', label: 'Owner' },
            { value: 'admin', label: 'Admin' },
            { value: 'member', label: 'Member' },
            { value: 'viewer', label: 'Viewer' },
        ],
    };
}

describe('Organizations/Settings', () => {
    it('does not render the legacy add consultant surface', () => {
        const wrapper = mount(OrganizationSettings, {
            props: baseProps(),
        });

        expect(wrapper.text()).not.toContain('Add Consultant');
        expect(wrapper.text()).not.toContain(
            'Attach existing staff directly to this company without sending an invite.',
        );
        expect(wrapper.text()).not.toContain('Select consultant');

        wrapper.unmount();
    });
});
