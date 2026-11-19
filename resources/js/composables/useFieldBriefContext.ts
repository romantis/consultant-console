import { buildDeterministicWorkshopCue } from '@/lib/workshopCues';
import type {
    FieldBriefContext,
    FieldRole,
    MetricAlignmentBrief,
    WorkshopCue,
} from '@/types';
import { computed, type MaybeRefOrGetter, toValue } from 'vue';

export type FieldAttentionTier =
    | 'must-resolve'
    | 'missing-evidence'
    | 'needs-confirmation';

export function useFieldBriefContext(
    fields: MaybeRefOrGetter<FieldRole[] | undefined>,
    brief: MaybeRefOrGetter<MetricAlignmentBrief | null | undefined>,
) {
    const briefContextByKey = computed<Record<string, FieldBriefContext>>(
        () => {
            const context: Record<string, FieldBriefContext> = {};
            const resolvedFields = toValue(fields) ?? [];
            const resolvedBrief = toValue(brief);

            // Initialize with empty arrays for all fields
            resolvedFields.forEach((field) => {
                context[field.key] = {
                    conflicts: [],
                    decisions: [],
                    missing: [],
                    recommendedQuestions: [],
                    companionAnswers: [],
                    stakeholderPositions: [],
                    cueContext: null,
                    workshopCue: null,
                };
            });

            if (!resolvedBrief) {
                return context;
            }

            // Distribute material_conflicts, decision_needed, missing_evidence
            resolvedBrief.material_conflicts?.forEach((item) => {
                if (context[item.field_key]) {
                    context[item.field_key].conflicts.push(item);
                }
            });
            resolvedBrief.decision_needed?.forEach((item) => {
                if (context[item.field_key]) {
                    context[item.field_key].decisions.push(item);
                }
            });
            resolvedBrief.missing_evidence?.forEach((item) => {
                if (context[item.field_key]) {
                    context[item.field_key].missing.push(item);
                }
            });

            // Distribute recommended_questions to recommendedQuestions
            resolvedBrief.recommended_questions?.forEach((item) => {
                if (context[item.field_key]) {
                    context[item.field_key].recommendedQuestions.push(item);
                }
            });

            // Attach companion answer groups under the primary field they explain
            resolvedBrief.companion_answers?.forEach((group) => {
                if (context[group.primary_field_key]) {
                    context[group.primary_field_key].companionAnswers.push(
                        group,
                    );
                }
            });

            // Distribute primary submitted positions so the expanded field prep
            // can render each respondent's answer with companion answers nested
            // directly underneath (T039).
            resolvedBrief.stakeholder_positions?.forEach((position) => {
                if (context[position.field_key]) {
                    context[position.field_key].stakeholderPositions.push(
                        position,
                    );
                }
            });

            // Deterministic future cue-generation input, keyed by the field-bound
            // segment it belongs to. Read-only context only: it must not affect
            // plan dirty state on its own.
            resolvedBrief.cue_contexts?.forEach((cueContext) => {
                if (context[cueContext.field_key]) {
                    context[cueContext.field_key].cueContext = cueContext;
                }
            });

            const explicitCueByFieldKey = new Map<string, WorkshopCue>();

            resolvedBrief.workshop_cues?.forEach((workshopCue) => {
                if (context[workshopCue.field_key]) {
                    explicitCueByFieldKey.set(
                        workshopCue.field_key,
                        workshopCue,
                    );
                }
            });

            Object.entries(context).forEach(([fieldKey, fieldContext]) => {
                const explicitCue = explicitCueByFieldKey.get(fieldKey);

                if (explicitCue) {
                    fieldContext.workshopCue = explicitCue;

                    return;
                }

                if (fieldContext.cueContext) {
                    // Until a provider-backed cue payload exists, render the
                    // review UX with a deterministic local fixture derived from
                    // the typed cue context so the UI contract stays stable.
                    fieldContext.workshopCue = buildDeterministicWorkshopCue(
                        fieldContext.cueContext,
                    );
                }
            });

            return context;
        },
    );

    const fieldAttentionTierByKey = computed<
        Partial<Record<string, FieldAttentionTier>>
    >(() => {
        const tiers: Partial<Record<string, FieldAttentionTier>> = {};
        const resolvedFields = toValue(fields) ?? [];

        resolvedFields.forEach((field) => {
            const ctx = briefContextByKey.value[field.key];

            if (!ctx) {
                return;
            }

            if (ctx.conflicts.length > 0) {
                tiers[field.key] = 'must-resolve';

                return;
            }

            if (ctx.missing.length > 0) {
                tiers[field.key] = 'missing-evidence';

                return;
            }

            if (ctx.decisions.length > 0) {
                tiers[field.key] = 'needs-confirmation';
            }
        });

        return tiers;
    });

    return {
        briefContextByKey,
        fieldAttentionTierByKey,
    };
}
