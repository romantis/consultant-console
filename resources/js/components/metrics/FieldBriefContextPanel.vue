<script setup lang="ts">
import SourceReferenceGroups from '@/components/metrics/SourceReferenceGroups.vue';
import type {
    BriefStakeholder,
    FieldBriefContext,
    RecommendedQuestion,
    SourceRef,
    StakeholderPosition,
} from '@/types';
import { HelpCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    briefInfo: FieldBriefContext;
    fallbackQuestion?: RecommendedQuestion | null;
    segmentTitle?: string | null;
}>();

const questions = computed(() => {
    if (props.briefInfo.recommendedQuestions.length > 0) {
        return props.briefInfo.recommendedQuestions;
    }

    return props.fallbackQuestion ? [props.fallbackQuestion] : [];
});

const normalizeStr = (str: string): string =>
    str.toLowerCase().replace(/[?.!]/g, '').trim();

const isDuplicate = (qText: string): boolean => {
    if (!props.segmentTitle) {
        return false;
    }
    return normalizeStr(qText) === normalizeStr(props.segmentTitle);
};

const filteredQuestions = computed(() => {
    return questions.value.filter((q) => !isDuplicate(q.question));
});

const hasFieldPrepContext = computed(
    () =>
        props.briefInfo.conflicts.length > 0 ||
        props.briefInfo.decisions.length > 0 ||
        props.briefInfo.missing.length > 0 ||
        props.briefInfo.stakeholderPositions.length > 0 ||
        filteredQuestions.value.length > 0,
);

// The T039 respondent-positions grid presents each submitted answer (with
// affiliation + nested companion answers) as the canonical evidence. When it is
// shown, the same answers must not also render as a "Submitted Answers" source
// group inside the attention items, or the left column shows each answer twice.
const hasRespondentPositions = computed(
    () => props.briefInfo.stakeholderPositions.length > 0,
);

// When the respondent-positions grid is shown it already makes the divergence
// concrete, so the machine-style conflict rationale (e.g. "Stakeholders
// submitted 2 different values for X") only restates what the submitted answers
// prove. Decision and missing rationale is kept because it explains why the
// field is on the agenda rather than re-describing visible submissions.
const showConflictRationale = computed(() => !hasRespondentPositions.value);

/**
 * Respondent role/team context for the position label, e.g. the "Finance" in
 * "Lia Price (Finance)". Returns null when no affiliation is available so the
 * label stays as the plain name.
 */
const respondentAffiliation = (
    stakeholder: BriefStakeholder,
): string | null => {
    const affiliation = stakeholder.affiliation?.trim();

    return affiliation ? affiliation : null;
};

/**
 * Raw submitted answer text shown verbatim. Display value is preferred (it
 * resolves option keys to labels); the raw value is the fallback. Never marked
 * as generated — this is source evidence.
 */
const primaryAnswerText = (position: StakeholderPosition): string => {
    if (position.display_value !== '') {
        return position.display_value;
    }

    if (
        position.raw_value !== null &&
        position.raw_value !== undefined &&
        position.raw_value !== ''
    ) {
        return String(position.raw_value);
    }

    return '—';
};

/**
 * Companion answers (e.g. a `_notes` field) submitted by the same respondent for
 * the primary answer, matched by definition id so each companion renders
 * directly under that respondent's primary answer.
 */
const companionRowsForDefinition = (
    definitionId: StakeholderPosition['definition_id'],
): { key: string; label: string; value: string }[] => {
    const rows: { key: string; label: string; value: string }[] = [];

    props.briefInfo.companionAnswers.forEach((group) => {
        group.answers.forEach((answer) => {
            if (String(answer.definition_id) === String(definitionId)) {
                rows.push({
                    key: `${group.companion_field_key}:${answer.definition_id}`,
                    label: group.companion_field_label,
                    value: answer.display_value,
                });
            }
        });
    });

    return rows;
};

