<script setup lang="ts">
import GeneratedMarker from '@/components/metrics/GeneratedMarker.vue';
import SourceReferenceGroups from '@/components/metrics/SourceReferenceGroups.vue';
import { Badge } from '@/components/ui/badge';
import {
    buildFieldFacilitationAssist,
    shouldRenderFieldAssist,
} from '@/lib/workshopCuePresentation';
import type { SourceRef, WorkshopCue, WorkshopCueContext } from '@/types';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        cue: WorkshopCue;
        cueContext?: WorkshopCueContext | null;
    }>(),
    {
        cueContext: null,
    },
);

const contextExpanded = ref(false);

// The compact field assist (Start with / Fallback / Watch out) that replaces the
// heavy why/probe/risk/decision-frame stack in deterministic Phase 1 (T002).
const assist = computed(() => buildFieldFacilitationAssist(props.cue));

const contextLabels = computed<string[]>(() => {
    const labels = new Set<string>();

    props.cueContext?.companion_answers.forEach((group) => {
        if (group.companion_field_label.trim()) {
            labels.add(group.companion_field_label);
        }
    });

    props.cueContext?.matrix_context.forEach((field) => {
        if (field.field_label.trim()) {
            labels.add(field.field_label);
        }
    });

    return Array.from(labels);
});

// T058/F2: submitted answers are already shown in the segment's left column, so
// re-listing them in the cue disclosure buries the cue under a second evidence
// browser. Keep only context not already visible on the left (detector signal,
// field guidance, matrix context) and dedupe by id to match how
// SourceReferenceGroups collapses them.
// T061: De-duplicate workshop guidance by filtering out guidance of kind 'workshop_guidance'.
const disclosedSourceRefs = computed<SourceRef[]>(() => {
    const seen = new Set<string>();

    return props.cue.source_refs.filter((ref) => {
        if (ref.type === 'submitted_answer') {
            return false;
        }

        if (
            ref.type === 'field_guidance' &&
            (ref.meta?.guidance_kind === 'workshop_guidance' ||
                ref.label === 'Workshop guidance')
        ) {
            return false;
        }

        if (seen.has(ref.id)) {
            return false;
        }

        seen.add(ref.id);

        return true;
    });
});

// The counter must reflect everything the disclosure reveals — the context
// labels plus the retained source refs — not just the label groups, so the
// number no longer understates the disclosure (T058/F2).
const disclosedCount = computed<number>(
    () => contextLabels.value.length + disclosedSourceRefs.value.length,
);

const provenanceLabel = computed<string>(() => {
    switch (props.cue.provenance) {
        case 'ai_generated':
            return 'AI generated';
        case 'consultant_edited':
            return 'Edited';
        default:
            return 'Deterministic draft';
    }
});

const editStateLabel = computed<string>(() =>
    props.cue.edit_state === 'ready_to_edit' ? 'Ready to edit' : 'Review only',
);

const isSubstantiveCue = computed<boolean>(() =>
    shouldRenderFieldAssist(props.cue),
);

// T067: when the assist is gated as too thin (shouldRenderFieldAssist), the
// detector signal still carries the one source-specific sentence for the field.
// Surface just that line — no assist shell, no boilerplate — so a thin metric
// does not lose its only substantive content. It stays in the assist's
// right-column home (alignment signals are facilitation help, not left
// evidence — T040), only in a minimal shell.
const signalOnlyDescription = computed<string>(
    () => props.cueContext?.alignment_signal?.description?.trim() ?? '',
);

const hasContextDisclosure = computed<boolean>(
    () =>
        contextLabels.value.length > 0 || disclosedSourceRefs.value.length > 0,
);

const contextToggleLabel = computed<string>(() =>
    contextExpanded.value ? 'Hide context used' : 'Show context used',
);

const contextSectionId = computed<string>(
    () => `workshop-cue-context-${props.cue.id}`,
);
</script>

