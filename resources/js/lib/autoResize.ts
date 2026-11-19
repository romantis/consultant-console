import type { Directive } from 'vue';

/**
 * Textarea element augmented with the auto-resize bookkeeping the directive
 * attaches across its lifecycle hooks.
 */
interface AutoResizeElement extends HTMLTextAreaElement {
    _autoResize?: () => void;
    _autoResizeCleanup?: () => void;
}

export const vAutoResize: Directive<AutoResizeElement> = {
    mounted(el) {
        const resize = () => {
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight}px`;
        };

        el._autoResize = resize;
        el.addEventListener('input', resize);

        // Run on initial mount/render
        setTimeout(resize, 0);

        el._autoResizeCleanup = () => {
            el.removeEventListener('input', resize);
        };
    },
    updated(el) {
        // The textarea is bound one-way via :value, so a programmatic value
        // change (discard / regenerate / save reset) fires no input event.
        // Recompute height here so it never keeps a stale size.
        el._autoResize?.();
    },
    unmounted(el) {
        el._autoResizeCleanup?.();
    },
};