const visibleQuestionSourceRefs = (
    question: RecommendedQuestion,
): SourceRef[] =>
    question.source_refs.filter((sourceRef) => {
        const isSelfSourcedWorkshopPrompt =
            sourceRef.type === 'field_guidance' &&
            sourceRef.meta?.guidance_kind === 'workshop_prompt' &&
            sourceRef.excerpt === question.question;

        // Workshop cues are facilitation help, rendered in the right column
        // instead of the left source evidence groups (T040).
        return (
            !isSelfSourcedWorkshopPrompt &&
            sourceRef.type !== 'alignment_signal'
        );
    });

const visibleAttentionSourceRefs = (sourceRefs: SourceRef[]): SourceRef[] =>
    sourceRefs.filter((sourceRef) => {
        const promotedFieldGuidance =
            sourceRef.type === 'field_guidance' &&
            (sourceRef.meta?.guidance_kind === 'workshop_prompt' ||
                sourceRef.meta?.guidance_kind === 'workshop_guidance');

        // The respondent-positions grid already shows these submitted answers,
        // so drop the duplicate source group when that grid is present.
        const duplicatedSubmittedAnswer =
            hasRespondentPositions.value &&
            sourceRef.type === 'submitted_answer';

        // Workshop cues are facilitation help, rendered in the right column
        // instead of the left source evidence groups (T040).
        return (
            !promotedFieldGuidance &&
            !duplicatedSubmittedAnswer &&
            sourceRef.type !== 'alignment_signal'
        );
    });
</script>

