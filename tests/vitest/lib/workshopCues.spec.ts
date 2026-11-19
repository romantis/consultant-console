import { describe, expect, it } from 'vitest';
import { buildDeterministicWorkshopCue } from '@/lib/workshopCues';
import type { WorkshopCueContext } from '@/types';

const mockCueContext = (fieldKey: string): WorkshopCueContext => ({
    field_key: fieldKey,
    field_label: `${fieldKey} label`,
    field_role: 'definition',
    stakeholder_positions: [
        {
            field_key: fieldKey,
            field_label: `${fieldKey} label`,
            stakeholder: { id: 'usr-1', name: 'Lia Price', affiliation: 'Finance' },
            definition_id: 'def-1',
            raw_value: 'value',
            normalized_value: 'value',
            display_value: 'value',
            submitted_at: '2026-06-01T10:00:00Z',
            source_refs: [
                {
                    id: `submitted_answer:def-1:${fieldKey}`,
                    type: 'submitted_answer',
                    label: 'Lia Price answer',
                    field_key: fieldKey,
                    source_id: 'def-1',
                    excerpt: 'value',
                },
            ],
        },
    ],
    companion_answers: [],
    workshop_guidance: null,
    matrix_context: [],
    citation_ids: [`submitted_answer:def-1:${fieldKey}`],
});

