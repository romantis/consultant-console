<script setup lang="ts">
import FieldBriefContextPanel from '@/components/metrics/FieldBriefContextPanel.vue';
import ReadinessDecisionSegmentSupport from '@/components/metrics/ReadinessDecisionSegmentSupport.vue';
import WorkshopCuePanel from '@/components/metrics/WorkshopCuePanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { FieldAttentionTier } from '@/composables/useFieldBriefContext';
import { useRunOfShowFieldContext } from '@/composables/useRunOfShowFieldContext';
import { vAutoResize } from '@/lib/autoResize';
import type {
    FacilitationBrief,
    FacilitationDecisionToLock,
    FacilitationProvenance,
    FacilitationRunOfShowItem,
    FieldBriefContext,
    FieldRole,
    ReadinessDecision,
    RecommendedQuestion,
} from '@/types';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ClipboardCheck,
    GripVertical,
    Plus,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type EditableStatus = 'draft' | 'ready_for_workshop';

const props = withDefaults(
    defineProps<{
        brief: FacilitationBrief | null;
        canPrepare: boolean;
        preparing: boolean;
        saving: boolean;
        prepareError: string | null;
        saveError: string | null;
        fieldRoles?: FieldRole[];
        briefContextByKey?: Record<string, FieldBriefContext>;
        fieldAttentionTierByKey?: Partial<Record<string, FieldAttentionTier>>;
        // A field key targeted by a #field-detail anchor. The matching field-bound
        // segment expands and briefly pulses, making the integrated segment the
        // single field-prep surface that anchor navigation lands on (T028).
        pulseFieldKey?: string | null;
        // Deterministic readiness-pack decisions, used to enrich the matching
        // field-bound segment with why-this-matters, capture requirements, and
        // related-decision hints (Task 5A). The top-level pack carries the
        // workshop-level script; the per-decision detail lands here.
        readinessDecisions?: ReadinessDecision[];
    }>(),
    {
        fieldRoles: () => [],
        briefContextByKey: () => ({}),
        fieldAttentionTierByKey: () => ({}),
        pulseFieldKey: null,
        readinessDecisions: () => [],
    },
);

const emit = defineEmits<{
    prepare: [];
    save: [
        {
            revision: number;
            status: EditableStatus;
            run_of_show: FacilitationRunOfShowItem[];
        },
    ];
}>();

const runOfShow = ref<FacilitationRunOfShowItem[]>([]);
const status = ref<EditableStatus>('draft');

// Per-segment field context, resolved from the saved decisions on the brief and
// the editable run-of-show working copy. Typed access for the expanded segment
// body (T026); the visible UI is unchanged for now.
const decisionsToLock = computed<FacilitationDecisionToLock[]>(
    () => props.brief?.document.decisions_to_lock ?? [],
);

const { segmentContextById } = useRunOfShowFieldContext(
    runOfShow,
    decisionsToLock,
    () => props.fieldRoles,
    () => props.briefContextByKey,
    () => props.fieldAttentionTierByKey,
);

// Readiness-pack decision lookups, kept explicit so a segment is enriched only
// from the typed pack — never inferred from rendered DOM or field-name guesses.
const readinessDecisionByItemId = computed<Record<string, ReadinessDecision>>(
    () => {
        const map: Record<string, ReadinessDecision> = {};
        props.readinessDecisions.forEach((decision) => {
            if (decision.run_of_show_item_id) {
                map[decision.run_of_show_item_id] = decision;
            }
        });

        return map;
    },
);

const readinessDecisionByFieldKey = computed<Record<string, ReadinessDecision>>(
    () => {
        const map: Record<string, ReadinessDecision> = {};
        props.readinessDecisions.forEach((decision) => {
            map[decision.field_key] = decision;
        });

        return map;
    },
);

const readinessDecisionLabelsById = computed<
    Record<string, { label: string; field_key: string }>