<template>
    <div
        v-if="hasFieldPrepContext"
        class="space-y-4"
        data-test="field-brief-context"
    >
        <h4
            class="text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
        >
            Source Context & Discussion Prep
        </h4>
        <div class="space-y-3">
            <div
                v-for="conflict in briefInfo.conflicts"
                :key="conflict.id"
                class="border-l-2 border-destructive py-1 pl-3 text-xs"
                data-test="field-conflict-item"
                :data-test-id="conflict.id"
            >
                <div
                    class="font-bold text-destructive"
                    data-test="conflict-title"
                >
                    {{ conflict.title }}
                </div>
                <p
                    v-if="showConflictRationale"
                    class="mt-1 text-muted-foreground"
                    data-test="attention-rationale"
                >
                    {{ conflict.rationale }}
                </p>
                <div
                    v-if="
                        visibleAttentionSourceRefs(conflict.source_refs)
                            .length > 0
                    "
                    class="mt-2"
                >
                    <SourceReferenceGroups
                        :source-refs="
                            visibleAttentionSourceRefs(conflict.source_refs)
                        "
                    />
                </div>
            </div>

            <div
                v-for="decision in briefInfo.decisions"
                :key="decision.id"
                class="border-l-2 border-blue-500 py-1 pl-3 text-xs"
                data-test="field-decision-item"
                :data-test-id="decision.id"
            >
                <div
                    class="font-bold text-blue-600 dark:text-blue-400"
                    data-test="attention-title"
                >
                    {{ decision.title }}
                </div>
                <p
                    class="mt-1 text-muted-foreground"
                    data-test="attention-rationale"
                >
                    {{ decision.rationale }}
                </p>
                <div
                    v-if="
                        visibleAttentionSourceRefs(decision.source_refs)
                            .length > 0
                    "
                    class="mt-2"
                >
                    <SourceReferenceGroups
                        :source-refs="
                            visibleAttentionSourceRefs(decision.source_refs)
                        "
                    />
                </div>
            </div>

            <div
                v-for="miss in briefInfo.missing"
                :key="miss.id"
                class="border-l-2 border-amber-500 py-1 pl-3 text-xs"
                data-test="field-missing-item"
                :data-test-id="miss.id"
            >
                <div
                    class="font-bold text-amber-600 dark:text-amber-400"
                    data-test="missing-title"
                >
                    {{ miss.title }}
                </div>
                <p
                    class="mt-1 text-muted-foreground"
                    data-test="attention-rationale"
                >
                    {{ miss.rationale }}
                </p>
                <div
                    v-if="
                        visibleAttentionSourceRefs(miss.source_refs).length > 0
                    "
                    class="mt-2"
                >
                    <SourceReferenceGroups
                        :source-refs="
                            visibleAttentionSourceRefs(miss.source_refs)
                        "
                    />
                </div>
            </div>
        </div>

        <!-- Submitted positions: each respondent's primary answer with their
        companion answers nested directly underneath (T039). Raw evidence, never
        marked as generated. -->
        <div
            v-if="briefInfo.stakeholderPositions.length > 0"
            class="grid gap-4 md:grid-cols-2"
            data-test="field-respondent-positions"
        >
            <div
                v-for="position in briefInfo.stakeholderPositions"
                :key="position.definition_id"
                class="border-l border-indigo-200 pl-3 text-xs dark:border-indigo-900/50"
                data-test="respondent-position"
                :data-test-id="position.definition_id"
            >
                <p
                    class="font-semibold text-foreground"
                    data-test="respondent-name"
                >
                    {{ position.stakeholder.name
                    }}<span
                        v-if="respondentAffiliation(position.stakeholder)"
                        class="font-normal text-muted-foreground"
                    >
                        ({{
                            respondentAffiliation(position.stakeholder)
                        }})</span
                    >
                </p>
                <p
                    class="mt-1 leading-relaxed text-muted-foreground"
                    data-test="respondent-primary-answer"
                >
                    {{ primaryAnswerText(position) }}
                </p>
                <div
                    v-for="companion in companionRowsForDefinition(
                        position.definition_id,
                    )"
                    :key="companion.key"
                    class="mt-2 border-l border-border pl-2"
                    data-test="companion-answer"
                >
                    <p
                        class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                        data-test="companion-answer-label"
                    >
                        {{ companion.label }}
                    </p>
                    <p
                        class="mt-1 leading-relaxed text-muted-foreground"
                        data-test="companion-answer-value"
                    >
                        {{ companion.value }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="filteredQuestions.length > 0"
            class="mt-3 space-y-2 border-t pt-3"
        >
            <h5
                class="flex items-center gap-1 text-[10px] font-bold tracking-wide text-muted-foreground uppercase"
            >
                <HelpCircle class="size-3 text-muted-foreground" />
                Supplemental prompts ({{ filteredQuestions.length }})
            </h5>
            <div class="space-y-2" data-test="questions-list">
                <div
                    v-for="q in filteredQuestions"
                    :key="q.id"
                    class="rounded-lg border border-l-2 bg-muted/20 p-2.5 text-xs"
                    :class="[
                        q.priority === 'high'
                            ? 'border-l-destructive'
                            : q.priority === 'medium'
                              ? 'border-l-amber-500'
                              : 'border-l-muted-foreground/30',
                    ]"
                    data-test="question-item"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p
                            class="leading-snug font-semibold text-foreground"
                            data-test="question-text"
                        >
                            {{ q.question }}
                        </p>
                        <span
                            class="shrink-0 text-[10px] text-muted-foreground capitalize"
                        >
                            {{ q.priority }}
                        </span>
                    </div>
                    <p
                        v-if="q.reason"
                        class="mt-1 text-muted-foreground"
                        data-test="question-reason"
                    >
                        {{ q.reason }}
                    </p>
                    <div
                        v-if="visibleQuestionSourceRefs(q).length > 0"
                        class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1"
                    >
                        <span class="text-[10px] text-muted-foreground"
                            >Sources</span
                        >
                        <span
                            v-for="refItem in visibleQuestionSourceRefs(q)"
                            :key="refItem.id"
                            class="cursor-help text-[10px] text-muted-foreground underline decoration-dotted underline-offset-2"
                            :title="
                                refItem.excerpt !== null &&
                                refItem.excerpt !== undefined &&
                                refItem.excerpt !== ''
                                    ? `${refItem.label}: ${refItem.excerpt}`
                                    : refItem.label
                            "
                            data-test="question-source-ref"
                        >
                            {{ refItem.label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
