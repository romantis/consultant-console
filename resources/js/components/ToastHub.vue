<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, X } from 'lucide-vue-next';
import {
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
    type Component,
} from 'vue';

import type { AppPageProps } from '@/types';

type ToastType = 'success' | 'error';

type ToastRecord = {
    id: number;
    type: ToastType;
    message: string;
};

const DISPLAY_DURATION_MS = 5000;

const page = usePage<AppPageProps>();
const toasts = ref<ToastRecord[]>([]);

let counter = 0;

const lastFlash = reactive<Record<ToastType, string | null>>({
    success: null,
    error: null,
});

function normalizeMessage(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : null;
}

function pushToast(type: ToastType, message: string) {
    counter += 1;
    const id = counter;

    toasts.value = [...toasts.value, { id, type, message }];

    setTimeout(() => dismissToast(id), DISPLAY_DURATION_MS);
}

function dismissToast(id: number) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function consumeFlash(type: ToastType, rawMessage: unknown) {
    const message = normalizeMessage(rawMessage);

    if (!message) {
        lastFlash[type] = null;
        return;
    }

    if (lastFlash[type] === message) {
        return;
    }

    lastFlash[type] = message;
    pushToast(type, message);
}

watchFlash<'success'>('success');
watchFlash<'error'>('error');

function watchFlash<T extends ToastType>(type: T) {
    const property: 'success' | 'error' = type;

    watch(
        () => page.props.flash?.[property],
        (value) => consumeFlash(type, value),
        { immediate: true },
    );
}

const handleToastEvent = (event: Event) => {
    if (!(event instanceof CustomEvent)) {
        return;
    }

    const detail = event.detail as { type?: ToastType; message?: string };

    if (
        !detail?.message ||
        (detail.type !== 'success' && detail.type !== 'error')
    ) {
        return;
    }

    pushToast(detail.type, detail.message);
};

onMounted(() => {
    window.addEventListener('toast:show', handleToastEvent as EventListener);
});

onBeforeUnmount(() => {
    window.removeEventListener('toast:show', handleToastEvent as EventListener);
});

const toastIcons: Record<ToastType, Component> = {
    success: CheckCircle2,
    error: AlertCircle,
};

const toastClasses: Record<ToastType, string> = {
    success:
        'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-100',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-500/40 dark:bg-red-500/10 dark:text-red-100',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-0 z-[60] flex flex-col items-end gap-3 overflow-hidden px-4 py-6 sm:px-6 sm:py-8"
        >
            <TransitionGroup
                name="toast"
                tag="div"
                class="flex w-full flex-col gap-3 sm:max-w-sm"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto overflow-hidden rounded-lg border shadow-lg shadow-black/5 backdrop-blur"
                    :class="toastClasses[toast.type]"
                >
                    <div class="flex items-start gap-3 p-4">
                        <component
                            :is="toastIcons[toast.type]"
                            class="mt-0.5 size-5 shrink-0"
                        />
                        <div class="flex-1 text-sm leading-5">
                            {{ toast.message }}
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-6 shrink-0 items-center justify-center rounded-md border border-transparent/20 text-current transition hover:bg-black/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-black/50 dark:hover:bg-white/10"
                            @click="dismissToast(toast.id)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-16px) scale(0.98);
}

.toast-leave-from {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.toast-enter-active,
.toast-leave-active {
    transition: all 180ms ease;
}

.toast-move {
    transition: transform 180ms ease;
}
</style>
