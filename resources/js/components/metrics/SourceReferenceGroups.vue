<script setup lang="ts">
import GeneratedMarker from '@/components/metrics/GeneratedMarker.vue';
import type { SourceRef, SourceType } from '@/types';
import {
    AlertCircle,
    CheckSquare,
    ChevronDown,
    ChevronUp,
    Compass,
    FileText,
    Hammer,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

const props = defineProps<{
    sourceRefs: SourceRef[];
}>();

// Expand state for individual source refs (by id)
const expandedRefs = ref<Record<string, boolean>>({});

function toggleRef(id: string) {
    expandedRefs.value[id] = !expandedRefs.value[id];
}

function hasExcerpt(refItem: SourceRef): boolean {
    const val = refItem.excerpt;
    return val !== null && val !== undefined && val !== '';
}

function sourceExcerptToggleLabel(refItem: SourceRef): string {
    const action = expandedRefs.value[refItem.id] ? 'Collapse' : 'Expand';

    return `${action} source excerpt for ${refItem.label}`;
}

// Group configuration in fixed order
const groupConfig: {
    type: SourceType;
    label: string;
    icon: Component;
    colorClass: string;
    bgClass: string;
    // Workshop Cues are deterministically generated/inferred, not raw submitted
    // evidence, so the group carries a generated marker (T041).
    generated?: boolean;
}[] = [
    {
        type: 'submitted_answer',
        label: 'Submitted Answers',
        icon: FileText,
        colorClass: 'text-indigo-500',
        bgClass: 'bg-indigo-500/10',
    },
    {
        type: 'field_guidance',
        label: 'Field Guidance',
        icon: Compass,
        colorClass: 'text-sky-500',
        bgClass: 'bg-sky-500/10',
    },
    {
        type: 'alignment_signal',
        label: 'Alignment Signals',
        icon: AlertCircle,
        colorClass: 'text-amber-500',
        bgClass: 'bg-amber-500/10',
        generated: true,
    },
    {
        type: 'signed_definition',
        label: 'Signed Definitions',
        icon: CheckSquare,
        colorClass: 'text-emerald-500',
        bgClass: 'bg-emerald-500/10',
    },
    {
        type: 'workshop_decision',
        label: 'Workshop Decisions',
        icon: Hammer,
        colorClass: 'text-purple-500',
        bgClass: 'bg-purple-500/10',
    },
];

// Computed grouped refs
const groupedRefs = computed(() => {
    // 1. Deduplicate by ID
    const uniqueRefsMap = new Map<string, SourceRef>();
    props.sourceRefs.forEach((refItem) => {
        if (refItem && refItem.id) {
            uniqueRefsMap.set(refItem.id, refItem);
        }
    });
    const uniqueRefs = Array.from(uniqueRefsMap.values());

    // 2. Group into defined ordering
    return groupConfig
        .map((config) => {
            const items = uniqueRefs.filter((r) => r.type === config.type);
            return {
                ...config,
                items,
            };
        })
        .filter((group) => group.items.length > 0);
});
</script>

<template>
    <div class="space-y-3" data-test="source-reference-groups">
        <div
            v-for="group in groupedRefs"
            :key="group.type"
            class="rounded-lg border border-border bg-muted/20 p-3"
            :data-test="`group-${group.type}`"
        >
            <!-- Group Header -->
            <div class="mb-2 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div :class="['rounded p-1', group.bgClass]">
                        <component
                            :is="group.icon"
                            class="size-3.5"
                            :class="group.colorClass"
                        />
                    </div>
                    <span
                        class="text-xs font-bold text-foreground"
                        data-test="group-label"
                    >
                        {{ group.label }}
                    </span>
                    <GeneratedMarker v-if="group.generated" />
                </div>
                <span
                    class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                    data-test="group-count"
                >
                    {{ group.items.length }}
                    {{ group.items.length === 1 ? 'item' : 'items' }}
                </span>
            </div>

            <!-- Group items -->
            <div class="space-y-1.5 pl-7">
                <div
                    v-for="refItem in group.items"
                    :key="refItem.id"
                    class="rounded border border-border/60 bg-card p-2 text-xs transition-colors hover:bg-muted/30"
                    data-test="source-item"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span
                                class="font-semibold text-foreground"
                                data-test="source-label"
                            >
                                {{ refItem.label }}
                            </span>
                        </div>
                        <button
                            v-if="hasExcerpt(refItem)"
                            type="button"
                            @click="toggleRef(refItem.id)"
                            class="rounded-sm text-muted-foreground hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            :aria-expanded="
                                expandedRefs[refItem.id] ? 'true' : 'false'
                            "
                            :aria-controls="'excerpt-' + refItem.id"
                            :aria-label="sourceExcerptToggleLabel(refItem)"
                            data-test="toggle-excerpt"
                        >
                            <ChevronUp
                                v-if="expandedRefs[refItem.id]"
                                class="size-3.5"
                            />
                            <ChevronDown v-else class="size-3.5" />
                        </button>
                    </div>

                    <!-- Excerpt/Metadata display -->
                    <div
                        v-if="hasExcerpt(refItem)"
                        :id="'excerpt-' + refItem.id"
                        class="mt-1"
                    >
                        <p
                            class="leading-relaxed text-muted-foreground transition-all"
                            :class="[
                                expandedRefs[refItem.id] ? '' : 'line-clamp-1',
                            ]"
                            data-test="source-excerpt"
                        >
                            {{ refItem.excerpt }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
