import type { SourceContext } from './MetricSourceContext.vue';
import type { SignalSection } from './handbook-signals';

export type SignalSourceChip = {
    kind:
        | 'signed_definition'
        | 'workshop_decision'
        | 'resolved_discrepancy'
        | 'implementation_evidence'
        | 'validation_rule'
        | 'missing_source'
        | 'matrix_leftover';
    label: string;
    status: 'available' | 'missing';
    summary?: string;
};

export function buildSignalSourceChips(
    section: SignalSection,
    sourceContext: SourceContext | null,
): SignalSourceChip[] {
    const list: SignalSourceChip[] = [];

    switch (section) {
        case 'takeaway': {
            const sec = sourceContext?.signed_definition?.sections?.find(
                (s) => s.key === 'official_definition',
            );
            const exists = !!sec?.items?.length;
            list.push({
                kind: 'signed_definition',
                label: 'Signed Definition',
                status: exists ? 'available' : 'missing',
                summary: exists
                    ? 'Official definition fields from signed definition'
                    : undefined,
            });
            break;
        }

        case 'alignment': {
            const alignment = sourceContext?.workshop_alignment;
            const hasWorkshop = !!alignment?.workshop;
            const hasDecisions = !!(
                alignment?.workshop?.decisions?.consensus ||
                (alignment?.workshop?.decisions?.follow_up &&
                    alignment.workshop.decisions.follow_up.length > 0) ||
                (alignment?.workshop?.decisions?.field_decisions &&
                    Object.keys(alignment.workshop.decisions.field_decisions)
                        .length > 0)
            );
            const workshopExists = hasWorkshop && hasDecisions;

            list.push({
                kind: 'workshop_decision',
                label: 'Workshop Decision',
                status: workshopExists ? 'available' : 'missing',
                summary: workshopExists
                    ? 'Consensus decisions and field decisions'
                    : undefined,
            });

            const discrepancyExists = !!(
                alignment?.discrepancies && alignment.discrepancies.length > 0
            );
            list.push({
                kind: 'resolved_discrepancy',
                label: 'Resolved Discrepancy',
                status: discrepancyExists ? 'available' : 'missing',
                summary: discrepancyExists
                    ? 'Reconciled input discrepancies'
                    : undefined,
            });
            break;
        }

        case 'status': {
            const signedDef = sourceContext?.signed_definition;
            const exists = !!(
                signedDef?.version ||
                signedDef?.signed_off_at ||
                signedDef?.signed_off_by ||
                signedDef?.owner ||
                signedDef?.notes
            );
            list.push({
                kind: 'signed_definition',
                label: 'Signed Definition Header',
                status: exists ? 'available' : 'missing',
                summary: exists
                    ? 'Signed definition version, signee, or notes'
                    : undefined,
            });
            break;
        }

        case 'gaps': {
            const sec = sourceContext?.signed_definition?.sections?.find(
                (s) => s.key === 'controls_caveats',
            );
            const controlsExists = !!sec?.items?.length;
            list.push({
                kind: 'implementation_evidence',
                label: 'Controls & Caveats',
                status: controlsExists ? 'available' : 'missing',
                summary: controlsExists
                    ? 'Definition caveats and data exceptions'
                    : undefined,
            });

            const followUpExists = !!(
                sourceContext?.workshop_alignment?.workshop?.decisions
                    ?.follow_up &&
                sourceContext.workshop_alignment.workshop.decisions.follow_up
                    .length > 0
            );
            list.push({
                kind: 'workshop_decision',
                label: 'Workshop Follow-up',
                status: followUpExists ? 'available' : 'missing',
                summary: followUpExists
                    ? 'Actions and next steps from workshop'
                    : undefined,
            });

            const leftoversGapsExists = !!sourceContext?.matrix_leftovers?.some(
                (leftover) =>
                    leftover.used_for?.includes('implementation_gaps'),
            );
            list.push({
                kind: 'matrix_leftover',
                label: 'Unselected Stakeholder Positions',
                status: leftoversGapsExists ? 'available' : 'missing',
                summary: leftoversGapsExists
                    ? 'Matrix leftovers used for implementation gaps'
                    : undefined,
            });
            break;
        }

        case 'validation': {
            const sec = sourceContext?.signed_definition?.sections?.find(
                (s) => s.key === 'measurement_logic_rules',
            );
            const logicExists = !!sec?.items?.length;
            list.push({
                kind: 'validation_rule',
                label: 'Measurement Logic & Rules',
                status: logicExists ? 'available' : 'missing',
                summary: logicExists
                    ? 'Calculation details and measurement logic'
                    : undefined,
            });

            const discrepancyExists = !!(
                sourceContext?.workshop_alignment?.discrepancies &&
                sourceContext.workshop_alignment.discrepancies.length > 0
            );
            list.push({
                kind: 'resolved_discrepancy',
                label: 'Resolved Discrepancy',
                status: discrepancyExists ? 'available' : 'missing',
                summary: discrepancyExists
                    ? 'Resolved input discrepancies indicating causes'
                    : undefined,
            });

            const leftoversValExists = !!sourceContext?.matrix_leftovers?.some(
                (leftover) =>
                    leftover.used_for?.includes('validation') ||
                    leftover.used_for?.includes('common_mismatch_causes'),
            );
            list.push({
                kind: 'matrix_leftover',
                label: 'Unselected Stakeholder Positions',
                status: leftoversValExists ? 'available' : 'missing',
                summary: leftoversValExists
                    ? 'Matrix leftovers used for validation or mismatch causes'
                    : undefined,
            });
            break;
        }
    }

    return list;
}
