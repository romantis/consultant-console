export type MrrSnapshotChoice = 'last_calendar_day' | 'rolling_daily';

export type MetricKey =
    | 'golden-mrr'
    | 'golden-gross-churn'
    | 'golden-cac-cpl';

export type OperationalStatus = 'operationally_ready' | 'needs_follow_up';

export interface MemberProfile {
    key: 'cfo' | 'revops' | 'finance' | 'marketing';
    name: string;
    email: string;
    affiliation: string;
    roleLabel: string;
}

export interface ImplementationGap {
    currentState: string;
    gap: string;
    owner: string;
    suggestedNextStep: string;
}

export interface DefinitionField {
    key: string;
    label: string;
    value: string;
    isInteractiveDecision?: boolean;
}

export interface RevisionEntry {
    version: number;
    signedOffAt: string;
    signedOffBy: string;
    notes: string;
    snapshotTiming?: string;
}

export interface WorkshopMatrixRow {
    fieldKey: string;
    label: string;
    financeAnswer: string;
    financeNotes: string;
    revopsAnswer: string;
    revopsNotes: string;
    hasDiscrepancy: boolean;
    isPrimaryDecision?: boolean;
    resolvedLabel: string;
    resolutionNotes: string;
}

export interface MrrSnapshotOption {
    id: MrrSnapshotChoice;
    team: string;
    contributor: string;
    shortTitle: string;
    canonicalLabel: string;
    summary: string;
    intakeRationale: string;
    boardImpact: string;
    matchesBundledPdf: boolean;
}

export interface DemoMetric {
    key: MetricKey;
    templateKey: 'MRR' | 'CHURN' | 'CAC_CPL';
    name: string;
    shortName: string;
    owner: MemberProfile;
    approver: MemberProfile;
    contributors: MemberProfile[];
    isSignedOff: boolean;
    statusLabel: 'Finalized' | 'Pending sign-off';
    urgency: 'finalized' | 'in_progress' | 'needs_attention';
    stageLabel: string;
    version: number;
    signedOffAt: string | null;
    signedOffBy: string | null;
    notes: string;
    approvalComment: string;
    executiveTakeaway: string;
    alignmentSummary: string;
    operationalStatus: OperationalStatus;
    operationalStatusNotes: string;
    implementationGaps: ImplementationGap | null;
    validationSteps: string;
    commonMismatchCauses: string;
    workshopConsensus: string;
    workshopParticipants: string[];
    workshopScheduledAt: string;
    definitionFields: DefinitionField[];
    revisionHistory: RevisionEntry[];
}

export const GOLDEN_PATH_ORG = {
    slug: 'orion-golden-path',
    name: 'Orion Insight Collective',
    timezone: 'America/New_York',
} as const;

export const GOLDEN_PATH_ENGAGEMENT = {
    title: 'Unit Economics Board Baseline',
    startsOn: '2026-05-01',
    endsOn: '2026-05-15',
    nextReviewOn: '2026-08-01',
    nextReviewLabel: 'Aug 1, 2026',
    reviewCadence: 'Quarterly',
} as const;

export const SEEDED_PDF_MRR_CHOICE: MrrSnapshotChoice = 'last_calendar_day';

export const MEMBERS: Record<MemberProfile['key'], MemberProfile> = {
    cfo: {
        key: 'cfo',
        name: 'Nora Kim',
        email: 'nora.kim.golden@orion.test',
        affiliation: 'Chief Financial Officer',
        roleLabel: 'Owner',
    },
    revops: {
        key: 'revops',
        name: 'Anthony Reyes',
        email: 'anthony.reyes.golden@orion.test',
        affiliation: 'Revenue Operations',
        roleLabel: 'Approver',
    },
    finance: {
        key: 'finance',
        name: 'Lia Price',
        email: 'lia.price.golden@orion.test',
        affiliation: 'Finance',
        roleLabel: 'Contributor',
    },
    marketing: {
        key: 'marketing',
        name: 'Dana Whitfield',
        email: 'dana.whitfield.golden@orion.test',
        affiliation: 'Growth Marketing',
        roleLabel: 'Contributor',
    },
};

