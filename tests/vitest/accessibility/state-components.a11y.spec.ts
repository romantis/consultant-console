import { mount } from '@vue/test-utils';
import { axe } from 'vitest-axe';
import { defineComponent, h, markRaw } from 'vue';

import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';

const axeConfig = {
    rules: {
        'color-contrast': { enabled: false },
    },
};
vi.mock('@/components/ui/button', () => ({
    Button: defineComponent({
        name: 'ButtonStub',
        props: {
            variant: {
                type: String,
                default: 'default',
            },
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    'button',
                    {
                        type: 'button',
                        'data-variant': props.variant,
                        disabled: props.disabled,
                        class: 'rounded border px-3 py-2 text-sm',
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

const DecorativeIcon = defineComponent({
    name: 'DecorativeIcon',
    setup() {
        return () =>
            h(
                'svg',
                {
                    viewBox: '0 0 24 24',
                    role: 'img',
                    'aria-label': 'Decorative indicator',
                },
                [h('title', 'Decorative indicator')],
            );
    },
});

describe('EmptyState accessibility', () => {
    it('meets axe expectations across variants', async () => {
        const wrapper = mount(EmptyState, {
            props: {
                title: 'No metrics found',
                description:
                    'Metrics and discrepancies will appear after you finish the engagement wizard.',
                icon: markRaw(DecorativeIcon),
                variant: 'card',
                align: 'start',
            },
            attrs: {
                role: 'region',
                'aria-label': 'Empty metrics state',
            },
            slots: {
                actions: () =>
                    h(
                        'a',
                        {
                            href: '#launch',
                            class: 'text-primary underline',
                        },
                        'Launch engagement',
                    ),
            },
        });

        const results = await axe(wrapper.element, axeConfig);

        expect(results).toHaveNoViolations();
        wrapper.unmount();
    });
});

describe('ErrorState accessibility', () => {
    it('meets axe expectations with retry affordance and supplemental details', async () => {
        const wrapper = mount(ErrorState, {
            props: {
                title: 'Failed to load reminders',
                description:
                    'Network latency interrupted the cadence sync. Retry in a few seconds.',
                retryText: 'Retry load',
                tone: 'surface',
            },
            slots: {
                details: () =>
                    h('p', [
                        h('strong', { class: 'font-semibold' }, 'Last attempt:'),
                        ' 2025-11-03T10:15Z',
                    ]),
                actions: () =>
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'rounded border px-3 py-1 text-sm',
                        },
                        'Contact support',
                    ),
            },
        });

        const results = await axe(wrapper.element, axeConfig);

        expect(wrapper.attributes('role')).toBe('alert');
        expect(results).toHaveNoViolations();
        wrapper.unmount();
    });
});