>(() => {
    const map: Record<string, { label: string; field_key: string }> = {};
    props.readinessDecisions.forEach((decision) => {
        map[decision.id] = {
            label: decision.field_label,
            field_key: decision.field_key,
        };
    });

    return map;
});

/**
 * Resolve the readiness decision for a field-bound segment: by run-of-show item
 * id first, then by field key as a fallback.
 */
const readinessDecisionForField = (
    itemId: string,
    fieldKey: string,
): ReadinessDecision | null =>
    readinessDecisionByItemId.value[itemId] ??
    readinessDecisionByFieldKey.value[fieldKey] ??
    null;

// One field-bound segment is expanded at a time, matching the field-evidence
// accordion's mental model. General segments have no expanded body.
const expandedSegmentId = ref<string | null>(null);

const toggleSegment = (segmentId: string): void => {
    expandedSegmentId.value =
        expandedSegmentId.value === segmentId ? null : segmentId;
};

const emptyFieldBriefContext: FieldBriefContext = {
    conflicts: [],
    decisions: [],
    missing: [],
    recommendedQuestions: [],
    companionAnswers: [],
    stakeholderPositions: [],
    cueContext: null,
    workshopCue: null,
};

const tierLabels: Record<FieldAttentionTier, string> = {
    'must-resolve': 'Must resolve',
    'missing-evidence': 'Missing evidence',
    'needs-confirmation': 'Needs confirmation',
};

const tierOrder: FieldAttentionTier[] = [
    'must-resolve',
    'missing-evidence',
    'needs-confirmation',
];

const segmentTier = (segmentId: string): FieldAttentionTier | null => {
    const fields = segmentContextById.value[segmentId]?.fields ?? [];

    return (
        tierOrder.find((tier) =>
            fields.some((field) => field.attentionTier === tier),
        ) ?? null
    );
};

const segmentTierLabel = (segmentId: string): string | null => {
    const tier = segmentTier(segmentId);

    return tier ? tierLabels[tier] : null;
};

/**
 * Field badges for a field-bound segment, resolved to their human label. A field
 * whose role/label is unknown is dropped rather than leaking the raw machine key
 * (e.g. `costs_included`) into the operator UI.
 */
const segmentFieldBadges = (
    segmentId: string,
): Array<{ fieldKey: string; label: string }> =>
    (segmentContextById.value[segmentId]?.fields ?? [])
        .map((field) => ({
            fieldKey: field.fieldKey,
            label: field.fieldRole?.label ?? '',
        }))
        .filter((badge) => badge.label !== '');

const segmentIdForField = (fieldKey: string): string | null =>
    runOfShow.value.find((item) =>
        (segmentContextById.value[item.id]?.fields ?? []).some(
            (field) => field.fieldKey === fieldKey,
        ),
    )?.id ?? null;

// True while a #field-detail anchor targets one of this segment's fields, so the
// integrated segment wrapper carries the brief highlight (formerly on the field
// evidence accordion).
const isSegmentPulsing = (segmentId: string): boolean => {
    const fieldKey = props.pulseFieldKey;

    if (!fieldKey) {
        return false;
    }

    return (segmentContextById.value[segmentId]?.fields ?? []).some(
        (field) => field.fieldKey === fieldKey,
    );
};

/**
 * Derive the template workshop prompt as a fallback facilitator question, so a
 * field whose brief carries no recommended question still surfaces its prompt.
 * Mirrors the field-evidence accordion's behavior.
 */
const workshopPromptQuestion = (
    field: FieldRole | null,
): RecommendedQuestion | null => {
    const question = field?.workshop_prompt?.trim();

    if (!field || !question) {
        return null;
    }

    return {
        id: `field_prompt:${field.key}`,
        field_key: field.key,
        question,
        reason: 'Template workshop prompt.',
        priority: 'medium',
        source_refs: [
            {
                id: `field_guidance:${field.key}:prompt`,
                type: 'field_guidance',
                label: 'Workshop prompt',
                field_key: field.key,
                source_id: null,
                excerpt: question,
                meta: {
                    guidance_kind: 'workshop_prompt',
                },
            },
        ],
    };
};

