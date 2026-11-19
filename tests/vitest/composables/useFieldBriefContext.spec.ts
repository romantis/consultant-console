import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useFieldBriefContext } from '@/composables/useFieldBriefContext';
import type {
    CompanionAnswerGroup,
    FieldRole,
    MetricAlignmentBrief,
    StakeholderPosition,
    WorkshopCue,
    WorkshopCueContext,
} from '@/types';

const field = (key: string, overrides: Partial<FieldRole> = {}): FieldRole => ({
    key,
    label: key,
    role: 'definition',
    required: true,
    exclude_from_consensus: false,
    companion_of: null,
    executive_section: 'official_definition',
    value: null,
    unclassified: false,
    workshop_prompt: null,
    workshop_guidance: null,
    alignment_signal: null,
    values: [],
    ...overrides,
});

const brief = (
    overrides: Partial<MetricAlignmentBrief> = {},
): MetricAlignmentBrief => ({
    summary: {
        metric_id: 'm-1',
        metric_name: 'CAC & CPL',
        status: 'InReview',
        source_counts: {
            submitted_answers: 0,
            alignment_signals: 0,
            guided_fields: 0,
            missing_evidence_items: 0,
        },
    },
    stakeholder_positions: [],
    companion_answers: [],
    cue_contexts: [],
    material_conflicts: [],
    decision_needed: [],
    missing_evidence: [],
    context_signals: [],
    likely_non_issues: [],
    recommended_questions: [],
    resolution_options: [],
    draft_alignment_summary: null,
    ...overrides,
});

const companionGroup = (
    primaryFieldKey: string,
    companionFieldKey: string,
): CompanionAnswerGroup => ({
    primary_field_key: primaryFieldKey,
    companion_field_key: companionFieldKey,
    companion_field_label: 'Included and excluded GL/cost center rules',
    companion_field_role: 'context',
    answers: [
        {
            definition_id: 'def-1',
            stakeholder: { id: 'usr-1', name: 'Lia Price' },
            display_value: 'Includes GL 5000-5400.',
            raw_value: 'Includes GL 5000-5400.',
            normalized_value: 'Includes GL 5000-5400.',
            submitted_at: '2026-06-01T10:00:00Z',
            source_refs: [
                {
                    id: 'submitted_answer:def-1:costs_included_notes',
                    type: 'submitted_answer',
                    label: 'Lia Price answer',
                    field_key: 'costs_included_notes',
                    source_id: 'def-1',
                    excerpt: 'Includes GL 5000-5400.',
                },
            ],
        },
    ],
});

const position = (
    fieldKey: string,
    overrides: Partial<StakeholderPosition> = {},
): StakeholderPosition => ({
    field_key: fieldKey,
    field_label: fieldKey,
    stakeholder: { id: 'usr-1', name: 'Lia Price' },
    definition_id: 'def-1',
    raw_value: 'value',
    normalized_value: 'value',
    display_value: 'value',
    submitted_at: '2026-06-01T10:00:00Z',
    source_refs: [],
    ...overrides,
});

const cueContext = (
    fieldKey: string,
    overrides: Partial<WorkshopCueContext> = {},
): WorkshopCueContext => ({
    field_key: fieldKey,
    field_label: `${fieldKey} label`,
    field_role: 'definition',
    stakeholder_positions: [
        position(fieldKey, {
            stakeholder: {
                id: 'usr-1',
                name: 'Lia Price',
                affiliation: 'Finance',
            },
            source_refs: [
                {
                    id: `submitted_answer:def-1:${fieldKey}`,
                    type: 'submitted_answer',
                    label: 'Lia Price answer',
                    field_key: fieldKey,
                    source_id: 'def-1',
                    excerpt: 'Fully loaded',
                },
            ],
        }),
    ],
    companion_answers: [
        companionGroup(fieldKey, `${fieldKey}_notes`),
    ],
    workshop_guidance: {
        text: 'Compare the trade-offs before locking the official baseline.',
        source_refs: [
            {
                id: `field_guidance:${fieldKey}:guidance`,
                type: 'field_guidance',
                label: 'Workshop guidance',
                field_key: fieldKey,
                source_id: null,
                excerpt:
                    'Compare the trade-offs before locking the official baseline.',
                meta: { guidance_kind: 'workshop_guidance' },
            },
        ],
        citation_ids: [`field_guidance:${fieldKey}:guidance`],
    },
    matrix_context: [
        {
            field_key: 'cac_pain_points',
            field_label: 'CAC/CPL divergence points',
            field_role: 'diagnostic',
            stakeholder_positions: [
                position('cac_pain_points', {
                    raw_value: 'Finance needs a board baseline.',
                    normalized_value: 'Finance needs a board baseline.',
                    display_value: 'Finance needs a board baseline.',
                    source_refs: [
                        {
                            id: 'submitted_answer:def-1:cac_pain_points',
                            type: 'submitted_answer',
                            label: 'Lia Price answer',
                            field_key: 'cac_pain_points',
                            source_id: 'def-1',
                            excerpt: 'Finance needs a board baseline.',
                        },
                    ],
                }),
            ],
            source_refs: [
                {
                    id: 'submitted_answer:def-1:cac_pain_points',
                    type: 'submitted_answer',
                    label: 'Lia Price answer',
                    field_key: 'cac_pain_points',
                    source_id: 'def-1',
                    excerpt: 'Finance needs a board baseline.',
                },
            ],
            citation_ids: ['submitted_answer:def-1:cac_pain_points'],
        },
    ],
    citation_ids: [
        `submitted_answer:def-1:${fieldKey}`,
        `field_guidance:${fieldKey}:guidance`,
        'submitted_answer:def-1:cac_pain_points',
    ],
    alignment_signal: null,
    ...overrides,
});

