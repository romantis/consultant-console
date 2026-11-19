<?php

namespace Database\Seeders\StorySeeds;

use App\Actions\Metrics\ApproveSignOff;
use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Enums\StakeholderRole;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\FinalDefinition;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\ReconciliationWorkshop;
use App\Support\EngagementChecklist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class GoldenPathExecutiveArtifactStorySeeder extends BaseStorySeeder
{
    private const ORGANIZATION_SLUG = 'orion-golden-path';

    private const ORGANIZATION_NAME = 'Orion Insight Collective';

    private const ENGAGEMENT_TITLE = 'Unit Economics Board Baseline';

    private const STARTS_ON = '2026-05-01';

    private const ENDS_ON = '2026-05-15';

    private const NEXT_REVIEW_ON = '2026-08-01';

    /**
     * @var array<string, array{name: string, email: string, role: MembershipRole, affiliation: string, stakeholder_role: StakeholderRole}>
     */
    private const MEMBERS = [
        'cfo' => [
            'name' => 'Nora Kim',
            'email' => 'nora.kim.golden@orion.test',
            'role' => MembershipRole::OWNER,
            'affiliation' => 'Chief Financial Officer',
            'stakeholder_role' => StakeholderRole::ADMIN,
        ],
        'revops' => [
            'name' => 'Anthony Reyes',
            'email' => 'anthony.reyes.golden@orion.test',
            'role' => MembershipRole::ADMIN,
            'affiliation' => 'Revenue Operations',
            'stakeholder_role' => StakeholderRole::ADMIN,
        ],
        'finance' => [
            'name' => 'Lia Price',
            'email' => 'lia.price.golden@orion.test',
            'role' => MembershipRole::MEMBER,
            'affiliation' => 'Finance',
            'stakeholder_role' => StakeholderRole::MEMBER,
        ],
        'marketing' => [
            'name' => 'Dana Whitfield',
            'email' => 'dana.whitfield.golden@orion.test',
            'role' => MembershipRole::MEMBER,
            'affiliation' => 'Growth Marketing',
            'stakeholder_role' => StakeholderRole::MEMBER,
        ],
    ];

    /**
     * @var array<int, array{
     *   key: string,
     *   template_key: string,
     *   name: string,
     *   owner: string,
     *   approver: string,
     *   contributors: list<string>,
     *   sign_off_at: string,
     *   input_matrix?: array{
     *     form_title: string,
     *     contributors: list<array{
     *       member: string,
     *       tags?: list<string>,
     *       submitted_at: string,
     *       answers: array<string, mixed>,
     *       normalized: array<string, mixed>
     *     }>,
     *     discrepancies: list<array{
     *       field_key: string,
     *       rule_type: string,
     *       severity: int,
     *       status: string,
     *       description: string,
     *       detected_at: string,
     *       resolved_at?: string,
     *       resolution_notes?: string
     *     }>
     *   },
     *   definition: array<string, mixed>,
     *   notes: string,
     *   comment: string,
     *   consultant_signals: array{
     *     alignment_summary: string,
     *     operational_status: string,
     *     operational_status_notes: string,
     *     implementation_gaps: array<string, string>|null,
     *     validation_steps: string,
     *     common_mismatch_causes: string,
     *     executive_takeaway: string
     *   },
     *   prior_revision?: array{
     *     signed_off_at: string,
     *     definition: array<string, mixed>,
     *     notes: string
     *   },
     *   workshop?: array{
     *     scheduled_at: string,
     *     participants: list<string>,
     *     decision_snapshot: array{
     *       consensus: string,
     *       follow_up: list<string>,
     *       field_decisions: array<string, array{selected: mixed, rationale: string}>
     *     },
     *     notes: string
     *   }
     * }>
     */
    private const METRICS = [
        [
            'key' => 'golden-mrr',
            'template_key' => 'mrr',
            'name' => 'Monthly Recurring Revenue (MRR)',
            'owner' => 'cfo',
            'approver' => 'revops',
            'contributors' => ['finance', 'revops'],
            'sign_off_at' => '2026-05-14 14:00:00',
            'input_matrix' => [
                'form_title' => 'Monthly Recurring Revenue Measurement Form',
                'contributors' => [
                    [
                        'member' => 'finance',
                        'tags' => ['Finance'],
                        'submitted_at' => '2026-05-10 10:00:00',
                        'answers' => [
                            'team_department' => 'Finance',
                            'mrr_description' => 'Board MRR is the month-end recurring subscription run rate for paying customers, excluding one-time services and non-recurring adjustments.',
                            'mrr_snapshot_timing' => 'last_calendar_day',
                            'mrr_snapshot_notes' => 'We require month-end locking to align with the financial close schedule.',
                            'annual_deal_handling' => 'full_invoice_in_invoice_month',
                            'annual_deal_notes' => 'Large enterprise annual contracts are recognized upfront on invoice date per accounting rules.',
                            'discount_handling' => 'net_recurring_price',
                            'discount_handling_notes' => 'Credits and promotional discounts are deducted from the MRR base immediately.',
                            'mid_month_changes' => 'wait_next_cycle',
                            'mid_month_changes_notes' => 'We true up mid-month updates on the next billing run.',
                            'churn_timing' => 'paid_through_date',
                            'churn_timing_notes' => 'Customers remain in MRR until their active paid-through term ends.',
                            'trial_handling' => 'exclude_until_payment',
                            'trial_handling_notes' => 'Free trials do not contribute to MRR until the first payment clears.',
                            'included_revenue_streams' => ['recurring_subscription_charges'],
                            'revenue_stream_notes' => 'Usage fees, professional services, and trial accounts are excluded.',
                            'mrr_example' => 'A customer signing a $12k annual contract is recognized as $12k MRR upfront under our legacy invoice-date method.',
                            'mrr_pain_points' => 'RevOps dashboards re-cut MRR daily, while finance locks the board number after month-end close.',
                            'additional_notes' => 'We want to standardize on the prorated board baseline.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'mrr_description' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR measurement approach',
                                    'raw' => 'Board MRR is the month-end recurring subscription run rate for paying customers, excluding one-time services and non-recurring adjustments.',
                                    'normalized' => 'board mrr is the month-end recurring subscription run rate for paying customers, excluding one-time services and non-recurring adjustments.',
                                ],
                                'mrr_snapshot_timing' => [
                                    'type' => 'select',
                                    'label' => 'Official MRR snapshot timing',
                                    'raw' => 'last_calendar_day',
                                    'normalized' => 'last_calendar_day',
                                ],
                                'annual_deal_handling' => [
                                    'type' => 'select',
                                    'label' => 'Annual and multi-year contract treatment',
                                    'raw' => 'full_invoice_in_invoice_month',
                                    'normalized' => 'full_invoice_in_invoice_month',
                                ],
                                'discount_handling' => [
                                    'type' => 'select',
                                    'label' => 'Discount, promotion, and credit treatment in MRR',
                                    'raw' => 'net_recurring_price',
                                    'normalized' => 'net_recurring_price',
                                ],
                                'mid_month_changes' => [
                                    'type' => 'select',
                                    'label' => 'Mid-month plan change treatment',
                                    'raw' => 'wait_next_cycle',
                                    'normalized' => 'wait_next_cycle',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'Churn removal timing from MRR',
                                    'raw' => 'paid_through_date',
                                    'normalized' => 'paid_through_date',
                                ],
                                'trial_handling' => [
                                    'type' => 'select',
                                    'label' => 'Trial and grace-period treatment in MRR',
                                    'raw' => 'exclude_until_payment',
                                    'normalized' => 'exclude_until_payment',
                                ],
                                'included_revenue_streams' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Charge types included in MRR',
                                    'raw' => ['recurring_subscription_charges'],
                                    'normalized' => ['recurring_subscription_charges'],
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Respondent team or department',
                                    'raw' => 'Finance',
                                    'normalized' => 'finance',
                                ],
                                'mrr_snapshot_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR snapshot timing rationale',
                                    'raw' => 'We require month-end locking to align with the financial close schedule.',
                                    'normalized' => 'we require month-end locking to align with the financial close schedule.',
                                ],
                                'annual_deal_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Annual deal exceptions and context',
                                    'raw' => 'Large enterprise annual contracts are recognized upfront on invoice date per accounting rules.',
                                    'normalized' => 'large enterprise annual contracts are recognized upfront on invoice date per accounting rules.',
                                ],
                                'discount_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Discount and credit edge cases',
                                    'raw' => 'Credits and promotional discounts are deducted from the MRR base immediately.',
                                    'normalized' => 'credits and promotional discounts are deducted from the mrr base immediately.',
                                ],
                                'mid_month_changes_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Mid-month change handling nuances',
                                    'raw' => 'We true up mid-month updates on the next billing run.',
                                    'normalized' => 'we true up mid-month updates on the next billing run.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn timing exceptions and rules',
                                    'raw' => 'Customers remain in MRR until their active paid-through term ends.',
                                    'normalized' => 'customers remain in mrr until their active paid-through term ends.',
                                ],
                                'trial_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Trial and grace-period context',
                                    'raw' => 'Free trials do not contribute to MRR until the first payment clears.',
                                    'normalized' => 'free trials do not contribute to mrr until the first payment clears.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Charge type special cases affecting MRR',
                                    'raw' => 'Usage fees, professional services, and trial accounts are excluded.',
                                    'normalized' => 'usage fees, professional services, and trial accounts are excluded.',
                                ],
                                'mrr_example' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR calculation example',
                                    'raw' => 'A customer signing a $12k annual contract is recognized as $12k MRR upfront under our legacy invoice-date method.',
                                    'normalized' => 'a customer signing a $12k annual contract is recognized as $12k mrr upfront under our legacy invoice-date method.',
                                ],
                                'mrr_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Cross-team MRR definition conflict points',
                                    'raw' => 'RevOps dashboards re-cut MRR daily, while finance locks the board number after month-end close.',
                                    'normalized' => 'revops dashboards re-cut mrr daily, while finance locks the board number after month-end close.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional MRR notes and disclaimers',
                                    'raw' => 'We want to standardize on the prorated board baseline.',
                                    'normalized' => 'we want to standardize on the prorated board baseline.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'member' => 'revops',
                        'tags' => ['Revenue Operations'],
                        'submitted_at' => '2026-05-10 11:30:00',
                        'answers' => [
                            'team_department' => 'Revenue Operations',
                            'mrr_description' => 'We track live plan amounts in the CRM. Our analytics dashboards report rolling updates daily for real-time visibility.',
                            'mrr_snapshot_timing' => 'rolling_daily',
                            'mrr_snapshot_notes' => 'We prefer daily rolling views for operational monitoring.',
                            'annual_deal_handling' => 'normalized_contract_value',
                            'annual_deal_notes' => 'We prorate annual contracts to reflect the true monthly run rate from the start of the contract.',
                            'discount_handling' => 'net_recurring_price',
                            'discount_handling_notes' => 'Discounts are applied on a monthly net basis once Stripe invoices are posted.',
                            'mid_month_changes' => 'update_immediately',
                            'mid_month_changes_notes' => 'Upgrades are shown immediately on CRM boards to credit the sales team.',
                            'churn_timing' => 'paid_through_date',
                            'churn_timing_notes' => 'We leave the customer in the active MRR cohort until the paid-through term is over.',
                            'trial_handling' => 'exclude_until_payment',
                            'trial_handling_notes' => 'Trial accounts are filtered out until the trial expires and billing begins.',
                            'included_revenue_streams' => ['recurring_subscription_charges', 'variable_usage_charges'],
                            'revenue_stream_notes' => 'We include usage overages in our operational pipeline view, but they are excluded from the main MRR baseline.',
                            'mrr_example' => 'For a $12k annual contract starting June 1, we prorate and add $1k to MRR starting in June.',
                            'mrr_pain_points' => 'RevOps dashboards re-cut MRR daily and include usage fees, causing mismatches against the finance board pack.',
                            'additional_notes' => 'We use the approved close-pack baseline for board reporting while keeping the daily operational view separate.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'mrr_description' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR measurement approach',
                                    'raw' => 'We track live plan amounts in the CRM. Our analytics dashboards report rolling updates daily for real-time visibility.',
                                    'normalized' => 'we track live plan amounts in the crm. our analytics dashboards report rolling updates daily for real-time visibility.',
                                ],
                                'mrr_snapshot_timing' => [
                                    'type' => 'select',
                                    'label' => 'Official MRR snapshot timing',
                                    'raw' => 'rolling_daily',
                                    'normalized' => 'rolling_daily',
                                ],
                                'annual_deal_handling' => [
                                    'type' => 'select',
                                    'label' => 'Annual and multi-year contract treatment',
                                    'raw' => 'normalized_contract_value',
                                    'normalized' => 'normalized_contract_value',
                                ],
                                'discount_handling' => [
                                    'type' => 'select',
                                    'label' => 'Discount, promotion, and credit treatment in MRR',
                                    'raw' => 'net_recurring_price',
                                    'normalized' => 'net_recurring_price',
                                ],
                                'mid_month_changes' => [
                                    'type' => 'select',
                                    'label' => 'Mid-month plan change treatment',
                                    'raw' => 'update_immediately',
                                    'normalized' => 'update_immediately',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'Churn removal timing from MRR',
                                    'raw' => 'paid_through_date',
                                    'normalized' => 'paid_through_date',
                                ],
                                'trial_handling' => [
                                    'type' => 'select',
                                    'label' => 'Trial and grace-period treatment in MRR',
                                    'raw' => 'exclude_until_payment',
                                    'normalized' => 'exclude_until_payment',
                                ],
                                'included_revenue_streams' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Charge types included in MRR',
                                    'raw' => ['recurring_subscription_charges', 'variable_usage_charges'],
                                    'normalized' => ['recurring_subscription_charges', 'variable_usage_charges'],
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Respondent team or department',
                                    'raw' => 'Revenue Operations',
                                    'normalized' => 'revenue_operations',
                                ],
                                'mrr_snapshot_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR snapshot timing rationale',
                                    'raw' => 'We prefer daily rolling views for operational monitoring.',
                                    'normalized' => 'we prefer daily rolling views for operational monitoring.',
                                ],
                                'annual_deal_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Annual deal exceptions and context',
                                    'raw' => 'We prorate annual contracts to reflect the true monthly run rate from the start of the contract.',
                                    'normalized' => 'we prorate annual contracts to reflect the true monthly run rate from the start of the contract.',
                                ],
                                'discount_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Discount and credit edge cases',
                                    'raw' => 'Discounts are applied on a monthly net basis once Stripe invoices are posted.',
                                    'normalized' => 'discounts are applied on a monthly net basis once stripe invoices are posted.',
                                ],
                                'mid_month_changes_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Mid-month change handling nuances',
                                    'raw' => 'Upgrades are shown immediately on CRM boards to credit the sales team.',
                                    'normalized' => 'upgrades are shown immediately on crm boards to credit the sales team.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn timing exceptions and rules',
                                    'raw' => 'We leave the customer in the active MRR cohort until the paid-through term is over.',
                                    'normalized' => 'we leave the customer in the active mrr cohort until the paid-through term is over.',
                                ],
                                'trial_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Trial and grace-period context',
                                    'raw' => 'Trial accounts are filtered out until the trial expires and billing begins.',
                                    'normalized' => 'trial accounts are filtered out until the trial expires and billing begins.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Charge type special cases affecting MRR',
                                    'raw' => 'We include usage overages in our operational pipeline view, but they are excluded from the main MRR baseline.',
                                    'normalized' => 'we include usage overages in our operational pipeline view, but they are excluded from the main mrr baseline.',
                                ],
                                'mrr_example' => [
                                    'type' => 'textarea',
                                    'label' => 'MRR calculation example',
                                    'raw' => 'For a $12k annual contract starting June 1, we prorate and add $1k to MRR starting in June.',
                                    'normalized' => 'for a $12k annual contract starting june 1, we prorate and add $1k to mrr starting in june.',
                                ],
                                'mrr_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Cross-team MRR definition conflict points',
                                    'raw' => 'RevOps dashboards re-cut MRR daily and include usage fees, causing mismatches against the finance board pack.',
                                    'normalized' => 'revops dashboards re-cut mrr daily and include usage fees, causing mismatches against the finance board pack.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional MRR notes and disclaimers',
                                    'raw' => 'We use the approved close-pack baseline for board reporting while keeping the daily operational view separate.',
                                    'normalized' => 'we use the approved close-pack baseline for board reporting while keeping the daily operational view separate.',
                                ],
                            ],
                        ],
                    ],
                ],
                'discrepancies' => [
                    [
                        'field_key' => 'mrr_snapshot_timing',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Finance requires month-end cutoff while RevOps uses a rolling daily view.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 15:30:00',
                        'resolution_notes' => 'Workshop aligned on month-end close-pack view as the board baseline.',
                    ],
                    [
                        'field_key' => 'annual_deal_handling',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Finance recognizes annual contracts upfront while RevOps prorates them over the term.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 15:30:00',
                        'resolution_notes' => 'Workshop agreed to evenly prorate annual contracts across the term for board reporting.',
                    ],
                    [
                        'field_key' => 'mid_month_changes',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Finance waits for the next cycle for mid-month changes while RevOps updates immediately.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 15:30:00',
                        'resolution_notes' => 'Workshop agreed to wait until the next billing cycle for board baseline MRR adjustments.',
                    ],
                    [
                        'field_key' => 'included_revenue_streams',
                        'rule_type' => 'list_mismatch',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'RevOps includes usage-based and overage fees which Finance excludes from MRR.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 15:30:00',
                        'resolution_notes' => 'Workshop agreed to exclude usage and overage fees from board MRR, keeping them as separate line items.',
                    ],
                ],
            ],
            'definition' => [
                'mrr_description' => 'Board MRR is the month-end recurring subscription run rate for paying customers, excluding one-time services and non-recurring adjustments.',
                'mrr_snapshot_timing' => 'last_calendar_day',
                'annual_deal_handling' => 'normalized_contract_value',
                'discount_handling' => 'net_recurring_price',
                'mid_month_changes' => 'wait_next_cycle',
                'churn_timing' => 'paid_through_date',
                'trial_handling' => 'exclude_until_payment',
                'included_revenue_streams' => [
                    'recurring_subscription_charges',
                ],
                'mrr_pain_points' => 'RevOps dashboards re-cut MRR daily, while finance locks the board number after month-end close.',
            ],
            'notes' => 'Locked for board and lender reporting.',
            'comment' => 'Approved for board reporting baseline.',
            'consultant_signals' => [
                'alignment_summary' => 'MRR is now aligned for board use: finance, RevOps, and leadership all read the same month-end recurring run-rate number.',
                'operational_status' => 'operationally_ready',
                'operational_status_notes' => 'Board MRR already runs from the finance close pack and matches the approved month-end cutoff.',
                'implementation_gaps' => null,
                'validation_steps' => 'At month-end close, confirm the board pack MRR matches the locked finance close workbook and the recurring revenue warehouse extract.',
                'common_mismatch_causes' => 'MRR drifts when annual contracts are recognized upfront or when daily operational snapshots are used instead of the month-end locked close.',
                'executive_takeaway' => 'Board MRR now has one approved month-end recurring run-rate baseline across Finance, RevOps, and leadership.',
            ],
            'prior_revision' => [
                'signed_off_at' => '2026-05-02 10:00:00',
                'definition' => [
                    'mrr_description' => 'Board MRR followed the billing export at invoice date, including annual deals when invoiced.',
                    'mrr_snapshot_timing' => 'first_business_day',
                    'annual_deal_handling' => 'full_invoice_in_invoice_month',
                    'discount_handling' => 'net_recurring_price',
                    'mid_month_changes' => 'update_immediately',
                    'churn_timing' => 'immediate',
                    'trial_handling' => 'payment_method_forecast_view',
                    'included_revenue_streams' => [
                        'recurring_subscription_charges',
                        'variable_usage_charges',
                    ],
                    'mrr_pain_points' => 'Invoice-date reporting overstated board MRR against finance close in annual-deal months.',
                ],
                'notes' => 'Superseded after finance and RevOps reconciled annual-deal treatment.',
            ],
            'workshop' => [
                'scheduled_at' => '2026-05-13 15:00:00',
                'participants' => [
                    'Nora Kim',
                    'Lia Price',
                    'Anthony Reyes',
                ],
                'decision_snapshot' => [
                    'consensus' => 'Board MRR baseline is defined as the locked month-end close pack, with annual deals prorated and usage/overage fees excluded.',
                    'follow_up' => [],
                    'field_decisions' => [
                        'mrr_snapshot_timing' => [
                            'selected' => 'last_calendar_day',
                            'rationale' => 'Aligns board reporting with the locked finance month-end close pack.',
                        ],
                        'annual_deal_handling' => [
                            'selected' => 'normalized_contract_value',
                            'rationale' => 'Prorating annual contracts reflects a stable recurring run rate and matches investor guidelines.',
                        ],
                        'discount_handling' => [
                            'selected' => 'net_recurring_price',
                            'rationale' => 'Report net MRR by factoring in active credits and promotions.',
                        ],
                        'mid_month_changes' => [
                            'selected' => 'wait_next_cycle',
                            'rationale' => 'Excludes daily fluctuations from the official board snapshot.',
                        ],
                        'churn_timing' => [
                            'selected' => 'paid_through_date',
                            'rationale' => 'Retain active customers in the cohort until the paid-through term expires.',
                        ],
                        'trial_handling' => [
                            'selected' => 'exclude_until_payment',
                            'rationale' => 'Ensure MRR represents actual paying subscription run rate.',
                        ],
                        'included_revenue_streams' => [
                            'selected' => [
                                'recurring_subscription_charges',
                            ],
                            'rationale' => 'Usage fees and professional services are excluded from board MRR.',
                        ],
                    ],
                ],
                'notes' => 'No implementation gaps; RevOps dashboards already fetch the approved baseline for board reports.',
            ],
        ],
        [
            'key' => 'golden-gross-churn',
            'template_key' => 'churn',
            'name' => 'Gross Revenue Churn',
            'owner' => 'revops',
            'approver' => 'finance',
            'contributors' => ['finance', 'revops'],
            'sign_off_at' => '2026-05-14 14:15:00',
            'definition' => [
                'churn_types_tracked' => ['revenue'],
                'churn_timeframe' => 'monthly',
                'downgrade_handling' => 'count_as_churn',
                'voluntary_treatment' => 'combined',
                'churn_timing' => 'end_of_cycle',
                'churn_pain_points' => 'Revenue Operations tracks logo churn operationally, but the board-facing KPI is gross revenue churn after billing-cycle close.',
            ],
            'notes' => 'The board figure sums MRR dollars lost to cancellations and downgrades in the closed billing month. Gross churn remains separate from contraction and net retention views.',
            'comment' => 'Approved as the official gross churn lens.',
            'consultant_signals' => [
                'alignment_summary' => 'Revenue Operations and Finance now share one gross churn definition: MRR dollars lost in the closed billing month, with downgrades counted and voluntary and involuntary churn combined.',
                'operational_status' => 'needs_follow_up',
                'operational_status_notes' => 'Revenue Operations and Finance agree on the KPI, but the BI view still refreshes cancellations before billing-cycle close.',
                'implementation_gaps' => [
                    'current_state' => 'Gross churn is checked in a finance workbook after the billing run closes.',
                    'gap' => 'The BI dashboard posts cancellations immediately instead of waiting for end-of-cycle recognition.',
                    'owner' => 'Revenue Operations',
                    'suggested_next_step' => 'Update the churn model to apply board logic only after the billing cycle closes, then rerun the reconciliation checklist.',
                ],
                'validation_steps' => 'After each monthly billing close, compare the approved gross churn numerator against the finance churn workbook and the board pack export.',
                'common_mismatch_causes' => 'Gross churn usually drifts when downgrades are mixed into net retention logic or when cancellation timing is taken from CRM status changes instead of billing close.',
                'executive_takeaway' => 'Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; dashboard timing remains the only implementation follow-up.',
            ],
            'input_matrix' => [
                'form_title' => 'Gross Churn Measurement Form',
                'contributors' => [
                    [
                        'member' => 'finance',
                        'tags' => ['Finance'],
                        'submitted_at' => '2026-05-10 10:00:00',
                        'answers' => [
                            'team_department' => 'Finance',
                            'churn_types_tracked' => ['logo', 'revenue'],
                            'churn_types_notes' => 'We report gross revenue churn to the board, but also track logo churn for customer retention views.',
                            'churn_timeframe' => 'monthly',
                            'churn_timeframe_notes' => 'We report on a monthly basis based on billing runs.',
                            'downgrade_handling' => 'count_as_churn',
                            'downgrade_handling_notes' => 'Any reduction in customer spend is treated as gross churn in the finance workbook until Net Retention reconciles expansion.',
                            'voluntary_treatment' => 'combined',
                            'voluntary_treatment_notes' => 'We combine voluntary cancellations and non-renewals/involuntary payment failures.',
                            'churn_timing' => 'end_of_cycle',
                            'churn_timing_notes' => 'We only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                            'special_case_notes' => 'Refunds and chargebacks are handled as GL adjustments, not as direct churn.',
                            'churn_example' => 'If a customer paying $1k/month cancels, we count $1k as gross churn at the end of their billing month.',
                            'churn_pain_points' => 'RevOps reports churn immediately at CRM status change, which makes their monthly numbers diverge from the finance billing close.',
                            'additional_notes' => 'We need the board metrics to be based on end-of-cycle billing records.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'churn_types_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which churn scopes do you track? (select canonical values)',
                                    'raw' => ['logo', 'revenue'],
                                    'normalized' => ['logo', 'revenue'],
                                ],
                                'churn_timeframe' => [
                                    'type' => 'select',
                                    'label' => 'Which reporting window do you use for churn?',
                                    'raw' => 'monthly',
                                    'normalized' => 'monthly',
                                ],
                                'downgrade_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do downgrades factor into churn?',
                                    'raw' => 'count_as_churn',
                                    'normalized' => 'count_as_churn',
                                ],
                                'voluntary_treatment' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat voluntary vs involuntary churn?',
                                    'raw' => 'combined',
                                    'normalized' => 'combined',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you mark churn effective?',
                                    'raw' => 'end_of_cycle',
                                    'normalized' => 'end_of_cycle',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Respondent team or department',
                                    'raw' => 'Finance',
                                    'normalized' => 'finance',
                                ],
                                'churn_types_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context for churn scope selections (segments, filters, exclusions).',
                                    'raw' => 'We report gross revenue churn to the board, but also track logo churn for customer retention views.',
                                    'normalized' => 'we report gross revenue churn to the board, but also track logo churn for customer retention views.',
                                ],
                                'churn_timeframe_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Reporting window timing notes',
                                    'raw' => 'We report on a monthly basis based on billing runs.',
                                    'normalized' => 'we report on a monthly basis based on billing runs.',
                                ],
                                'downgrade_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Downgrade handling rules and guardrails',
                                    'raw' => 'Any reduction in customer spend is treated as gross churn in the finance workbook until Net Retention reconciles expansion.',
                                    'normalized' => 'any reduction in customer spend is treated as gross churn in the finance workbook until net retention reconciles expansion.',
                                ],
                                'voluntary_treatment_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Voluntary/involuntary segmentation notes',
                                    'raw' => 'We combine voluntary cancellations and non-renewals/involuntary payment failures.',
                                    'normalized' => 'we combine voluntary cancellations and non-renewals/involuntary payment failures.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn timing policy exceptions',
                                    'raw' => 'We only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                                    'normalized' => 'we only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                                ],
                                'special_case_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special-case churn treatment',
                                    'raw' => 'Refunds and chargebacks are handled as GL adjustments, not as direct churn.',
                                    'normalized' => 'refunds and chargebacks are handled as gl adjustments, not as direct churn.',
                                ],
                                'churn_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn calculation example',
                                    'raw' => 'If a customer paying $1k/month cancels, we count $1k as gross churn at the end of their billing month.',
                                    'normalized' => 'if a customer paying $1k/month cancels, we count $1k as gross churn at the end of their billing month.',
                                ],
                                'churn_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn definition divergence points',
                                    'raw' => 'RevOps reports churn immediately at CRM status change, which makes their monthly numbers diverge from the finance billing close.',
                                    'normalized' => 'revops reports churn immediately at crm status change, which makes their monthly numbers diverge from the finance billing close.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional context and disclaimers',
                                    'raw' => 'We need the board metrics to be based on end-of-cycle billing records.',
                                    'normalized' => 'we need the board metrics to be based on end-of-cycle billing records.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'member' => 'revops',
                        'tags' => ['Revenue Operations'],
                        'submitted_at' => '2026-05-10 11:30:00',
                        'answers' => [
                            'team_department' => 'Revenue Operations',
                            'churn_types_tracked' => ['revenue', 'net_revenue'],
                            'churn_types_notes' => 'We track revenue churn and net revenue churn in the customer database.',
                            'churn_timeframe' => 'monthly',
                            'churn_timeframe_notes' => 'Snapshots are taken monthly.',
                            'downgrade_handling' => 'track_as_contraction',
                            'downgrade_handling_notes' => 'Contraction is handled as a separate line item from absolute churn.',
                            'voluntary_treatment' => 'separate',
                            'voluntary_treatment_notes' => 'We tag cancellations vs payment failures separately in CRM.',
                            'churn_timing' => 'immediate',
                            'churn_timing_notes' => 'Once a user clicks cancel, the subscription status updates in CRM and reports immediately in the analytics view.',
                            'special_case_notes' => 'Account pauses are treated as active but with contracted MRR.',
                            'churn_example' => 'A customer cancels mid-month; they show as churned immediately on that day.',
                            'churn_pain_points' => 'Finance waits for billing cycle ends, so our dashboard numbers look higher or lower depending on when in the month cancellations occur.',
                            'additional_notes' => 'We want to align with Finance close but need automated dashboard sync.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'churn_types_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which churn scopes do you track? (select canonical values)',
                                    'raw' => ['revenue', 'net_revenue'],
                                    'normalized' => ['revenue', 'net_revenue'],
                                ],
                                'churn_timeframe' => [
                                    'type' => 'select',
                                    'label' => 'Which reporting window do you use for churn?',
                                    'raw' => 'monthly',
                                    'normalized' => 'monthly',
                                ],
                                'downgrade_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do downgrades factor into churn?',
                                    'raw' => 'track_as_contraction',
                                    'normalized' => 'track_as_contraction',
                                ],
                                'voluntary_treatment' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat voluntary vs involuntary churn?',
                                    'raw' => 'separate',
                                    'normalized' => 'separate',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you mark churn effective?',
                                    'raw' => 'immediate',
                                    'normalized' => 'immediate',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Respondent team or department',
                                    'raw' => 'Revenue Operations',
                                    'normalized' => 'revenue_operations',
                                ],
                                'churn_types_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context for churn scope selections (segments, filters, exclusions).',
                                    'raw' => 'We track revenue churn and net revenue churn in the customer database.',
                                    'normalized' => 'we track revenue churn and net revenue churn in the customer database.',
                                ],
                                'churn_timeframe_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Reporting window timing notes',
                                    'raw' => 'Snapshots are taken monthly.',
                                    'normalized' => 'snapshots are taken monthly.',
                                ],
                                'downgrade_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Downgrade handling rules and guardrails',
                                    'raw' => 'Contraction is handled as a separate line item from absolute churn.',
                                    'normalized' => 'contraction is handled as a separate line item from absolute churn.',
                                ],
                                'voluntary_treatment_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Voluntary/involuntary segmentation notes',
                                    'raw' => 'We tag cancellations vs payment failures separately in CRM.',
                                    'normalized' => 'we tag cancellations vs payment failures separately in CRM.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn timing policy exceptions',
                                    'raw' => 'Once a user clicks cancel, the subscription status updates in CRM and reports immediately in the analytics view.',
                                    'normalized' => 'once a user clicks cancel, the subscription status updates in crm and reports immediately in the analytics view.',
                                ],
                                'special_case_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special-case churn treatment',
                                    'raw' => 'Account pauses are treated as active but with contracted MRR.',
                                    'normalized' => 'account pauses are treated as active but with contracted mrr.',
                                ],
                                'churn_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn calculation example',
                                    'raw' => 'A customer cancels mid-month; they show as churned immediately on that day.',
                                    'normalized' => 'a customer cancels mid-month; they show as churned immediately on that day.',
                                ],
                                'churn_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Churn definition divergence points',
                                    'raw' => 'Finance waits for billing cycle ends, so our dashboard numbers look higher or lower depending on when in the month cancellations occur.',
                                    'normalized' => 'finance waits for billing cycle ends, so our dashboard numbers look higher or lower depending on when in the month cancellations occur.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional context and disclaimers',
                                    'raw' => 'We want to align with Finance close but need automated dashboard sync.',
                                    'normalized' => 'we want to align with finance close but need automated dashboard sync.',
                                ],
                            ],
                        ],
                    ],
                ],
                'discrepancies' => [
                    [
                        'field_key' => 'churn_types_tracked',
                        'rule_type' => 'value_divergence',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Divergence in churn types: Finance tracks Logo and Revenue, RevOps tracks Revenue and Net Revenue.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 16:00:00',
                        'resolution_notes' => 'Workshop agreed to focus official Gross Revenue Churn baseline on revenue scope, keeping logo churn as an operational detail.',
                    ],
                    [
                        'field_key' => 'downgrade_handling',
                        'rule_type' => 'value_divergence',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Divergence in downgrade treatment: Finance counts downgrades as churn, RevOps tracks separately as contraction.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 16:00:00',
                        'resolution_notes' => 'Workshop decided that board Gross Revenue Churn includes downgrades (count_as_churn) to remain conservative; contraction will be isolated in Net Retention.',
                    ],
                    [
                        'field_key' => 'voluntary_treatment',
                        'rule_type' => 'value_divergence',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Divergence in voluntary vs involuntary treatment: Finance combines, RevOps separates.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 16:00:00',
                        'resolution_notes' => 'Workshop agreed to combine both for the top-level Gross Revenue Churn baseline, keeping CRM categorization as secondary filters.',
                    ],
                    [
                        'field_key' => 'churn_timing',
                        'rule_type' => 'value_divergence',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Divergence in churn effective timing: Finance uses end of cycle, RevOps immediate.',
                        'detected_at' => '2026-05-11 09:00:00',
                        'resolved_at' => '2026-05-13 16:00:00',
                        'resolution_notes' => 'Workshop agreed to end-of-cycle timing for revenue recognition. An implementation gap exists for RevOps to update the BI dashboards.',
                    ],
                ],
            ],
            'workshop' => [
                'scheduled_at' => '2026-05-13 15:00:00',
                'participants' => [
                    'Nora Kim',
                    'Anthony Reyes',
                    'Lia Price',
                ],
                'decision_snapshot' => [
                    'consensus' => 'Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; downgrade reductions are included to remain conservative.',
                    'follow_up' => [
                        'Revenue Operations will update the BI dashboard timing logic to match billing end-of-cycle rules.',
                        'Finance will supply the historic GL close workbooks for dashboard validation.',
                    ],
                    'field_decisions' => [
                        'churn_types_tracked' => [
                            'selected' => ['revenue'],
                            'rationale' => 'Revenue scope aligns directly with MRR loss reporting for board metrics.',
                        ],
                        'churn_timeframe' => [
                            'selected' => 'monthly',
                            'rationale' => 'Monthly window matches the standard finance billing cycle and close pack.',
                        ],
                        'downgrade_handling' => [
                            'selected' => 'count_as_churn',
                            'rationale' => 'Treating downgrades as churn ensures we do not understate gross revenue leakage on the board baseline.',
                        ],
                        'voluntary_treatment' => [
                            'selected' => 'combined',
                            'rationale' => 'Board package requires a single conservative churn metric combining voluntary and involuntary revenue loss.',
                        ],
                        'churn_timing' => [
                            'selected' => 'end_of_cycle',
                            'rationale' => 'Timing must align with billing end-of-cycle revenue recognition rules, not raw CRM status updates.',
                        ],
                    ],
                ],
                'notes' => 'Conceptually aligned, but implementation follow-up is active for RevOps to adjust BI dashboards.',
            ],
        ],
        [
            'key' => 'golden-cac-cpl',
            'template_key' => 'cac_cpl',
            'name' => 'CAC and CPL',
            'owner' => 'finance',
            'approver' => 'marketing',
            'contributors' => ['finance', 'marketing'],
            'sign_off_at' => '2026-05-14 14:30:00',
            'input_matrix' => [
                'form_title' => 'CAC & CPL Measurement Form',
                'contributors' => [
                    [
                        'member' => 'finance',
                        'tags' => ['Finance'],
                        'submitted_at' => '2026-05-10 10:00:00',
                        'answers' => [
                            'team_department' => 'Finance',
                            'costs_included' => ['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'],
                            'costs_included_notes' => 'We include all acquisition-related salaries, benefits, media, and shared RevOps overhead in CAC and CPL.',
                            'lead_definition' => 'marketing_qualified',
                            'lead_definition_notes' => 'Leads must meet our MQL scoring rules in the automation platform before entering the denominator.',
                            'customer_definition' => 'first_payment',
                            'customer_definition_notes' => 'We only count a new customer once the first invoice is successfully paid.',
                            'cost_timing' => 'cohort',
                            'cost_timing_notes' => 'We attribute spend to the cohort of customers that convert over the following 1-3 months.',
                            'deduplication_method' => 'unify_by_person',
                            'deduplication_notes' => 'We unify duplicate leads by CRM contact ID and email so one person appears once in CPL and CAC.',
                            'overhead_handling' => 'fully_loaded',
                            'overhead_handling_notes' => 'We allocate a share of G&A and RevOps salaries into the acquisition cost pool each quarter.',
                            'cac_example' => 'In Q1 we spent $120k on acquisition and added 60 new paying customers, so CAC is $2k per customer; $40k of that spend generated 800 MQLs, so CPL is $50.',
                            'cac_pain_points' => 'Marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their CAC and CPL much lower than the finance fully loaded view.',
                            'additional_notes' => 'For board reporting we want CAC and CPL to reflect the fully loaded cost to acquire a paying customer, even if teams keep lighter internal views.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'costs_included' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which costs are included in CAC/CPL?',
                                    'raw' => ['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'],
                                    'normalized' => ['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'],
                                ],
                                'lead_definition' => [
                                    'type' => 'select',
                                    'label' => 'What do you count as a lead in your CPL calculation?',
                                    'raw' => 'marketing_qualified',
                                    'normalized' => 'marketing_qualified',
                                ],
                                'customer_definition' => [
                                    'type' => 'select',
                                    'label' => 'When is someone considered an acquired customer for CAC?',
                                    'raw' => 'first_payment',
                                    'normalized' => 'first_payment',
                                ],
                                'cost_timing' => [
                                    'type' => 'select',
                                    'label' => 'How do you align costs with the resulting leads/customers?',
                                    'raw' => 'cohort',
                                    'normalized' => 'cohort',
                                ],
                                'deduplication_method' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate people who touch multiple campaigns?',
                                    'raw' => 'unify_by_person',
                                    'normalized' => 'unify_by_person',
                                ],
                                'overhead_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle overhead or indirect costs?',
                                    'raw' => 'fully_loaded',
                                    'normalized' => 'fully_loaded',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Finance',
                                    'normalized' => 'finance',
                                ],
                                'costs_included_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on which GL codes or cost centers are included or excluded.',
                                    'raw' => 'We include all acquisition-related salaries, benefits, media, and shared RevOps overhead in CAC and CPL.',
                                    'normalized' => 'we include all acquisition-related salaries, benefits, media, and shared revops overhead in cac and cpl.',
                                ],
                                'lead_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or qualifiers to your lead definition.',
                                    'raw' => 'Leads must meet our MQL scoring rules in the automation platform before entering the denominator.',
                                    'normalized' => 'leads must meet our mql scoring rules in the automation platform before entering the denominator.',
                                ],
                                'customer_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or exceptions for customer counting.',
                                    'raw' => 'We only count a new customer once the first invoice is successfully paid.',
                                    'normalized' => 'we only count a new customer once the first invoice is successfully paid.',
                                ],
                                'cost_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe lookback windows or lag between spend and results.',
                                    'raw' => 'We attribute spend to the cohort of customers that convert over the following 1-3 months.',
                                    'normalized' => 'we attribute spend to the cohort of customers that convert over the following 1-3 months.',
                                ],
                                'deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail any matching logic or IDs used for deduplication.',
                                    'raw' => 'We unify duplicate leads by CRM contact ID and email so one person appears once in CPL and CAC.',
                                    'normalized' => 'we unify duplicate leads by crm contact id and email so one person appears once in cpl and cac.',
                                ],
                                'overhead_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain any allocation or apportionment rules.',
                                    'raw' => 'We allocate a share of G&A and RevOps salaries into the acquisition cost pool each quarter.',
                                    'normalized' => 'we allocate a share of g&a and revops salaries into the acquisition cost pool each quarter.',
                                ],
                                'cac_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide a short CAC/CPL example.',
                                    'raw' => 'In Q1 we spent $120k on acquisition and added 60 new paying customers, so CAC is $2k per customer; $40k of that spend generated 800 MQLs, so CPL is $50.',
                                    'normalized' => 'in q1 we spent $120k on acquisition and added 60 new paying customers, so cac is $2k per customer; $40k of that spend generated 800 mqls, so cpl is $50.',
                                ],
                                'cac_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do CAC/CPL definitions conflict today?',
                                    'raw' => 'Marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their CAC and CPL much lower than the finance fully loaded view.',
                                    'normalized' => 'marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their cac and cpl much lower than the finance fully loaded view.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'For board reporting we want CAC and CPL to reflect the fully loaded cost to acquire a paying customer, even if teams keep lighter internal views.',
                                    'normalized' => 'for board reporting we want cac and cpl to reflect the fully loaded cost to acquire a paying customer, even if teams keep lighter internal views.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'member' => 'marketing',
                        'tags' => ['Marketing'],
                        'submitted_at' => '2026-05-10 12:30:00',
                        'answers' => [
                            'team_department' => 'Marketing',
                            'costs_included' => ['ad_spend', 'direct_campaign_only'],
                            'costs_included_notes' => 'We only include campaign media and tools tied directly to acquisition programs; salaries and overhead are excluded.',
                            'lead_definition' => 'any_contact',
                            'lead_definition_notes' => 'Any net-new form fill or signup is treated as a lead, even if they are not yet qualified.',
                            'customer_definition' => 'contract_signed',
                            'customer_definition_notes' => 'We treat a signed contract as a new customer, even if Finance recognizes revenue after go-live or first payment.',
                            'cost_timing' => 'same_month',
                            'cost_timing_notes' => 'We match campaign spend to the same month those leads or contracts originate without cohort spreading.',
                            'deduplication_method' => 'count_all_touches',
                            'deduplication_notes' => 'If the same person responds to three campaigns, we treat that as three leads so each campaign shows its own CPL.',
                            'overhead_handling' => 'direct_only',
                            'overhead_handling_notes' => 'We intentionally exclude salaries, G&A, and RevOps overhead from our CAC and CPL slides.',
                            'cac_example' => 'If we spend $50k on campaigns in April and see 2,000 new leads and 40 signed contracts, we show $25 CPL and $1,250 CAC.',
                            'cac_pain_points' => 'The finance fully loaded CAC is nearly double our campaign-only figure, and our broader lead definition makes CPL look lower than what Finance would report.',
                            'additional_notes' => 'We are open to labeling our numbers as a marketing CAC/CPL view as long as Finance owns the official board definition.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'costs_included' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which costs are included in CAC/CPL?',
                                    'raw' => ['ad_spend', 'direct_campaign_only'],
                                    'normalized' => ['ad_spend', 'direct_campaign_only'],
                                ],
                                'lead_definition' => [
                                    'type' => 'select',
                                    'label' => 'What do you count as a lead in your CPL calculation?',
                                    'raw' => 'any_contact',
                                    'normalized' => 'any_contact',
                                ],
                                'customer_definition' => [
                                    'type' => 'select',
                                    'label' => 'When is someone considered an acquired customer for CAC?',
                                    'raw' => 'contract_signed',
                                    'normalized' => 'contract_signed',
                                ],
                                'cost_timing' => [
                                    'type' => 'select',
                                    'label' => 'How do you align costs with the resulting leads/customers?',
                                    'raw' => 'same_month',
                                    'normalized' => 'same_month',
                                ],
                                'deduplication_method' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate people who touch multiple campaigns?',
                                    'raw' => 'count_all_touches',
                                    'normalized' => 'count_all_touches',
                                ],
                                'overhead_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle overhead or indirect costs?',
                                    'raw' => 'direct_only',
                                    'normalized' => 'direct_only',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Marketing',
                                    'normalized' => 'marketing',
                                ],
                                'costs_included_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on which GL codes or cost centers are included or excluded.',
                                    'raw' => 'We only include campaign media and tools tied directly to acquisition programs; salaries and overhead are excluded.',
                                    'normalized' => 'we only include campaign media and tools tied directly to acquisition programs; salaries and overhead are excluded.',
                                ],
                                'lead_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or qualifiers to your lead definition.',
                                    'raw' => 'Any net-new form fill or signup is treated as a lead, even if they are not yet qualified.',
                                    'normalized' => 'any net-new form fill or signup is treated as a lead, even if they are not yet qualified.',
                                ],
                                'customer_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or exceptions for customer counting.',
                                    'raw' => 'We treat a signed contract as a new customer, even if Finance recognizes revenue after go-live or first payment.',
                                    'normalized' => 'we treat a signed contract as a new customer, even if finance recognizes revenue after go-live or first payment.',
                                ],
                                'cost_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe lookback windows or lag between spend and results.',
                                    'raw' => 'We match campaign spend to the same month those leads or contracts originate without cohort spreading.',
                                    'normalized' => 'we match campaign spend to the same month those leads or contracts originate without cohort spreading.',
                                ],
                                'deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail any matching logic or IDs used for deduplication.',
                                    'raw' => 'If the same person responds to three campaigns, we treat that as three leads so each campaign shows its own CPL.',
                                    'normalized' => 'if the same person responds to three campaigns, we treat that as three leads so each campaign shows its own cpl.',
                                ],
                                'overhead_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain any allocation or apportionment rules.',
                                    'raw' => 'We intentionally exclude salaries, G&A, and RevOps overhead from our CAC and CPL slides.',
                                    'normalized' => 'we intentionally exclude salaries, g&a, and revops overhead from our cac and cpl slides.',
                                ],
                                'cac_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide a short CAC/CPL example.',
                                    'raw' => 'If we spend $50k on campaigns in April and see 2,000 new leads and 40 signed contracts, we show $25 CPL and $1,250 CAC.',
                                    'normalized' => 'if we spend $50k on campaigns in april and see 2,000 new leads and 40 signed contracts, we show $25 cpl and $1,250 cac.',
                                ],
                                'cac_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do CAC/CPL definitions conflict today?',
                                    'raw' => 'The finance fully loaded CAC is nearly double our campaign-only figure, and our broader lead definition makes CPL look lower than what Finance would report.',
                                    'normalized' => 'the finance fully loaded cac is nearly double our campaign-only figure, and our broader lead definition makes cpl look lower than what finance would report.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We are open to labeling our numbers as a marketing CAC/CPL view as long as Finance owns the official board definition.',
                                    'normalized' => 'we are open to labeling our numbers as a marketing cac/cpl view as long as finance owns the official board definition.',
                                ],
                            ],
                        ],
                    ],
                ],
                'discrepancies' => [
                    [
                        'field_key' => 'costs_included',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Finance uses a fully loaded cost base while Marketing reports direct campaign spend only.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop selected fully loaded acquisition spend as the board package; direct campaign CPL remains a labeled marketing operating view.',
                    ],
                    [
                        'field_key' => 'lead_definition',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Finance counts MQLs while Marketing counts any contact, so the CPL denominator needs an explicit board rule.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop chose SQL as the board CPL denominator even though neither intake response used SQL as its raw answer.',
                    ],
                    [
                        'field_key' => 'customer_definition',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Finance counts first-paying customers while Marketing counts signed contracts.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop selected first payment so CAC customer counts reconcile to finance close and cohort reporting.',
                    ],
                    [
                        'field_key' => 'cost_timing',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Finance uses cohort timing while Marketing reports same-month campaign efficiency.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop selected cohort timing for board CAC/CPL and preserved same-month campaign reporting as an operating view.',
                    ],
                    [
                        'field_key' => 'deduplication_method',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'medium',
                        'status' => 'Resolved',
                        'description' => 'Finance unifies people while Marketing counts each campaign touch separately.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop selected person-level deduplication for board CPL; campaign touches remain attribution detail.',
                    ],
                    [
                        'field_key' => 'overhead_handling',
                        'rule_type' => 'enum_mismatch',
                        'severity' => 'high',
                        'status' => 'Resolved',
                        'description' => 'Finance includes allocated overhead while Marketing excludes salaries, G&A, and RevOps overhead.',
                        'detected_at' => '2026-05-10 13:00:00',
                        'resolved_at' => '2026-05-13 16:30:00',
                        'resolution_notes' => 'Workshop selected fully loaded overhead treatment for the official acquisition cost pool.',
                    ],
                ],
            ],
            'definition' => [
                'costs_included' => [
                    'ad_spend',
                    'marketing_comp',
                    'sales_comp',
                    'overhead',
                ],
                'lead_definition' => 'sales_qualified',
                'customer_definition' => 'first_payment',
                'cost_timing' => 'cohort',
                'deduplication_method' => 'unify_by_person',
                'overhead_handling' => 'fully_loaded',
                'cac_pain_points' => 'Marketing monitors direct campaign CPL, while finance and sales need one cohort-based CAC/CPL package for board use.',
            ],
            'notes' => 'Board CAC divides fully loaded acquisition spend in a cohort month by the first-paying customers attributed to that cohort; board CPL divides the same spend by SQLs generated in the month. A lead qualifies as Sales Qualified (SQL) once Sales Operations accepts it in the CRM pipeline after Account Executive review against BANT criteria. Spend is attributed to the cohort of leads generated in the spend month, and customer conversions are tracked for three months after that spend. Duplicate leads across campaigns are unified by CRM contact ID, with verified email as the fallback match key, so each person counts once in CPL and CAC.',
            'comment' => 'Approved as the cohort-based acquisition efficiency package.',
            'consultant_signals' => [
                'alignment_summary' => 'Finance, marketing, and sales now read CAC/CPL from one board definition: fully loaded spend, SQL leads, and first-paying customers on a three-month cohort.',
                'operational_status' => 'needs_follow_up',
                'operational_status_notes' => 'The approved cohort logic is clear, but reporting still combines paid media, marketing payroll, and sales payroll in separate source views.',
                'implementation_gaps' => [
                    'current_state' => 'Finance reconciles cohort CAC manually while marketing reviews direct-channel CPL in campaign tools.',
                    'gap' => 'There is no single warehouse model that packages shared spend and first-payment timing into one board-ready CAC/CPL output.',
                    'owner' => 'Finance and Growth Marketing',
                    'suggested_next_step' => 'Publish one board-ready acquisition-efficiency model with shared acquisition-cost inclusion rules to both finance and marketing dashboards.',
                ],
                'validation_steps' => 'For each board period, verify included spend categories match the approved package and that first-paying customer counts reconcile to the cohort close workbook.',
                'common_mismatch_causes' => 'CAC/CPL usually drifts when payroll costs or overhead are excluded from one view, when leads or signed contracts are mistaken for first-paying customers, or when cohort dates are mixed with invoice-period timing.',
                'executive_takeaway' => 'CAC/CPL is approved as a fully loaded cohort-based acquisition efficiency package for board review.',
            ],
            'workshop' => [
                'scheduled_at' => '2026-05-13 15:00:00',
                'participants' => [
                    'Nora Kim',
                    'Lia Price',
                    'Dana Whitfield',
                    'Anthony Reyes',
                ],
                'decision_snapshot' => [
                    'consensus' => 'Board CAC/CPL uses fully loaded acquisition spend, SQL leads, first-paying customers, and cohort timing.',
                    'follow_up' => [
                        'Finance and Growth Marketing will publish one board-ready acquisition-efficiency model.',
                        'Sales Operations will implement the approved person-level matching rule.',
                    ],
                    'field_decisions' => [
                        'costs_included' => [
                            'selected' => ['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'],
                            'rationale' => 'Board CAC/CPL should reflect the fully loaded acquisition cost base.',
                        ],
                        'lead_definition' => [
                            'selected' => 'sales_qualified',
                            'rationale' => 'SQL is the agreed sales-accepted demand unit for board CPL.',
                        ],
                        'customer_definition' => [
                            'selected' => 'first_payment',
                            'rationale' => 'CAC customer counts should reconcile to first-paying customer cohorts.',
                        ],
                        'cost_timing' => [
                            'selected' => 'cohort',
                            'rationale' => 'Same-month campaign views do not reflect delayed acquisition conversion.',
                        ],
                        'deduplication_method' => [
                            'selected' => 'unify_by_person',
                            'rationale' => 'Campaign touches remain attribution detail and do not multiply board CPL.',
                        ],
                        'overhead_handling' => [
                            'selected' => 'fully_loaded',
                            'rationale' => 'Direct-only campaign cost remains an operating view; the board package uses fully loaded acquisition cost.',
                        ],
                    ],
                ],
                'notes' => 'Marketing campaign CPL remains an operating view. It is not the board CAC/CPL baseline.',
            ],
        ],
    ];

    public function run(): void
    {
        $templates = $this->requiredTemplates();
        $staffAdmin = $this->context->staffAdmin();
        $organization = $this->context->ensureClientOrganization(
            self::ORGANIZATION_SLUG,
            self::ORGANIZATION_NAME,
            ['timezone' => 'America/New_York']
        );

        $this->context->ensureConsultantAccess($organization, $staffAdmin, MembershipRole::ADMIN);

        $memberships = $this->seedMemberships($organization);
        $engagement = $this->seedEngagement($organization->id, $memberships);
        $participants = $this->seedEngagementParticipants($engagement, $memberships);
        $metricContexts = [];

        foreach (self::METRICS as $metricConfig) {
            $metric = $this->seedMetric($organization->id, $engagement->id, $templates, $metricConfig);

            $ownerMembership = $memberships[$metricConfig['owner']];
            $approverMembership = $memberships[$metricConfig['approver']];

            $ownerParticipant = $this->attachMetricParticipant(
                $metric,
                $participants[$metricConfig['owner']],
                'owner',
                ['Executive'],
                $ownerMembership
            );

            $approverParticipant = $this->attachMetricParticipant(
                $metric,
                $participants[$metricConfig['approver']],
                'approver',
                ['Approver'],
                $approverMembership
            );

            foreach ($metricConfig['contributors'] as $contributorKey) {
                $this->attachMetricParticipant(
                    $metric,
                    $participants[$contributorKey],
                    'contributor',
                    [$memberships[$contributorKey]->affiliation ?? 'Contributor'],
                    $memberships[$contributorKey]
                );
            }

            $this->seedOrgMetricParticipants($metric, $ownerMembership, $approverMembership);
            $this->seedInputMatrix($metric, $engagement, $memberships, $participants, $metricConfig);

            if (isset($metricConfig['workshop'])) {
                $workshop = $metricConfig['workshop'];
                ReconciliationWorkshop::updateOrCreate(
                    [
                        'metric_id' => $metric->id,
                        'engagement_id' => $engagement->id,
                    ],
                    [
                        'facilitator_id' => $approverMembership->id,
                        'scheduled_at' => $workshop['scheduled_at'],
                        'participants' => $workshop['participants'] ?? [],
                        'decision_snapshot' => $workshop['decision_snapshot'] ?? [],
                        'notes' => $workshop['notes'] ?? null,
                    ]
                );
            }

            $metricContexts[] = [
                'metric' => $metric,
                'owner_participant' => $ownerParticipant,
                'approver_membership' => $approverMembership,
                'config' => $metricConfig,
            ];
        }

        foreach ($metricContexts as $metricContext) {
            $this->seedSignedDefinition(
                $metricContext['metric'],
                $metricContext['owner_participant'],
                $metricContext['approver_membership'],
                $metricContext['config']
            );
        }
    }

    /**
     * @return Collection<string, MetricTemplate>
     */
    private function requiredTemplates(): Collection
    {
        $requiredKeys = collect(self::METRICS)->pluck('template_key')->unique()->values();
        $templates = MetricTemplate::query()
            ->whereIn('key', $requiredKeys)
            ->get()
            ->keyBy('key');

        $missing = $requiredKeys
            ->reject(fn (string $key): bool => $templates->has($key))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Golden path seed requires metric templates: %s.',
                $missing->join(', ')
            ));
        }

        return $templates;
    }

    /**
     * @return array<string, Membership>
     */
    private function seedMemberships(Organization $organization): array
    {
        $memberships = [];

        foreach (self::MEMBERS as $key => $memberConfig) {
            $membership = $this->context->ensureMember(
                $organization,
                $memberConfig['email'],
                $memberConfig['name'],
                $memberConfig['role'],
                ['affiliation' => $memberConfig['affiliation']]
            );

            $membership->loadMissing('user');
            $memberships[$key] = $membership;
        }

        return $memberships;
    }

    /**
     * @param  array<string, Membership>  $memberships
     */
    private function seedEngagement(string $organizationId, array $memberships): Engagement
    {
        $engagement = Engagement::firstOrNew([
            'organization_id' => $organizationId,
            'title' => self::ENGAGEMENT_TITLE,
        ]);

        $engagement->fill([
            'starts_on' => self::STARTS_ON,
            'ends_on' => self::ENDS_ON,
            'review_cadence' => 'Quarterly',
            'next_review_on' => self::NEXT_REVIEW_ON,
            'repository_driver' => 'google_drive',
            'repository_reference' => 'golden-path://unit-economics-board-baseline',
            'repository_notes' => 'Deterministic golden-path setup for the CEO/CFO Unit Economics artifact.',
            'stakeholders_json' => collect($memberships)
                ->map(fn (Membership $membership): array => [
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => strtolower((string) $membership->role->value),
                    'department' => $membership->affiliation,
                ])
                ->values()
                ->all(),
            'onboarding_checklist_json' => EngagementChecklist::merge([], [
                'metrics_selected' => true,
                'stakeholders_invited' => true,
                'repository_connected' => true,
            ]),
        ]);

        if (! $engagement->exists || $engagement->status !== EngagementStatus::FINALIZED) {
            $engagement->status = EngagementStatus::IN_WORKSHOP;
        }

        $engagement->save();

        return $engagement;
    }

    /**
     * @param  array<string, Membership>  $memberships
     * @return array<string, EngagementParticipant>
     */
    private function seedEngagementParticipants(Engagement $engagement, array $memberships): array
    {
        $participants = [];

        foreach (self::MEMBERS as $key => $memberConfig) {
            $participants[$key] = $this->createMembershipParticipant(
                $engagement,
                $memberships[$key],
                $memberConfig['stakeholder_role']
            );
        }

        return $participants;
    }

    /**
     * @param  Collection<string, MetricTemplate>  $templates
     * @param  array{
     *   key: string,
     *   template_key: string,
     *   name: string,
     *   owner: string,
     *   approver: string,
     *   contributors: list<string>,
     *   sign_off_at: string,
     *   definition: array<string, mixed>,
     *   notes: string,
     *   comment: string,
     *   consultant_signals: array{
     *     alignment_summary: string,
     *     operational_status: string,
     *     operational_status_notes: string,
     *     implementation_gaps: array<string, string>|null,
     *     validation_steps: string,
     *     common_mismatch_causes: string
     *   },
     *   prior_revision?: array{
     *     signed_off_at: string,
     *     definition: array<string, mixed>,
     *     notes: string
     *   }
     * }  $metricConfig
     */
    private function seedMetric(
        string $organizationId,
        string $engagementId,
        Collection $templates,
        array $metricConfig
    ): Metric {
        $template = $templates->get($metricConfig['template_key']);

        if (! $template instanceof MetricTemplate) {
            throw new RuntimeException(sprintf(
                'Metric template [%s] was not loaded.',
                $metricConfig['template_key']
            ));
        }

        $metric = Metric::firstOrNew([
            'engagement_id' => $engagementId,
            'key' => $metricConfig['key'],
        ]);

        $metric->fill([
            'organization_id' => $organizationId,
            'metric_template_id' => $template->id,
            'name' => $metricConfig['name'],
            'slug' => $metricConfig['key'],
        ]);

        if (! $metric->exists || $metric->status !== MetricStatus::FINALIZED) {
            $metric->status = MetricStatus::IN_WORKSHOP;
        }

        $metric->save();

        return $metric;
    }

    private function seedOrgMetricParticipants(
        Metric $metric,
        Membership $ownerMembership,
        Membership $approverMembership
    ): void {
        $metric->loadMissing('orgMetric');

        if (! $metric->org_metric_id) {
            throw new RuntimeException(sprintf(
                'Metric [%s] must be linked to an org metric before seeding org participants.',
                $metric->key
            ));
        }

        $this->seedOrgMetricParticipant($metric->org_metric_id, $ownerMembership, 'owner');
        $this->seedOrgMetricParticipant($metric->org_metric_id, $approverMembership, 'approver');
    }

    private function seedOrgMetricParticipant(string $orgMetricId, Membership $membership, string $responsibility): void
    {
        OrgMetricParticipant::updateOrCreate(
            [
                'org_metric_id' => $orgMetricId,
                'subject_type' => Membership::class,
                'subject_id' => $membership->id,
                'responsibility' => $responsibility,
            ],
            [
                'user_id' => $membership->user_id,
            ]
        );
    }

    /**
     * @param  array<string, Membership>  $memberships
     * @param  array<string, EngagementParticipant>  $participants
     * @param  array<string, mixed>  $metricConfig
     */
    private function seedInputMatrix(
        Metric $metric,
        Engagement $engagement,
        array $memberships,
        array $participants,
        array $metricConfig
    ): void {
        $inputMatrix = $metricConfig['input_matrix'] ?? null;

        if (! is_array($inputMatrix)) {
            return;
        }

        $form = $this->ensureForm($metric, $engagement, [
            'title' => $inputMatrix['form_title'] ?? $metric->name.' - Definition',
        ]);

        $definitionSnapshotsByField = [];

        foreach ($inputMatrix['contributors'] ?? [] as $contributorConfig) {
            if (! is_array($contributorConfig)) {
                continue;
            }

            $memberKey = (string) ($contributorConfig['member'] ?? '');
            $membership = $memberships[$memberKey] ?? null;
            $participant = $participants[$memberKey] ?? null;

            if (! $membership instanceof Membership || ! $participant instanceof EngagementParticipant) {
                throw new RuntimeException(sprintf(
                    'Golden path input matrix contributor [%s] is not a seeded member.',
                    $memberKey
                ));
            }

            $this->attachMetricParticipant(
                $metric,
                $participant,
                'contributor',
                $contributorConfig['tags'] ?? [],
                $membership
            );

            $submittedAt = Carbon::parse((string) $contributorConfig['submitted_at']);

            $submission = FormSubmission::updateOrCreate(
                [
                    'form_id' => $form->id,
                    'user_id' => $membership->user_id,
                ],
                [
                    'answers_json' => $contributorConfig['answers'] ?? [],
                    'submitted_at' => $submittedAt,
                ]
            );

            $definition = MetricDefinition::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'form_submission_id' => $submission->id,
                ],
                [
                    'user_id' => $membership->user_id,
                    'source_type' => 'form_submission',
                    'status' => 'Submitted',
                    'definition_json' => $contributorConfig['answers'] ?? [],
                    'normalized_json' => $contributorConfig['normalized'] ?? [],
                    'submitted_at' => $submittedAt,
                ]
            );

            foreach (($contributorConfig['normalized']['fields'] ?? []) as $fieldKey => $field) {
                if (! is_string($fieldKey) || ! is_array($field)) {
                    continue;
                }

                $definitionSnapshotsByField[$fieldKey][] = array_filter([
                    'definition_id' => (string) $definition->id,
                    'user' => $membership->user?->only(['id', 'name']),
                    'raw' => $field['raw'] ?? null,
                    'normalized' => $field['normalized'] ?? null,
                    'submitted_at' => $submittedAt->toIso8601String(),
                ], fn (mixed $value): bool => $value !== null);
            }
        }

        foreach ($inputMatrix['discrepancies'] ?? [] as $discrepancyConfig) {
            if (! is_array($discrepancyConfig)) {
                continue;
            }

            $fieldKey = (string) ($discrepancyConfig['field_key'] ?? '');
            if ($fieldKey === '') {
                continue;
            }

            $resolvedAt = isset($discrepancyConfig['resolved_at'])
                ? Carbon::parse((string) $discrepancyConfig['resolved_at'])
                : null;

            $valuesSnapshot = [
                'label' => $this->inputMatrixFieldLabel($metric, $fieldKey),
                'values' => array_values($definitionSnapshotsByField[$fieldKey] ?? []),
            ];

            if (! empty($discrepancyConfig['resolution_notes'])) {
                $valuesSnapshot['resolution'] = [
                    'notes' => $discrepancyConfig['resolution_notes'],
                    'updated_at' => ($resolvedAt ?? Carbon::parse((string) $discrepancyConfig['detected_at']))->toIso8601String(),
                ];
            }

            Discrepancy::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'field_key' => $fieldKey,
                ],
                [
                    'engagement_id' => $engagement->id,
                    'rule_type' => $discrepancyConfig['rule_type'],
                    'severity' => $discrepancyConfig['severity'],
                    'status' => $discrepancyConfig['status'],
                    'description' => $discrepancyConfig['description'],
                    'values_snapshot' => $valuesSnapshot,
                    'detected_at' => Carbon::parse((string) $discrepancyConfig['detected_at']),
                    'resolved_at' => $resolvedAt,
                ]
            );
        }
    }

    private function inputMatrixFieldLabel(Metric $metric, string $fieldKey): string
    {
        $metric->loadMissing('template');

        foreach ($metric->template?->form_schema_json['fields'] ?? [] as $field) {
            if (($field['name'] ?? null) !== $fieldKey) {
                continue;
            }

            $label = $field['label'] ?? null;

            if (is_string($label) && trim($label) !== '') {
                return $label;
            }
        }

        return Str::headline($fieldKey);
    }

    /**
     * @param  array{
     *   key: string,
     *   template_key: string,
     *   name: string,
     *   owner: string,
     *   approver: string,
     *   contributors: list<string>,
     *   sign_off_at: string,
     *   definition: array<string, mixed>,
     *   notes: string,
     *   comment: string,
     *   consultant_signals: array{
     *     alignment_summary: string,
     *     operational_status: string,
     *     operational_status_notes: string,
     *     implementation_gaps: array<string, string>|null,
     *     validation_steps: string,
     *     common_mismatch_causes: string
     *   }
     * }  $metricConfig
     */
    private function seedSignedDefinition(
        Metric $metric,
        MetricParticipant $ownerParticipant,
        Membership $approverMembership,
        array $metricConfig
    ): void {
        $currentVersion = array_key_exists('prior_revision', $metricConfig) ? 2 : 1;
        $currentFinal = FinalDefinition::query()
            ->where('metric_id', $metric->getKey())
            ->where('version', $currentVersion)
            ->first();

        $official = OrgFinalDefinition::query()
            ->where('org_metric_id', $metric->org_metric_id)
            ->where('source_metric_id', $metric->getKey())
            ->where('version', $currentVersion)
            ->first();

        if ($currentFinal?->signed_off_at && $official?->signed_off_at) {
            $artifactAttributes = $this->signedDefinitionArtifactAttributes($metric, $metricConfig);

            $currentFinal->update($artifactAttributes);
            $official->update($artifactAttributes);

            return;
        }

        $this->seedPriorRevisionIfConfigured(
            $metric,
            $ownerParticipant,
            $approverMembership,
            $metricConfig
        );

        $finalDefinition = FinalDefinition::updateOrCreate(
            [
                'metric_id' => $metric->getKey(),
                'version' => $currentVersion,
            ],
            array_merge([
                'user_id' => $ownerParticipant->user_id,
                'status' => 'Draft',
                'owner_participant_id' => $ownerParticipant->getKey(),
                'signed_off_at' => null,
                'signed_off_by' => null,
            ], $this->signedDefinitionArtifactAttributes($metric, $metricConfig))
        );

        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse($metricConfig['sign_off_at']));

        try {
            app(ApproveSignOff::class)->handle($metric, [
                'decision' => 'approve',
                'comment' => $metricConfig['comment'],
            ], $approverMembership->user);
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    /**
     * @param  array<string, mixed>  $metricConfig
     * @return array<string, mixed>
     */
    private function signedDefinitionArtifactAttributes(Metric $metric, array $metricConfig): array
    {
        return [
            'definition_json' => $this->definitionPayload($metric, $metricConfig['definition']),
            'notes' => $metricConfig['notes'],
            'alignment_summary' => $metricConfig['consultant_signals']['alignment_summary'],
            'operational_status' => $metricConfig['consultant_signals']['operational_status'],
            'operational_status_notes' => $metricConfig['consultant_signals']['operational_status_notes'],
            'implementation_gaps' => $metricConfig['consultant_signals']['implementation_gaps'],
            'validation_steps' => $metricConfig['consultant_signals']['validation_steps'],
            'common_mismatch_causes' => $metricConfig['consultant_signals']['common_mismatch_causes'],
            'executive_takeaway' => $metricConfig['consultant_signals']['executive_takeaway'] ?? null,
        ];
    }

    /**
     * @param  array{
     *   key: string,
     *   template_key: string,
     *   name: string,
     *   owner: string,
     *   approver: string,
     *   contributors: list<string>,
     *   sign_off_at: string,
     *   definition: array<string, mixed>,
     *   notes: string,
     *   comment: string,
     *   prior_revision?: array{
     *     signed_off_at: string,
     *     definition: array<string, mixed>,
     *     notes: string
     *   }
     * }  $metricConfig
     */
    private function seedPriorRevisionIfConfigured(
        Metric $metric,
        MetricParticipant $ownerParticipant,
        Membership $approverMembership,
        array $metricConfig
    ): void {
        $priorRevision = $metricConfig['prior_revision'] ?? null;

        if (! is_array($priorRevision)) {
            return;
        }

        $priorDefinition = FinalDefinition::updateOrCreate(
            [
                'metric_id' => $metric->getKey(),
                'version' => 1,
            ],
            [
                'user_id' => $ownerParticipant->user_id,
                'status' => 'Approved',
                'definition_json' => $this->definitionPayload($metric, $priorRevision['definition']),
                'notes' => $priorRevision['notes'],
                'owner_participant_id' => $ownerParticipant->getKey(),
                'signed_off_at' => Carbon::parse($priorRevision['signed_off_at']),
                'signed_off_by' => $approverMembership->user_id,
            ]
        );

        $orgOwnerParticipant = OrgMetricParticipant::query()
            ->where('org_metric_id', $metric->org_metric_id)
            ->where('responsibility', 'owner')
            ->where('user_id', $ownerParticipant->user_id)
            ->first();

        if (! $orgOwnerParticipant) {
            throw new RuntimeException(sprintf(
                'Missing org owner participant for metric [%s] prior revision seeding.',
                $metric->key
            ));
        }

        OrgFinalDefinition::updateOrCreate(
            [
                'org_metric_id' => $metric->org_metric_id,
                'version' => 1,
            ],
            [
                'source_metric_id' => $metric->getKey(),
                'source_metric_template_id' => $metric->metric_template_id,
                'status' => 'Approved',
                'definition_json' => $priorDefinition->definition_json,
                'notes' => $priorDefinition->notes,
                'owner_participant_id' => $orgOwnerParticipant->getKey(),
                'signed_off_at' => Carbon::parse($priorRevision['signed_off_at']),
                'signed_off_by' => $approverMembership->user_id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     * @return array<string, array{label: string, value: mixed}>
     */
    private function definitionPayload(Metric $metric, array $fieldValues): array
    {
        $metric->loadMissing('template');

        $fields = collect($metric->template?->form_schema_json['fields'] ?? [])
            ->filter(fn (array $field): bool => is_string($field['name'] ?? null) && ($field['meta'] ?? false) !== true)
            ->keyBy('name');

        $definition = [];

        foreach ($fieldValues as $key => $value) {
            $label = $fields->get($key)['label'] ?? null;

            $definition[$key] = [
                'label' => is_string($label) && $label !== '' ? $label : $metric->name.' '.ucfirst(str_replace('_', ' ', $key)),
                'value' => $value,
            ];
        }

        return $definition;
    }
}