const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));

const resetWorkingCopy = (brief: FacilitationBrief | null): void => {
    runOfShow.value = brief ? clone(brief.document.run_of_show) : [];
    status.value =
        brief && brief.status === 'ready_for_workshop'
            ? 'ready_for_workshop'
            : 'draft';
    expandedSegmentId.value = null;
};

watch(
    () => [props.brief?.id, props.brief?.revision] as const,
    () => resetWorkingCopy(props.brief),
    { immediate: true },
);

// An anchor/hash navigation targets a field; open the run-of-show segment that
// prepares it so the integrated workspace is the single field-prep surface.
watch(
    () => props.pulseFieldKey,
    (fieldKey) => {
        if (!fieldKey) {
            return;
        }

        const segmentId = segmentIdForField(fieldKey);

        if (segmentId) {
            expandedSegmentId.value = segmentId;
        }
    },
);

const reposition = (): void => {
    runOfShow.value.forEach((item, index) => {
        item.position = index + 1;
    });
};

const moveSegment = (index: number, direction: -1 | 1): void => {
    const target = index + direction;
    if (target < 0 || target >= runOfShow.value.length) {
        return;
    }
    const items = runOfShow.value;
    [items[index], items[target]] = [items[target], items[index]];
    reposition();
};

const updateSegmentPurpose = (index: number, text: string): void => {
    const item = runOfShow.value[index];
    item.purpose.text = text;
    item.purpose.provenance = 'consultant_authored';
};

const removeSegment = (index: number): void => {
    runOfShow.value.splice(index, 1);
    reposition();
};

const addSegment = (): void => {
    runOfShow.value.push({
        id: `segment:custom-${Date.now()}-${runOfShow.value.length}`,
        position: runOfShow.value.length + 1,
        duration_minutes: 10,
        title: 'New segment',
        decision_ids: [],
        purpose: {
            text: '',
            provenance: 'consultant_authored',
            source_item_ids: [],
            citation_ids: [],
        },
    });
};

const totalMinutes = computed<number>(() =>
    runOfShow.value.reduce(
        (total, item) => total + (Number(item.duration_minutes) || 0),
        0,
    ),
);

const provenanceLabel = (provenance: FacilitationProvenance): string =>
    provenance === 'consultant_authored' ? 'Your edit' : 'Suggested';

const isDirty = computed<boolean>(() => {
    if (!props.brief) {
        return false;
    }

    // Expected outputs are hidden (T027) and server-authoritative (T060), so they
    // are not in the save payload and never contribute to dirtiness — only the
    // editable plan fields do.
    return (
        status.value !==
            (props.brief.status === 'ready_for_workshop'
                ? 'ready_for_workshop'
                : 'draft') ||
        JSON.stringify(runOfShow.value) !==
            JSON.stringify(props.brief.document.run_of_show)
    );
});

const canSave = computed<boolean>(
    () => !!props.brief && isDirty.value && !props.saving,
);

const submit = (): void => {
    if (!props.brief || !canSave.value) {
        return;
    }

    emit('save', {
        revision: props.brief.revision,
        status: status.value,
        run_of_show: clone(runOfShow.value),
    });
};

const mustResolveCount = computed<number>(() => {
    return Object.values(props.fieldAttentionTierByKey).filter(
        (tier) => tier === 'must-resolve',
    ).length;
});

const confirmationCount = computed<number>(() => {
    return Object.values(props.fieldAttentionTierByKey).filter(
        (tier) => tier === 'needs-confirmation',
    ).length;
});

const handleHeaderClick = (segmentId: string, event: MouseEvent): void => {
    const target = event.target as HTMLElement;
    if (target.closest('input, textarea, button, a, select')) {
        return;
    }
    toggleSegment(segmentId);
};

defineExpose({
    isDirty,
    segmentContextById,
});
</script>

