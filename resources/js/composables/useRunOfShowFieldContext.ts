import type { FieldAttentionTier } from '@/composables/useFieldBriefContext';
import type {
    FacilitationDecisionToLock,
    FacilitationRunOfShowItem,
    FieldBriefContext,
    FieldRole,
    WorkshopCueContext,
} from '@/types';
import { computed, type MaybeRefOrGetter, toValue } from 'vue';

/**
 * The preparation context a single field contributes to a run-of-show segment:
 * its template role metadata and the brief attention items (conflict, decision,
 * missing evidence, recommended questions) that put it on the agenda.
 */
export interface SegmentFieldContext {
    fieldKey: string;
    fieldRole: FieldRole | null;
    briefContext: FieldBriefContext | null;
    cueContext: WorkshopCueContext | null;
    attentionTier: FieldAttentionTier | null;
}

/**
 * A run-of-show segment resolved to its field context. General segments (no
 * `decision_ids`, e.g. framing / next-steps) resolve to no fields and stay
 * `isFieldBound: false` so the UI can keep them compact.
 */
export interface RunOfShowSegmentContext {
    isFieldBound: boolean;
    fields: SegmentFieldContext[];
}

/**
 * Maps each run-of-show segment to the field context it should render, following
 * the existing source of truth without new backend props:
 * `run_of_show[].decision_ids` → `decisions_to_lock[].field_keys` →
 * `FieldRole` + `FieldBriefContext`.
 *
 * The mapping is keyed by segment id and reactive to the editable run-of-show
 * working copy, so reordering, adding, or removing segments keeps the lookup in
 * sync. A field key with no matching role or brief context still produces an
 * entry (with null members) so a field-bound segment is never silently dropped.
 */
export function useRunOfShowFieldContext(
    runOfShow: MaybeRefOrGetter<FacilitationRunOfShowItem[] | undefined>,
    decisionsToLock: MaybeRefOrGetter<FacilitationDecisionToLock[] | undefined>,
    fieldRoles: MaybeRefOrGetter<FieldRole[] | undefined>,
    briefContextByKey: MaybeRefOrGetter<
        Record<string, FieldBriefContext> | undefined
    >,
    fieldAttentionTierByKey: MaybeRefOrGetter<
        Partial<Record<string, FieldAttentionTier>> | undefined
    >,
) {
    const segmentContextById = computed<
        Record<string, RunOfShowSegmentContext>
    >(() => {
        const segments = toValue(runOfShow) ?? [];
        const decisions = toValue(decisionsToLock) ?? [];
        const roles = toValue(fieldRoles) ?? [];
        const contextByKey = toValue(briefContextByKey) ?? {};
        const tierByKey = toValue(fieldAttentionTierByKey) ?? {};

        const fieldKeysByDecisionId: Record<string, string[]> = {};
        decisions.forEach((decision) => {
            fieldKeysByDecisionId[decision.id] = decision.field_keys ?? [];
        });

        const roleByKey: Record<string, FieldRole> = {};
        roles.forEach((role) => {
            roleByKey[role.key] = role;
        });

        const result: Record<string, RunOfShowSegmentContext> = {};

        segments.forEach((segment) => {
            // Collect the unique field keys across every decision linked to this
            // segment; deterministic derivation links one field per segment, but
            // resolving a list keeps custom/multi-decision segments correct.
            const fieldKeys: string[] = [];
            (segment.decision_ids ?? []).forEach((decisionId) => {
                (fieldKeysByDecisionId[decisionId] ?? []).forEach(
                    (fieldKey) => {
                        if (fieldKey !== '' && !fieldKeys.includes(fieldKey)) {
                            fieldKeys.push(fieldKey);
                        }
                    },
                );
            });

            const fields: SegmentFieldContext[] = fieldKeys.map((fieldKey) => ({
                fieldKey,
                fieldRole: roleByKey[fieldKey] ?? null,
                briefContext: contextByKey[fieldKey] ?? null,
                cueContext: contextByKey[fieldKey]?.cueContext ?? null,
                attentionTier: tierByKey[fieldKey] ?? null,
            }));

            result[segment.id] = {
                isFieldBound: fields.length > 0,
                fields,
            };
        });

        return result;
    });

    return { segmentContextById };
}