export const MRR_SNAPSHOT_OPTIONS: Record<
    MrrSnapshotChoice,
    MrrSnapshotOption
> = {
    last_calendar_day: {
        id: 'last_calendar_day',
        team: 'Finance',
        contributor: 'Lia Price',
        shortTitle: 'Month-end close snapshot',
        canonicalLabel: 'Last calendar day (month-end close)',
        summary:
            'Lock official Board MRR on the last calendar day of each month after financial close.',
        intakeRationale:
            'We require month-end locking to align with the financial close schedule.',
        boardImpact:
            'Matches the finance close pack immediately with zero implementation gaps; RevOps keeps a separate daily operating view.',
        matchesBundledPdf: true,
    },
    rolling_daily: {
        id: 'rolling_daily',
        team: 'Revenue Operations',
        contributor: 'Anthony Reyes',
        shortTitle: 'Rolling daily view',
        canonicalLabel: 'Rolling daily snapshot',
        summary:
            'Report Board MRR from live CRM plan amounts updated daily for real-time visibility.',
        intakeRationale:
            'We prefer daily rolling views for operational monitoring.',
        boardImpact:
            'Unifies CRM and board cadence around live daily cuts, but adds a follow-up task to reconcile Finance close packs against rolling numbers.',
        matchesBundledPdf: false,
    },
};

export const MRR_WORKSHOP_MATRIX: WorkshopMatrixRow[] = [
    {
        fieldKey: 'mrr_snapshot_timing',
        label: 'Official MRR snapshot timing',
        financeAnswer: 'Last calendar day (month-end close)',
        financeNotes:
            'We require month-end locking to align with the financial close schedule.',
        revopsAnswer: 'Rolling daily snapshot',
        revopsNotes:
            'We prefer daily rolling views for operational monitoring.',
        hasDiscrepancy: true,
        isPrimaryDecision: true,
        resolvedLabel: 'Choose in workshop',
        resolutionNotes:
            'Finance requires month-end cutoff while RevOps uses a rolling daily view.',
    },
    {
        fieldKey: 'annual_deal_handling',
        label: 'Annual and multi-year contract treatment',
        financeAnswer: 'Full invoice in invoice month',
        financeNotes:
            'Large enterprise annual contracts are recognized upfront on invoice date per accounting rules.',
        revopsAnswer: 'Normalized contract value (prorated)',
        revopsNotes:
            'We prorate annual contracts to reflect the true monthly run rate from the start of the contract.',
        hasDiscrepancy: true,
        resolvedLabel: 'Normalized contract value (prorated)',
        resolutionNotes:
            'Workshop agreed to evenly prorate annual contracts across the term for board reporting.',
    },
    {
        fieldKey: 'mid_month_changes',
        label: 'Mid-month plan change treatment',
        financeAnswer: 'Wait until next billing cycle',
        financeNotes:
            'We true up mid-month updates on the next billing run.',
        revopsAnswer: 'Update immediately',
        revopsNotes:
            'Upgrades are shown immediately on CRM boards to credit the sales team.',
        hasDiscrepancy: true,
        resolvedLabel: 'Wait until next billing cycle',
        resolutionNotes:
            'Workshop agreed to wait until the next billing cycle for board baseline MRR adjustments.',
    },
    {
        fieldKey: 'included_revenue_streams',
        label: 'Charge types included in MRR',
        financeAnswer: 'Recurring subscription charges',
        financeNotes:
            'Usage fees, professional services, and trial accounts are excluded.',
        revopsAnswer: 'Recurring subscription + variable usage charges',
        revopsNotes:
            'We include usage overages in our operational pipeline view, but they are excluded from the main MRR baseline.',
        hasDiscrepancy: true,
        resolvedLabel: 'Recurring subscription charges only',
        resolutionNotes:
            'Workshop agreed to exclude usage and overage fees from board MRR, keeping them as separate line items.',
    },
    {
        fieldKey: 'discount_handling',
        label: 'Discount, promotion, and credit treatment',
        financeAnswer: 'Net recurring price',
        financeNotes:
            'Credits and promotional discounts are deducted from the MRR base immediately.',
        revopsAnswer: 'Net recurring price',
        revopsNotes:
            'Discounts are applied on a monthly net basis once Stripe invoices are posted.',
        hasDiscrepancy: false,
        resolvedLabel: 'Net recurring price',
        resolutionNotes: 'Both teams already aligned on net recurring price.',
    },
    {
        fieldKey: 'churn_timing',
        label: 'Churn removal timing from MRR',
        financeAnswer: 'Paid-through date',
        financeNotes:
            'Customers remain in MRR until their active paid-through term ends.',
        revopsAnswer: 'Paid-through date',
        revopsNotes:
            'We leave the customer in the active MRR cohort until the paid-through term is over.',
        hasDiscrepancy: false,
        resolvedLabel: 'Paid-through date',
        resolutionNotes: 'Both teams already aligned on paid-through date.',
    },
    {
        fieldKey: 'trial_handling',
        label: 'Trial and grace-period treatment',
        financeAnswer: 'Exclude until payment',
        financeNotes:
            'Free trials do not contribute to MRR until the first payment clears.',
        revopsAnswer: 'Exclude until payment',
        revopsNotes:
            'Trial accounts are filtered out until the trial expires and billing begins.',
        hasDiscrepancy: false,
        resolvedLabel: 'Exclude until payment',
        resolutionNotes: 'Both teams already aligned on excluding unpaid trials.',
    },
];

