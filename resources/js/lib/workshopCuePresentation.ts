import type { SourceRef, WorkshopCue, WorkshopCueContentBlock } from '@/types';

export type FieldAssistSection = {
    text: string;
    source_refs: SourceRef[];
    citation_ids: string[];
};

export type FieldAssistWatchOut = {
    id: string;
    text: string;
    source_refs: SourceRef[];
    citation_ids: string[];
};

/**
 * The compact field-level facilitation assist that replaces the heavy
 * why/probe/risk/decision-frame cue stack in deterministic Phase 1 (spec 037
 * Task 2). It carries only what the facilitator needs to ask the next question:
 * a start move, an optional fallback probe, and optional watch-outs.
 */
export type FieldFacilitationAssist = {
    start_with: FieldAssistSection | null;
    fallback_question: FieldAssistSection | null;
    watch_outs: FieldAssistWatchOut[];
};

const draftText = (block: { draft_text: string } | null | undefined): string =>
    block?.draft_text?.trim() ?? '';

const sectionFromBlock = (
    block: WorkshopCueContentBlock | null | undefined,
): FieldAssistSection | null => {
    const text = draftText(block);

    if (!block || text === '') {
        return null;
    }

    return {
        text,
        source_refs: block.source_refs,
        citation_ids: block.citation_ids,
    };
};

/**
 * Slim a deterministic Workshop Cue into the compact field-level assist. The
 * decision cue becomes the facilitation start move, the probe cue becomes the
 * fallback question, and the risk cue becomes a watch-out. Trade-off framing and
 * decision wording move to the workshop-level readiness pack, so they are
 * intentionally dropped here rather than re-rendered as another heavy stack.
 */
export const buildFieldFacilitationAssist = (
    cue: WorkshopCue,
): FieldFacilitationAssist => {
    const risk = sectionFromBlock(cue.risk_cue);

    return {
        start_with: sectionFromBlock(cue.decision_cue),
        fallback_question: sectionFromBlock(cue.probe_cue),
        watch_outs: risk
            ? [
                  {
                      id: `${cue.id}:watch_out:risk`,
                      text: risk.text,
                      source_refs: risk.source_refs,
                      citation_ids: risk.citation_ids,
                  },
              ]
            : [],
    };
};

/**
 * A field assist is substantive enough to render once it has a start move plus
 * at least one supporting slot (a fallback probe or a watch-out). A start move
 * on its own is too thin: that path keeps the detector-signal-only note instead,
 * matching the pre-compaction gate so a thin metric stays sparse without filler.
 */
export const shouldRenderFieldAssist = (
    cue: WorkshopCue | null | undefined,
): boolean => {
    if (!cue) {
        return false;
    }

    const assist = buildFieldFacilitationAssist(cue);

    if (!assist.start_with) {
        return false;
    }

    return assist.fallback_question !== null || assist.watch_outs.length > 0;
};
