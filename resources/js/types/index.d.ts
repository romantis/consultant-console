import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export type AppUser = {
    id: string;
    name: string;
    email: string;
    is_staff?: boolean;
    is_internal_staff?: boolean;
    staff_role?: StaffRole | null;
    can_operate_delivery?: boolean;
    has_operator_assignments?: boolean;
    can_govern_internal?: boolean;
    can_impersonate?: boolean;
};

export type StaffRole = 'owner' | 'admin' | 'operator';

export type AppFlash = {
    success?: string | null;
    error?: string | null;
    impersonation_error?: string | null;
};

export type AppFlags = {
    allow_public_registration: boolean;
    workshop_discrepancies?: boolean;
    workshop_force_start_enabled?: boolean;
};

export interface Auth {
    user: AppUser | null;
    abilities?: string[] | Record<string, boolean>;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type EngagementWizardTemplateVariant = {
    id: string;
    key: string;
    name: string;
    description: string;
    form_schema_id: string;
    default_due_days: number;
    required_departments?: string[];
};

export type EngagementWizardMetric = {
    id: string;
    key: string;
    name: string;
    description?: string | null;
    is_active: boolean;
    active_engagement?: { id: string; title: string; status: string } | null;
    default_metric_template_id?: string | null;
    templates: EngagementWizardTemplateVariant[];
};

export type OrgMetricTemplateVariant = {
    id: string;
    key: string;
    name: string;
    description?: string | null;
    engagements_count: number;
    last_used_at?: string | null;
    is_default?: boolean;
};

export type OrgMetricParticipantOption = {
    participant_id: string;
    label: string;
    email?: string | null;
    is_pending?: boolean;
};

export type OrgMetricParticipantSummary = OrgMetricParticipantOption & {
    id: string;
};

export type OrgMetricDefinitionSummary = {
    id: string;
    status?: string | null;
    version?: number | null;
    notes?: string | null;
    signed_off_at?: string | null;
    signed_off_by?: { id: string; name: string } | null;
    metric?: {
        id: string;
        name: string;
        engagement?: { id: string; title: string } | null;
    } | null;
};

export type FinalDefinitionProgress =
    | { state: 'signed_off' }
    | { state: 'ready'; missing_count: 0 }
    | { state: 'missing'; missing_count: number };

export type OrgMetricActiveEngagement = {
    id: string;
    title: string;
    status?: string | null;
    metric: {
        id: string;
        status?: string | null;
        last_submitted_at?: string | null;
        definitions_changed_at?: string | null;
        final_definition_progress: FinalDefinitionProgress;
    };
};

export type OrgMetricDetail = {
    org_metric: {
        id: string;
        alias?: string | null;
        key: string;
        name: string;
        default_metric_template_id?: string | null;
        canonical: {
            id?: string | null;
            key: string;
            name: string;
            description?: string | null;
            is_active: boolean;
        };
    };
    active_engagement?: OrgMetricActiveEngagement | null;
    template_variants: OrgMetricTemplateVariant[];
    participants: {
        owners: OrgMetricParticipantSummary[];
        approvers: OrgMetricParticipantSummary[];
        options: OrgMetricParticipantOption[];
    };
    official_definition: OrgMetricDefinitionSummary | null;
    official_definition_history: OrgMetricDefinitionSummary[];
};

export type ClientNavigationLinkMap = {
    forms?: string | null;
    metrics?: string | null;
    handbook?: string | null;
    support?: string | null;
};

export type ClientNavigationEngagement = {
    id: string;
    title: string;
    status?: string | null;
    href?: string | null;
    reviewCadence?: string | null;
    nextReviewOn?: string | null;
};

export type ClientNavigationHandbookMetric = {
    id: string;
    name: string;
    href?: string | null;
};

export type ClientNavigation = {
    organization: { id: string; name: string };
    membership: { id: string; role: string | null; canViewHandbook: boolean };
    engagements: ClientNavigationEngagement[];
    links: ClientNavigationLinkMap;
    handbookMetrics?: ClientNavigationHandbookMetric[];
    activeEngagementId?: string | null;
};

export type HandbookExportSummary = {
    id: string;
    status: 'queued' | 'processing' | 'ready' | 'failed' | string;
    version_label: string;
    queued_at?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    failed_at?: string | null;
    failure_reason?: string | null;
    is_ready?: boolean;
};

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: AppFlash;
    flags: AppFlags;
    sidebarOpen: boolean;
    organizations?: Organization[];
    activeOrganizationId?: string | null;
    engagements?: EngagementSummary[];
    activeEngagementId?: string | null;
    staffWorkspace?: Organization | null;
    impersonation?: ImpersonationState | null;
    ownerOrganizations?: Organization[] | null;
    abilities?: string[] | Record<string, boolean>;
    clientNavigation?: ClientNavigation | null;
    handbookExports?: HandbookExportSummary[];
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type Organization = {
    id: string;
    name: string;
    slug: string;
    owned?: boolean;
    is_internal?: boolean;
    role?: string | null;
    can_manage?: boolean;
    can_operate_delivery?: boolean;
};
type EngagementSummary = { id: string; title: string; status?: string };
type FormRow = {
    id: string;
    title: string;
    template_key: string;
    status: string;
    created_at: string | null;
    submitted_count: number;
    draft_count: number;
    assignment_count: number;
    last_submission_at: string | null;
    next_due_at: string | null;
    viewer_can_submit?: boolean;
};
type TemplateRow = { key: string; name: string; description?: string };
export type MemberRow = {
    id: string;
    name: string;
    email: string;
    role: string;
    affiliation?: string | null;
};
export type PendingMemberRow = {
    id: string;
    email: string;
    name: string | null;
    role: string;
    affiliation?: string | null;
    engagement_id?: string | null;
    sent_at?: string | null;
    expires_at?: string | null;
};

export type BreadcrumbItemType = BreadcrumbItem;
export type ImpersonationState = {
    organization: Organization;
    role?: Organization['role'];
    started_at?: string | null;
    justification?: string | null;
};

export interface UserSummary {
    id?: string | number;
    name?: string | null;
}

export interface ContributorValue {
    definition_id?: string | number;
    raw?: unknown;
    normalized?: unknown;
    other_text?: string | null;
    other_text_normalized?: string | null;
    user?: UserSummary;
    submitted_at?: string | null;
}

export interface AlignmentSignalInfo {
    id: string;
    severity?: string | null;
    description?: string | null;
    status?: string | null;
}

export type SourceType =
    | 'submitted_answer'
    | 'field_guidance'
    | 'alignment_signal'
    | 'signed_definition'
    | 'workshop_decision';

export interface SourceRef {
    id: string;
    type: SourceType;
    label: string;
    field_key?: string | null;
    source_id?: string | null;
    excerpt?: string | number | boolean | null;
    meta?: Record<string, unknown>;
}

export interface BriefStakeholder {
    id: string | number | null;
    name: string;
    /**
     * Optional source-backed role/team context for the respondent (e.g.
     * "Finance"), derived from submitted `team_department` answers when present.
     * Rendered as "<name> (<affiliation>)" when available.
     */
    affiliation?: string | null;
}

export interface StakeholderPosition {
    field_key: string;
    field_label: string;
    stakeholder: BriefStakeholder;
    definition_id: string | number;
    raw_value: unknown;
    normalized_value: unknown;
    display_value: string;
    submitted_at: string | null;
    source_refs: SourceRef[];
}

export interface CompanionAnswer {
    definition_id: string | number;
    stakeholder: BriefStakeholder;
    display_value: string;
    raw_value: unknown;
    normalized_value: unknown;
    submitted_at: string | null;
    source_refs: SourceRef[];
}

export interface CompanionAnswerGroup {
    primary_field_key: string;
    companion_field_key: string;
    companion_field_label: string;
    companion_field_role: BriefFieldRole;
    answers: CompanionAnswer[];
}

export interface WorkshopCueContextGuidance {
    text: string;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface WorkshopCueContextMatrixField {
    field_key: string;
    field_label: string;
    field_role: BriefFieldRole;
    stakeholder_positions: StakeholderPosition[];
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface WorkshopCueContext {
    field_key: string;
    field_label: string;
    field_role: BriefFieldRole;
    stakeholder_positions: StakeholderPosition[];
    companion_answers: CompanionAnswerGroup[];
    workshop_guidance: WorkshopCueContextGuidance | null;
    matrix_context: WorkshopCueContextMatrixField[];
    citation_ids: string[];
    alignment_signal?: AlignmentSignalInfo | null;
}

export type WorkshopCueProvenance =
    | 'local_fixture'
    | 'ai_generated'
    | 'consultant_edited';

export type WorkshopCueStatus = 'generated' | 'edited';

export type WorkshopCueEditState = 'review_only' | 'ready_to_edit';

export interface WorkshopCueContentBlock {
    text: string;
    draft_text: string;
    provenance: WorkshopCueProvenance;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface WorkshopCue {
    id: string;
    field_key: string;
    field_label: string;
    status: WorkshopCueStatus;
    edit_state: WorkshopCueEditState;
    provenance: WorkshopCueProvenance;
    why_this_cue: WorkshopCueContentBlock;
    decision_cue: WorkshopCueContentBlock;
    probe_cue?: WorkshopCueContentBlock | null;
    risk_cue?: WorkshopCueContentBlock | null;
    tradeoff_cue?: WorkshopCueContentBlock | null;
    decision_wording: WorkshopCueContentBlock;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export type AttentionCategory =
    | 'material_conflict'
    | 'decision_needed'
    | 'missing_evidence'
    | 'context_signal'
    | 'likely_non_issue'
    | 'resolved_or_reference';

export type BriefFieldRole =
    | 'definition'
    | 'context'
    | 'governance'
    | 'diagnostic'
    | 'unknown';

export interface AttentionItem {
    id: string;
    category: AttentionCategory;
    field_key: string;
    field_label: string;
    field_role: BriefFieldRole;
    severity?: string | null;
    title: string;
    rationale: string;
    stakeholder_positions: StakeholderPosition[];
    source_refs: SourceRef[];
}

export interface RecommendedQuestion {
    id: string;
    field_key: string;
    question: string;
    reason: string;
    priority: 'high' | 'medium' | 'low';
    source_refs: SourceRef[];
}

export interface ResolutionOption {
    id: string;
    field_key: string;
    label: string;
    description: string;
    source_refs: SourceRef[];
}

export interface MetricAlignmentBrief {
    summary: {
        metric_id: string;
        metric_name: string;
        status: string;
        source_counts: {
            submitted_answers: number;
            alignment_signals: number;
            guided_fields: number;
            missing_evidence_items: number;
        };
    };
    stakeholder_positions: StakeholderPosition[];
    companion_answers: CompanionAnswerGroup[];
    cue_contexts?: WorkshopCueContext[];
    workshop_cues?: WorkshopCue[];
    material_conflicts: AttentionItem[];
    decision_needed: AttentionItem[];
    missing_evidence: AttentionItem[];
    context_signals: AttentionItem[];
    likely_non_issues: AttentionItem[];
    recommended_questions: RecommendedQuestion[];
    resolution_options: ResolutionOption[];
    draft_alignment_summary: string | null;
}

export type FacilitationProvenance = 'system_suggested' | 'consultant_authored';

export interface FacilitationContentBlock {
    text: string;
    provenance: FacilitationProvenance;
    source_item_ids: string[];
    citation_ids: string[];
}

export interface FacilitationRunOfShowItem {
    id: string;
    position: number;
    duration_minutes: number;
    title: string;
    decision_ids: string[];
    purpose: FacilitationContentBlock;
}

export interface FacilitationExpectedOutput {
    id: string;
    label: string;
    status: 'planned' | 'prepared';
    decision_ids: string[];
    description: FacilitationContentBlock;
}

export interface FacilitationDecisionToLock {
    id: string;
    title: string;
    priority: 'high' | 'medium' | 'low';
    agenda_topic_ids: string[];
    field_keys: string[];
    decision_prompt: {
        text: string;
        provenance:
            | 'source_backed'
            | 'system_suggested'
            | 'consultant_authored';
        source_item_ids: string[];
        citation_ids: string[];
    };
}

export interface FacilitationBriefDocument {
    source_snapshot: {
        brief_fingerprint: string;
        generated_draft: {
            schema_version: string;
            prompt_version: string;
            profile_version: string;
        } | null;
    };
    coverage?: {
        agenda_topic_ids: string[];
        covered_topic_ids: string[];
        omitted_topics: { agenda_topic_id: string }[];
    };
    decisions_to_lock: FacilitationDecisionToLock[];
    run_of_show: FacilitationRunOfShowItem[];
    expected_outputs: FacilitationExpectedOutput[];
}

export interface FacilitationBrief {
    id: string;
    status: 'draft' | 'ready_for_workshop' | 'needs_source_review';
    schema_version: string;
    source_fingerprint: string;
    revision: number;
    needs_source_review: boolean;
    document: FacilitationBriefDocument;
}

export type ReadinessProvenance =
    | 'deterministic'
    | 'ai_generated'
    | 'consultant_edited';

export interface SourceBackedText {
    text: string;
    provenance: ReadinessProvenance;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface ReadinessStakeholderPosition {
    stakeholder_name: string;
    stakeholder_affiliation: string | null;
    value: string;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface ReadinessCompanionGroup {
    field_key: string;
    field_label: string;
    answers: ReadinessStakeholderPosition[];
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface FacilitationRisk {
    id: string;
    decision_id: string | null;
    severity: 'medium' | 'high';
    label: string;
    mitigation: string;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface CaptureChecklistItem {
    id: string;
    decision_id: string | null;
    label: string;
    reason: string;
    required: boolean;
    source_refs: SourceRef[];
    citation_ids: string[];
}

export type ReadinessState =
    | 'blocked'
    | 'needs_review'
    | 'ready'
    | 'in_progress'
    | 'no_prep_needed';

export interface ReadinessOverview {
    state: ReadinessState;
    decision_count: number;
    blocker_count: number;
    planned_minutes: number;
    next_action: string;
    blocker_summary: SourceBackedText[];
}

export type ReadinessAttentionTier =
    | 'must_resolve'
    | 'missing_evidence'
    | 'needs_confirmation';

export interface ReadinessDecision {
    id: string;
    field_key: string;
    field_label: string;
    run_of_show_item_id: string | null;
    attention_tier: ReadinessAttentionTier;
    decision_question: SourceBackedText;
    why_this_matters: SourceBackedText | null;
    source_positions: ReadinessStakeholderPosition[];
    companion_groups: ReadinessCompanionGroup[];
    matrix_context: SourceBackedText[];
    start_with: SourceBackedText | null;
    fallback_question: SourceBackedText | null;
    watch_outs: FacilitationRisk[];
    capture_requirements: CaptureChecklistItem[];
    related_decision_ids: string[];
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface OpeningPlan {
    first_decision_id: string | null;
    opening_question: SourceBackedText;
    fallback_question: SourceBackedText | null;
    setup_note: SourceBackedText | null;
    timebox_note: string | null;
}

export interface FacilitatorReadinessPack {
    schema_version: '0.1';
    metric_id: string;
    metric_name: string;
    generated_at: string | null;
    provenance: ReadinessProvenance;
    readiness: ReadinessOverview;
    workshop_thesis: SourceBackedText | null;
    decisions: ReadinessDecision[];
    opening_plan: OpeningPlan | null;
    capture_checklist: CaptureChecklistItem[];
    facilitation_risks: FacilitationRisk[];
    source_refs: SourceRef[];
    citation_ids: string[];
}

export interface FieldBriefContext {
    conflicts: AttentionItem[];
    decisions: AttentionItem[];
    missing: AttentionItem[];
    recommendedQuestions: RecommendedQuestion[];
    companionAnswers: CompanionAnswerGroup[];
    stakeholderPositions: StakeholderPosition[];
    cueContext: WorkshopCueContext | null;
    workshopCue: WorkshopCue | null;
}

export interface FieldRole {
    key: string;
    label: string;
    role: 'definition' | 'context' | 'governance' | 'diagnostic';
    required: boolean;
    exclude_from_consensus: boolean;
    companion_of: string | null;
    cue_context_scope?: 'matrix_wide' | null;
    executive_section: string | null;
    value: unknown;
    unclassified: boolean;
    workshop_prompt: string | null;
    workshop_guidance: string | null;
    alignment_signal: AlignmentSignalInfo | null;
    values: ContributorValue[];
}