const workshopCue = (
    fieldKey: string,
    overrides: Partial<WorkshopCue> = {},
): WorkshopCue => ({
    id: `workshop_cue:${fieldKey}:fixture`,
    field_key: fieldKey,
    field_label: `${fieldKey} label`,
    status: 'generated',
    edit_state: 'review_only',
    provenance: 'local_fixture',
    why_this_cue: {
        text: 'Current field plus matrix context change the decision.',
        draft_text: 'Current field plus matrix context change the decision.',
        provenance: 'local_fixture',
        source_refs: [
            {
                id: `submitted_answer:def-1:${fieldKey}`,
                type: 'submitted_answer',
                label: 'Lia Price answer',
                field_key: fieldKey,
                source_id: 'def-1',
                excerpt: 'Fully loaded',
            },
        ],
        citation_ids: [`submitted_answer:def-1:${fieldKey}`],
    },
    decision_cue: {
        text: 'Name the official baseline.',
        draft_text: 'Name the official baseline.',
        provenance: 'local_fixture',
        source_refs: [],
        citation_ids: [],
    },
    probe_cue: null,
    risk_cue: null,
    tradeoff_cue: null,
    decision_wording: {
        text: 'Official wording goes here.',
        draft_text: 'Official wording goes here.',
        provenance: 'local_fixture',
        source_refs: [],
        citation_ids: [],
    },
    source_refs: [
        {
            id: `submitted_answer:def-1:${fieldKey}`,
            type: 'submitted_answer',
            label: 'Lia Price answer',
            field_key: fieldKey,
            source_id: 'def-1',
            excerpt: 'Fully loaded',
        },
    ],
    citation_ids: [`submitted_answer:def-1:${fieldKey}`],
    ...overrides,
});

describe('useFieldBriefContext companion answers', () => {
    it('attaches companion answer groups under the primary field they explain', () => {
        const fields = ref<FieldRole[]>([
            field('costs_included'),
            field('costs_included_notes', {
                role: 'context',
                companion_of: 'costs_included',
            }),
        ]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    companion_answers: [
                        companionGroup('costs_included', 'costs_included_notes'),
                    ],
                }),
            ),
        );

        const primary = briefContextByKey.value.costs_included;

        expect(primary.companionAnswers).toHaveLength(1);
        expect(primary.companionAnswers[0].companion_field_key).toBe(
            'costs_included_notes',
        );
        expect(primary.companionAnswers[0].companion_field_label).toBe(
            'Included and excluded GL/cost center rules',
        );
        expect(primary.companionAnswers[0].answers[0].stakeholder.name).toBe(
            'Lia Price',
        );
        expect(primary.companionAnswers[0].answers[0].source_refs[0].id).toBe(
            'submitted_answer:def-1:costs_included_notes',
        );

        // The companion field's own context entry stays free of companion groups.
        expect(
            briefContextByKey.value.costs_included_notes.companionAnswers,
        ).toEqual([]);
    });

    it('initializes empty companion answers when the brief has none', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(fields, ref(brief()));

        expect(briefContextByKey.value.costs_included.companionAnswers).toEqual(
            [],
        );
    });

    it('ignores companion groups whose primary field is absent', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    companion_answers: [
                        companionGroup('nonexistent_field', 'orphan_notes'),
                    ],
                }),
            ),
        );

        expect(briefContextByKey.value.costs_included.companionAnswers).toEqual(
            [],
        );
        expect(briefContextByKey.value.nonexistent_field).toBeUndefined();
    });
});

