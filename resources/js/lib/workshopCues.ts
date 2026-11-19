/**
 * Workshop Cues Deterministic Generation Library
 *
 * NOTE: This library acts as a temporary deterministic stand-in for Workshop Cues
 * generation. Cues are currently generated client-side from the backend-provided
 * cue contexts. In the future, this transitional implementation will be replaced
 * by a server-side AI provider (model integration) populating the `workshop_cues` brief payload.
 */

import type {
    CompanionAnswerGroup,
    SourceRef,
    StakeholderPosition,
    WorkshopCue,
    WorkshopCueContentBlock,
    WorkshopCueContext,
    WorkshopCueProvenance,
} from '@/types';

const cueProvenance: WorkshopCueProvenance = 'local_fixture';

const dedupeSourceRefs = (sourceRefs: SourceRef[]): SourceRef[] => {
    const uniqueRefs = new Map<string, SourceRef>();

    sourceRefs.forEach((sourceRef) => {
        if (sourceRef?.id) {
            uniqueRefs.set(sourceRef.id, sourceRef);
        }
    });

    return Array.from(uniqueRefs.values());
};

const citationIdsFromRefs = (sourceRefs: SourceRef[]): string[] =>
    dedupeSourceRefs(sourceRefs).map((sourceRef) => sourceRef.id);

const humanList = (items: string[]): string => {
    const uniqueItems = Array.from(
        new Set(items.map((item) => item.trim()).filter((item) => item !== '')),
    );

    if (uniqueItems.length <= 1) {
        return uniqueItems[0] ?? '';
    }

    if (uniqueItems.length === 2) {
        return `${uniqueItems[0]} and ${uniqueItems[1]}`;
    }

    return `${uniqueItems.slice(0, -1).join(', ')}, and ${uniqueItems.at(-1)}`;
};

const stakeholderLabel = (position: StakeholderPosition): string => {
    const affiliation = position.stakeholder.affiliation?.trim();

    return affiliation
        ? `${position.stakeholder.name} (${affiliation})`
        : position.stakeholder.name;
};

const companionSourceRefs = (
    companionAnswers: CompanionAnswerGroup[],
): SourceRef[] =>
    companionAnswers.flatMap((group) =>
        group.answers.flatMap((answer) => answer.source_refs),
    );

const matrixSourceRefs = (cueContext: WorkshopCueContext): SourceRef[] =>
    cueContext.matrix_context.flatMap((field) => field.source_refs);

const workshopGuidanceSourceRefs = (
    cueContext: WorkshopCueContext,
): SourceRef[] => cueContext.workshop_guidance?.source_refs ?? [];

const stakeholderSourceRefs = (cueContext: WorkshopCueContext): SourceRef[] =>
    cueContext.stakeholder_positions.flatMap(
        (position) => position.source_refs,
    );

const cueBlock = (
    text: string,
    sourceRefs: SourceRef[],
    provenance: WorkshopCueProvenance = cueProvenance,
): WorkshopCueContentBlock => {
    const dedupedSourceRefs = dedupeSourceRefs(sourceRefs);

    return {
        text,
        draft_text: text,
        provenance,
        source_refs: dedupedSourceRefs,
        citation_ids: citationIdsFromRefs(dedupedSourceRefs),
    };
};

