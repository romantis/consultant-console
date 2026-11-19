<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ImpersonationState } from '@/types';
import { AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';

type Props = {
    impersonation?: ImpersonationState | null;
    error?: string | null;
    showStopAction?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    impersonation: null,
    error: null,
    showStopAction: false,
});

const emit = defineEmits<{
    (e: 'stop'): void;
}>();

const organizationName = computed(
    () => props.impersonation?.organization?.name ?? null,
);
const roleLabel = computed(() => {
    const role = props.impersonation?.role;
    if (typeof role !== 'string' || role.trim() === '') {
        return null;
    }

    return role.charAt(0).toUpperCase() + role.slice(1);
});

const normalizedError = computed(() => {
    if (typeof props.error !== 'string') {
        return null;
    }

    const trimmed = props.error.trim();

    return trimmed === '' ? null : trimmed;
});

const hasContext = computed(
    () => Boolean(props.impersonation) || Boolean(normalizedError.value),
);

const statusAnnouncement = computed(() => {
    if (!organizationName.value) {
        return 'Impersonation mode is active.';
    }

    if (roleLabel.value) {
        return `Impersonating ${organizationName.value} as ${roleLabel.value}.`;
    }

    return `Impersonating ${organizationName.value}.`;
});

const statusSummary = computed(() => {
    if (!organizationName.value) {
        return 'Impersonation mode active';
    }

    if (roleLabel.value) {
        return `Impersonating ${organizationName.value} · Role: ${roleLabel.value}`;
    }

    return `Impersonating ${organizationName.value}`;
});
</script>

<template>
    <div
        v-if="hasContext"
        class="flex w-full flex-col gap-2 text-xs text-muted-foreground"
        data-test="impersonation-banner"
    >
        <div
            v-if="impersonation"
            class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-200/70 bg-amber-50/90 px-4 py-2 text-[13px] font-medium text-amber-900 shadow-sm dark:border-amber-500/40 dark:bg-amber-950/50 dark:text-amber-50"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            <span class="sr-only">{{ statusAnnouncement }}</span>
            <div class="flex flex-1 items-center gap-3">
                <Badge
                    variant="secondary"
                    class="bg-amber-200 text-[10px] font-semibold tracking-wide text-amber-900 uppercase dark:bg-amber-500/20 dark:text-amber-100"
                >
                    Impersonating
                </Badge>
                <div class="flex flex-col text-sm leading-tight">
                    <span
                        class="font-semibold text-amber-900 dark:text-amber-50"
                    >
                        {{ organizationName ?? 'Unknown organization' }}
                    </span>
                    <span
                        v-if="roleLabel"
                        class="text-[12px] text-amber-800/90 dark:text-amber-200"
                    >
                        Role: {{ roleLabel }}
                    </span>
                    <span
                        v-else
                        class="text-[12px] text-amber-800/90 dark:text-amber-200"
                    >
                        {{ statusSummary }}
                    </span>
                </div>
            </div>
            <Button
                v-if="showStopAction"
                type="button"
                size="sm"
                variant="outline"
                class="ml-auto border-amber-300 text-[12px] font-semibold tracking-wide text-amber-900 uppercase hover:bg-amber-100 dark:border-amber-300/70 dark:text-amber-100 dark:hover:bg-amber-500/20"
                data-test="impersonation-stop"
                @click="emit('stop')"
            >
                Stop impersonating
            </Button>
        </div>
        <div
            v-if="normalizedError"
            class="inline-flex items-center gap-1 rounded-lg bg-destructive/10 px-3 py-1 text-xs font-semibold text-destructive"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
            data-test="impersonation-error"
        >
            <AlertCircle class="size-3" aria-hidden="true" />
            <span class="truncate">{{ normalizedError }}</span>
        </div>
    </div>
</template>
