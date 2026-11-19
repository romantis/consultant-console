import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import EngagementIndex from '@/pages/Engagements/Index.vue';

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

vi.mock('@inertiajs/vue3', () => {
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
        Head: basicWrapper('div'),
        Link,
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

vi.mock('@/composables/useWayfinder', () => ({
    useWayfinder: () => ({
        owner: {
            console: () => ({
                url: '/owner/console',
            }),
        },
    }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: basicWrapper('div'),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardContent: basicWrapper('div'),
}));

vi.mock('@/components/ui/table', () => ({
    Table: basicWrapper('table'),
    TableHeader: basicWrapper('thead'),
    TableBody: basicWrapper('tbody'),
    TableRow: basicWrapper('tr'),
    TableHead: basicWrapper('th'),
    TableCell: basicWrapper('td'),
}));

vi.mock('@/components/ui/badge', () => ({
    Badge: basicWrapper('span'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: basicWrapper('button'),
}));

vi.mock('@/components/ui/dropdown-menu', () => {
    const Wrapper = basicWrapper('div');
    return {
        DropdownMenu: Wrapper,
        DropdownMenuCheckboxItem: Wrapper,
        DropdownMenuContent: Wrapper,
        DropdownMenuLabel: Wrapper,
        DropdownMenuSeparator: Wrapper,
        DropdownMenuTrigger: Wrapper,
    };
});

vi.mock('@/components/ui/input', () => ({
    Input: basicWrapper('input'),
}));

function baseProps() {
    return {
        organization: {
            id: 'org-1',
            name: 'Northwind Analytics',
            is_internal: false,
            can_launch: true,
        },
        engagements: {
            data: [],
            links: [],
        },
        filters: {
            available: {
                statuses: [],
            },
            active: {
                status: null,
                starts_on: null,
                ends_on: null,
                search: null,
            },
        },
    };
}

describe('Engagements/Index launch visibility', () => {
    it('shows create engagement actions when the organization can launch engagements', () => {
        const wrapper = mount(EngagementIndex, {
            props: baseProps(),
        });

        expect(wrapper.text()).toContain('Create engagement');
        expect(wrapper.findAll('a').some((link) => link.text().includes('Create engagement'))).toBe(true);

        wrapper.unmount();
    });

    it('hides create engagement actions when the organization lacks launch permission', () => {
        const wrapper = mount(EngagementIndex, {
            props: {
                ...baseProps(),
                organization: {
                    ...baseProps().organization,
                    can_launch: false,
                },
            },
        });

        expect(wrapper.text()).not.toContain('Create engagement');
        expect(wrapper.findAll('a').some((link) => link.text().includes('Create engagement'))).toBe(false);

        wrapper.unmount();
    });
});
