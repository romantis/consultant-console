<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    FileText,
    HelpCircle,
    Info,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

import { Button } from '@/components/ui/button';
import DefinitionSourceReference from './DefinitionSourceReference.vue';
import {
    createSignalDraft,
    formatOperationalStatus,
    isSectionDirty,
    isSignalDraftDirty,
    resetSectionDraft,
    type Signals,
    type SignalSection,
} from './handbook-signals';
import type { SourceContext } from './MetricSourceContext.vue';
import {
    buildSignalSourceChips,
    type SignalSourceChip,
} from './signal-source-lineage';
import SourceCue from './SourceCue.vue';

interface ChecklistItem {
    label: string;
    status: 'ready' | 'missing';
}

const props = defineProps<{
    signals: Signals | null;
    checklist?: Record<string, ChecklistItem> | null;
    sourceContext?: SourceContext | null;
    isEditing: boolean;
    isSaving: boolean;
    isUnsigned?: boolean;
}>();

const emit = defineEmits<{
    (e: 'save'): void;
}>();

const draft = ref(createSignalDraft(props.signals));

const activeSection = ref<SignalSection | null>(null);

const isSectionEditing = (section: SignalSection) => {
    return props.isEditing || activeSection.value === section;
};

const startSectionEdit = (section: SignalSection) => {
    if (props.isUnsigned) return;
    if (activeSection.value && activeSection.value !== section) {
        if (isDirtySection(activeSection.value)) {
            // Prevent silent draft reset if dirty
            return;
        }
        resetSectionDraft(draft.value, props.signals, activeSection.value);
    }
    activeSection.value = section;
};

const cancelSectionEdit = (section: SignalSection) => {
    resetSectionDraft(draft.value, props.signals, section);
    if (activeSection.value === section) {
        activeSection.value = null;
    }
};

const saveSection = () => {
    emit('save');
};

watch(
    () => props.signals,
    (newSignals) => {
        draft.value = createSignalDraft(newSignals);
        activeSection.value = null;
    },
    { immediate: true },
);

watch(
    () => props.isEditing,
    (isGlobalEditing) => {
        if (!isGlobalEditing) {
            activeSection.value = null;
        }
    },
);

const reset = () => {
    draft.value = createSignalDraft(props.signals);
    activeSection.value = null;
};

const isDirty = computed(() => {
    return isSignalDraftDirty(draft.value, props.signals);
});

const isDirtySection = (section: SignalSection) => {
    return isSectionDirty(draft.value, props.signals, section);
};

const placeholderText = computed(() => {
    return props.isUnsigned
        ? 'Awaiting sign-off'
        : 'Missing for handbook review';
});

const getSourceChips = (section: SignalSection): SignalSourceChip[] => {
    return buildSignalSourceChips(section, props.sourceContext ?? null);
};

const takeawayChips = computed(() => getSourceChips('takeaway'));
const alignmentChips = computed(() => getSourceChips('alignment'));
const statusChips = computed(() => getSourceChips('status'));
const gapsChips = computed(() => getSourceChips('gaps'));
const validationChips = computed(() => getSourceChips('validation'));

const getSectionSource = (
    sectionKey: 'takeaway' | 'alignment' | 'status' | 'gaps' | 'validation',
) => {
    if (!props.sourceContext) return null;

    if (sectionKey === 'takeaway') {
        const sec = props.sourceContext.signed_definition?.sections?.find(
            (s) => s.key === 'official_definition',
        );
        return {
            exists: !!sec?.items?.length,
            title: 'Official Definition',
            items: sec?.items || [],
        };
    }

    if (sectionKey === 'alignment') {
        const alignment = props.sourceContext.workshop_alignment;
        return {
            exists: !!alignment?.has_evidence,
            title: 'Workshop Alignment',
            workshop: alignment?.workshop || null,
            discrepancies: alignment?.discrepancies || [],
        };
    }

    if (sectionKey === 'status') {
        const signedDef = props.sourceContext.signed_definition;
        const exists = !!(
            signedDef?.version ||
            signedDef?.signed_off_at ||
            signedDef?.signed_off_by ||
            signedDef?.owner ||
            signedDef?.notes
        );
        return {
            exists,
            title: 'Signed Definition Header',
            version: signedDef?.version,
            signed_off_at: signedDef?.signed_off_at,
            signed_off_by: signedDef?.signed_off_by,
            owner: signedDef?.owner,
            notes: signedDef?.notes,
        };
    }

    if (sectionKey === 'gaps') {
        const sec = props.sourceContext.signed_definition?.sections?.find(
            (s) => s.key === 'controls_caveats',
        );
        return {
            exists: !!sec?.items?.length,
            title: 'Controls & Caveats',
            items: sec?.items || [],
        };
    }

    if (sectionKey === 'validation') {
        const sec = props.sourceContext.signed_definition?.sections?.find(
            (s) => s.key === 'measurement_logic_rules',
        );
        return {
            exists: !!sec?.items?.length,
            title: 'Measurement Logic & Rules',
            items: sec?.items || [],
        };
    }

    return null;
};

