import { useRemember } from '@inertiajs/vue3';
import type { Ref } from 'vue';
import { computed } from 'vue';

type RememberedImpersonationJustification = { text: string };

const STORAGE_KEY = 'owner.impersonation.justification';
let rememberedState: Ref<RememberedImpersonationJustification> | null = null;

function ensureRememberedState() {
    if (!rememberedState) {
        rememberedState = useRemember<RememberedImpersonationJustification>(
            { text: '' },
            STORAGE_KEY,
        ) as Ref<RememberedImpersonationJustification>;
    }

    return rememberedState;
}

export function useImpersonationJustification() {
    const remembered = ensureRememberedState();

    const justification = computed<string>({
        get: () => remembered.value?.text ?? '',
        set: (value) => {
            remembered.value = { text: value ?? '' };
        },
    });

    const justificationField = computed<string>({
        get: () => justification.value,
        set: (value) => {
            justification.value = value ?? '';
        },
    });

    const justificationPayload = computed(() => justification.value.trim());

    return {
        justification,
        justificationField,
        justificationPayload,
    };
}
