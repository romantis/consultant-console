import { mount } from '@vue/test-utils';
import { axe } from 'vitest-axe';
import { defineComponent, h } from 'vue';

import AppShell from '@/layouts/AppShell.vue';

vi.mock('@/components/AppShell.vue', () => ({
    default: defineComponent({
        name: 'BaseAppShellStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'div',
                    { 'data-test': 'base-shell', role: 'presentation' },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/AppContent.vue', () => ({
    default: defineComponent({
        name: 'AppContentStub',
        setup(_, { slots }) {
            return () =>
                h(
                    'main',
                    {
                        'data-test': 'app-content',
                        tabindex: -1,
                        'aria-label': 'Primary panel',
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/components/AppSidebar.vue', () => ({
    default: defineComponent({
        name: 'AppSidebarStub',
        setup() {
            return () =>
                h('nav', {
                    'data-test': 'sidebar',
                    'aria-label': 'Primary navigation',
                });
        },
    }),
}));

vi.mock('@/components/AppSidebarHeader.vue', () => ({
    default: defineComponent({
        name: 'AppSidebarHeaderStub',
        props: {
            breadcrumbs: {
                type: Array,
                default: () => [],
            },
        },
        setup(props) {
            return () =>
                h(
                    'nav',
                    {
                        'aria-label': 'Breadcrumb',
                        'data-test': 'breadcrumbs',
                    },
                    props.breadcrumbs.map((crumb) =>
                        h(
                            'span',
                            { class: 'breadcrumb-item' },
                            (crumb as { label: string }).label,
                        ),
                    ),
                );
        },
    }),
}));

vi.mock('@/components/ToastHub.vue', () => ({
    default: defineComponent({
        name: 'ToastHubStub',
        setup() {
            return () =>
                h('div', {
                    role: 'status',
                    'aria-live': 'polite',
                    'data-test': 'toast-hub',
                });
        },
    }),
}));

const baseProps = {
    title: 'Engagement backlog',
    description: 'Track in-flight client engagements and readiness gates.',
    breadcrumbs: [
        { label: 'Home', href: '#' },
        { label: 'Engagements', href: '#engagements' },
    ],
};

const mountShell = (overrideProps: Record<string, unknown> = {}) =>
    mount(AppShell, {
        props: {
            ...baseProps,
            ...overrideProps,
        },
        slots: {
            default: () =>
                h('section', { 'aria-label': 'Content', class: 'space-y-4' }, [
                    h('p', 'Active engagements will render here.'),
                    h(
                        'ul',
                        { class: 'list-disc pl-6' },
                        [1, 2, 3].map((n) =>
                            h('li', { key: n }, `Engagement ${n}`),
                        ),
                    ),
                ]),
            actions: () =>
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'rounded border px-3 py-1 text-sm',
                    },
                    'Start engagement',
                ),
            toolbar: () =>
                h('div', { role: 'search' }, [
                    h('label', { for: 'toolbar-search', class: 'sr-only' }, 'Search'),
                    h('input', {
                        id: 'toolbar-search',
                        type: 'search',
                        placeholder: 'Search engagements',
                    }),
                ]),
            'empty-actions': () =>
                h(
                    'button',
                    {
                        type: 'button',
                        class: 'rounded border px-3 py-1 text-sm',
                    },
                    'Create engagement',
                ),
        },
    });

const axeConfig = {
    rules: {
        'color-contrast': { enabled: false },
    },
};

describe('AppShell accessibility', () => {
    it('meets axe expectations when rendering populated content', async () => {
        const wrapper = mountShell({ isEmpty: false });
        const results = await axe(wrapper.element, axeConfig);

        expect(results).toHaveNoViolations();
        wrapper.unmount();
    });

    it('meets axe expectations when showing the empty state', async () => {
        const wrapper = mountShell({
            isEmpty: true,
            emptyState: {
                show: true,
                title: 'No engagements found',
                description: 'Launch a new engagement to populate this view.',
            },
        });
        const results = await axe(wrapper.element, axeConfig);

        expect(results).toHaveNoViolations();
        wrapper.unmount();
    });
});