defineExpose({
    draft,
    isDirty,
    reset,
});
</script>

<template>
    <div class="space-y-4" data-test="handbook-signal-editor">
        <!-- Section 1: Executive Takeaway -->
        <div class="rounded-lg border border-border bg-muted/5 p-4.5">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <FileText class="size-3.5 text-muted-foreground/80" />
                    Executive Takeaway
                </h3>
                <div class="flex items-center gap-2">
                    <span
                        v-if="props.checklist?.executive_takeaway"
                        class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                        :class="
                            props.checklist.executive_takeaway.status ===
                            'ready'
                                ? 'border-emerald-100/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                : 'border-amber-100/30 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                        "
                        data-test="status-badge-executive_takeaway"
                    >
                        {{ props.checklist.executive_takeaway.status }}
                    </span>
                    <Button
                        v-if="
                            !isSectionEditing('takeaway') && !props.isUnsigned
                        "
                        size="sm"
                        variant="ghost"
                        class="h-6 px-2 text-xs"
                        :disabled="!!activeSection"
                        @click="startSectionEdit('takeaway')"
                        data-test="edit-section-takeaway-btn"
                    >
                        Edit
                    </Button>
                </div>
            </div>
            <!-- Source Chips -->
            <div
                class="mt-2 flex flex-wrap gap-1.5"
                data-test="source-chips-takeaway"
            >
                <span
                    v-for="chip in takeawayChips"
                    :key="chip.label"
                    :title="chip.summary"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-medium transition-all duration-200 select-none"
                    :class="
                        chip.status === 'available'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-border/50 bg-muted/40 text-muted-foreground dark:bg-muted/10'
                    "
                    :data-test="`source-chip-${chip.kind}-${chip.status}`"
                >
                    <CheckCircle2
                        v-if="chip.status === 'available'"
                        class="size-2.5 shrink-0 text-emerald-500"
                    />
                    <HelpCircle
                        v-else
                        class="size-2.5 shrink-0 text-muted-foreground/60"
                    />
                    {{ chip.label }}
                </span>
            </div>
            <template v-if="isSectionEditing('takeaway')">
                <div class="mt-4 space-y-3.5">
                    <div class="space-y-1.5">
                        <Label
                            for="executive_takeaway"
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Takeaway Text</Label
                        >
                        <Textarea
                            id="executive_takeaway"
                            v-model="draft.executive_takeaway"
                            :disabled="props.isSaving"
                            placeholder="State the locked baseline, decision, or outcome..."
                            class="min-h-[100px] text-sm leading-relaxed"
                        />

                        <!-- Source Cues -->
                        <SourceCue
                            source-name="Official Definition"
                            source-description="locked decisions, official metrics, or business outcomes"
                            :show-warning="
                                !props.sourceContext?.signed_definition
                                    ?.sections?.length
                            "
                            warning-message="No structured definition fields are available in the signed definition."
                            data-test-cue="executive-takeaway-source-cue"
                            data-test-warning="executive-takeaway-missing-source-cue"
                        />
                    </div>

                    <!-- Inline Writing Guidance -->
                    <details
                        class="group cursor-pointer rounded-md border border-border bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                        data-test="takeaway-writing-guide"
                    >
                        <summary
                            class="flex list-none items-center gap-1 rounded-sm font-bold text-foreground select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                        >
                            <span>Takeaway Writing Guide:</span>
                            <span
                                class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                                >(click to expand)</span
                            >
                            <span
                                class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                                >(click to collapse)</span
                            >
                        </summary>
                        <ul
                            class="mt-2 cursor-default list-disc space-y-1 pl-4"
                        >
                            <li>
                                <strong>Purpose:</strong> State the locked
                                baseline, decision, or business outcome of the
                                engagement (not just what the metric measures).
                            </li>
                            <li>
                                <strong>Length:</strong> Keep it to one
                                sentence, typically under ~180 characters.
                            </li>
                            <li>
                                <strong>Timing:</strong> Author this
                                post-workshop once final definitions and results
                                are finalized.
                            </li>
                            <li>
                                <strong>Anti-patterns:</strong> Avoid copying
                                metric definitions, repeating gaps/snapshots,
                                raw SQL, or overly technical jargon.
                            </li>
                        </ul>
                    </details>

                    <!-- Local Controls -->
                    <div
                        v-if="!props.isEditing"
                        class="mt-4 flex items-center justify-end gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="cancelSectionEdit('takeaway')"
                            :disabled="props.isSaving"
                            class="h-7 px-3 text-xs"
                            data-test="cancel-section-takeaway-btn"
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            @click="saveSection"
                            :disabled="
                                props.isSaving || !isDirtySection('takeaway')
                            "
                            class="h-7 px-3 text-xs"
                            data-test="save-section-takeaway-btn"
                        >
                            {{ props.isSaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="mt-2.5 text-sm leading-relaxed text-foreground">
                    <template
                        v-if="
                            props.signals?.executive_takeaway &&
                            props.signals.executive_takeaway.trim()
                        "
                    >
                        {{ props.signals.executive_takeaway }}
                    </template>
                    <template v-else>
                        <span class="text-muted-foreground/60 italic">{{
                            placeholderText
                        }}</span>
                    </template>
                </div>
            </template>

            <!-- Section-Local Source Reference -->
            <details
                v-if="props.sourceContext"
                class="group mt-3.5 rounded-md border border-border/60 bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                data-test="source-reference-takeaway"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-1 font-bold text-foreground/80 select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <span>Source Reference: Official Definition</span>
                    <span
                        class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                        >(click to view)</span
                    >
                    <span
                        class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                        >(click to hide)</span
                    >
                </summary>
                <div class="mt-2 space-y-2.5 text-foreground/90">
                    <DefinitionSourceReference
                        :items="getSectionSource('takeaway')?.items"
                        empty-text="No official definition fields are available."
                    />
                </div>
            </details>
        </div>

        <!-- Section 2: Executive Alignment -->
        <div class="rounded-lg border border-border bg-muted/5 p-4.5">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <Info class="size-3.5 text-muted-foreground/80" />
                    Executive Alignment
                </h3>
                <div class="flex items-center gap-2">
                    <span
                        v-if="props.checklist?.executive_alignment"
                        class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                        :class="
                            props.checklist.executive_alignment.status ===
                            'ready'
                                ? 'border-emerald-100/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                : 'border-amber-100/30 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                        "
                        data-test="status-badge-executive_alignment"
                    >
                        {{ props.checklist.executive_alignment.status }}
                    </span>
                    <Button
                        v-if="
                            !isSectionEditing('alignment') && !props.isUnsigned
                        "
                        size="sm"
                        variant="ghost"
                        class="h-6 px-2 text-xs"
                        :disabled="!!activeSection"
                        @click="startSectionEdit('alignment')"
                        data-test="edit-section-alignment-btn"
                    >
                        Edit
                    </Button>
                </div>
            </div>
            <!-- Source Chips -->
            <div
                class="mt-2 flex flex-wrap gap-1.5"
                data-test="source-chips-alignment"
            >
                <span
                    v-for="chip in alignmentChips"
                    :key="chip.label"
                    :title="chip.summary"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-medium transition-all duration-200 select-none"
                    :class="
                        chip.status === 'available'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-border/50 bg-muted/40 text-muted-foreground dark:bg-muted/10'
                    "
                    :data-test="`source-chip-${chip.kind}-${chip.status}`"
                >
                    <CheckCircle2
                        v-if="chip.status === 'available'"
                        class="size-2.5 shrink-0 text-emerald-500"
                    />
                    <HelpCircle
                        v-else
                        class="size-2.5 shrink-0 text-muted-foreground/60"
                    />
                    {{ chip.label }}
                </span>
            </div>
            <template v-if="isSectionEditing('alignment')">
                <div class="mt-4 space-y-1.5">
                    <Label
                        for="alignment_summary"
                        class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >Alignment Summary</Label
                    >
                    <Textarea
                        id="alignment_summary"
                        v-model="draft.alignment_summary"
                        :disabled="props.isSaving"
                        placeholder="Explain what was resolved for this metric..."
                        class="min-h-[100px] text-sm leading-relaxed"
                    />

                    <!-- Source Cues -->
                    <SourceCue
                        source-name="Workshop Alignment"
                        source-description="consensus decisions, resolved discrepancies, and discussion notes"
                        :show-warning="
                            !props.sourceContext?.workshop_alignment
                                ?.has_evidence
                        "
                        warning-message="No workshop alignment evidence (decisions, discrepancies, or notes) is available for this metric."
                        data-test-cue="alignment-summary-source-cue"
                        data-test-warning="alignment-summary-missing-source-cue"
                    />

                    <!-- Local Controls -->
                    <div
                        v-if="!props.isEditing"
                        class="mt-4 flex items-center justify-end gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="cancelSectionEdit('alignment')"
                            :disabled="props.isSaving"
                            class="h-7 px-3 text-xs"
                            data-test="cancel-section-alignment-btn"
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            @click="saveSection"
                            :disabled="
                                props.isSaving || !isDirtySection('alignment')
                            "
                            class="h-7 px-3 text-xs"
                            data-test="save-section-alignment-btn"
                        >
                            {{ props.isSaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div
                    class="mt-2.5 text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                >
                    <template
                        v-if="
                            props.signals?.alignment_summary &&
                            props.signals.alignment_summary.trim()
                        "
                    >
                        {{ props.signals.alignment_summary }}
                    </template>
                    <template v-else>
                        <span class="text-muted-foreground/60 italic">{{
                            placeholderText
                        }}</span>
                    </template>
                </div>
            </template>

            <!-- Section-Local Source Reference -->
            <details
                v-if="props.sourceContext"
                class="group mt-3.5 rounded-md border border-border/60 bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                data-test="source-reference-alignment"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-1 font-bold text-foreground/80 select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <span>Source Reference: Workshop Alignment</span>
                    <span
                        class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                        >(click to view)</span
                    >
                    <span
                        class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                        >(click to hide)</span
                    >
                </summary>
                <div class="mt-2 space-y-2.5 text-foreground/90">
                    <template v-if="getSectionSource('alignment')?.exists">
                        <!-- Decisions -->
                        <div
                            v-if="
                                getSectionSource('alignment')?.workshop
                                    ?.decisions &&
                                Object.keys(
                                    getSectionSource('alignment')?.workshop
                                        ?.decisions || {},
                                ).length > 0
                            "
                            class="space-y-1"
                        >
                            <div
                                v-if="
                                    getSectionSource('alignment')?.workshop
                                        ?.decisions?.consensus
                                "
                            >
                                <span
                                    class="font-semibold text-muted-foreground"
                                    >Consensus Decision:</span
                                >
                                <p
                                    class="mt-0.5 leading-relaxed text-foreground/90"
                                >
                                    {{
                                        getSectionSource('alignment')?.workshop
                                            ?.decisions?.consensus
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    getSectionSource('alignment')?.workshop
                                        ?.decisions?.follow_up &&
                                    getSectionSource('alignment')?.workshop
                                        ?.decisions?.follow_up?.length > 0
                                "
                            >
                                <span
                                    class="font-semibold text-muted-foreground"
                                    >Follow-up:</span
                                >
                                <ul
                                    class="mt-0.5 list-disc space-y-0.5 pl-4 text-foreground/80"
                                >
                                    <li
                                        v-for="(item, idx) in getSectionSource(
                                            'alignment',
                                        )?.workshop?.decisions?.follow_up"
                                        :key="idx"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Discrepancies -->
                        <div
                            v-if="
                                (getSectionSource('alignment')?.discrepancies
                                    ?.length ?? 0) > 0
                            "
                            class="space-y-1.5"
                        >
                            <span
                                class="block text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Resolved Discrepancies
                            </span>
                            <div class="space-y-1">
                                <div
                                    v-for="d in getSectionSource('alignment')
                                        ?.discrepancies"
                                    :key="d.field_key"
                                    class="rounded border border-border/40 bg-background/50 p-2 text-xs leading-relaxed text-foreground/80"
                                >
                                    <div
                                        class="font-semibold text-foreground/90"
                                    >
                                        {{ d.description }}
                                    </div>
                                    <span
                                        class="mt-1 inline-block rounded border border-border/60 bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground capitalize"
                                    >
                                        {{ d.severity }} severity
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div
                            v-if="
                                getSectionSource(
                                    'alignment',
                                )?.workshop?.notes?.trim()
                            "
                        >
                            <span class="font-semibold text-muted-foreground"
                                >Discussion Notes:</span
                            >
                            <p
                                class="mt-1 rounded border border-border/40 bg-background/50 p-2 leading-relaxed whitespace-pre-line text-foreground/75"
                            >
                                {{
                                    getSectionSource('alignment')?.workshop
                                        ?.notes
                                }}
                            </p>
                        </div>
                    </template>
                    <div v-else class="text-muted-foreground/60 italic">
                        No workshop alignment evidence recorded.
                    </div>
                </div>
            </details>
        </div>

        <!-- Section 3: Implementation Status -->
        <div class="rounded-lg border border-border bg-muted/5 p-4.5">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <Activity class="size-3.5 text-muted-foreground/80" />
                    Implementation Status
                </h3>
                <div class="flex items-center gap-2">
                    <span
                        v-if="props.checklist?.implementation_review"
                        class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                        :class="
                            props.checklist.implementation_review.status ===
                            'ready'
                                ? 'border-emerald-100/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                : 'border-amber-100/30 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                        "
                        data-test="status-badge-implementation_review_status"
                    >
                        {{ props.checklist.implementation_review.status }}
                    </span>
                    <Button
                        v-if="!isSectionEditing('status') && !props.isUnsigned"
                        size="sm"
                        variant="ghost"
                        class="h-6 px-2 text-xs"
                        :disabled="!!activeSection"
                        @click="startSectionEdit('status')"
                        data-test="edit-section-status-btn"
                    >
                        Edit
                    </Button>
                </div>
            </div>
            <!-- Source Chips -->
            <div
                class="mt-2 flex flex-wrap gap-1.5"
                data-test="source-chips-status"
            >
                <span
                    v-for="chip in statusChips"
                    :key="chip.label"
                    :title="chip.summary"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-medium transition-all duration-200 select-none"
                    :class="
                        chip.status === 'available'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-border/50 bg-muted/40 text-muted-foreground dark:bg-muted/10'
                    "
                    :data-test="`source-chip-${chip.kind}-${chip.status}`"
                >
                    <CheckCircle2
                        v-if="chip.status === 'available'"
                        class="size-2.5 shrink-0 text-emerald-500"
                    />
                    <HelpCircle
                        v-else
                        class="size-2.5 shrink-0 text-muted-foreground/60"
                    />
                    {{ chip.label }}
                </span>
            </div>
            <template v-if="isSectionEditing('status')">
                <div class="mt-4 space-y-4">
                    <div class="space-y-1.5">
                        <Label
                            for="operational_status"
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Operational Status</Label
                        >
                        <Select
                            v-model="draft.operational_status"
                            :disabled="props.isSaving"
                            id="operational_status"
                        >
                            <SelectTrigger class="h-9 w-full text-sm">
                                <SelectValue placeholder="Select Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="operationally_ready"
                                    >Ready for Use</SelectItem
                                >
                                <SelectItem value="needs_follow_up"
                                    >Implementation Follow-up Needed</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="operational_status_notes"
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Status Notes</Label
                        >
                        <Textarea
                            id="operational_status_notes"
                            v-model="draft.operational_status_notes"
                            :disabled="props.isSaving"
                            placeholder="Describe current status..."
                            class="min-h-[80px] text-sm"
                        />

                        <!-- Source Cues -->
                        <SourceCue
                            source-name="Signed Definition Header"
                            source-description="operational status, owner, and implementation notes"
                            :show-warning="
                                !props.sourceContext?.signed_definition
                                    ?.sections?.length
                            "
                            warning-message="No structured definition fields are available in the signed definition."
                            data-test-cue="operational-status-source-cue"
                            data-test-warning="operational-status-missing-source-cue"
                        />
                    </div>

                    <!-- Local Controls -->
                    <div
                        v-if="!props.isEditing"
                        class="mt-4 flex items-center justify-end gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="cancelSectionEdit('status')"
                            :disabled="props.isSaving"
                            class="h-7 px-3 text-xs"
                            data-test="cancel-section-status-btn"
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            @click="saveSection"
                            :disabled="
                                props.isSaving || !isDirtySection('status')
                            "
                            class="h-7 px-3 text-xs"
                            data-test="save-section-status-btn"
                        >
                            {{ props.isSaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="mt-2.5 space-y-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center rounded border px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                            :class="
                                props.signals?.operational_status ===
                                'operationally_ready'
                                    ? 'border-emerald-200/60 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-400'
                                    : props.signals?.operational_status ===
                                        'needs_follow_up'
                                      ? 'border-amber-200/60 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400'
                                      : 'border-border bg-muted text-muted-foreground'
                            "
                        >
                            {{
                                props.isUnsigned
                                    ? 'Awaiting sign-off'
                                    : formatOperationalStatus(
                                          props.signals?.operational_status ??
                                              null,
                                      )
                            }}
                        </span>
                    </div>
                    <div
                        class="text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                    >
                        <template
                            v-if="
                                props.signals?.operational_status_notes &&
                                props.signals.operational_status_notes.trim()
                            "
                        >
                            {{ props.signals.operational_status_notes }}
                        </template>
                        <template v-else>
                            <span class="text-muted-foreground/60 italic">{{
                                placeholderText
                            }}</span>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Section-Local Source Reference -->
            <details
                v-if="props.sourceContext"
                class="group mt-3.5 rounded-md border border-border/60 bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                data-test="source-reference-status"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-1 font-bold text-foreground/80 select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <span>Source Reference: Signed Definition Header</span>
                    <span
                        class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                        >(click to view)</span
                    >
                    <span
                        class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                        >(click to hide)</span
                    >
                </summary>
                <div class="mt-2 space-y-2 text-foreground/90">
                    <template v-if="getSectionSource('status')?.exists">
                        <div
                            class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span v-if="getSectionSource('status')?.version">
                                v{{ getSectionSource('status')?.version }}
                            </span>
                            <span
                                v-if="getSectionSource('status')?.signed_off_at"
                            >
                                Signed
                                {{
                                    new Date(
                                        getSectionSource('status')
                                            ?.signed_off_at as string,
                                    ).toLocaleDateString()
                                }}
                            </span>
                            <span
                                v-if="getSectionSource('status')?.signed_off_by"
                            >
                                by
                                {{
                                    getSectionSource('status')?.signed_off_by
                                        ?.name
                                }}
                            </span>
                        </div>
                        <div
                            v-if="getSectionSource('status')?.owner?.value"
                            class="text-xs text-muted-foreground"
                        >
                            <span class="font-medium"
                                >{{
                                    getSectionSource('status')?.owner?.label
                                }}:</span
                            >
                            {{ getSectionSource('status')?.owner?.value }}
                        </div>
                        <div
                            v-if="getSectionSource('status')?.notes?.trim()"
                            class="rounded border border-border/40 bg-background/50 p-2 text-xs leading-relaxed text-foreground/80"
                        >
                            {{ getSectionSource('status')?.notes }}
                        </div>
                    </template>
                    <div v-else class="text-muted-foreground/60 italic">
                        No signed definition metadata is available.
                    </div>
                </div>
            </details>
        </div>

        <!-- Section 4: Implementation Follow-up -->
        <div class="rounded-lg border border-border bg-muted/5 p-4.5">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <AlertTriangle class="size-3.5 text-muted-foreground/80" />
                    Implementation Follow-up
                </h3>
                <div class="flex items-center gap-2">
                    <span
                        v-if="props.checklist?.implementation_review"
                        class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                        :class="
                            props.checklist.implementation_review.status ===
                            'ready'
                                ? 'border-emerald-100/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                : 'border-amber-100/30 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                        "
                        data-test="status-badge-implementation_review_followup"
                    >
                        {{ props.checklist.implementation_review.status }}
                    </span>
                    <Button
                        v-if="!isSectionEditing('gaps') && !props.isUnsigned"
                        size="sm"
                        variant="ghost"
                        class="h-6 px-2 text-xs"
                        :disabled="!!activeSection"
                        @click="startSectionEdit('gaps')"
                        data-test="edit-section-gaps-btn"
                    >
                        Edit
                    </Button>
                </div>
            </div>
            <!-- Source Chips -->
            <div
                class="mt-2 flex flex-wrap gap-1.5"
                data-test="source-chips-gaps"
            >
                <span
                    v-for="chip in gapsChips"
                    :key="chip.label"
                    :title="chip.summary"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-medium transition-all duration-200 select-none"
                    :class="
                        chip.status === 'available'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-border/50 bg-muted/40 text-muted-foreground dark:bg-muted/10'
                    "
                    :data-test="`source-chip-${chip.kind}-${chip.status}`"
                >
                    <CheckCircle2
                        v-if="chip.status === 'available'"
                        class="size-2.5 shrink-0 text-emerald-500"
                    />
                    <HelpCircle
                        v-else
                        class="size-2.5 shrink-0 text-muted-foreground/60"
                    />
                    {{ chip.label }}
                </span>
            </div>
            <template v-if="isSectionEditing('gaps')">
                <div class="mt-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label
                                for="gap_current_state"
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Current State</Label
                            >
                            <Input
                                id="gap_current_state"
                                v-model="
                                    draft.implementation_gaps.current_state
                                "
                                :disabled="props.isSaving"
                                placeholder="e.g. Partially tracked"
                                class="h-9 text-sm"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label
                                for="gap_gap"
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Gap</Label
                            >
                            <Input
                                id="gap_gap"
                                v-model="draft.implementation_gaps.gap"
                                :disabled="props.isSaving"
                                placeholder="e.g. Missing historical backlog"
                                class="h-9 text-sm"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label
                                for="gap_owner"
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Responsible Team</Label
                            >
                            <Input
                                id="gap_owner"
                                v-model="draft.implementation_gaps.owner"
                                :disabled="props.isSaving"
                                placeholder="e.g. Data Engineering"
                                class="h-9 text-sm"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label
                                for="gap_next_step"
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >Suggested Next Step</Label
                            >
                            <Input
                                id="gap_next_step"
                                v-model="
                                    draft.implementation_gaps
                                        .suggested_next_step
                                "
                                :disabled="props.isSaving"
                                placeholder="e.g. Sync offline database tables"
                                class="h-9 text-sm"
                            />
                        </div>
                    </div>

                    <!-- Source Cues -->
                    <SourceCue
                        source-name="Controls & Caveats"
                        source-description="current gap details, system owner, and proposed next steps"
                        :show-warning="
                            !props.sourceContext?.signed_definition?.sections
                                ?.length
                        "
                        warning-message="No structured definition fields are available in the signed definition."
                        data-test-cue="implementation-gaps-source-cue"
                        data-test-warning="implementation-gaps-missing-source-cue"
                    />

                    <!-- Local Controls -->
                    <div
                        v-if="!props.isEditing"
                        class="mt-4 flex items-center justify-end gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="cancelSectionEdit('gaps')"
                            :disabled="props.isSaving"
                            class="h-7 px-3 text-xs"
                            data-test="cancel-section-gaps-btn"
                        >
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            @click="saveSection"
                            :disabled="
                                props.isSaving || !isDirtySection('gaps')
                            "
                            class="h-7 px-3 text-xs"
                            data-test="save-section-gaps-btn"
                        >
                            {{ props.isSaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="mt-3">
                    <dl
                        class="grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2"
                    >
                        <div>
                            <dt
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Current State
                            </dt>
                            <dd class="mt-1 text-sm text-foreground/90">
                                <template
                                    v-if="
                                        props.signals?.implementation_gaps
                                            ?.current_state &&
                                        props.signals.implementation_gaps.current_state.trim()
                                    "
                                >
                                    {{
                                        props.signals.implementation_gaps
                                            .current_state
                                    }}
                                </template>
                                <template v-else>
                                    <span
                                        class="text-muted-foreground/60 italic"
                                        >{{ placeholderText }}</span
                                    >
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Gap
                            </dt>
                            <dd class="mt-1 text-sm text-foreground/90">
                                <template
                                    v-if="
                                        props.signals?.implementation_gaps
                                            ?.gap &&
                                        props.signals.implementation_gaps.gap.trim()
                                    "
                                >
                                    {{ props.signals.implementation_gaps.gap }}
                                </template>
                                <template v-else>
                                    <span
                                        class="text-muted-foreground/60 italic"
                                        >{{ placeholderText }}</span
                                    >
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Responsible Team
                            </dt>
                            <dd class="mt-1 text-sm text-foreground/90">
                                <template
                                    v-if="
                                        props.signals?.implementation_gaps
                                            ?.owner &&
                                        props.signals.implementation_gaps.owner.trim()
                                    "
                                >
                                    {{
                                        props.signals.implementation_gaps.owner
                                    }}
                                </template>
                                <template v-else>
                                    <span
                                        class="text-muted-foreground/60 italic"
                                        >{{ placeholderText }}</span
                                    >
                                </template>
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Suggested Next Step
                            </dt>
                            <dd class="mt-1 text-sm text-foreground/90">
                                <template
                                    v-if="
                                        props.signals?.implementation_gaps
                                            ?.suggested_next_step &&
                                        props.signals.implementation_gaps.suggested_next_step.trim()
                                    "
                                >
                                    {{
                                        props.signals.implementation_gaps
                                            .suggested_next_step
                                    }}
                                </template>
                                <template v-else>
                                    <span
                                        class="text-muted-foreground/60 italic"
                                        >{{ placeholderText }}</span
                                    >
                                </template>
                            </dd>
                        </div>
                    </dl>
                </div>
            </template>

            <!-- Section-Local Source Reference -->
            <details
                v-if="props.sourceContext"
                class="group mt-3.5 rounded-md border border-border/60 bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                data-test="source-reference-gaps"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-1 font-bold text-foreground/80 select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <span>Source Reference: Controls & Caveats</span>
                    <span
                        class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                        >(click to view)</span
                    >
                    <span
                        class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                        >(click to hide)</span
                    >
                </summary>
                <div class="mt-2 space-y-2.5 text-foreground/90">
                    <DefinitionSourceReference
                        :items="getSectionSource('gaps')?.items"
                        empty-text="No controls or caveats fields are available."
                    />
                </div>
            </details>
        </div>

        <!-- Section 5: Operational Validation Checklist -->
        <div class="rounded-lg border border-border bg-muted/5 p-4.5">
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <CheckCircle2 class="size-3.5 text-muted-foreground/80" />
                    Operational Validation Checklist
                </h3>
                <div class="flex items-center gap-2">
                    <span
                        v-if="props.checklist?.validation_guidance"
                        class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                        :class="
                            props.checklist.validation_guidance.status ===
                            'ready'
                                ? 'border-emerald-100/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                : 'border-amber-100/30 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                        "
                        data-test="status-badge-validation_guidance"
                    >
                        {{ props.checklist.validation_guidance.status }}
                    </span>
                    <Button
                        v-if="
                            !isSectionEditing('validation') && !props.isUnsigned
                        "
                        size="sm"
                        variant="ghost"
                        class="h-6 px-2 text-xs"
                        :disabled="!!activeSection"
                        @click="startSectionEdit('validation')"
                        data-test="edit-section-validation-btn"
                    >
                        Edit
                    </Button>
                </div>
            </div>
            <!-- Source Chips -->
            <div
                class="mt-2 flex flex-wrap gap-1.5"
                data-test="source-chips-validation"
            >
                <span
                    v-for="chip in validationChips"
                    :key="chip.label"
                    :title="chip.summary"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-medium transition-all duration-200 select-none"
                    :class="
                        chip.status === 'available'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-border/50 bg-muted/40 text-muted-foreground dark:bg-muted/10'
                    "
                    :data-test="`source-chip-${chip.kind}-${chip.status}`"
                >
                    <CheckCircle2
                        v-if="chip.status === 'available'"
                        class="size-2.5 shrink-0 text-emerald-500"
                    />
                    <HelpCircle
                        v-else
                        class="size-2.5 shrink-0 text-muted-foreground/60"
                    />
                    {{ chip.label }}
                </span>
            </div>
            <template v-if="isSectionEditing('validation')">
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label
                            for="validation_steps"
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Validation Steps</Label
                        >
                        <Textarea
                            id="validation_steps"
                            v-model="draft.validation_steps"
                            :disabled="props.isSaving"
                            placeholder="List steps to validate this metric..."
                            class="min-h-[100px] text-sm"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label
                            for="common_mismatch_causes"
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >Common Mismatch Causes</Label
                        >
                        <Textarea
                            id="common_mismatch_causes"
                            v-model="draft.common_mismatch_causes"
                            :disabled="props.isSaving"
                            placeholder="List known reasons for discrepancies..."
                            class="min-h-[100px] text-sm"
                        />
                    </div>
                </div>

                <!-- Source Cues -->
                <SourceCue
                    source-name="Measurement Logic & Rules"
                    source-description="measurement logic, definition caveats, and mismatch causes"
                    :show-warning="
                        !props.sourceContext?.signed_definition?.sections
                            ?.length
                    "
                    warning-message="No structured definition fields are available in the signed definition."
                    data-test-cue="validation-checklist-source-cue"
                    data-test-warning="validation-checklist-missing-source-cue"
                />

                <!-- Local Controls -->
                <div
                    v-if="!props.isEditing"
                    class="mt-4 flex items-center justify-end gap-2"
                >
                    <Button
                        size="sm"
                        variant="outline"
                        @click="cancelSectionEdit('validation')"
                        :disabled="props.isSaving"
                        class="h-7 px-3 text-xs"
                        data-test="cancel-section-validation-btn"
                    >
                        Cancel
                    </Button>
                    <Button
                        size="sm"
                        @click="saveSection"
                        :disabled="
                            props.isSaving || !isDirtySection('validation')
                        "
                        class="h-7 px-3 text-xs"
                        data-test="save-section-validation-btn"
                    >
                        {{ props.isSaving ? 'Saving...' : 'Save' }}
                    </Button>
                </div>
            </template>
            <template v-else>
                <div
                    class="mt-3 grid grid-cols-1 gap-x-4 gap-y-3.5 sm:grid-cols-2"
                >
                    <div>
                        <h4
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Validation Steps
                        </h4>
                        <div
                            class="mt-1 text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                        >
                            <template
                                v-if="
                                    props.signals?.validation_steps &&
                                    props.signals.validation_steps.trim()
                                "
                            >
                                {{ props.signals.validation_steps }}
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground/60 italic">{{
                                    placeholderText
                                }}</span>
                            </template>
                        </div>
                    </div>
                    <div>
                        <h4
                            class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Common Mismatch Causes
                        </h4>
                        <div
                            class="mt-1 text-sm leading-relaxed whitespace-pre-line text-foreground/90"
                        >
                            <template
                                v-if="
                                    props.signals?.common_mismatch_causes &&
                                    props.signals.common_mismatch_causes.trim()
                                "
                            >
                                {{ props.signals.common_mismatch_causes }}
                            </template>
                            <template v-else>
                                <span class="text-muted-foreground/60 italic">{{
                                    placeholderText
                                }}</span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Section-Local Source Reference -->
            <details
                v-if="props.sourceContext"
                class="group mt-3.5 rounded-md border border-border/60 bg-muted/30 p-3 text-xs leading-relaxed text-muted-foreground"
                data-test="source-reference-validation"
            >
                <summary
                    class="flex cursor-pointer list-none items-center gap-1 font-bold text-foreground/80 select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&::-webkit-details-marker]:hidden"
                >
                    <span>Source Reference: Measurement Logic & Rules</span>
                    <span
                        class="text-[10px] font-normal text-muted-foreground/60 group-open:hidden"
                        >(click to view)</span
                    >
                    <span
                        class="hidden text-[10px] font-normal text-muted-foreground/60 group-open:inline"
                        >(click to hide)</span
                    >
                </summary>
                <div class="mt-2 space-y-2.5 text-foreground/90">
                    <DefinitionSourceReference
                        :items="getSectionSource('validation')?.items"
                        empty-text="No measurement logic or rules fields are available."
                    />
                </div>
            </details>
        </div>
    </div>
</template>
