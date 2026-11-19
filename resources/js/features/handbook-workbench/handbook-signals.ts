export interface ImplementationGaps {
    current_state: string | null;
    gap: string | null;
    owner: string | null;
    suggested_next_step: string | null;
}

export interface Signals {
    org_final_definition_id: string;
    version: number | null;
    signed_off_at: string | null;
    signed_off_by: {
        id: string;
        name: string;
    } | null;
    executive_takeaway: string | null;
    alignment_summary: string | null;
    operational_status: string | null;
    operational_status_notes: string | null;
    implementation_gaps: ImplementationGaps | null;
    validation_steps: string | null;
    common_mismatch_causes: string | null;
}

export interface ImplementationGapsDraft {
    current_state: string;
    gap: string;
    owner: string;
    suggested_next_step: string;
}

export interface SignalDraft {
    executive_takeaway: string;
    alignment_summary: string;
    operational_status: string;
    operational_status_notes: string;
    validation_steps: string;
    common_mismatch_causes: string;
    implementation_gaps: ImplementationGapsDraft;
}

export function createSignalDraft(signals?: Signals | null): SignalDraft {
    const gaps = signals?.implementation_gaps;
    return {
        executive_takeaway: signals?.executive_takeaway ?? '',
        alignment_summary: signals?.alignment_summary ?? '',
        operational_status: signals?.operational_status ?? '',
        operational_status_notes: signals?.operational_status_notes ?? '',
        validation_steps: signals?.validation_steps ?? '',
        common_mismatch_causes: signals?.common_mismatch_causes ?? '',
        implementation_gaps: {
            current_state: gaps?.current_state ?? '',
            gap: gaps?.gap ?? '',
            owner: gaps?.owner ?? '',
            suggested_next_step: gaps?.suggested_next_step ?? '',
        },
    };
}

export type SignalSection =
    | 'takeaway'
    | 'alignment'
    | 'status'
    | 'gaps'
    | 'validation';

export function isSectionDirty(
    draft: SignalDraft,
    signals: Signals | null,
    section: SignalSection,
): boolean {
    if (!signals) return false;
    switch (section) {
        case 'takeaway':
            return (
                draft.executive_takeaway !== (signals.executive_takeaway ?? '')
            );
        case 'alignment':
            return (
                draft.alignment_summary !== (signals.alignment_summary ?? '')
            );
        case 'status':
            return (
                draft.operational_status !==
                    (signals.operational_status ?? '') ||
                draft.operational_status_notes !==
                    (signals.operational_status_notes ?? '')
            );
        case 'gaps': {
            const gaps = signals.implementation_gaps;
            return (
                draft.implementation_gaps.current_state !==
                    (gaps?.current_state ?? '') ||
                draft.implementation_gaps.gap !== (gaps?.gap ?? '') ||
                draft.implementation_gaps.owner !== (gaps?.owner ?? '') ||
                draft.implementation_gaps.suggested_next_step !==
                    (gaps?.suggested_next_step ?? '')
            );
        }
        case 'validation':
            return (
                draft.validation_steps !== (signals.validation_steps ?? '') ||
                draft.common_mismatch_causes !==
                    (signals.common_mismatch_causes ?? '')
            );
    }
}

export function resetSectionDraft(
    draft: SignalDraft,
    signals: Signals | null,
    section: SignalSection,
) {
    if (!signals) return;
    switch (section) {
        case 'takeaway':
            draft.executive_takeaway = signals.executive_takeaway ?? '';
            break;
        case 'alignment':
            draft.alignment_summary = signals.alignment_summary ?? '';
            break;
        case 'status':
            draft.operational_status = signals.operational_status ?? '';
            draft.operational_status_notes =
                signals.operational_status_notes ?? '';
            break;
        case 'gaps': {
            const gaps = signals.implementation_gaps;
            draft.implementation_gaps.current_state = gaps?.current_state ?? '';
            draft.implementation_gaps.gap = gaps?.gap ?? '';
            draft.implementation_gaps.owner = gaps?.owner ?? '';
            draft.implementation_gaps.suggested_next_step =
                gaps?.suggested_next_step ?? '';
            break;
        }
        case 'validation':
            draft.validation_steps = signals.validation_steps ?? '';
            draft.common_mismatch_causes = signals.common_mismatch_causes ?? '';
            break;
    }
}

export function isSignalDraftDirty(
    draft: SignalDraft,
    signals: Signals | null,
): boolean {
    if (!signals) return false;
    const takeawayDirty =
        draft.executive_takeaway !== (signals.executive_takeaway ?? '');
    const alignmentSummaryDirty =
        draft.alignment_summary !== (signals.alignment_summary ?? '');
    const opStatusDirty =
        draft.operational_status !== (signals.operational_status ?? '');
    const opStatusNotesDirty =
        draft.operational_status_notes !==
        (signals.operational_status_notes ?? '');
    const valStepsDirty =
        draft.validation_steps !== (signals.validation_steps ?? '');
    const mismatchDirty =
        draft.common_mismatch_causes !== (signals.common_mismatch_causes ?? '');

    const gaps = signals.implementation_gaps;
    const gapCurrentDirty =
        draft.implementation_gaps.current_state !== (gaps?.current_state ?? '');
    const gapGapDirty = draft.implementation_gaps.gap !== (gaps?.gap ?? '');
    const gapOwnerDirty =
        draft.implementation_gaps.owner !== (gaps?.owner ?? '');
    const gapNextStepDirty =
        draft.implementation_gaps.suggested_next_step !==
        (gaps?.suggested_next_step ?? '');

    return (
        takeawayDirty ||
        alignmentSummaryDirty ||
        opStatusDirty ||
        opStatusNotesDirty ||
        valStepsDirty ||
        mismatchDirty ||
        gapCurrentDirty ||
        gapGapDirty ||
        gapOwnerDirty ||
        gapNextStepDirty
    );
}

export function normalizeSignalText(value: string | null): string | null {
    if (!value) return null;
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
}

export function buildSignalPayload(draft: SignalDraft): {
    executive_takeaway: string | null;
    alignment_summary: string | null;
    operational_status: string | null;
    operational_status_notes: string | null;
    validation_steps: string | null;
    common_mismatch_causes: string | null;
    implementation_gaps: {
        current_state: string | null;
        gap: string | null;
        owner: string | null;
        suggested_next_step: string | null;
    };
} {
    return {
        executive_takeaway: normalizeSignalText(draft.executive_takeaway),
        alignment_summary: normalizeSignalText(draft.alignment_summary),
        operational_status: normalizeSignalText(draft.operational_status),
        operational_status_notes: normalizeSignalText(
            draft.operational_status_notes,
        ),
        validation_steps: normalizeSignalText(draft.validation_steps),
        common_mismatch_causes: normalizeSignalText(
            draft.common_mismatch_causes,
        ),
        implementation_gaps: {
            current_state: normalizeSignalText(
                draft.implementation_gaps.current_state,
            ),
            gap: normalizeSignalText(draft.implementation_gaps.gap),
            owner: normalizeSignalText(draft.implementation_gaps.owner),
            suggested_next_step: normalizeSignalText(
                draft.implementation_gaps.suggested_next_step,
            ),
        },
    };
}

export function formatOperationalStatus(status: string | null): string {
    if (!status) return 'Not Specified';
    if (status === 'operationally_ready') return 'Ready for Use';
    if (status === 'needs_follow_up') return 'Implementation Follow-up Needed';
    return status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}