export function buildMrrMetric(
    choice: MrrSnapshotChoice | null,
    isApproved: boolean,
    approvedAt: string | null,
): DemoMetric {
    const effectiveChoice: MrrSnapshotChoice = choice ?? 'last_calendar_day';
    const isMonthEnd = effectiveChoice === 'last_calendar_day';
    const option = MRR_SNAPSHOT_OPTIONS[effectiveChoice];

    const definitionFields: DefinitionField[] = [
        {
            key: 'mrr_snapshot_timing',
            label: 'Official MRR snapshot timing',
            value: choice
                ? option.canonicalLabel
                : 'Unresolved — select in Workshop',
            isInteractiveDecision: true,
        },
        {
            key: 'mrr_description',
            label: 'MRR measurement approach',
            value: isMonthEnd
                ? 'Board MRR is the month-end recurring subscription run rate for paying customers, excluding one-time services and non-recurring adjustments.'
                : 'Board MRR is the rolling daily recurring subscription run rate for paying customers from live CRM plan amounts, excluding one-time services and usage overages.',
        },
        {
            key: 'annual_deal_handling',
            label: 'Annual and multi-year contract treatment',
            value: 'Normalized contract value (prorated evenly across contract term)',
        },
        {
            key: 'discount_handling',
            label: 'Discount, promotion, and credit treatment in MRR',
            value: 'Net recurring price (deduct active credits and promotions)',
        },
        {
            key: 'mid_month_changes',
            label: 'Mid-month plan change treatment',
            value: isMonthEnd
                ? 'Wait until next billing cycle'
                : 'Wait until next billing cycle (board pack cut)',
        },
        {
            key: 'churn_timing',
            label: 'Churn removal timing from MRR',
            value: 'Paid-through date',
        },
        {
            key: 'trial_handling',
            label: 'Trial and grace-period treatment in MRR',
            value: 'Exclude until payment clears',
        },
        {
            key: 'included_revenue_streams',
            label: 'Charge types included in MRR',
            value: 'Recurring subscription charges only',
        },
    ];

    const revisionHistory: RevisionEntry[] = [
        ...(isApproved
            ? [
                  {
                      version: 2,
                      signedOffAt: approvedAt ?? 'May 14, 2026',
                      signedOffBy: MEMBERS.revops.name,
                      notes: isMonthEnd
                          ? 'Locked for board and lender reporting using month-end close snapshot.'
                          : 'Approved for board reporting using rolling daily snapshot with Finance close-pack reconciliation.',
                      snapshotTiming: option.canonicalLabel,
                  },
              ]
            : []),
        {
            version: 1,
            signedOffAt: 'May 2, 2026',
            signedOffBy: MEMBERS.revops.name,
            notes: 'Superseded after finance and RevOps reconciled annual-deal treatment.',
            snapshotTiming: 'First business day (invoice-date legacy)',
        },
    ];

    return {
        key: 'golden-mrr',
        templateKey: 'MRR',
        name: 'Monthly Recurring Revenue (MRR)',
        shortName: 'Monthly Recurring Revenue',
        owner: MEMBERS.cfo,
        approver: MEMBERS.revops,
        contributors: [MEMBERS.finance, MEMBERS.revops],
        isSignedOff: isApproved,
        statusLabel: isApproved ? 'Finalized' : 'Pending sign-off',
        urgency: isApproved ? 'finalized' : 'in_progress',
        stageLabel: isApproved
            ? 'Signed off'
            : choice
              ? 'Draft ready for Workbench approval'
              : 'Workshop discrepancy open',
        version: isApproved ? 2 : 1,
        signedOffAt: isApproved ? (approvedAt ?? 'May 14, 2026') : null,
        signedOffBy: isApproved ? MEMBERS.revops.name : null,
        notes: isMonthEnd
            ? 'Locked for board and lender reporting.'
            : 'Approved as rolling daily MRR view; Finance will publish a month-end reconciliation bridge for lender packets.',
        approvalComment: isMonthEnd
            ? 'Approved for board reporting baseline (month-end close snapshot).'
            : 'Approved for board reporting baseline (rolling daily snapshot).',
        executiveTakeaway: isMonthEnd
            ? 'Board MRR now has one approved month-end recurring run-rate baseline across Finance, RevOps, and leadership.'
            : 'Board MRR is approved on a rolling daily recurring run-rate cadence so leadership and RevOps share real-time visibility, with a Finance close bridge for month-end packs.',
        alignmentSummary: isMonthEnd
            ? 'MRR is now aligned for board use: finance, RevOps, and leadership all read the same month-end recurring run-rate number.'
            : 'Finance and RevOps aligned on a rolling daily MRR snapshot with prorated annual deals and usage fees excluded, pairing live CRM visibility with a monthly Finance tie-out.',
        operationalStatus: isMonthEnd
            ? 'operationally_ready'
            : 'needs_follow_up',
        operationalStatusNotes: isMonthEnd
            ? 'Board MRR already runs from the finance close pack and matches the approved month-end cutoff.'
            : 'RevOps dashboards already publish the rolling daily MRR view, while Finance needs a documented month-end tie-out bridge for board and lender packs.',
        implementationGaps: isMonthEnd
            ? null
            : {
                  currentState:
                      'RevOps computes rolling daily MRR in CRM analytics while Finance locks its board workbook on the last calendar day.',
                  gap: 'The finance board workbook does not yet ingest the daily snapshot timestamp or log the variance against month-end close.',
                  owner: 'Finance and Revenue Operations',
                  suggestedNextStep:
                      'Add a frozen daily-cutoff timestamp to the warehouse MRR mart and attach a monthly reconciliation note to the board pack.',
              },
        validationSteps: isMonthEnd
            ? 'At month-end close, confirm the board pack MRR matches the locked finance close workbook and the recurring revenue warehouse extract.'
            : 'Before each board packet cutoff, confirm the rolling daily MRR extract matches live CRM subscription schedules and attach the Finance close bridge.',
        commonMismatchCauses: isMonthEnd
            ? 'MRR drifts when annual contracts are recognized upfront or when daily operational snapshots are used instead of the month-end locked close.'
            : 'MRR drifts when annual contracts are recognized upfront, usage overages slip into CRM rollups, or unlogged timing differences appear against month-end GL close.',
        workshopConsensus: isMonthEnd
            ? 'Board MRR baseline is defined as the locked month-end close pack, with annual deals prorated and usage/overage fees excluded.'
            : 'Board MRR baseline uses the rolling daily snapshot from CRM subscriptions, with annual deals prorated, usage/overage fees excluded, and a Finance close-pack bridge.',
        workshopParticipants: [
            MEMBERS.cfo.name,
            MEMBERS.finance.name,
            MEMBERS.revops.name,
        ],
        workshopScheduledAt: 'May 13, 2026 · 3:00 PM',
        definitionFields,
        revisionHistory,
    };
}

