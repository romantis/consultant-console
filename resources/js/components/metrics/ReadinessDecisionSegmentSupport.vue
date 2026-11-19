<script setup lang="ts">
import type { ReadinessDecision } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    decision: ReadinessDecision;
    // Pack decisions keyed by id, used to resolve "decide alongside" links to
    // related decisions that are actually present in the current Run of Show.
    decisionsById: Record<string, { label: string; field_key: string }>;
}>();

const relatedDecisions = computed(() =>
    props.decision.related_decision_ids
        .map((id) => props.decisionsById[id])
        .filter(
            (entry): entry is { label: string; field_key: string } =>
                entry !== undefined,
        ),
);

const hasSupport = computed(
    () =>
        props.decision.why_this_matters !== null ||
        props.decision.capture_requirements.length > 0 ||
        relatedDecisions.value.length > 0,
);
</script>

<template>
    <!-- Pack-derived prep for this decision. Distinct from raw evidence and from
    the Facilitation assist: it carries only what the readiness pack adds —
    why it matters, what to capture, and which decisions move together. -->
    <div
        v-if="hasSupport"
        class="space-y-2 rounded-md border-l-2 border-indigo-300 bg-indigo-50/30 px-3 py-2 dark:border-indigo-800 dark:bg-indigo-950/15"
        data-test="readiness-segment-support"
    >
        <p
            v-if="decision.why_this_matters"
            class="text-xs text-muted-foreground"
            data-test="readiness-segment-why"
        >
            <span class="font-semibold text-foreground">Why this matters:</span>
            {{ decision.why_this_matters.text }}
        </p>

        <div
            v-if="decision.capture_requirements.length > 0"
            class="space-y-1"
            data-test="readiness-segment-capture"
        >
            <p
                class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
            >
                Capture before closing
            </p>
            <ul class="space-y-0.5 text-xs text-muted-foreground">
                <li
                    v-for="item in decision.capture_requirements"
                    :key="item.id"
                    data-test="readiness-segment-capture-item"
                >
                    {{ item.label }}
                </li>
            </ul>
        </div>

        <p
            v-if="relatedDecisions.length > 0"
            class="text-xs text-muted-foreground"
            data-test="readiness-segment-related"
        >
            <span class="font-semibold text-foreground">Decide alongside:</span>
            <template
                v-for="(related, index) in relatedDecisions"
                :key="related.field_key"
            >
                <a
                    :href="`#field-detail-${related.field_key}`"
                    class="text-indigo-700 underline-offset-2 hover:underline dark:text-indigo-300"
                    data-test="readiness-segment-related-link"
                    >{{ related.label }}</a
                ><span v-if="index < relatedDecisions.length - 1">, </span>
            </template>
        </p>
    </div>
</template>
