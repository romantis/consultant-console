export type MatrixValue = {
    definition_id: string;
    raw: unknown;
    other_text?: string | null;
    user: { id: string; name: string } | null;
    submitted_at: string | null;
};

export type OptionLabels = Record<string, string>;

export type MatrixRow = {
    field: string;
    label: string;
    type: string;
    values: MatrixValue[];
    options?: OptionLabels | null;
    discrepancy: {
        severity: string;
        description: string;
        status: string;
    } | null;
};

export type ContextRow = Omit<MatrixRow, 'discrepancy'>;

export type FieldRoleEntry = {
    key: string;
    label: string;
    role: 'definition' | 'context' | 'governance' | 'diagnostic';
    exclude_from_consensus?: boolean;
    companion_of?: string | null;
    value: unknown;
    unclassified: boolean;
};

export type FinalDefinitionRequirement = {
    key: string;
    label: string;
};

export type FinalDefinitionRequirements = {
    required_fields: FinalDefinitionRequirement[];
    missing_keys: string[];
    owner_eligible: boolean;
};

export type ImplementationGaps = {
    current_state?: string | null;
    gap?: string | null;
    owner?: string | null;
    suggested_next_step?: string | null;
    next_step?: string | null;
};

export type EditableImplementationGaps = {
    current_state: string;
    gap: string;
    owner: string;
    suggested_next_step: string;
};

export type FinalDefinitionDraftValues = {
    rows: EditableRow[];
    notes: string;
    alignmentSummary: string;
    ownerParticipantId: string;
};

export type DraftEditorState = {
    current: FinalDefinitionDraftValues;
    saved: FinalDefinitionDraftValues;
};

export type FinalDefinitionEditorState = {
    draft: DraftEditorState;
    isEditingDraft: boolean;
    isSavingDraft: boolean;
    isSigningOff: boolean;
    isSignOffDialogOpen: boolean;
};

export type OwnerOption = {
    id: string;
    label: string;
    responsibility: string;
    affiliation?: string | null;
    is_default: boolean;
    disabled: boolean;
    user: { id: string; name: string; email?: string | null } | null;
};

export type MetricView = {
    id: string;
    name: string;
    status: string;
    engagement_id: string;
    last_submitted_at: string | null;
    matrix: MatrixRow[];
    context_fields?: ContextRow[];
    field_roles: FieldRoleEntry[];
    unclassified: string[];
    definitions: Array<{
        id: string;
        submitted_at: string | null;
        user: { id: string; name: string } | null;
    }>;
    owner_options?: OwnerOption[];
};

export type FinalDefinitionEntry = {
    label: string;
    value: string | null;
    companion_of?: string | null;
};

export type FinalDefinitionJson = Record<string, FinalDefinitionEntry>;

export type FinalDefinition = {
    id: string | null;
    status: string;
    version?: number | null;
    definition_json: FinalDefinitionJson;
    notes: string | null;
    alignment_summary?: string | null;
    operational_status?: string | null;
    operational_status_notes?: string | null;
    implementation_gaps?: ImplementationGaps | null;
    validation_steps?: string | null;
    common_mismatch_causes?: string | null;
    executive_takeaway?: string | null;
    owner_participant_id: string | null;
    owner_participant: {
        id: string;
        responsibility: string | null;
        user: { id: string; name: string; email?: string | null } | null;
    } | null;
    signed_off_at: string | null;
    signed_off_by: { id: string; name: string } | null;
};

export type OfficialSignals = {
    id: string;
    source: 'org_final_definition' | 'final_definition';
    status: string;
    version: number | null;
    signed_off_at: string | null;
    signed_off_by: { id: string; name: string } | null;
    alignment_summary: string | null;
    operational_status: string | null;
    operational_status_notes: string | null;
    implementation_gaps: ImplementationGaps | null;
    validation_steps?: string | null;
    common_mismatch_causes?: string | null;
    executive_takeaway?: string | null;
};

export type OrganizationSummary = { id: string; name: string };

export type EngagementSummary = {
    id: string;
    title: string;
    status?: string | null;
} | null;
export type Permissions = {
    update_final_definition?: boolean;
    sign_off?: boolean;
    manage_workshop_sessions?: boolean;
    sign_off_message?: string | null;
};

export type FinalDefinitionProgress = {
    state: 'signed_off' | 'ready' | 'missing';
    missing_count?: number;
};

export type WorkshopMetric = {
    id: string;
    name: string;
    status: string | null;
    last_submitted_at: string | null;
    final_definition_progress?: FinalDefinitionProgress | null;
};

export type WorkshopSession = {
    id: string;
    organization_id?: string | null;
    engagement_id?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    timezone?: string | null;
    status?: string | null;
    notes?: string | null;
    metric_ids?: string[];
    participant_ids?: string[];
};

export type WorkshopSessionContext = {
    selected_session_id?: string | null;
    sessions?: WorkshopSession[];
};

export type RecomputeStatus = {
    status?: string | null;
    queued_at?: string | null;
    finished_at?: string | null;
    changed_at?: string | null;
    pending_changes?: boolean | null;
};

export type WorkshopPayload = {
    base_url?: string | null;
    metrics?: WorkshopMetric[];
    recompute?: RecomputeStatus | null;
    session_context?: WorkshopSessionContext | null;
};

export type AnswersViewVariant = 'compare' | 'compare-list' | 'form-order';

export type EditableRow = {
    field: string;
    label: string;
    value: string;
    companion_of?: string | null;
};

export type DefinitionFieldRow = {
    key: string;
    label: string;
    required: boolean;
    companion_of?: string | null;
};

export type MatrixAnswerDoubleClickPayload = {
    field: string;
    label: string;
    value: string;
};

export type SummaryRow = {
    key: string;
    label: string;
    required: boolean;
    value: string | null;
};

export type WorkshopProgressVariant = 'default' | 'outline' | 'secondary';