export const STATIC_METRICS: Record<
    Exclude<MetricKey, 'golden-mrr'>,
    DemoMetric
> = {
    'golden-gross-churn': {
        key: 'golden-gross-churn',
        templateKey: 'CHURN',
        name: 'Gross Revenue Churn',
        shortName: 'Churn',
        owner: MEMBERS.revops,
        approver: MEMBERS.finance,
        contributors: [MEMBERS.finance, MEMBERS.revops],
        isSignedOff: true,
        statusLabel: 'Finalized',
        urgency: 'finalized',
        stageLabel: 'Signed off',
        version: 1,
        signedOffAt: 'May 14, 2026',
        signedOffBy: MEMBERS.finance.name,
        notes: 'The board figure sums MRR dollars lost to cancellations and downgrades in the closed billing month. Gross churn remains separate from contraction and net retention views.',
        approvalComment: 'Approved as the official gross churn lens.',
        executiveTakeaway:
            'Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; dashboard timing remains the only implementation follow-up.',
        alignmentSummary:
            'Revenue Operations and Finance now share one gross churn definition: MRR dollars lost in the closed billing month, with downgrades counted and voluntary and involuntary churn combined.',
        operationalStatus: 'needs_follow_up',
        operationalStatusNotes:
            'Revenue Operations and Finance agree on the KPI, but the BI view still refreshes cancellations before billing-cycle close.',
        implementationGaps: {
            currentState:
                'Gross churn is checked in a finance workbook after the billing run closes.',
            gap: 'The BI dashboard posts cancellations immediately instead of waiting for end-of-cycle recognition.',
            owner: 'Revenue Operations',
            suggestedNextStep:
                'Update the churn model to apply board logic only after the billing cycle closes, then rerun the reconciliation checklist.',
        },
        validationSteps:
            'After each monthly billing close, compare the approved gross churn numerator against the finance churn workbook and the board pack export.',
        commonMismatchCauses:
            'Gross churn usually drifts when downgrades are mixed into net retention logic or when cancellation timing is taken from CRM status changes instead of billing close.',
        workshopConsensus:
            'Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; downgrade reductions are included to remain conservative.',
        workshopParticipants: [
            MEMBERS.cfo.name,
            MEMBERS.revops.name,
            MEMBERS.finance.name,
        ],
        workshopScheduledAt: 'May 13, 2026 · 3:00 PM',
        definitionFields: [
            {
                key: 'churn_types_tracked',
                label: 'Churn scopes tracked',
                value: 'Revenue (MRR dollars lost)',
            },
            {
                key: 'churn_timeframe',
                label: 'Reporting window',
                value: 'Monthly (closed billing cycle)',
            },
            {
                key: 'downgrade_handling',
                label: 'Downgrade handling',
                value: 'Count as churn',
            },
            {
                key: 'voluntary_treatment',
                label: 'Voluntary vs involuntary churn',
                value: 'Combined',
            },
            {
                key: 'churn_timing',
                label: 'Churn effective timing',
                value: 'End of billing cycle',
            },
        ],
        revisionHistory: [
            {
                version: 1,
                signedOffAt: 'May 14, 2026',
                signedOffBy: MEMBERS.finance.name,
                notes: 'Approved as the official gross churn lens.',
            },
        ],
    },
    'golden-cac-cpl': {
        key: 'golden-cac-cpl',
        templateKey: 'CAC_CPL',
        name: 'CAC and CPL',
        shortName: 'CAC & CPL',
        owner: MEMBERS.finance,
        approver: MEMBERS.marketing,
        contributors: [MEMBERS.finance, MEMBERS.marketing],
        isSignedOff: true,
        statusLabel: 'Finalized',
        urgency: 'finalized',
        stageLabel: 'Signed off',
        version: 1,
        signedOffAt: 'May 14, 2026',
        signedOffBy: MEMBERS.marketing.name,
        notes: 'Board CAC divides fully loaded acquisition spend in a cohort month by the first-paying customers attributed to that cohort; board CPL divides the same spend by SQLs generated in the month. A lead qualifies as Sales Qualified (SQL) once Sales Operations accepts it in the CRM pipeline after Account Executive review against BANT criteria.',
        approvalComment:
            'Approved as the cohort-based acquisition efficiency package.',
        executiveTakeaway:
            'CAC/CPL is approved as a fully loaded cohort-based acquisition efficiency package for board review.',
        alignmentSummary:
            'Finance, marketing, and sales now read CAC/CPL from one board definition: fully loaded spend, SQL leads, and first-paying customers on a three-month cohort.',
        operationalStatus: 'needs_follow_up',
        operationalStatusNotes:
            'The approved cohort logic is clear, but reporting still combines paid media, marketing payroll, and sales payroll in separate source views.',
        implementationGaps: {
            currentState:
                'Finance reconciles cohort CAC manually while marketing reviews direct-channel CPL in campaign tools.',
            gap: 'There is no single warehouse model that packages shared spend and first-payment timing into one board-ready CAC/CPL output.',
            owner: 'Finance and Growth Marketing',
            suggestedNextStep:
                'Publish one board-ready acquisition-efficiency model with shared acquisition-cost inclusion rules to both finance and marketing dashboards.',
        },
        validationSteps:
            'For each board period, verify included spend categories match the approved package and that first-paying customer counts reconcile to the cohort close workbook.',
        commonMismatchCauses:
            'CAC/CPL usually drifts when payroll costs or overhead are excluded from one view, when leads or signed contracts are mistaken for first-paying customers, or when cohort dates are mixed with invoice-period timing.',
        workshopConsensus:
            'Board CAC/CPL uses fully loaded acquisition spend, SQL leads, first-paying customers, and cohort timing.',
        workshopParticipants: [
            MEMBERS.cfo.name,
            MEMBERS.finance.name,
            MEMBERS.marketing.name,
            MEMBERS.revops.name,
        ],
        workshopScheduledAt: 'May 13, 2026 · 3:00 PM',
        definitionFields: [
            {
                key: 'costs_included',
                label: 'CAC/CPL cost components included',
                value: 'Direct advertising spend, Marketing salaries / agency fees, Sales salaries / commissions, Overhead',
            },
            {
                key: 'lead_definition',
                label: 'CPL lead definition',
                value: 'Sales Qualified Leads (SQLs)',
            },
            {
                key: 'customer_definition',
                label: 'CAC customer acquisition point',
                value: 'First payment processed',
            },
            {
                key: 'cost_timing',
                label: 'Cost-to-outcome alignment',
                value: 'Cohort (3-month conversion window)',
            },
            {
                key: 'deduplication_method',
                label: 'Multi-touch deduplication',
                value: 'Unify by person (CRM contact ID + verified email)',
            },
            {
                key: 'overhead_handling',
                label: 'Overhead & indirect costs',
                value: 'Fully loaded',
            },
        ],
        revisionHistory: [
            {
                version: 1,
                signedOffAt: 'May 14, 2026',
                signedOffBy: MEMBERS.marketing.name,
                notes: 'Approved as the cohort-based acquisition efficiency package.',
            },
        ],
    },
};