<template>
    <section
        v-if="isSubstantiveCue"
        class="space-y-3 rounded-r-md border-l-2 border-amber-300 bg-amber-50/20 px-3 py-2.5 dark:border-amber-900/40 dark:bg-amber-950/5"
        data-test="run-of-show-workshop-cues"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <h4
                    class="text-xs font-bold text-amber-900 dark:text-amber-200"
                    data-test="workshop-cue-heading"
                >
                    Facilitation assist
                </h4>
                <GeneratedMarker />
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <Badge
                    variant="outline"
                    class="border-transparent bg-amber-100/40 px-1.5 py-0.5 text-[9px] font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                    data-test="workshop-cue-provenance"
                >
                    {{ provenanceLabel }}
                </Badge>
                <Badge
                    variant="outline"
                    class="border-transparent bg-amber-50/20 px-1.5 py-0.5 text-[9px] text-amber-700/80 dark:bg-amber-950/20 dark:text-amber-400/80"
                    data-test="workshop-cue-edit-state"
                >
                    {{ editStateLabel }}
                </Badge>
            </div>
        </div>

        <div class="space-y-2.5 text-xs leading-relaxed">
            <div
                v-if="assist.start_with"
                class="rounded border border-amber-200/60 bg-background/40 p-2 text-[11px] text-amber-900 dark:border-amber-900/30 dark:text-amber-200"
                data-test="field-assist-start"
            >
                <span
                    class="text-[9px] font-bold tracking-wider text-amber-950/70 uppercase dark:text-amber-200/70"
                >
                    Start with
                </span>
                <p class="mt-1 leading-relaxed text-foreground">
                    {{ assist.start_with.text }}
                </p>
            </div>

            <div
                v-if="assist.fallback_question"
                class="leading-relaxed"
                data-test="field-assist-fallback"
            >
                <span class="font-semibold text-foreground">Fallback:</span>
                {{ assist.fallback_question.text }}
            </div>

            <div
                v-if="assist.watch_outs.length > 0"
                class="space-y-1"
                data-test="field-assist-watch-outs"
            >
                <span class="font-semibold text-foreground">Watch out:</span>
                <ul class="list-disc space-y-1 pl-4">
                    <li
                        v-for="watchOut in assist.watch_outs"
                        :key="watchOut.id"
                        class="leading-relaxed"
                        data-test="field-assist-watch-out"
                    >
                        {{ watchOut.text }}
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="hasContextDisclosure"
            class="space-y-2 border-t border-amber-200/70 pt-3 dark:border-amber-900/50"
        >
            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus:outline-none focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                :aria-expanded="contextExpanded ? 'true' : 'false'"
                :aria-controls="contextSectionId"
                :aria-label="contextToggleLabel"
                data-test="workshop-cue-context-toggle"
                @click="contextExpanded = !contextExpanded"
            >
                Context used
                <span v-if="disclosedCount > 0"> ({{ disclosedCount }}) </span>
                <ChevronUp
                    v-if="contextExpanded"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <ChevronDown v-else class="size-3.5" aria-hidden="true" />
            </button>

            <div
                v-if="contextExpanded"
                :id="contextSectionId"
                class="space-y-3"
                data-test="workshop-cue-context"
            >
                <ul
                    v-if="contextLabels.length > 0"
                    class="space-y-1 text-xs text-muted-foreground"
                >
                    <li
                        v-for="label in contextLabels"
                        :key="label"
                        data-test="workshop-cue-context-item"
                    >
                        {{ label }}
                    </li>
                </ul>

                <div
                    v-if="disclosedSourceRefs.length > 0"
                    data-test="workshop-cue-sources"
                >
                    <SourceReferenceGroups :source-refs="disclosedSourceRefs" />
                </div>
            </div>
        </div>
    </section>

    <!-- T067: gated thin assist, but a detector signal still exists — keep the
    one source-specific sentence in a minimal shell instead of dropping it. -->
    <div
        v-else-if="signalOnlyDescription"
        class="rounded-r-md border-l-2 border-amber-200 bg-amber-50/10 px-3 py-2 dark:border-amber-900/30 dark:bg-amber-950/5"
        data-test="run-of-show-workshop-cue-signal"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="text-[9px] font-bold tracking-wider text-amber-950/70 uppercase dark:text-amber-200/70"
            >
                Detector signal
            </span>
            <GeneratedMarker />
        </div>
        <p
            class="mt-1 text-[11px] leading-relaxed text-amber-900 dark:text-amber-200"
            data-test="workshop-cue-signal-text"
        >
            {{ signalOnlyDescription }}
        </p>
    </div>
</template>