describe('useFieldBriefContext stakeholder positions', () => {
    it('distributes submitted positions under their field', () => {
        const fields = ref<FieldRole[]>([
            field('costs_included'),
            field('lead_definition'),
        ]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    stakeholder_positions: [
                        position('costs_included', { definition_id: 'def-1' }),
                        position('costs_included', { definition_id: 'def-2' }),
                        position('lead_definition', { definition_id: 'def-3' }),
                    ],
                }),
            ),
        );

        expect(
            briefContextByKey.value.costs_included.stakeholderPositions,
        ).toHaveLength(2);
        expect(
            briefContextByKey.value.lead_definition.stakeholderPositions,
        ).toHaveLength(1);
        expect(
            briefContextByKey.value.lead_definition.stakeholderPositions[0]
                .definition_id,
        ).toBe('def-3');
    });

    it('initializes empty stakeholder positions when the brief has none', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(fields, ref(brief()));

        expect(
            briefContextByKey.value.costs_included.stakeholderPositions,
        ).toEqual([]);
    });

    it('ignores positions whose field is absent', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    stakeholder_positions: [position('nonexistent_field')],
                }),
            ),
        );

        expect(
            briefContextByKey.value.costs_included.stakeholderPositions,
        ).toEqual([]);
        expect(briefContextByKey.value.nonexistent_field).toBeUndefined();
    });
});

describe('useFieldBriefContext cue contexts', () => {
    it('attaches deterministic cue context under the matching field', () => {
        const fields = ref<FieldRole[]>([
            field('costs_included'),
            field('cac_pain_points', { role: 'diagnostic' }),
        ]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    cue_contexts: [cueContext('costs_included')],
                }),
            ),
        );

        expect(briefContextByKey.value.costs_included.cueContext).not.toBeNull();
        expect(
            briefContextByKey.value.costs_included.cueContext?.matrix_context[0]
                .field_key,
        ).toBe('cac_pain_points');
        expect(
            briefContextByKey.value.costs_included.cueContext
                ?.stakeholder_positions[0].stakeholder.affiliation,
        ).toBe('Finance');
        expect(briefContextByKey.value.cac_pain_points.cueContext).toBeNull();
    });

    it('ignores cue contexts whose field is absent', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    cue_contexts: [cueContext('nonexistent_field')],
                }),
            ),
        );

        expect(briefContextByKey.value.costs_included.cueContext).toBeNull();
        expect(briefContextByKey.value.nonexistent_field).toBeUndefined();
    });
});

describe('useFieldBriefContext workshop cues', () => {
    it('builds a deterministic local fixture cue from cue context when no explicit output exists', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    cue_contexts: [cueContext('costs_included')],
                }),
            ),
        );

        const cue = briefContextByKey.value.costs_included.workshopCue;

        expect(cue).not.toBeNull();
        expect(cue?.id).toBe('workshop_cue:costs_included:local_fixture');
        expect(cue?.why_this_cue.draft_text).toContain('costs_included label');
        expect(cue?.probe_cue?.draft_text).toContain(
            'Included and excluded GL/cost center rules',
        );
        expect(cue?.edit_state).toBe('review_only');
    });

    it('attaches explicit cue output under the matching field', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    cue_contexts: [cueContext('costs_included')],
                    workshop_cues: [workshopCue('costs_included')],
                }),
            ),
        );

        expect(briefContextByKey.value.costs_included.workshopCue?.id).toBe(
            'workshop_cue:costs_included:fixture',
        );
        expect(
            briefContextByKey.value.costs_included.workshopCue?.decision_cue
                .draft_text,
        ).toBe('Name the official baseline.');
    });

    it('initializes null workshop cue when neither cue context nor explicit output exists', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(fields, ref(brief()));

        expect(briefContextByKey.value.costs_included.workshopCue).toBeNull();
    });

    it('uses alignment_signal description in why_this_cue and adds to source refs when present', () => {
        const fields = ref<FieldRole[]>([field('costs_included')]);

        const { briefContextByKey } = useFieldBriefContext(
            fields,
            ref(
                brief({
                    cue_contexts: [
                        cueContext('costs_included', {
                            alignment_signal: {
                                id: 'sig-123',
                                severity: 'high',
                                description: 'Finance uses margin-based LTV while RevOps uses revenue LTV.',
                                status: 'Open',
                            },
                        }),
                    ],
                }),
            ),
        );

        const cue = briefContextByKey.value.costs_included.workshopCue;

        expect(cue).not.toBeNull();
        expect(cue?.why_this_cue.draft_text).toBe(
            'Finance uses margin-based LTV while RevOps uses revenue LTV. ' +
            'Companion context from Included and excluded GL/cost center rules changes how the group should interpret that choice. ' +
            'The wider matrix also surfaces CAC/CPL divergence points as related context — check whether any of it should shape the final wording.'
        );

        const sigRef = cue?.why_this_cue.source_refs.find(r => r.type === 'alignment_signal');
        expect(sigRef).not.toBeUndefined();
        expect(sigRef?.id).toBe('alignment_signal:sig-123:costs_included');
        expect(sigRef?.excerpt).toBe('Finance uses margin-based LTV while RevOps uses revenue LTV.');
    });
});
