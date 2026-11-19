import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useRunOfShowFieldContext } from '@/composables/useRunOfShowFieldContext';
import type { FieldAttentionTier } from '@/composables/useFieldBriefContext';
import type {
    FacilitationDecisionToLock,
    FacilitationRunOfShowItem,
    FieldBriefContext,
    FieldRole,
    WorkshopCueContext,
} from '@/types';

const segment = (
    id: string,
    decisionIds: string[],
): FacilitationRunOfShowItem => ({
    id,
    position: 1,
    duration_minutes: 10,
    title: id,
    decision_ids: decisionIds,
    purpose: {
        text: '',
        provenance: 'system_suggested',
        source_item_ids: [],
        citation_ids: [],
    },
});

const decision = (
    id: string,
    fieldKeys: string[],
): FacilitationDecisionToLock => ({
    id,
    title: id,
    priority: 'high',
    agenda_topic_ids: [],
    field_keys: fieldKeys,
    decision_prompt: {
        text: '',
        provenance: 'source_backed',
        source_item_ids: [],
        citation_ids: [],
    },
});

const fieldRole = (key: string): FieldRole => ({
    key,
    label: `${key} label`,
    role: 'definition',
    required: true,
    exclude_from_consensus: false,
    companion_of: null,
    cue_context_scope: null,
    executive_section: null,
    value: null,
    unclassified: false,
    workshop_prompt: null,
    workshop_guidance: null,
    alignment_signal: null,
    values: [],
});

const cueContext = (fieldKey: string): WorkshopCueContext => ({
    field_key: fieldKey,
    field_label: `${fieldKey} label`,
    field_role: 'definition',
    stakeholder_positions: [],
    companion_answers: [],
    workshop_guidance: null,
    matrix_context: [],
    citation_ids: [],
});

const briefContext = (): FieldBriefContext => ({
    conflicts: [],
    decisions: [],
    missing: [],
    recommendedQuestions: [],
    companionAnswers: [],
    stakeholderPositions: [],
    cueContext: null,
    workshopCue: null,
});

describe('useRunOfShowFieldContext', () => {
    it('resolves a field-bound segment to its role, brief context, and tier', () => {
        const { segmentContextById } = useRunOfShowFieldContext(
            [segment('segment:lock:cost', ['decision:cost'])],
            [decision('decision:cost', ['cost'])],
            [fieldRole('cost')],
            { cost: briefContext() } as Record<string, FieldBriefContext>,
            { cost: 'must-resolve' } as Partial<
                Record<string, FieldAttentionTier>
            >,
        );

        const context = segmentContextById.value['segment:lock:cost'];
        expect(context.isFieldBound).toBe(true);
        expect(context.fields).toHaveLength(1);
        expect(context.fields[0].fieldKey).toBe('cost');
        expect(context.fields[0].fieldRole?.label).toBe('cost label');
        expect(context.fields[0].briefContext).not.toBeNull();
        expect(context.fields[0].cueContext).toBeNull();
        expect(context.fields[0].attentionTier).toBe('must-resolve');
    });

    it('marks a segment with no decision_ids as not field-bound', () => {
        const { segmentContextById } = useRunOfShowFieldContext(
            [segment('segment:framing', [])],
            [decision('decision:cost', ['cost'])],
            [fieldRole('cost')],
            {},
            {},
        );

        const context = segmentContextById.value['segment:framing'];
        expect(context.isFieldBound).toBe(false);
        expect(context.fields).toEqual([]);
    });

    it('still resolves a field key with no matching role or brief context', () => {
        const { segmentContextById } = useRunOfShowFieldContext(
            [segment('segment:lock:orphan', ['decision:orphan'])],
            [decision('decision:orphan', ['orphan'])],
            [],
            {},
            {},
        );

        const context = segmentContextById.value['segment:lock:orphan'];
        expect(context.isFieldBound).toBe(true);
        expect(context.fields[0].fieldKey).toBe('orphan');
        expect(context.fields[0].fieldRole).toBeNull();
        expect(context.fields[0].briefContext).toBeNull();
        expect(context.fields[0].cueContext).toBeNull();
        expect(context.fields[0].attentionTier).toBeNull();
    });

    it('makes deterministic cue context available on the resolved segment field', () => {
        const { segmentContextById } = useRunOfShowFieldContext(
            [segment('segment:lock:cost', ['decision:cost'])],
            [decision('decision:cost', ['cost'])],
            [fieldRole('cost')],
            {
                cost: {
                    ...briefContext(),
                    cueContext: cueContext('cost'),
                },
            } as Record<string, FieldBriefContext>,
            {},
        );

        const context = segmentContextById.value['segment:lock:cost'];

        expect(context.fields[0].cueContext?.field_key).toBe('cost');
        expect(context.fields[0].cueContext?.matrix_context).toEqual([]);
    });

    it('reacts to edits in the run-of-show working copy', () => {
        const runOfShow = ref<FacilitationRunOfShowItem[]>([
            segment('segment:framing', []),
        ]);

        const { segmentContextById } = useRunOfShowFieldContext(
            runOfShow,
            [decision('decision:cost', ['cost'])],
            [fieldRole('cost')],
            { cost: briefContext() } as Record<string, FieldBriefContext>,
            {},
        );

        expect(
            segmentContextById.value['segment:lock:cost'],
        ).toBeUndefined();

        runOfShow.value.push(segment('segment:lock:cost', ['decision:cost']));

        expect(
            segmentContextById.value['segment:lock:cost'].isFieldBound,
        ).toBe(true);
    });
});