describe('workshopCues buildDeterministicWorkshopCue', () => {
    it('uses the default local_fixture provenance and derives ID and content block provenances from it', () => {
        const context = mockCueContext('costs_included');
        const cue = buildDeterministicWorkshopCue(context);

        expect(cue.id).toBe('workshop_cue:costs_included:local_fixture');
        expect(cue.provenance).toBe('local_fixture');
        expect(cue.why_this_cue.provenance).toBe('local_fixture');
        expect(cue.decision_cue.provenance).toBe('local_fixture');
        expect(cue.decision_wording.provenance).toBe('local_fixture');
    });

    it('derives the cue id and content block provenances from the passed custom provenance value', () => {
        const context = mockCueContext('costs_included');
        const cue = buildDeterministicWorkshopCue(context, 'ai_generated');

        expect(cue.id).toBe('workshop_cue:costs_included:ai_generated');
        expect(cue.provenance).toBe('ai_generated');
        expect(cue.why_this_cue.provenance).toBe('ai_generated');
        expect(cue.decision_cue.provenance).toBe('ai_generated');
        expect(cue.decision_wording.provenance).toBe('ai_generated');
    });

    // T049: this builder is the live path (backend workshop_cues is reserved and
    // empty), so the alignment_signal it emits must carry the relabeled "Detector
    // signal" provenance, not the old "Workshop cue" that collided with the panel.
    it('labels the alignment-signal source reference as a Detector signal', () => {
        const cue = buildDeterministicWorkshopCue({
            ...mockCueContext('costs_included'),
            alignment_signal: {
                id: 'disc-1',
                severity: 'medium',
                description: 'Finance and Marketing submitted different positions.',
                status: 'open',
            },
        });

        const signalRef = cue.source_refs.find(
            (ref) => ref.type === 'alignment_signal',
        );

        expect(signalRef).toBeDefined();
        expect(signalRef?.label).toBe('Detector signal');
        expect(
            cue.source_refs.some((ref) => ref.label === 'Workshop cue'),
        ).toBe(false);
    });

    // T052: the deterministic builder also renders for simple numeric/text metrics
    // (Focus Time shape — no matrix context, per T050), so its copy must stay
    // metric-neutral, free of the revenue/governance framing it carried before.
    it('keeps the deterministic copy metric-neutral on a simple metric (Focus Time shape)', () => {
        // mockCueContext has no matrix_context, matching the Focus Time shape.
        const cue = buildDeterministicWorkshopCue({
            ...mockCueContext('weekly_focus_hours'),
            field_label: 'Weekly focus hours',
        });

        const allText = [
            cue.why_this_cue.text,
            cue.decision_cue.text,
            cue.probe_cue?.text ?? '',
            cue.risk_cue?.text ?? '',
            cue.tradeoff_cue?.text ?? '',
            cue.decision_wording.text,
        ].join(' ');

        // No revenue/governance jargon: results aren't "calculated" and a metric
        // definition isn't "reported".
        expect(allText).not.toMatch(/calculating different results/);
        expect(allText).not.toMatch(/continue to be reported/);
        expect(allText).not.toMatch(/\breported\b/);
        expect(cue.decision_wording.text).toBe(
            'Official Weekly focus hours = [agreed wording]. Note any alternative as a separately labeled option.',
        );
        // Focus Time has no matrix context, so no matrix risk segment is emitted.
        expect(cue.risk_cue).toBeNull();
    });

    // T052: when matrix context IS present (CAC/CPL shape), the relevance/risk
    // prose is softened — it suggests checking the related fields rather than
    // asserting they all change the wording, and drops the "calculating different
    // results" revenue framing. CAC/CPL stays unchanged in substance.
    it('softens the matrix relevance and risk copy without revenue framing', () => {
        const cue = buildDeterministicWorkshopCue({
            ...mockCueContext('cac_definition'),
            field_label: 'CAC/CPL definition',
            matrix_context: [
                {
                    field_key: 'cac_pain_points',
                    field_label: 'CAC/CPL divergence points',
                    field_role: 'diagnostic',
                    stakeholder_positions: [],
                    source_refs: [],
                    citation_ids: [],
                },
            ],
        });

        expect(cue.why_this_cue.text).toContain(
            'check whether any of it should shape the final wording',
        );
        expect(cue.why_this_cue.text).not.toMatch(
            /as context that can change the final wording/,
        );
        expect(cue.risk_cue?.text).toContain('interpreting the metric differently');
        expect(cue.risk_cue?.text).not.toMatch(/calculating different results/);
    });

    // T059/F4: the cross-metric regression proved the *absence* of revenue tokens
    // but would still pass on maximally bland boilerplate. These assert the
    // satisfiable substance invariant: why_this_cue carries source-specific
    // content, not the generic "still needs a facilitator-ready decision" fallback
    // branch. With multiple stakeholder positions and no detector signal, the cue
    // must be grounded in the stakeholder divergence (Focus Time shape).
    it('grounds why_this_cue in the stakeholder divergence, not the generic fallback (Focus Time shape)', () => {
        const base = mockCueContext('focus_hours_target');
        const firstPosition = base.stakeholder_positions[0];
        const cue = buildDeterministicWorkshopCue({
            ...base,
            field_label: 'Ideal focus hours per workday',
            stakeholder_positions: [
                firstPosition,
                {
                    ...firstPosition,
                    stakeholder: {
                        id: 'usr-2',
                        name: 'Jordan Park',
                        affiliation: 'Product',
                    },
                    definition_id: 'def-2',
                    source_refs: [
                        {
                            id: 'submitted_answer:def-2:focus_hours_target',
                            type: 'submitted_answer',
                            label: 'Jordan Park answer',
                            field_key: 'focus_hours_target',
                            source_id: 'def-2',
                            excerpt: 'value',
                        },
                    ],
                },
            ],
        });

        expect(cue.why_this_cue.text).toContain('Ideal focus hours per workday');
        expect(cue.why_this_cue.text).toContain(
            'submitted different source-backed positions',
        );
        // The generic fallback branch must not be what carries the cue.
        expect(cue.why_this_cue.text).not.toContain(
            'still needs a facilitator-ready decision',
        );
    });

    // T059/F4: when a detector signal is present (CAC/CPL shape), why_this_cue must
    // surface that source-specific description verbatim — not collapse to the
    // generic fallback.
    it('grounds why_this_cue in the detector-signal description, not the generic fallback (CAC/CPL shape)', () => {
        const cue = buildDeterministicWorkshopCue({
            ...mockCueContext('cac_definition'),
            field_label: 'CAC/CPL definition',
            alignment_signal: {
                id: 'disc-1',
                severity: 'high',
                description:
                    'Finance and Sales submitted divergent CAC/CPL definitions.',
                status: 'open',
            },
        });

        expect(cue.why_this_cue.text).toContain(
            'Finance and Sales submitted divergent CAC/CPL definitions.',
        );
        expect(cue.why_this_cue.text).not.toContain(
            'still needs a facilitator-ready decision',
        );
    });
});