<template>
    <section
        class="space-y-6"
        data-test="facilitation-plan"
        aria-labelledby="facilitation-plan-heading"
    >
        <div class="space-y-4 px-1 sm:px-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            id="facilitation-plan-heading"
                            class="scroll-mt-24 text-base font-bold text-foreground"
                        >
                            Run of show
                        </h2>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span
                        class="font-bold text-foreground tabular-nums"
                        title="Planned time"
                        data-test="run-of-show-total"
                    >
                        {{ totalMinutes }} min
                        <span class="hidden lg:inline"> planned</span>
                    </span>
                    <span
                        v-if="mustResolveCount > 0"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 dark:text-rose-400"
                        data-test="run-of-show-must-resolve-count"
                    >
                        <span
                            class="size-2 rounded-full bg-rose-500"
                            title="Must resolve"
                        ></span>
                        {{ mustResolveCount
                        }}<span class="hidden lg:inline"> must resolve</span>
                    </span>
                    <span
                        v-if="confirmationCount > 0"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-400"
                        data-test="run-of-show-confirmation-count"
                    >
                        <span
                            class="size-2 rounded-full bg-amber-500"
                            title="To confirm"
                        ></span>
                        {{ confirmationCount
                        }}<span class="hidden lg:inline"> to confirm</span>
                    </span>
                </div>
            </div>

            <div
                v-if="!brief"
                class="flex flex-col gap-4 rounded-md border border-dashed p-5 sm:flex-row sm:items-center sm:justify-between"
                data-test="facilitation-empty"
            >
                <p class="max-w-2xl text-sm text-muted-foreground">
                    {{
                        canPrepare
                            ? 'Prepare a time-boxed run of show from the workshop agenda.'
                            : 'No conflicts, missing evidence, or decisions to confirm yet — there is nothing to prepare for this metric.'
                    }}
                </p>
                <Button
                    type="button"
                    size="sm"
                    :disabled="!canPrepare || preparing"
                    data-test="facilitation-prepare"
                    @click="emit('prepare')"
                >
                    <Sparkles class="mr-1.5 size-4" />
                    {{ preparing ? 'Preparing...' : 'Prepare run of show' }}
                </Button>
            </div>

            <template v-else>
                <p
                    v-if="brief.needs_source_review"
                    class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-300"
                    data-test="facilitation-needs-review"
                    role="status"
                >
                    The source brief changed after this plan was prepared.
                    Review the run of show against the current decisions before
                    the workshop.
                </p>

                <div class="space-y-3 pb-24" data-test="run-of-show">
                    <ol class="space-y-3" data-test="run-of-show-list">
                        <li
                            v-for="(item, index) in runOfShow"
                            :key="item.id"
                            :class="[
                                'group overflow-hidden rounded-xl border bg-card shadow-xs transition-all',
                                expandedSegmentId === item.id
                                    ? 'border-border shadow-sm ring-1 ring-primary/20'
                                    : '',
                                { 'evidence-pulse': isSegmentPulsing(item.id) },
                            ]"
                            data-test="run-of-show-item"
                            :data-pulsing="
                                isSegmentPulsing(item.id) ? 'true' : undefined
                            "
                        >
                            <!-- Segment header row -->
                            <div
                                class="flex flex-col gap-3 p-3 select-none sm:flex-row sm:items-center sm:justify-between"
                                :class="{
                                    'cursor-pointer transition-colors hover:bg-muted/30':
                                        segmentContextById[item.id]
                                            ?.isFieldBound,
                                }"
                                @click="
                                    segmentContextById[item.id]?.isFieldBound &&
                                    handleHeaderClick(item.id, $event)
                                "
                            >
                                <!-- Left column: index, kind badge, title, and metadata -->
                                <div class="min-w-0 flex-1 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <GripVertical
                                            class="size-4 shrink-0 cursor-grab text-muted-foreground/60"
                                            aria-hidden="true"
                                            data-test="run-of-show-drag-handle"
                                        />
                                        <span
                                            class="text-xs font-semibold text-muted-foreground tabular-nums"
                                        >
                                            {{ index + 1 }}.
                                        </span>
                                        <span
                                            v-if="
                                                !segmentContextById[item.id]
                                                    ?.isFieldBound
                                            "
                                            class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{
                                                item.id.includes('framing')
                                                    ? 'Opening'
                                                    : item.id.includes(
                                                            'next-steps',
                                                        )
                                                      ? 'Closing'
                                                      : 'General'
                                            }}
                                        </span>
                                        <!-- Editable title (primary facilitator question) -->
                                        <input
                                            v-model="item.title"
                                            type="text"
                                            maxlength="180"
                                            class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 py-1 text-sm font-semibold transition-colors hover:border-input focus:border-input focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                            aria-label="Segment title"
                                            data-test="run-of-show-title"
                                        />
                                    </div>

                                    <!-- Badges row for field-bound segment -->
                                    <div
                                        v-if="
                                            segmentContextById[item.id]
                                                ?.isFieldBound
                                        "
                                        class="flex flex-wrap items-center gap-1.5 pl-6"
                                    >
                                        <span
                                            v-for="badge in segmentFieldBadges(
                                                item.id,
                                            )"
                                            :key="`role-${badge.fieldKey}`"
                                            class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground"
                                            data-test="run-of-show-field-badge"
                                        >
                                            {{ badge.label }}
                                        </span>
                                        <Badge
                                            v-if="segmentTierLabel(item.id)"
                                            variant="outline"
                                            class="px-2 py-0.5 text-[10px]"
                                            :class="[
                                                segmentTier(item.id) ===
                                                'must-resolve'
                                                    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300'
                                                    : segmentTier(item.id) ===
                                                        'missing-evidence'
                                                      ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-300'
                                                      : 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/50 dark:bg-blue-950/20 dark:text-blue-300',
                                            ]"
                                            data-test="run-of-show-tier"
                                        >
                                            {{ segmentTierLabel(item.id) }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Right column: duration, toggle, and actions -->
                                <div
                                    class="flex w-full shrink-0 flex-wrap items-center justify-between gap-3 pl-6 sm:w-auto sm:justify-end sm:pl-0"
                                >
                                    <div class="flex items-center gap-3">
                                        <!-- Duration -->
                                        <label
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <input
                                                v-model.number="
                                                    item.duration_minutes
                                                "
                                                type="number"
                                                min="1"
                                                max="180"
                                                class="w-14 rounded-md border bg-background px-2 py-1 text-right text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                aria-label="Segment duration in minutes"
                                                data-test="run-of-show-duration"
                                            />
                                            min
                                        </label>

                                        <!-- Toggle Button for field-bound segment -->
                                        <button
                                            v-if="
                                                segmentContextById[item.id]
                                                    ?.isFieldBound
                                            "
                                            type="button"
                                            class="flex size-8 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground shadow-xs transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            :aria-expanded="
                                                expandedSegmentId === item.id
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            :aria-controls="`run-of-show-field-prep-${item.id}`"
                                            aria-label="Toggle segment details"
                                            data-test="run-of-show-field-prep-toggle"
                                            @click.stop="toggleSegment(item.id)"
                                        >
                                            <ChevronDown
                                                class="size-4 transition-transform duration-200"
                                                :class="{
                                                    'rotate-180':
                                                        expandedSegmentId ===
                                                        item.id,
                                                }"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>

                                    <!-- Action Buttons (Up, Down, Remove) - Always reachable in header -->
                                    <div class="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="index === 0"
                                            aria-label="Move segment earlier"
                                            data-test="run-of-show-move-up"
                                            @click.stop="moveSegment(index, -1)"
                                        >
                                            <ArrowUp class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                index === runOfShow.length - 1
                                            "
                                            aria-label="Move segment later"
                                            data-test="run-of-show-move-down"
                                            @click.stop="moveSegment(index, 1)"
                                        >
                                            <ArrowDown class="size-4" />
                                        </Button>
                                        <Dialog>
                                            <DialogTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-muted-foreground hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive focus-visible:text-destructive"
                                                    aria-label="Remove segment"
                                                    data-test="run-of-show-remove"
                                                    @click.stop
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent
                                                data-test="run-of-show-remove-dialog"
                                            >
                                                <DialogHeader>
                                                    <DialogTitle
                                                        >Remove
                                                        segment?</DialogTitle
                                                    >
                                                    <DialogDescription>
                                                        Are you sure you want to
                                                        remove this segment from
                                                        the run of show? This
                                                        will discard its notes
                                                        and cannot be undone.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <DialogFooter class="gap-2">
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="secondary"
                                                            data-test="run-of-show-remove-cancel"
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </DialogClose>
                                                    <DialogClose as-child>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            data-test="run-of-show-remove-confirm"
                                                            @click="
                                                                removeSegment(
                                                                    index,
                                                                )
                                                            "
                                                        >
                                                            Remove
                                                        </Button>
                                                    </DialogClose>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </div>
                            </div>

                            <!-- Field-bound segment body prep area -->
                            <template
                                v-if="segmentContextById[item.id]?.isFieldBound"
                            >
                                <!-- Always-rendered scroll anchors so
                                #field-detail-{key} navigation lands on the
                                integrated segment even while it is collapsed. -->
                                <span
                                    v-for="field in segmentContextById[item.id]
                                        .fields"
                                    :key="`field-anchor-${field.fieldKey}`"
                                    :id="`field-detail-${field.fieldKey}`"
                                    class="block scroll-mt-24"
                                    aria-hidden="true"
                                />

                                <div
                                    v-if="expandedSegmentId === item.id"
                                    :id="`run-of-show-field-prep-${item.id}`"
                                    class="border-t bg-muted/5 p-4"
                                    data-test="run-of-show-field-prep"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start"
                                    >
                                        <!-- Left column: Situation evidence (Submitted answers, conflicts, decisions, missing) -->
                                        <div class="space-y-4">
                                            <div
                                                v-for="field in segmentContextById[
                                                    item.id
                                                ].fields"
                                                :key="field.fieldKey"
                                                class="space-y-3"
                                                data-test="run-of-show-field"
                                            >
                                                <!-- Why this needs alignment, submitted
                                                answers / source positions, and the
                                                facilitator questions — all read-only. -->
                                                <FieldBriefContextPanel
                                                    :brief-info="
                                                        field.briefContext ??
                                                        emptyFieldBriefContext
                                                    "
                                                    :fallback-question="
                                                        workshopPromptQuestion(
                                                            field.fieldRole,
                                                        )
                                                    "
                                                    :segment-title="item.title"
                                                />
                                            </div>
                                        </div>

                                        <!-- Right column: Facilitation guidance & editable notes -->
                                        <div class="space-y-4">
                                            <!-- Workshop guidance (template-derived) -->
                                            <div class="space-y-3">
                                                <div
                                                    v-for="field in segmentContextById[
                                                        item.id
                                                    ].fields"
                                                    :key="`guidance-${field.fieldKey}`"
                                                >
                                                    <div
                                                        v-if="
                                                            field.fieldRole
                                                                ?.workshop_guidance
                                                        "
                                                        class="rounded-md border-l-2 border-primary/30 bg-primary/5 py-2 pr-3 pl-3"
                                                        data-test="run-of-show-workshop-guidance"
                                                    >
                                                        <p
                                                            class="text-xs font-semibold text-primary"
                                                        >
                                                            Workshop guidance
                                                        </p>
                                                        <p
                                                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                                        >
                                                            {{
                                                                field.fieldRole
                                                                    .workshop_guidance
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Workshop cues are their own
                                            review surface: typed generated
                                            facilitation help with reachable
                                            context and sources, distinct from
                                            both static template guidance and
                                            raw evidence. -->
                                            <div class="space-y-3">
                                                <div
                                                    v-for="field in segmentContextById[
                                                        item.id
                                                    ].fields"
                                                    :key="`cues-${field.fieldKey}`"
                                                >
                                                    <WorkshopCuePanel
                                                        v-if="
                                                            field.briefContext
                                                                ?.workshopCue
                                                        "
                                                        :cue="
                                                            field.briefContext
                                                                .workshopCue
                                                        "
                                                        :cue-context="
                                                            field.cueContext
                                                        "
                                                    />
                                                </div>
                                            </div>

                                            <!-- Readiness-pack prep for this
                                            decision: why it matters, what to
                                            capture, and which decisions move
                                            together. Evidence and field assist
                                            are not repeated here (Task 5A). -->
                                            <div class="space-y-3">
                                                <template
                                                    v-for="field in segmentContextById[
                                                        item.id
                                                    ].fields"
                                                    :key="`readiness-${field.fieldKey}`"
                                                >
                                                    <ReadinessDecisionSegmentSupport
                                                        v-if="
                                                            readinessDecisionForField(
                                                                item.id,
                                                                field.fieldKey,
                                                            )
                                                        "
                                                        :decision="
                                                            readinessDecisionForField(
                                                                item.id,
                                                                field.fieldKey,
                                                            )!
                                                        "
                                                        :decisions-by-id="
                                                            readinessDecisionLabelsById
                                                        "
                                                    />
                                                </template>
                                            </div>

                                            <!-- Facilitator notes (editable) -->
                                            <div
                                                class="space-y-1.5 border-t border-border/50 pt-3"
                                            >
                                                <label
                                                    :for="`run-of-show-notes-${item.id}`"
                                                    class="text-xs font-semibold text-foreground"
                                                >
                                                    Facilitator notes
                                                </label>
                                                <textarea
                                                    v-auto-resize
                                                    :id="`run-of-show-notes-${item.id}`"
                                                    :value="item.purpose.text"
                                                    rows="3"
                                                    maxlength="1200"
                                                    placeholder="Optional: notes for this segment"
                                                    class="w-full rounded-md border bg-background px-2 py-1 text-xs leading-relaxed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                    aria-label="Segment purpose"
                                                    data-test="run-of-show-purpose"
                                                    @input="
                                                        updateSegmentPurpose(
                                                            index,
                                                            (
                                                                $event.target as HTMLTextAreaElement
                                                            ).value,
                                                        )
                                                    "
                                                />
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <Badge
                                                        variant="outline"
                                                        class="border-transparent bg-muted/60 px-1.5 py-0.5 text-[10px] text-muted-foreground hover:bg-muted/60"
                                                        data-test="run-of-show-provenance"
                                                    >
                                                        {{
                                                            provenanceLabel(
                                                                item.purpose
                                                                    .provenance,
                                                            )
                                                        }}
                                                    </Badge>
                                                    <span
                                                        class="text-[10px] text-muted-foreground"
                                                        data-test="run-of-show-save-status"
                                                    >
                                                        <span
                                                            v-if="saving"
                                                            class="animate-pulse"
                                                            >Saving...</span
                                                        >
                                                        <span
                                                            v-else-if="isDirty"
                                                            class="font-medium text-amber-600 dark:text-amber-400"
                                                            >Unsaved
                                                            changes</span
                                                        >
                                                        <span v-else>{{
                                                            status ===
                                                            'ready_for_workshop'
                                                                ? 'Ready for workshop'
                                                                : 'Saved as draft'
                                                        }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- General segment (framing / next steps): stays
                            compact and free of field-context UI. -->
                            <template v-else>
                                <div class="space-y-2 border-t bg-muted/5 p-3">
                                    <textarea
                                        v-auto-resize
                                        :value="item.purpose.text"
                                        rows="3"
                                        maxlength="1200"
                                        placeholder="Optional: notes for this segment"
                                        class="w-full rounded-md border bg-background px-2 py-1 text-xs leading-relaxed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        aria-label="Segment purpose"
                                        data-test="run-of-show-purpose"
                                        @input="
                                            updateSegmentPurpose(
                                                index,
                                                (
                                                    $event.target as HTMLTextAreaElement
                                                ).value,
                                            )
                                        "
                                    />
                                    <Badge
                                        variant="outline"
                                        class="px-2 py-0.5 text-[10px]"
                                        data-test="run-of-show-provenance"
                                    >
                                        {{
                                            provenanceLabel(
                                                item.purpose.provenance,
                                            )
                                        }}
                                    </Badge>
                                </div>
                            </template>
                        </li>
                    </ol>

                    <Button
                        type="button"
                        variant="outline"
                        class="mt-3 border-dashed text-muted-foreground hover:bg-accent hover:text-foreground"
                        size="sm"
                        data-test="run-of-show-add"
                        @click="addSegment"
                    >
                        <Plus class="mr-1.5 size-4" />
                        Add segment
                    </Button>
                </div>

                <!-- Sticky manual Save and status footer -->
                <div
                    class="sticky bottom-0 z-30 -mx-1 flex flex-col gap-3 border-t bg-background/95 p-4 shadow-lg backdrop-blur-sm sm:-mx-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <label
                        class="flex items-center gap-2 text-xs font-semibold text-muted-foreground"
                    >
                        Preparation status
                        <select
                            v-model="status"
                            class="rounded-md border bg-background px-2 py-1 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            data-test="facilitation-status"
                        >
                            <option value="draft">Draft</option>
                            <option value="ready_for_workshop">
                                Ready for workshop
                            </option>
                        </select>
                    </label>

                    <div class="flex flex-col items-end gap-1.5">
                        <div class="flex items-center gap-2">
                            <Dialog>
                                <DialogTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="!isDirty || saving"
                                        data-test="facilitation-discard"
                                    >
                                        Discard changes
                                    </Button>
                                </DialogTrigger>
                                <DialogContent
                                    data-test="facilitation-discard-dialog"
                                >
                                    <DialogHeader>
                                        <DialogTitle
                                            >Discard unsaved
                                            changes?</DialogTitle
                                        >
                                        <DialogDescription>
                                            Are you sure you want to discard
                                            your unsaved changes to this
                                            facilitation plan? This will revert
                                            the plan to the last saved state and
                                            cannot be undone.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter class="gap-2">
                                        <DialogClose as-child>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                data-test="facilitation-discard-cancel"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <DialogClose as-child>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                data-test="facilitation-discard-confirm"
                                                @click="
                                                    resetWorkingCopy(
                                                        props.brief,
                                                    )
                                                "
                                            >
                                                Discard
                                            </Button>
                                        </DialogClose>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>

                            <Button
                                type="button"
                                size="sm"
                                :disabled="!canSave"
                                data-test="facilitation-save"
                                @click="submit"
                            >
                                <ClipboardCheck class="mr-1.5 size-4" />
                                {{ saving ? 'Saving...' : 'Save plan' }}
                            </Button>
                        </div>
                        <p
                            v-if="saveError"
                            class="text-xs text-destructive"
                            data-test="facilitation-save-error"
                        >
                            {{ saveError }}
                        </p>
                    </div>
                </div>
            </template>

            <p
                v-if="prepareError"
                class="text-xs text-destructive"
                data-test="facilitation-prepare-error"
            >
                {{ prepareError }}
            </p>
        </div>
    </section>
</template>

<style scoped>
/*
 * Brief highlight that runs when a #field-detail anchor scrolls to the
 * integrated run-of-show segment, so the facilitator can spot the freshly
 * opened field-prep workspace (moved here from the field evidence accordion).
 */
@keyframes evidence-pulse {
    0% {
        background-color: color-mix(in oklab, var(--primary) 16%, transparent);
        box-shadow: inset 0 0 0 2px
            color-mix(in oklab, var(--primary) 45%, transparent);
    }
    100% {
        background-color: transparent;
        box-shadow: inset 0 0 0 2px transparent;
    }
}

.evidence-pulse {
    animation: evidence-pulse 1.6s ease-out 1;
}

@media (prefers-reduced-motion: reduce) {
    .evidence-pulse {
        animation: none;
    }
}
</style>