export const buildDeterministicWorkshopCue = (
    cueContext: WorkshopCueContext,
    provenance: WorkshopCueProvenance = cueProvenance,
): WorkshopCue => {
    const fieldLabel = cueContext.field_label.trim() || cueContext.field_key;
    const stakeholderLabels =
        cueContext.stakeholder_positions.map(stakeholderLabel);
    const companionLabels = cueContext.companion_answers.map(
        (group) => group.companion_field_label,
    );
    const matrixLabels = cueContext.matrix_context.map(
        (field) => field.field_label,
    );

    const currentFieldSourceRefs = stakeholderSourceRefs(cueContext);
    const companionRefs = companionSourceRefs(cueContext.companion_answers);
    const matrixRefs = matrixSourceRefs(cueContext);
    const guidanceRefs = workshopGuidanceSourceRefs(cueContext);

    const signalRefs: SourceRef[] = [];
    if (cueContext.alignment_signal) {
        signalRefs.push({
            id: `alignment_signal:${cueContext.alignment_signal.id}:${cueContext.field_key}`,
            type: 'alignment_signal',
            // Mirrors the backend ref label (BuildMetricAlignmentBrief): the
            // discrepancy detector signal is a "Detector signal", distinct from
            // the "Workshop Cues" review panel it appears inside (T049). This is
            // the live path — backend workshop_cues is reserved/empty, so this
            // deterministic builder is what populates the cue's Context used.
            label: 'Detector signal',
            field_key: cueContext.field_key,
            source_id: cueContext.alignment_signal.id,
            excerpt:
                cueContext.alignment_signal.description ||
                'Field evidence needs facilitator review.',
        });
    }

    const allSourceRefs = dedupeSourceRefs([
        ...currentFieldSourceRefs,
        ...companionRefs,
        ...matrixRefs,
        ...guidanceRefs,
        ...signalRefs,
    ]);

    const whyParts: string[] = [];

    if (cueContext.alignment_signal?.description) {
        whyParts.push(cueContext.alignment_signal.description);
    } else if (stakeholderLabels.length > 1) {
        whyParts.push(
            `${humanList(stakeholderLabels)} submitted different source-backed positions on ${fieldLabel}.`,
        );
    } else {
        whyParts.push(
            `${fieldLabel} still needs a facilitator-ready decision backed by the submitted source context.`,
        );
    }

    if (companionLabels.length > 0) {
        whyParts.push(
            `Companion context from ${humanList(companionLabels)} changes how the group should interpret that choice.`,
        );
    }

    if (matrixLabels.length > 0) {
        // T052: surface matrix fields as context to *check*, not as fields that
        // definitely change the wording — the deterministic prose must not imply
        // every matrix-wide field is relevant (the relevance half of the
        // matrix-wide finding).
        whyParts.push(
            `The wider matrix also surfaces ${humanList(matrixLabels)} as related context — check whether any of it should shape the final wording.`,
        );
    } else if (cueContext.workshop_guidance?.text.trim()) {
        whyParts.push(
            'Template workshop guidance adds extra facilitation context for the decision.',
        );
    }

    const decisionCue = `Start by naming the official choice for ${fieldLabel} and whether any competing view stays as a separately labeled operating view.`;
    const probeCue =
        companionLabels.length > 0 || matrixLabels.length > 0
            ? `Which surrounding assumption changes this choice the most: ${humanList(
                  [...companionLabels, ...matrixLabels],
              )}? Lock that dependency in the same discussion so the final wording stays reproducible.`
            : null;
    const riskCue =
        matrixLabels.length > 0
            ? `If ${fieldLabel} is decided without ${humanList(matrixLabels)} in scope, teams can appear aligned while still interpreting the metric differently.`
            : null;
    const tradeoffCue =
        stakeholderLabels.length > 1
            ? `Multiple stakeholder views can stay useful, but only one should be the official baseline. Any surviving alternative needs an explicit label and audience.`
            : null;
    // T052: keep this metric-neutral. The earlier "...remain explicitly labeled
    // anywhere they continue to be reported" carried revenue/governance framing
    // that misreads on a simple numeric metric (e.g. Focus Time).
    const decisionWording = `Official ${fieldLabel} = [agreed wording]. Note any alternative as a separately labeled option.`;

    return {
        id: `workshop_cue:${cueContext.field_key}:${provenance}`,
        field_key: cueContext.field_key,
        field_label: fieldLabel,
        status: 'generated',
        edit_state: 'review_only',
        provenance,
        why_this_cue: cueBlock(whyParts.join(' '), allSourceRefs, provenance),
        decision_cue: cueBlock(
            decisionCue,
            [...currentFieldSourceRefs, ...guidanceRefs],
            provenance,
        ),
        probe_cue: probeCue
            ? cueBlock(probeCue, [...companionRefs, ...matrixRefs], provenance)
            : null,
        risk_cue: riskCue ? cueBlock(riskCue, matrixRefs, provenance) : null,
        tradeoff_cue: tradeoffCue
            ? cueBlock(
                  tradeoffCue,
                  [...currentFieldSourceRefs, ...matrixRefs],
                  provenance,
              )
            : null,
        decision_wording: cueBlock(
            decisionWording,
            [...currentFieldSourceRefs, ...guidanceRefs, ...matrixRefs],
            provenance,
        ),
        source_refs: allSourceRefs,
        citation_ids: citationIdsFromRefs(allSourceRefs),
    };
};
