<?php

namespace Database\Seeders;

use App\Models\CanonicalMetric;
use App\Models\MetricTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MetricTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'field_classification_demo',
                'name' => 'Focus Time Alignment Demo',
                'description' => 'Captures how much deep-work time people need, when they prefer it, and how the team protects it.',
                'form_schema_json' => [
                    'fields' => [
                        [
                            'name' => 'focus_hours_target',
                            'type' => 'number',
                            'label' => 'Ideal focus hours per workday',
                            'required' => true,
                            'workshop_prompt' => 'Set the daily focus-hours target the team will plan around.',
                            'classification' => $this->classification('definition'),
                        ],
                        [
                            'name' => 'focus_window_preference',
                            'type' => 'select',
                            'label' => 'When do you prefer your main focus block?',
                            'required' => true,
                            'options' => [
                                ['value' => 'morning', 'label' => 'Morning'],
                                ['value' => 'afternoon', 'label' => 'Afternoon'],
                                ['value' => 'split', 'label' => 'Split across the day'],
                                ['value' => 'flexible', 'label' => 'Flexible / varies'],
                            ],
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'meeting_rules',
                            'type' => 'textarea',
                            'label' => 'What are your personal meeting rules during focus time?',
                            'required' => false,
                            'meta' => true,
                            'workshop_prompt' => 'Agree which meeting rules protect focus blocks.',
                            'classification' => $this->classification('governance'),
                        ],
                        [
                            'name' => 'notification_policy',
                            'type' => 'textarea',
                            'label' => 'How do you handle notifications during focus time?',
                            'required' => false,
                            'meta' => true,
                            'workshop_prompt' => 'Agree how notifications are handled during focus time.',
                            'classification' => $this->classification('governance'),
                        ],
                        [
                            'name' => 'common_interruptions',
                            'type' => 'textarea',
                            'label' => 'What typically interrupts your focus time?',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'impact_when_missed',
                            'type' => 'textarea',
                            'label' => 'What happens when you do not get enough focus time?',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                        ],
                    ],
                ],
            ],
            [
                'key' => 'active_users',
                'name' => 'Active Users',
                'description' => 'Captures DAU/WAU/MAU definitions and how each team handles deduplication or bots.',
                'form_schema_json' => [
                    'default_due_days' => 7,
                    'required_departments' => ['Product', 'Marketing'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'active_metrics_tracked', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Active user reporting windows tracked',
                            'form_label' => 'Which active user windows do you track?',
                            'required' => true,
                            'options' => [
                                ['value' => 'dau', 'label' => 'DAU (daily active users)'],
                                ['value' => 'wau', 'label' => 'WAU (weekly active users)'],
                                ['value' => 'mau', 'label' => 'MAU (monthly active users)'],
                                ['value' => 'rolling_window', 'label' => 'Rolling 28/30-day window'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the active window you report.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which active-user window is the official reporting baseline.',
                            'workshop_guidance' => 'DAU, WAU, MAU, and rolling windows measure activity over different periods and produce different counts. Name one official window and label the others as operating views.',
                        ],
                        ['name' => 'active_metrics_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Active user window segmentation notes', 'form_label' => 'Add context for the windows you selected (segments, carve-outs).', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'active_metrics_tracked'])],
                        [
                            'name' => 'active_trigger_signal', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Active user qualification criteria',
                            'form_label' => 'What action or threshold marks someone as active?',
                            'required' => true,
                            'options' => [
                                ['value' => 'login_event', 'label' => 'At least one login recorded'],
                                ['value' => 'key_action', 'label' => 'A key feature action completed'],
                                ['value' => 'usage_threshold', 'label' => 'A usage or time threshold met'],
                                ['value' => 'multi_condition', 'label' => 'Multiple qualifying conditions met'],
                                ['value' => 'seat_utilization', 'label' => 'A billed or assigned seat held'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the trigger.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the action or threshold that qualifies a user as active.',
                            'workshop_guidance' => 'A login, key action, usage threshold, multi-condition rule, and assigned seat represent different levels of activity or entitlement. Define one qualifying rule and document any segment-specific exceptions.',
                        ],
                        ['name' => 'active_trigger_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Active trigger rule details', 'form_label' => 'Describe the precise rules or thresholds for the trigger you chose.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic', ['companion_of' => 'active_trigger_signal'])],
                        [
                            'name' => 'deduplication_policy', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Multi-account deduplication policy',
                            'form_label' => 'How do you deduplicate multi-account users?',
                            'required' => true,
                            'options' => [
                                ['value' => 'merge_accounts', 'label' => 'Accounts for the same person merged'],
                                ['value' => 'count_accounts', 'label' => 'Each login or account counted separately'],
                                ['value' => 'hybrid_rules', 'label' => 'Hybrid (some accounts merged, others kept separate)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain your deduplication approach.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how multi-account or shared logins are deduplicated.',
                            'workshop_guidance' => 'Counting accounts measures account activity, while merging accounts aims to measure unique people and depends on reliable identity matching. Choose the unit of count and apply it consistently.',
                        ],
                        ['name' => 'deduplication_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Deduplication exceptions and edge cases', 'form_label' => 'Add nuance or known exceptions for deduplication.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic', ['companion_of' => 'deduplication_policy'])],
                        [
                            'name' => 'bot_filtering_method', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Bot and automated traffic handling policy',
                            'form_label' => 'How do you handle bot or automated traffic?',
                            'required' => true,
                            'options' => [
                                ['value' => 'exclude_known_bots', 'label' => 'Rule-based: known bots are filtered via detection rules or signals'],
                                ['value' => 'flag_for_review', 'label' => 'Flagged for review: suspected bots are counted until manually reviewed'],
                                ['value' => 'no_filter', 'label' => 'No filtering: all traffic, including bots, is included in the count'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your filtering approach.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree how bot and automated traffic is handled before counting active users.',
                            'workshop_guidance' => 'No filtering includes all automated activity, rule-based exclusion removes known bots, and review queues delay exclusion until classification. Document the signals and exceptions so the count is reproducible.',
                        ],
                        ['name' => 'bot_filtering_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Bot detection rules and exceptions', 'form_label' => 'Share detection sources, thresholds, or exceptions.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic', ['companion_of' => 'bot_filtering_method'])],
                        ['name' => 'special_segments_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Special segment counting adjustments', 'form_label' => 'Special segments (read-only, API-only, free tiers) that change the count.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'reporting_frequency', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Active user metric refresh cadence',
                            'form_label' => 'How often do you refresh active user metrics?',
                            'required' => true,
                            'options' => [
                                ['value' => 'daily_snapshot', 'label' => 'Daily snapshot (calendar day)'],
                                ['value' => 'weekly_snapshot', 'label' => 'Weekly snapshot (7-day window)'],
                                ['value' => 'monthly_snapshot', 'label' => 'Monthly snapshot (calendar month)'],
                                ['value' => 'rolling_window', 'label' => 'Rolling window (e.g., 30/90 days)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the reporting window.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree the refresh cadence and cut-off for the official active-user figure.',
                            'workshop_guidance' => 'Daily, weekly, monthly, and rolling methods differ in window length and cutoff behavior. Set the official cadence, activity window, timezone, and cutoff.',
                        ],
                        ['name' => 'reporting_frequency_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Reporting cutoff and timezone rules', 'form_label' => 'Detail cutoffs, timezone alignment, or offsets.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'reporting_frequency'])],
                        ['name' => 'active_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Active user calculation example', 'form_label' => 'Provide a short example calculation.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'active_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Active user definition divergence points',
                            'form_label' => 'Where do definitions of active users diverge today?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where active-user definitions diverge across Product, Marketing, and other teams today.',
                            'workshop_guidance' => 'Compare differences in activity window, qualifying action, identity unit, and filtering. Resolve the official reporting baseline separately from team-specific operating views.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional context', 'form_label' => 'Additional notes or context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'churn',
                'name' => 'Churn',
                'description' => 'Surfaces how teams count logo vs revenue churn, timing windows, and downgrade handling.',
                'form_schema_json' => [
                    'default_due_days' => 7,
                    'required_departments' => ['Customer Success', 'Finance'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'churn_types_tracked', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Churn scope types tracked',
                            'form_label' => 'Which churn scopes do you track? (select canonical values)',
                            'required' => true,
                            'options' => [
                                ['value' => 'logo', 'label' => 'Logo churn (customer count)'],
                                ['value' => 'revenue', 'label' => 'Revenue churn (MRR dollars)'],
                                ['value' => 'net_revenue', 'label' => 'Net revenue churn (incl. expansion)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the churn scope you track.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which churn scopes belong in official reporting and how each is labeled.',
                            'workshop_guidance' => 'Logo churn measures customer loss, gross revenue churn measures recurring revenue loss before expansion, and net revenue churn offsets losses with expansion. Report each selected scope as a distinct, clearly labeled metric.',
                        ],
                        ['name' => 'churn_types_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Churn scope segmentation notes', 'form_label' => 'Add context for churn scope selections (segments, filters, exclusions).', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'churn_timeframe', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Churn reporting window',
                            'form_label' => 'Which reporting window do you use for churn?',
                            'required' => true,
                            'options' => [
                                ['value' => 'monthly', 'label' => 'Monthly snapshot'],
                                ['value' => 'quarterly', 'label' => 'Quarterly snapshot'],
                                ['value' => 'annual', 'label' => 'Annual snapshot'],
                                ['value' => 'rolling', 'label' => 'Rolling window'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain the timeframe.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the official reporting window used for churn.',
                            'workshop_guidance' => 'Monthly, quarterly, annual, and rolling periods expose different levels of volatility and are not directly comparable without normalization. Set one official period and cutoff for each reported churn scope.',
                        ],
                        ['name' => 'churn_timeframe_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Reporting window timing notes', 'form_label' => 'Add detail for the churn reporting window (snapshot timing, offsets).', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'downgrade_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Downgrade treatment in churn',
                            'form_label' => 'How do downgrades factor into churn?',
                            'required' => true,
                            'options' => [
                                ['value' => 'count_as_churn', 'label' => 'Downgrades counted as churn'],
                                ['value' => 'exclude_downgrades', 'label' => 'Downgrades excluded from churn'],
                                ['value' => 'track_as_contraction', 'label' => 'Downgrades tracked separately as contraction'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, share how you treat downgrades.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide whether downgrades count as churn, are excluded, or are tracked separately as contraction.',
                            'workshop_guidance' => 'Downgrades do not change logo count but do reduce recurring revenue. Decide whether that reduction is included in revenue churn, excluded, or reported separately as contraction.',
                        ],
                        ['name' => 'downgrade_handling_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Downgrade handling rules and guardrails', 'form_label' => 'Add specifics or guardrails for downgrade handling.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        [
                            'name' => 'voluntary_treatment', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Voluntary vs involuntary churn treatment',
                            'form_label' => 'How do you treat voluntary vs involuntary churn?',
                            'required' => true,
                            'options' => [
                                ['value' => 'separate', 'label' => 'Voluntary and involuntary reported separately'],
                                ['value' => 'combined', 'label' => 'Voluntary and involuntary combined into one churn figure'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain how you classify churn.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree whether voluntary and involuntary churn are reported separately or combined.',
                            'workshop_guidance' => 'Separate reporting preserves the split between customer-initiated loss and payment-related loss, while a combined figure provides one aggregate but obscures the mix. Define the official presentation and any supporting breakdown.',
                        ],
                        ['name' => 'voluntary_treatment_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Voluntary/involuntary segmentation notes', 'form_label' => 'Describe how you segment voluntary/involuntary churn.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'churn_timing', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Churn recognition timing',
                            'form_label' => 'When do you mark churn effective?',
                            'required' => true,
                            'options' => [
                                ['value' => 'immediate', 'label' => 'Immediately at cancellation'],
                                ['value' => 'end_of_cycle', 'label' => 'After the billing cycle ends'],
                                ['value' => 'after_grace', 'label' => 'After a grace period lapses'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain your churn timing.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide when churn is marked effective.',
                            'workshop_guidance' => 'Cancellation date, service-end date, and post-grace date can place the same customer in different reporting periods. Choose one effective-date rule and align related MRR treatment to it.',
                        ],
                        ['name' => 'churn_timing_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Churn timing policy exceptions', 'form_label' => 'Explain timing policies or exceptions.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        ['name' => 'special_case_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Special-case churn treatment', 'form_label' => 'Special cases (trials, pauses, refunds) that affect churn.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        ['name' => 'churn_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Churn calculation example', 'form_label' => 'Provide an example churn calculation.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'churn_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Churn definition divergence points',
                            'form_label' => 'Where do churn definitions or numbers differ across teams?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where churn definitions and numbers differ across teams today.',
                            'workshop_guidance' => 'Compare scope, reporting period, downgrade treatment, voluntary split, and effective date. Resolve the official reporting baseline separately from operational retention and billing views.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional context and disclaimers', 'form_label' => 'Additional notes or disclaimers.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'conversion_rate',
                'name' => 'Conversion Rate',
                'description' => 'Documents which actions count as conversions, the denominator, and attribution window.',
                'form_schema_json' => [
                    'default_due_days' => 9,
                    'required_departments' => ['Marketing', 'Sales'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'conversion_actions', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Conversion actions counted',
                            'form_label' => 'Which actions count as a conversion?',
                            'required' => true,
                            'options' => [
                                ['value' => 'form_completion', 'label' => 'Form completion or lead capture'],
                                ['value' => 'trial_activation', 'label' => 'Trial sign-up or activation'],
                                ['value' => 'purchase_checkout', 'label' => 'Purchase or subscription checkout'],
                                ['value' => 'contract_signature', 'label' => 'Contract signed / closed won'],
                                ['value' => 'product_upgrade', 'label' => 'Upgrade or expansion event'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the conversion action.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which event or events the official conversion metric counts.',
                            'workshop_guidance' => 'Form fills, trials, purchases, closed-won deals, and upgrades represent different funnel transitions. Define the official numerator event and label separate funnel-stage rates explicitly.',
                        ],
                        ['name' => 'conversion_actions_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Conversion action exceptions and nuances', 'form_label' => 'Add nuance or exceptions for the actions selected.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'conversion_denominator', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Conversion rate denominator definition',
                            'form_label' => 'Who or what forms the denominator for this rate?',
                            'required' => true,
                            'options' => [
                                ['value' => 'all_visitors', 'label' => 'All visitors or sessions'],
                                ['value' => 'captured_leads', 'label' => 'All captured leads'],
                                ['value' => 'qualified_leads', 'label' => 'Qualified leads (MQL/SQL)'],
                                ['value' => 'trial_accounts', 'label' => 'Trial or freemium accounts'],
                                ['value' => 'pipeline_opportunities', 'label' => 'Pipeline opportunities / deals'],
                                ['value' => 'prior_step_customers', 'label' => 'Customers at the prior step (pilot, freemium, etc.)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the denominator group.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the denominator the conversion rate is measured against.',
                            'workshop_guidance' => 'All visitors, captured leads, qualified leads, trials, and opportunities represent different starting populations. Choose the denominator that matches the named funnel transition.',
                        ],
                        ['name' => 'conversion_denominator_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Denominator filters and exclusions', 'form_label' => 'Detail filters, cohorts, or exclusions in the denominator.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'conversion_timeframe', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Conversion measurement timeframe',
                            'form_label' => 'Over what time frame do you measure conversion?',
                            'required' => true,
                            'options' => [
                                ['value' => 'within_7_days', 'label' => 'Within 7 days'],
                                ['value' => 'within_30_days', 'label' => 'Within 30 days'],
                                ['value' => 'within_90_days', 'label' => 'Within 90 days'],
                                ['value' => 'rolling_window', 'label' => 'Rolling or indefinite window'],
                                ['value' => 'event_triggered', 'label' => 'Event-triggered / campaign window'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the time frame.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the time window within which a conversion is counted.',
                            'workshop_guidance' => 'Shorter windows exclude later conversions, while rolling or indefinite windows keep cohorts open longer. Set the official window, cohort boundary, and treatment of late conversions.',
                        ],
                        ['name' => 'conversion_timeframe_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Timeframe boundary and lag rules', 'form_label' => 'Mention timezone handling, lag windows, or grace periods.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'funnel_stage_tracked', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Funnel stage represented by conversion',
                            'form_label' => 'Which funnel step does this conversion represent?',
                            'required' => true,
                            'options' => [
                                ['value' => 'visitor_to_lead', 'label' => 'Visitor → Lead'],
                                ['value' => 'lead_to_mql', 'label' => 'Lead → MQL'],
                                ['value' => 'mql_to_sql', 'label' => 'MQL → SQL'],
                                ['value' => 'sql_to_opportunity', 'label' => 'SQL → Opportunity'],
                                ['value' => 'opportunity_to_customer', 'label' => 'Opportunity → Customer (closed won)'],
                                ['value' => 'trial_to_paid', 'label' => 'Trial or freemium → Paid'],
                                ['value' => 'expansion_upgrade', 'label' => 'Customer → Expansion / upgrade'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the funnel step.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which funnel step this conversion rate represents.',
                            'workshop_guidance' => 'Visitor-to-lead, MQL-to-SQL, opportunity-to-customer, and trial-to-paid are different transitions. Name the official step explicitly so its numerator and denominator are unambiguous.',
                        ],
                        ['name' => 'funnel_stage_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Funnel segmentation and regional splits', 'form_label' => 'Clarify segmentation, region splits, or multiple funnels.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'partial_conversion_tracking', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Partial conversion counting rules',
                            'form_label' => 'How are partial conversions or upgrades counted?',
                            'required' => true,
                            'options' => [
                                ['value' => 'primary_only', 'label' => 'Only the main purchase / subscription'],
                                ['value' => 'include_upgrades', 'label' => 'Primary conversion plus partial upgrades'],
                                ['value' => 'include_addons', 'label' => 'Primary conversion plus add-on purchases'],
                                ['value' => 'separate_events', 'label' => 'Each event is tracked separately'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe how you treat partial conversions.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how partial conversions, upgrades, and add-ons are counted.',
                            'workshop_guidance' => 'Primary-only counting records one conversion event, combined counting can record later upgrades or add-ons, and separate-event tracking preserves distinct numerators. Define the event and deduplication rules.',
                        ],
                        ['name' => 'partial_conversion_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Partial conversion thresholds and minimums', 'form_label' => 'Note thresholds or revenue minimums for counting upgrades.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        [
                            'name' => 'attribution_model', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Source attribution model',
                            'form_label' => 'Which attribution model assigns conversions to sources?',
                            'required' => false,
                            'options' => [
                                ['value' => 'first_touch', 'label' => 'First-touch attribution'],
                                ['value' => 'last_touch', 'label' => 'Last-touch attribution'],
                                ['value' => 'multi_touch', 'label' => 'Multi-touch attribution'],
                                ['value' => 'no_model', 'label' => 'No formal attribution model'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the attribution model.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree whether and which attribution model applies to conversions.',
                            'workshop_guidance' => 'First-touch, last-touch, and multi-touch models allocate the same conversion to different sources; they do not change the total conversion count unless source filters also change. Define the model used for source-level reporting.',
                        ],
                        ['name' => 'attribution_model_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Attribution model parameter notes', 'form_label' => 'Add detail on the attribution weights, lookback windows, or tools.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        ['name' => 'conversion_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Conversion calculation example', 'form_label' => 'Provide a brief example conversion calculation.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'conversion_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Conversion definition divergence points',
                            'form_label' => 'Where do conversion rate definitions differ across teams?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where conversion-rate definitions differ across Marketing and Sales today.',
                            'workshop_guidance' => 'Compare numerator event, denominator, funnel step, time window, event counting, and attribution. Resolve the official reporting baseline separately from team-specific funnel views.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional context', 'form_label' => 'Additional notes or context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'cac_cpl',
                'name' => 'CAC & CPL',
                'description' => 'Clarifies which costs, leads, and customers are included when calculating acquisition efficiency.',
                'form_schema_json' => [
                    'default_due_days' => 10,
                    'required_departments' => ['Finance', 'Marketing', 'Sales'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'costs_included', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'CAC/CPL cost components included',
                            'form_label' => 'Which costs are included in CAC/CPL?',
                            'required' => true,
                            'options' => [
                                ['value' => 'ad_spend', 'label' => 'Direct advertising spend (paid media platforms)'],
                                ['value' => 'marketing_comp', 'label' => 'Marketing salaries / agency fees'],
                                ['value' => 'sales_comp', 'label' => 'Sales salaries / commissions'],
                                ['value' => 'overhead', 'label' => 'Overhead (office, software, general expenses)'],
                                ['value' => 'direct_campaign_only', 'label' => 'Only direct campaign costs (ad spend + campaign tools)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the costs you include.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which acquisition cost pool defines official CAC/CPL.',
                            'workshop_guidance' => 'A fully loaded pool includes compensation and overhead, a direct pool isolates campaign spend, and partial allocation sits between them. Name the official cost pool and label narrower channel views separately.',
                        ],
                        [
                            'name' => 'lead_definition', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'CPL lead definition',
                            'form_label' => 'What do you count as a lead in your CPL calculation?',
                            'required' => true,
                            'options' => [
                                ['value' => 'any_contact', 'label' => 'Any new contact or form fill'],
                                ['value' => 'marketing_qualified', 'label' => 'Marketing Qualified Leads (MQLs)'],
                                ['value' => 'sales_qualified', 'label' => 'Sales Qualified Leads (SQLs)'],
                                ['value' => 'opportunity', 'label' => 'Opportunities / pipeline deals'],
                                ['value' => 'product_qualified', 'label' => 'Product-qualified trials or signups'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your lead definition.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which lead stage defines the official CPL denominator.',
                            'workshop_guidance' => 'Earlier, broader stages produce a larger denominator and generally lower CPL, while later qualified stages produce a smaller denominator and represent a different acquisition milestone. Name the selected stage in the metric label.',
                        ],
                        ['name' => 'lead_definition_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Lead definition qualifiers and exceptions', 'form_label' => 'Add nuance or qualifiers to your lead definition.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'lead_definition'])],
                        [
                            'name' => 'customer_definition', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'CAC customer acquisition point',
                            'form_label' => 'When is someone considered an acquired customer for CAC?',
                            'required' => true,
                            'options' => [
                                ['value' => 'contract_signed', 'label' => 'Contract signed / closed won'],
                                ['value' => 'first_payment', 'label' => 'First payment processed'],
                                ['value' => 'go_live', 'label' => 'Go-live date or onboarding completion'],
                                ['value' => 'activated_subscription', 'label' => 'Activated paid subscription or seat'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your customer definition.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Align on the exact qualification event that marks a customer as officially acquired.',
                            'workshop_guidance' => 'Contract signature, first payment, go-live, and subscription activation occur at different points and can fall in different reporting periods. Choose the acquisition event and align it with the cost-timing rule.',
                        ],
                        ['name' => 'customer_definition_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Customer counting exceptions and nuances', 'form_label' => 'Add nuance or exceptions for customer counting.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'customer_definition'])],
                        [
                            'name' => 'cost_timing', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Cost-to-outcome timing alignment method',
                            'form_label' => 'How do you align costs with the resulting leads/customers?',
                            'required' => true,
                            'options' => [
                                ['value' => 'same_month', 'label' => 'Same-period: costs are booked in the same month as the resulting leads/customers'],
                                ['value' => 'cohort', 'label' => 'Cohort-based: acquisition costs matched to the cohort of customers acquired in the attribution window'],
                                ['value' => 'quarterly_or_semiannual', 'label' => 'Quarterly or semiannual: costs aggregated across a longer reporting period'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain how you align timing.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how to align the timing of acquisition spend with the resulting leads or customers.',
                            'workshop_guidance' => 'Same-period matching emphasizes current activity but can misalign costs and outcomes for long sales cycles. Cohort methods follow outcomes over time, while broader aggregation smooths timing differences.',
                        ],
                        [
                            'name' => 'deduplication_method', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Cross-campaign deduplication method',
                            'form_label' => 'How do you deduplicate people who touch multiple campaigns?',
                            'required' => false,
                            'options' => [
                                ['value' => 'count_all_touches', 'label' => 'Each campaign touch counted as a separate lead'],
                                ['value' => 'unify_by_person', 'label' => 'Multiple touches unified into one lead per person'],
                                ['value' => 'hybrid_rules', 'label' => 'Hybrid rules depending on channel or stage'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your deduplication approach.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how prospects with multiple campaign touches are counted.',
                            'workshop_guidance' => 'Counting touches measures interactions, unifying by person measures unique prospects, and hybrid rules vary by channel or stage. Set the counting unit and identity-matching rule.',
                        ],
                        ['name' => 'deduplication_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Deduplication matching logic notes', 'form_label' => 'Detail any matching logic or IDs used for deduplication.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic', ['companion_of' => 'deduplication_method'])],
                        [
                            'name' => 'overhead_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Overhead and indirect cost treatment',
                            'form_label' => 'How do you handle overhead or indirect costs?',
                            'required' => false,
                            'options' => [
                                ['value' => 'fully_loaded', 'label' => 'Fully loaded: all marketing, sales, and overhead costs included'],
                                ['value' => 'direct_only', 'label' => 'Only direct campaign expenses'],
                                ['value' => 'partial_allocation', 'label' => 'Partial overhead allocation'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your overhead treatment.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree how overhead and indirect acquisition costs are allocated in CAC.',
                            'workshop_guidance' => 'Fully loaded CAC includes compensation and overhead, direct-only CAC isolates campaign expenses, and partial allocation includes a defined share of indirect costs. Document the allocation basis.',
                        ],
                        ['name' => 'costs_included_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Included and excluded GL/cost center rules', 'form_label' => 'Add detail on which GL codes or cost centers are included or excluded.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'costs_included'])],
                        ['name' => 'cost_timing_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Spend-to-result lag and lookback rules', 'form_label' => 'Describe lookback windows or lag between spend and results.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'cost_timing'])],
                        ['name' => 'overhead_handling_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Allocation and apportionment rules', 'form_label' => 'Explain any allocation or apportionment rules.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['companion_of' => 'overhead_handling'])],
                        ['name' => 'cac_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'CAC/CPL calculation example', 'form_label' => 'Provide a short CAC/CPL example.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['cue_context_scope' => 'matrix_wide', 'executive_section' => 'official_definition'])],
                        [
                            'name' => 'cac_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'CAC/CPL definition divergence points',
                            'form_label' => 'Where do CAC/CPL definitions conflict today?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic', ['cue_context_scope' => 'matrix_wide']),
                            'workshop_prompt' => 'Identify which CAC/CPL differences must be resolved or preserved as labeled operating views.',
                            'workshop_guidance' => 'Compare cost pool, denominator stage, acquisition event, timing, deduplication, allocation, and source system. Resolve the official reporting baseline separately from channel-optimization views.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional context', 'form_label' => 'Additional notes or context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context', ['cue_context_scope' => 'matrix_wide'])],
                    ],
                ],
            ],
            [
                'key' => 'lead_quality',
                'name' => 'Leads & Lead Quality',
                'description' => 'Aligns on what counts as a lead, the stages used (MQL/SQL), and how quality is scored.',
                'form_schema_json' => [
                    'default_due_days' => 8,
                    'required_departments' => ['Marketing', 'Sales'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'lead_definition', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Lead definition',
                            'form_label' => 'What do you count as a lead?',
                            'required' => true,
                            'options' => [
                                ['value' => 'any_contact', 'label' => 'Any new contact or form fill'],
                                ['value' => 'marketing_qualified', 'label' => 'Marketing Qualified Leads (MQLs)'],
                                ['value' => 'sales_qualified', 'label' => 'Sales Qualified Leads (SQLs)'],
                                ['value' => 'opportunity', 'label' => 'Opportunities / pipeline deals'],
                                ['value' => 'product_qualified', 'label' => 'Product-qualified trials or signups'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your lead definition.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree what counts as the official lead.',
                            'workshop_guidance' => 'Any contact, MQL, SQL, opportunity, and product-qualified lead represent different funnel stages and produce different volumes. Name the official lead stage and use it consistently across volume and quality reporting.',
                        ],
                        ['name' => 'lead_definition_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Lead definition qualifiers and nuances', 'form_label' => 'Add nuance or qualifiers to your lead definition.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'lead_stages_tracked', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Lead lifecycle stages tracked',
                            'form_label' => 'Which lead stages do you track?',
                            'required' => true,
                            'options' => [
                                ['value' => 'mql', 'label' => 'MQL (Marketing Qualified Lead)'],
                                ['value' => 'sql', 'label' => 'SQL (Sales Qualified Lead)'],
                                ['value' => 'opportunity', 'label' => 'Opportunity'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the stage(s).',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which lifecycle stages (MQL, SQL, opportunity) are officially tracked.',
                            'workshop_guidance' => 'Stage names are comparable only when their entry and exit criteria match. Define the shared stage model and map each stage to the relevant CRM status or event.',
                        ],
                        ['name' => 'lead_stages_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Stage-to-system mapping notes', 'form_label' => 'Clarify how stages map to your systems or processes.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'lead_reporting_frequency', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Lead reporting cadence',
                            'form_label' => 'How often do you report on leads?',
                            'required' => true,
                            'options' => [
                                ['value' => 'monthly', 'label' => 'Monthly (calendar-based)'],
                                ['value' => 'quarterly', 'label' => 'Quarterly'],
                                ['value' => 'rolling_window', 'label' => 'Rolling window (e.g., last 30 days)'],
                                ['value' => 'campaign_based', 'label' => 'Campaign-based / ad hoc'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain the reporting cadence.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree the official reporting cadence and cut-off for leads.',
                            'workshop_guidance' => 'Monthly, quarterly, rolling, and campaign-based periods group the same activity differently. Set the official cadence, cutoff, timezone, and treatment of late-arriving records.',
                        ],
                        ['name' => 'lead_reporting_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Reporting cutoff and timezone rules', 'form_label' => 'Describe cutoffs, timezone handling, or offsets.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'lead_deduplication', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Lead deduplication approach across touchpoints',
                            'form_label' => 'How do you deduplicate leads across touchpoints?',
                            'required' => true,
                            'options' => [
                                ['value' => 'count_all_touches', 'label' => 'Each form fill or touch counted as a separate lead'],
                                ['value' => 'unify_by_person', 'label' => 'Multiple sign-ups unified into one lead per person'],
                                ['value' => 'hybrid_rules', 'label' => 'Hybrid rules depending on channel or stage'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your deduplication approach.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how leads are deduplicated across touchpoints.',
                            'workshop_guidance' => 'Counting touches measures interactions, unifying by person measures unique prospects, and hybrid rules vary by channel or stage. Set the counting unit and identity-matching rule.',
                        ],
                        ['name' => 'lead_deduplication_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Deduplication matching logic notes', 'form_label' => 'Add detail on matching logic or IDs used for deduplication.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        [
                            'name' => 'lead_quality_model', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Lead quality assessment model',
                            'form_label' => 'How do you assess lead quality or scoring?',
                            'required' => true,
                            'options' => [
                                ['value' => 'no_model', 'label' => 'No formal scoring model; manual review only'],
                                ['value' => 'numeric_score', 'label' => 'Numeric score (e.g., 0–100 points)'],
                                ['value' => 'tiered_rating', 'label' => 'Tiered rating (e.g., A/B/C or hot/warm/cold)'],
                                ['value' => 'rule_based', 'label' => 'Rule-based qualification (BANT/ICP criteria required)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your lead quality model.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree how lead quality is assessed (scoring, tiers, or rules).',
                            'workshop_guidance' => 'Manual review, numeric scores, tiered ratings, and rule-based qualification can classify the same lead differently. Define the official model, thresholds, ownership, and review cadence.',
                        ],
                        ['name' => 'lead_quality_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Lead quality signals and thresholds', 'form_label' => 'Describe key signals, events, or thresholds in your model.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        ['name' => 'lead_special_cases', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Lead handling special cases', 'form_label' => 'Special cases (free trials, SMB vs enterprise, events) that change lead handling?', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        ['name' => 'lead_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Lead stage progression example', 'form_label' => 'Example of how a lead progresses through stages.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'lead_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Lead definition and quality-threshold divergence points',
                            'form_label' => 'Where do definitions or quality thresholds differ?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where lead definitions and quality thresholds diverge across Marketing and Sales today.',
                            'workshop_guidance' => 'Compare lead stage, lifecycle mapping, reporting period, deduplication, and quality thresholds. Resolve the official reporting baseline separately from team-specific working queues.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional context', 'form_label' => 'Additional notes or context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'ltv',
                'name' => 'Lifetime Value (LTV)',
                'description' => 'Captures the lifespan assumptions, revenue streams, and discounting choices behind LTV.',
                'form_schema_json' => [
                    'default_due_days' => 12,
                    'required_departments' => ['Finance', 'Revenue Operations'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'ltv_formula', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'LTV calculation methodology',
                            'form_label' => 'How do you calculate or conceptualize LTV?',
                            'required' => true,
                            'options' => [
                                ['value' => 'simple_revenue_lifespan', 'label' => 'Simple revenue × average lifespan (e.g., ARPU × months)'],
                                ['value' => 'discounted_cash_flow', 'label' => 'Discounted cash flow / NPV model'],
                                ['value' => 'margin_based', 'label' => 'Margin-based LTV (using gross margin)'],
                                ['value' => 'cohort_retention', 'label' => 'Cohort or retention-based model'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, briefly describe your LTV approach.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the official LTV calculation methodology.',
                            'workshop_guidance' => 'Simple revenue-times-lifespan, discounted cash flow, margin-based, and cohort models differ in timing, retention, and cost assumptions and can produce materially different values. Select one official method and label alternatives separately.',
                        ],
                        ['name' => 'ltv_formula_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'LTV formula details and assumptions', 'form_label' => 'Describe your LTV formula or key assumptions.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'lifespan_assumption', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Customer lifespan assumption horizon',
                            'form_label' => 'Which time horizon or lifespan do you assume?',
                            'required' => true,
                            'options' => [
                                ['value' => '24_months', 'label' => '24 months'],
                                ['value' => '30_months', 'label' => '30 months'],
                                ['value' => '36_months', 'label' => '36 months'],
                                ['value' => 'churn_derived', 'label' => 'Based on churn rate (1 / monthly churn)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, share the lifespan assumption.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree the customer lifespan assumption used in LTV.',
                            'workshop_guidance' => 'A fixed horizon is an explicit assumption, while a churn-derived lifespan changes with churn scope and period and relies on simplifying retention assumptions. Document the horizon, caps, and connection to the official churn metric.',
                        ],
                        ['name' => 'lifespan_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Lifespan and churn assumption context', 'form_label' => 'Add context on customer lifespan or churn assumptions.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'revenue_streams_included', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Revenue streams included in LTV',
                            'form_label' => 'Which revenue streams are included?',
                            'required' => true,
                            'options' => [
                                ['value' => 'base_subscription', 'label' => 'Base subscription fees'],
                                ['value' => 'upsells_addons', 'label' => 'Upsells or add-on features'],
                                ['value' => 'professional_services', 'label' => 'Professional services / implementation fees'],
                                ['value' => 'usage_based', 'label' => 'Usage-based or overages'],
                                ['value' => 'one_time_fees', 'label' => 'One-time setup fees'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the revenue streams.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which revenue streams are included in LTV.',
                            'workshop_guidance' => 'Subscriptions, upsells, services, usage charges, and one-time fees represent different revenue patterns. Define the included streams so the LTV scope is consistent across periods and cohorts.',
                        ],
                        ['name' => 'revenue_stream_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Revenue stream inclusions and exclusions for LTV', 'form_label' => 'Special revenue streams or exclusions (internal accounts, credits, etc.).', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'discount_method', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Present-value discounting method for LTV',
                            'form_label' => 'Do you discount future revenue to present value?',
                            'required' => true,
                            'options' => [
                                ['value' => 'no_discounting', 'label' => 'No discounting (simple ARPU × months)'],
                                ['value' => 'discount_rate', 'label' => 'A discount rate applied for present value'],
                                ['value' => 'hybrid', 'label' => 'Hybrid or partial discounting'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the discount method.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide whether future revenue is discounted to present value in LTV.',
                            'workshop_guidance' => 'No discounting reports an undiscounted nominal value, while a discount rate converts future amounts to present value and introduces a shared rate assumption. Choose the basis and document the rate and compounding period when used.',
                        ],
                        ['name' => 'discount_method_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Discount rate and NPV configuration details', 'form_label' => 'Add detail on discount rates, timelines, or NPV setup.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'margin_basis', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'LTV basis: revenue vs margin',
                            'form_label' => 'Is LTV based on revenue or margin?',
                            'required' => false,
                            'options' => [
                                ['value' => 'revenue_only', 'label' => 'Revenue-based (no costs subtracted)'],
                                ['value' => 'gross_margin', 'label' => 'Gross margin based'],
                                ['value' => 'fully_loaded_margin', 'label' => 'Fully loaded margin (includes overhead)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain the margin basis.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree whether LTV is based on revenue or margin.',
                            'workshop_guidance' => 'Revenue-based LTV subtracts no costs, gross-margin LTV subtracts direct costs or COGS, and fully loaded margin also allocates overhead. Use a clearly labeled basis when comparing LTV with CAC.',
                        ],
                        ['name' => 'margin_basis_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Included and excluded cost components', 'form_label' => 'Explain which cost components are included or excluded.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'one_time_fee_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'One-time fee and onboarding charge treatment',
                            'form_label' => 'Do you include one-time fees or onboarding charges?',
                            'required' => false,
                            'options' => [
                                ['value' => 'exclude_one_time', 'label' => 'One-time fees excluded'],
                                ['value' => 'include_upfront_or_spread', 'label' => 'One-time fees recognized upfront or spread across months'],
                                ['value' => 'depends_on_type', 'label' => 'Treatment varies by fee type'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe how you treat one-time fees or onboarding charges.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how one-time and onboarding fees are treated in LTV.',
                            'workshop_guidance' => 'Including one-time fees changes the LTV scope, while spreading them changes timing but not the total undiscounted amount. Choose a treatment consistent with the included revenue streams and discounting method.',
                        ],
                        ['name' => 'one_time_fee_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'One-time fee handling exceptions and rules', 'form_label' => 'Note special handling for setup, implementation, or onboarding fees.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        ['name' => 'ltv_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'LTV calculation example', 'form_label' => 'Provide an example LTV calculation.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'ltv_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Cross-team LTV assumption conflict points',
                            'form_label' => 'Where do LTV assumptions differ between teams?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where LTV assumptions differ between Finance and Revenue Operations today.',
                            'workshop_guidance' => 'Compare formula, lifespan, revenue streams, discounting, margin basis, and one-time fee treatment. Resolve the official reporting baseline separately from scenario and planning models.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional LTV context and notes', 'form_label' => 'Additional notes or context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'mrr',
                'name' => 'Monthly Recurring Revenue',
                'description' => 'Captures how each department calculates MRR, handles annual deals, discounts, and churn timing.',
                'form_schema_json' => [
                    'default_due_days' => 6,
                    'required_departments' => ['Finance', 'Revenue Operations'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'mrr_description', 'executive_section' => 'official_definition',
                            'type' => 'textarea',
                            'label' => 'MRR measurement approach',
                            'form_label' => 'Describe how you measure MRR each month.',
                            'required' => true,
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree on the single official method used to calculate MRR each month.',
                            'workshop_guidance' => 'Subscription schedules, billed amounts, and signed contract values are different source concepts. Define how the official metric converts source data into a recurring monthly run rate, and label billing or bookings views separately.',
                        ],
                        [
                            'name' => 'mrr_snapshot_timing', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Official MRR snapshot timing',
                            'form_label' => 'When do you capture the official MRR snapshot?',
                            'required' => true,
                            'options' => [
                                ['value' => 'last_calendar_day', 'label' => 'Last calendar day of the reporting month'],
                                ['value' => 'first_business_day', 'label' => 'First business day of the following month'],
                                ['value' => 'rolling_daily', 'label' => 'Rolling daily active-MRR view'],
                                ['value' => 'contract_close_bookings_view', 'label' => 'Contract-close bookings view (not MRR)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your snapshot timing cadence.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree when the official MRR snapshot is captured.',
                            'workshop_guidance' => 'Month-end, next-business-day, and rolling snapshots can differ because of late changes and close adjustments; contract-close timing is a bookings view rather than a settled MRR snapshot. Set the official cutoff and label pipeline views separately.',
                        ],
                        ['name' => 'mrr_snapshot_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'MRR snapshot timing rationale', 'form_label' => 'Explain why you use that snapshot timing (optional).', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'annual_deal_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Annual and multi-year contract treatment',
                            'form_label' => 'How do you treat annual or multi-year contracts?',
                            'required' => true,
                            'options' => [
                                ['value' => 'full_invoice_in_invoice_month', 'label' => 'Full invoice amount in the invoice month (billing view, not MRR)'],
                                ['value' => 'normalized_contract_value', 'label' => 'Recurring contract value divided across contracted months'],
                                ['value' => 'recurring_schedule_from_service_start', 'label' => 'Recurring monthly schedule from the service start date'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your approach for annual deals.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how annual and multi-year contracts convert into monthly MRR.',
                            'workshop_guidance' => 'Treating the full invoice amount as one month of MRR creates a spike, while proration converts contract value into a monthly run rate. Choose the conversion rule and contract effective date so annual and monthly contracts are comparable.',
                        ],
                        ['name' => 'annual_deal_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Annual deal exceptions and context', 'form_label' => 'Add context or exceptions for annual deals.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'discount_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Discount, promotion, and credit treatment in MRR',
                            'form_label' => 'How do discounts, promotions, or credits impact MRR?',
                            'required' => true,
                            'options' => [
                                ['value' => 'net_recurring_price', 'label' => 'Current recurring price net of active discounts and credits'],
                                ['value' => 'list_price_view', 'label' => 'Undiscounted list-price view (separate pricing view)'],
                                ['value' => 'scheduled_post_promotion_price', 'label' => 'Scheduled post-promotion price (forward view)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain how you handle discounts.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree which price basis and discount timing the official MRR uses.',
                            'workshop_guidance' => 'Billed amount, list price, and post-promotion amount are different pricing bases, and credits may require a separate rule. Choose the price basis and timing without treating billing as revenue recognition.',
                        ],
                        ['name' => 'discount_handling_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Discount and credit edge cases', 'form_label' => 'Add context or edge cases for discounts/credits.', 'required' => false, 'meta' => true, 'classification' => $this->classification('diagnostic')],
                        [
                            'name' => 'mid_month_changes', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Mid-month plan change treatment',
                            'form_label' => 'How do you reflect mid-month upgrades or downgrades?',
                            'required' => true,
                            'options' => [
                                ['value' => 'update_immediately', 'label' => 'MRR updated immediately'],
                                ['value' => 'wait_next_cycle', 'label' => 'Applied at the next billing cycle'],
                                ['value' => 'prorate_current_month', 'label' => 'Prorated within the current month'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe how you handle mid-month changes.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide how mid-month upgrades and downgrades are reflected in MRR.',
                            'workshop_guidance' => 'Immediate updates, current-month proration, and next-cycle updates place the same expansion or contraction in different snapshots. Align the treatment with the official cutoff and contract effective date.',
                        ],
                        ['name' => 'mid_month_changes_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Mid-month change handling nuances', 'form_label' => 'Explain any mid-month change nuances.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'churn_timing', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Churn removal timing from MRR',
                            'form_label' => 'When do you remove churned customers from MRR?',
                            'required' => true,
                            'options' => [
                                ['value' => 'immediate', 'label' => 'On the cancellation effective date'],
                                ['value' => 'paid_through_date', 'label' => 'At the paid-through or service-end date'],
                                ['value' => 'billing_and_service_status', 'label' => 'Using a documented billing and service-status rule'],
                                ['value' => 'retained_pipeline_history', 'label' => 'Cancelled deals retained in pipeline history (not active MRR)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain your churn timing.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree when churned customers are removed from MRR.',
                            'workshop_guidance' => 'Cancellation date, billing-period end, and conditional billing rules can remove the same account in different periods. Choose the effective removal rule and keep pipeline bookings separate from active MRR.',
                        ],
                        ['name' => 'churn_timing_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Churn timing exceptions and rules', 'form_label' => 'Explain churn timing exceptions.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'trial_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Trial and grace-period treatment in MRR',
                            'form_label' => 'How do you treat trials or grace periods in MRR?',
                            'required' => true,
                            'options' => [
                                ['value' => 'exclude_until_payment', 'label' => 'Excluded until paid recurring service begins'],
                                ['value' => 'payment_method_forecast_view', 'label' => 'Included with payment method on file (forecast view)'],
                                ['value' => 'trial_list_price_forecast_view', 'label' => 'Trial included at list price (forecast view)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your trial handling.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Decide when trials and grace-period accounts enter MRR.',
                            'workshop_guidance' => 'First payment, payment method on file, and trial list price represent different levels of commercial commitment. Define the entry criterion and label non-paying trial or forecast views separately from active MRR.',
                        ],
                        ['name' => 'trial_handling_notes', 'executive_section' => 'measurement_logic_rules', 'type' => 'textarea', 'label' => 'Trial and grace-period context', 'form_label' => 'Add trial/grace period context.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        [
                            'name' => 'included_revenue_streams', 'executive_section' => 'official_definition',
                            'type' => 'checkbox_group',
                            'label' => 'Charge types included in MRR',
                            'form_label' => 'Which charge types roll into MRR?',
                            'required' => true,
                            'options' => [
                                ['value' => 'recurring_subscription_charges', 'label' => 'Recurring subscription charges'],
                                ['value' => 'committed_recurring_usage', 'label' => 'Contracted recurring usage commitments'],
                                ['value' => 'variable_usage_charges', 'label' => 'Variable usage charges (separate usage view)'],
                                ['value' => 'one_time_setup_fees', 'label' => 'One-time setup fees (not MRR)'],
                                ['value' => 'professional_services_fees', 'label' => 'Professional services fees (not MRR)'],
                                ['value' => 'non_billable_accounts', 'label' => 'Internal or comp accounts (not MRR)'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe the charge type and whether it is included.',
                            'classification' => $this->classification('definition'),
                            'workshop_prompt' => 'Agree which charge types roll into the official MRR figure.',
                            'workshop_guidance' => 'Recurring subscription charges and contracted usage commitments can form recurring run rate; variable usage belongs in a separately defined usage view, while setup fees, professional services, and non-billable accounts are not MRR. Define the included charge types and report separate views explicitly.',
                        ],
                        ['name' => 'revenue_stream_notes', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'Charge type special cases affecting MRR', 'form_label' => 'Special charge cases (usage fees, internal accounts, etc.) that impact MRR.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                        ['name' => 'mrr_example', 'executive_section' => 'official_definition', 'type' => 'textarea', 'label' => 'MRR calculation example', 'form_label' => 'Provide an example MRR calculation.', 'required' => false, 'meta' => true, 'classification' => $this->classification('definition')],
                        [
                            'name' => 'mrr_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Cross-team MRR definition conflict points',
                            'form_label' => 'Where do MRR numbers or rules conflict across teams?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where MRR numbers and rules conflict across Finance, Product, and Sales today.',
                            'workshop_guidance' => 'Compare source system, snapshot cutoff, annual-contract conversion, discount basis, plan changes, churn timing, trial entry, and included charge types. Resolve the recurring run-rate baseline separately from billing, bookings, and pipeline views.',
                        ],
                        ['name' => 'additional_notes', 'executive_section' => 'controls_caveats', 'type' => 'textarea', 'label' => 'Additional MRR notes and disclaimers', 'form_label' => 'Additional comments or disclaimers.', 'required' => false, 'meta' => true, 'classification' => $this->classification('context')],
                    ],
                ],
            ],
            [
                'key' => 'nps',
                'name' => 'Net Promoter Score (NPS)',
                'description' => 'Captures survey timing, audiences, passives, and response quality for NPS.',
                'form_schema_json' => [
                    'default_due_days' => 14,
                    'required_departments' => ['Customer Success', 'Customer Insights'],
                    'fields' => [
                        ['name' => 'team_department', 'type' => 'text', 'label' => 'Respondent team or department', 'form_label' => 'Which team or department do you represent?', 'required' => true, 'meta' => true, 'classification' => $this->classification('context', ['exclude_from_consensus' => true])],
                        [
                            'name' => 'survey_cadence', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'NPS survey cadence',
                            'form_label' => 'How often do you send the NPS survey?',
                            'required' => true,
                            'options' => [
                                ['value' => 'quarterly_all_customers', 'label' => 'Quarterly to all paying customers'],
                                ['value' => 'biannual_all_customers', 'label' => 'Biannually (twice a year) to all paying customers'],
                                ['value' => 'ongoing_triggered', 'label' => 'Ongoing / in-app after usage milestones'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, explain your survey cadence or lifecycle trigger.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree the official NPS survey cadence.',
                            'workshop_guidance' => 'Periodic surveys and triggered in-app programs sample different lifecycle moments and populations. Define one official survey program or report the programs as separate NPS series.',
                        ],
                        [
                            'name' => 'survey_timing_notes', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'textarea',
                            'label' => 'NPS survey lifecycle timing',
                            'form_label' => 'When in the lifecycle do you send NPS (e.g., after onboarding, before renewal)?',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'survey_audience_segment', 'executive_section' => 'official_definition',
                            'type' => 'select',
                            'label' => 'Primary NPS survey audience',
                            'form_label' => 'Which customers or users do you primarily survey?',
                            'required' => true,
                            'options' => [
                                ['value' => 'all_customers', 'label' => 'All customers/accounts'],
                                ['value' => 'paying_customers_only', 'label' => 'Paying or contracted customers only'],
                                ['value' => 'active_product_users', 'label' => 'All active product users (regardless of buyer role)'],
                                ['value' => 'decision_makers_only', 'label' => 'Decision-makers or admins only'],
                                ['value' => 'free_trial_included', 'label' => 'Includes free or trial users'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe who you survey.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree the official NPS audience.',
                            'workshop_guidance' => 'All accounts, paying customers, active users, decision-makers, and trial users are different populations and can produce different scores. Define the official audience and report other segments separately.',
                        ],
                        [
                            'name' => 'survey_audience_notes', 'executive_section' => 'official_definition',
                            'type' => 'textarea',
                            'label' => 'NPS audience segmentation, channels, and exclusions',
                            'form_label' => 'Describe key segments, channels, or exclusions for your NPS survey.',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'passive_handling', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Passive respondent handling policy',
                            'form_label' => 'How do you handle passives (scores 7–8)?',
                            'required' => true,
                            'options' => [
                                ['value' => 'ignore_passives', 'label' => 'No special treatment: promoters and detractors are tracked; passives receive no distinct follow-up'],
                                ['value' => 'follow_up_passives', 'label' => 'Follow-up: passives receive dedicated outreach'],
                                ['value' => 'label_passives_only', 'label' => 'Labeled only: passives are tagged in reporting but receive no different workflow'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, describe your passive handling.',
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Agree what follow-up, if any, passive respondents (scores 7-8) receive.',
                            'workshop_guidance' => 'Following up, labeling passives only, or taking no special action does not change the standard NPS formula. Choose the operating workflow and keep it separate from score calculation.',
                        ],
                        [
                            'name' => 'passive_handling_notes', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'textarea',
                            'label' => 'Passive handling workflows and exceptions',
                            'form_label' => 'Add nuance or workflows for how you treat passives.',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                        ],
                        [
                            'name' => 'response_volume_band', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'Typical NPS response volume band',
                            'form_label' => 'In a typical period, how many NPS responses do you collect?',
                            'required' => true,
                            'options' => [
                                ['value' => 'under_50', 'label' => 'Fewer than 50 responses'],
                                ['value' => '50_to_199', 'label' => '50–199 responses'],
                                ['value' => '200_plus', 'label' => '200+ responses'],
                                ['value' => 'varies_widely', 'label' => 'Varies widely by period'],
                                ['value' => 'unsure', 'label' => 'Response volume not consistently tracked'],
                            ],
                            'with_other' => true,
                            'other_prompt' => 'If Other, share an approximate volume per period.',
                            'classification' => $this->classification('context'),
                            'workshop_prompt' => 'Assess how the typical NPS response volume affects official reporting.',
                            'workshop_guidance' => 'Response count affects sampling variability, but sufficiency also depends on population size and the decision being supported. Use the volume band as a reporting caveat, not as proof of representativeness.',
                        ],
                        [
                            'name' => 'sample_representativeness', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'select',
                            'label' => 'NPS sample representativeness assessment',
                            'form_label' => 'Is your NPS sample representative of your overall user base?',
                            'required' => true,
                            'options' => [
                                ['value' => 'very_representative', 'label' => 'Broadly representative of the user base'],
                                ['value' => 'somewhat_representative', 'label' => 'Somewhat representative (skews to certain segments)'],
                                ['value' => 'not_representative', 'label' => 'Mostly a narrow or biased slice of the user base'],
                                ['value' => 'unsure', 'label' => 'Representativeness not yet assessed'],
                            ],
                            'classification' => $this->classification('governance'),
                            'workshop_prompt' => 'Assess whether the NPS sample composition supports official reporting.',
                            'workshop_guidance' => 'Representativeness depends on who responded, not only how many responded. Record known segment skews and caveat the official score when the sample does not reflect the intended audience.',
                        ],
                        [
                            'name' => 'sample_notes', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Sample representativeness notes and segment skews',
                            'form_label' => 'Add notes on sample representativeness or key segment skews.',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'bias_mitigation', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'textarea',
                            'label' => 'Response bias mitigation approach',
                            'form_label' => 'How do you reduce response bias (if at all)?',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'benchmarking', 'executive_section' => 'measurement_logic_rules',
                            'type' => 'textarea',
                            'label' => 'NPS benchmarking and trend tracking',
                            'form_label' => 'Do you benchmark or trend NPS over time?',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                        [
                            'name' => 'nps_example', 'executive_section' => 'official_definition',
                            'type' => 'textarea',
                            'label' => 'NPS result example (promoters/passives/detractors)',
                            'form_label' => 'Provide an example NPS result (promoters/passives/detractors).',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('definition'),
                        ],
                        [
                            'name' => 'nps_pain_points', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'NPS definition and methodology conflict points',
                            'form_label' => 'Where do NPS definitions or survey methods differ today?',
                            'required' => true,
                            'meta' => true,
                            'classification' => $this->classification('diagnostic'),
                            'workshop_prompt' => 'Surface where NPS definitions and survey methods differ across teams today.',
                            'workshop_guidance' => 'Compare survey program, lifecycle timing, audience, passive workflow, response volume, and sample composition. Resolve the official NPS series separately from team-specific survey instances.',
                        ],
                        [
                            'name' => 'additional_notes', 'executive_section' => 'controls_caveats',
                            'type' => 'textarea',
                            'label' => 'Additional NPS context and notes',
                            'form_label' => 'Additional notes or context.',
                            'required' => false,
                            'meta' => true,
                            'classification' => $this->classification('context'),
                        ],
                    ],
                ],
            ],
        ];

        foreach ($templates as &$template) {
            if (
                ! isset($template['form_schema_json']) ||
                ! is_array($template['form_schema_json']) ||
                ! isset($template['form_schema_json']['fields']) ||
                ! is_array($template['form_schema_json']['fields'])
            ) {
                continue;
            }

            $template['form_schema_json']['fields'] = array_map(
                fn ($field) => is_array($field)
                    ? $this->applyExecutiveSectionDefaults(
                        $this->applyClassificationDefaults($this->applyFieldLabelDefaults($field))
                    )
                    : $field,
                $template['form_schema_json']['fields']
            );
        }
        unset($template);

        foreach ($templates as $template) {
            $canonical = CanonicalMetric::updateOrCreate(
                ['key' => $template['key']],
                [
                    'name' => $template['name'],
                    'description' => $template['description'] ?? null,
                    'is_active' => true,
                ]
            );

            MetricTemplate::updateOrCreate(
                ['key' => $template['key']],
                [
                    'canonical_metric_id' => $canonical->getKey(),
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'form_schema_json' => $template['form_schema_json'],
                ]
            );
        }
    }

    private function applyClassificationDefaults(array $field): array
    {
        if (isset($field['classification']) && is_array($field['classification'])) {
            return $field;
        }

        $field['classification'] = $this->classification($this->inferRole($field));

        return $field;
    }

    private function applyExecutiveSectionDefaults(array $field): array
    {
        $classification = is_array($field['classification'] ?? null)
            ? $field['classification']
            : $this->classification($this->inferRole($field));

        $role = $classification['role'] ?? null;
        $role = is_string($role) ? trim(strtolower($role)) : '';
        if (! in_array($role, ['definition', 'context', 'governance', 'diagnostic'], true)) {
            $role = $this->inferRole($field);
        }

        $fieldExecutiveSection = $field['executive_section'] ?? null;
        $fieldExecutiveSection = is_string($fieldExecutiveSection) ? trim($fieldExecutiveSection) : '';

        if (
            in_array(
                $fieldExecutiveSection,
                ['official_definition', 'measurement_logic_rules', 'controls_caveats'],
                true
            )
        ) {
            $executiveSection = $fieldExecutiveSection;
        } else {
            $executiveSection = $classification['executive_section'] ?? null;
            $executiveSection = is_string($executiveSection) ? trim($executiveSection) : '';

            if (
                ! in_array(
                    $executiveSection,
                    ['official_definition', 'measurement_logic_rules', 'controls_caveats'],
                    true
                )
            ) {
                $executiveSection = $this->inferExecutiveSection($field, $role);
            }
        }

        $classification['role'] = $role;
        $classification['executive_section'] = $executiveSection;
        $field['classification'] = $classification;
        $field['executive_section'] = $executiveSection;

        return $field;
    }

    private function applyFieldLabelDefaults(array $field): array
    {
        $name = is_string($field['name'] ?? null) ? trim($field['name']) : '';
        $label = is_string($field['label'] ?? null) ? trim($field['label']) : '';
        $formLabel = is_string($field['form_label'] ?? null) ? trim($field['form_label']) : '';

        if ($label !== '' && $formLabel !== '') {
            $field['label'] = $label;
            $field['form_label'] = $formLabel;

            return $field;
        }

        if ($name !== '') {
            $field['label'] = $this->canonicalLabelForFieldName($name);
        } elseif ($label !== '') {
            $field['label'] = $label;
        }

        if ($formLabel !== '') {
            $field['form_label'] = $formLabel;
        } elseif ($label !== '') {
            $field['form_label'] = $label;
        } elseif (isset($field['label']) && is_string($field['label']) && $field['label'] !== '') {
            $field['form_label'] = $field['label'];
        }

        return $field;
    }

    private function canonicalLabelForFieldName(string $name): string
    {
        $label = Str::of($name)
            ->replace('_', ' ')
            ->headline()
            ->toString();

        return str_replace(
            ['Mrr', 'Nps', 'Dau', 'Wau', 'Mau', 'Cac', 'Cpl', 'Ltv', 'Mql', 'Sql', 'Api', 'Gl'],
            ['MRR', 'NPS', 'DAU', 'WAU', 'MAU', 'CAC', 'CPL', 'LTV', 'MQL', 'SQL', 'API', 'GL'],
            $label
        );
    }

    private function inferRole(array $field): string
    {
        $name = Str::of((string) ($field['name'] ?? ''))->lower()->toString();

        if ($this->containsAny($name, ['pain_point', 'blocker', 'special_case', 'risk'])) {
            return 'diagnostic';
        }

        if ($this->containsAny($name, ['policy', 'governance', 'handling', 'timing', 'owner'])) {
            return 'governance';
        }

        if (! empty($field['meta'])) {
            return 'context';
        }

        return 'definition';
    }

    private function inferExecutiveSection(array $field, string $role): string
    {
        $name = Str::of((string) ($field['name'] ?? ''))->lower()->toString();

        if (
            $this->containsAny($name, [
                'pain_point',
                'divergence',
                'risk',
                'conflict',
                'blocker',
                'mismatch',
                'gap',
            ])
        ) {
            return 'controls_caveats';
        }

        if (
            $this->containsAny($name, [
                'owner',
                'review_cadence',
                'decision',
                'business_context',
            ])
        ) {
            return 'official_definition';
        }

        return match ($role) {
            'definition' => 'official_definition',
            'diagnostic' => 'controls_caveats',
            'governance', 'context' => 'measurement_logic_rules',
            default => 'measurement_logic_rules',
        };
    }

    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function classification(string $role, array $overrides = []): array
    {
        $role = in_array($role, ['definition', 'context', 'governance', 'diagnostic'], true) ? $role : 'context';
        $executiveSection = $overrides['executive_section'] ?? null;
        $executiveSection = is_string($executiveSection) ? trim($executiveSection) : '';

        if (
            ! in_array(
                $executiveSection,
                ['official_definition', 'measurement_logic_rules', 'controls_caveats'],
                true
            )
        ) {
            $executiveSection = match ($role) {
                'definition' => 'official_definition',
                'diagnostic' => 'controls_caveats',
                'governance', 'context' => 'measurement_logic_rules',
                default => 'measurement_logic_rules',
            };
        }

        $classification = [
            'role' => $role,
            'exclude_from_consensus' => false,
            'executive_section' => $executiveSection,
        ];

        if (array_key_exists('exclude_from_consensus', $overrides)) {
            $classification['exclude_from_consensus'] = (bool) $overrides['exclude_from_consensus'];
        }

        $companionOf = $overrides['companion_of'] ?? null;
        if (is_string($companionOf) && trim($companionOf) !== '') {
            $classification['companion_of'] = trim($companionOf);
        }

        $cueContextScope = $overrides['cue_context_scope'] ?? null;
        if (is_string($cueContextScope) && trim($cueContextScope) !== '') {
            $classification['cue_context_scope'] = trim($cueContextScope);
        }

        return $classification;
    }
}
