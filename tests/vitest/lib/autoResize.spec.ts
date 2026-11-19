import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref, withDirectives } from 'vue';
import { mount } from '@vue/test-utils';
import { vAutoResize } from '@/lib/autoResize';

const mountTextarea = (initial: string) => {
    const value = ref(initial);

    const wrapper = mount(
        defineComponent({
            setup() {
                return () =>
                    withDirectives(
                        h('textarea', { value: value.value, rows: 3 }),
                        [[vAutoResize]],
                    );
            },
        }),
    );

    return { wrapper, value };
};

const setScrollHeight = (el: HTMLTextAreaElement, value: number) => {
    Object.defineProperty(el, 'scrollHeight', {
        value,
        configurable: true,
    });
};

describe('vAutoResize directive', () => {
    it('recomputes height on a programmatic value change', async () => {
        const { wrapper, value } = mountTextarea('short');
        const el = wrapper.element as HTMLTextAreaElement;

        // Let the mount setTimeout(resize) flush.
        await new Promise((resolve) => setTimeout(resolve, 0));

        // A programmatic value change grows the content (jsdom reports 0 by
        // default, so stand in for the new content height).
        setScrollHeight(el, 200);
        value.value = 'a much taller block of facilitator notes';
        await wrapper.vm.$nextTick();

        expect(el.style.height).toBe('200px');
    });

    it('still resizes on input', async () => {
        const { wrapper } = mountTextarea('short');
        const el = wrapper.element as HTMLTextAreaElement;

        setScrollHeight(el, 96);
        el.dispatchEvent(new Event('input'));

        expect(el.style.height).toBe('96px');
    });
});
