<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

/**
 * Compact badge that flags deterministic summaries and generated/inferred
 * facilitation content (e.g. Workshop Cues) so they read differently from raw
 * submitted evidence (T041). The label is always rendered as text, never
 * icon-only, so the distinction is available to assistive technology.
 */
withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Generated',
    },
);
</script>

<template>
    <Badge
        variant="outline"
        class="px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
        title="Generated or inferred — not a raw submitted answer."
        data-test="generated-marker"
    >
        <span aria-hidden="true">✨</span>
        <span>{{ label }}</span>
    </Badge>
</template>
