import {
    buildFieldFacilitationAssist,
    shouldRenderFieldAssist,
} from '@/lib/workshopCuePresentation';
import type { WorkshopCue } from '@/types';
import { describe, expect, it } from 'vitest';

const block = (text: string) => ({
    text,
    draft_text: text,
    provenance: 'local_fixture' as const,
    source_refs: [],
    citation_ids: [],
});

const workshopCueFixture = (
    overrides: Partial<WorkshopCue> = {},
): WorkshopCue => ({
    id: 'workshop_cue:focus_hours_target:fixture',
    field_key: 'focus_hours_target',
    field_label: 'Focus Hours Target',
    status: 'generated',
    edit_state: 'review_only',
    provenance: 'local_fixture',
    why_this_cue: block(
        'The team disagrees on how many hours of uninterrupted focus time are realistic in a day.',
    ),
    decision_cue: block(
        'Start by naming the official choice for Focus Hours Target.',
    ),
    probe_cue: null,
    risk_cue: null,
    tradeoff_cue: block(
        'Multiple views can stay useful, but only one should be the official baseline.',
    ),
    decision_wording: block('Official Focus Hours Target = [agreed wording].'),
    source_refs: [],
    citation_ids: [],
    ...overrides,
});

describe('workshopCuePresentation', () => {
    it('slims a deterministic cue into a compact start / fallback / watch-out assist', () => {
        const assist = buildFieldFacilitationAssist(
            workshopCueFixture({
                probe_cue: block(
                    'Which downstream report changes the most if the target drops to two hours?',
                ),
                risk_cue: block(
                    'If the target stays implicit, teams can look aligned while planning to different hours.',
                ),
            }),
        );

        expect(assist.start_with?.text).toBe(
            'Start by naming the official choice for Focus Hours Target.',
        );
        expect(assist.fallback_question?.text).toBe(
            'Which downstream report changes the most if the target drops to two hours?',
        );
        expect(assist.watch_outs).toHaveLength(1);
        expect(assist.watch_outs[0].text).toBe(
            'If the target stays implicit, teams can look aligned while planning to different hours.',
        );
    });

    it('omits the fallback and watch-out slots when the source cue blocks are absent', () => {
        const assist = buildFieldFacilitationAssist(workshopCueFixture());

        expect(assist.start_with).not.toBeNull();
        expect(assist.fallback_question).toBeNull();
        expect(assist.watch_outs).toHaveLength(0);
    });

    it('drops trade-off and decision-wording framing from the field assist', () => {
        const serialized = JSON.stringify(
            buildFieldFacilitationAssist(workshopCueFixture()),
        );

        expect(serialized).not.toContain('Multiple views can stay useful');
        expect(serialized).not.toContain(
            'Official Focus Hours Target = [agreed wording]',
        );
    });

    it('treats a start move on its own as too thin to render', () => {
        expect(shouldRenderFieldAssist(workshopCueFixture())).toBe(false);
    });

    it('renders the assist once a fallback or watch-out joins the start move', () => {
        const cue = workshopCueFixture({
            probe_cue: block(
                'Which downstream report changes the most if the target drops to two hours?',
            ),
        });

        expect(shouldRenderFieldAssist(cue)).toBe(true);
    });
});
