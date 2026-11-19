<?php

namespace Database\Seeders\StorySeeds;

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
use App\Models\MetricTemplate;
use App\Models\ReconciliationWorkshop;
use App\Support\EngagementChecklist;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DiscrepancyResolutionStorySeeder extends BaseStorySeeder
{
    public function run(): void
    {
        $staffAdmin = $this->context->staffAdmin();
        $organization = $this->context->ensureClientOrganization(
            'orion-discrepancies',
            'Orion Insight Collective',
            ['timezone' => 'Europe/London']
        );

        $this->context->ensureConsultantAccess($organization, $staffAdmin, MembershipRole::ADMIN);

        $financeMembership = $this->context->ensureMember(
            $organization,
            'lia.price@orion.test',
            'Lia Price',
            MembershipRole::MEMBER,
            ['affiliation' => 'Finance']
        );
        $financeMembership->loadMissing('user');

        $revOpsMembership = $this->context->ensureMember(
            $organization,
            'anthony.reyes@orion.test',
            'Anthony Reyes',
            MembershipRole::MEMBER,
            ['affiliation' => 'Revenue Operations']
        );
        $revOpsMembership->loadMissing('user');

        $approverMembership = $this->context->ensureMember(
            $organization,
            'nora.kim@orion.test',
            'Nora Kim',
            MembershipRole::OWNER,
            ['affiliation' => 'Chief of Staff']
        );
        $approverMembership->loadMissing('user');

        $customerSuccessMembership = $this->context->ensureMember(
            $organization,
            'joy.lin@orion.test',
            'Joy Lin',
            MembershipRole::MEMBER,
            ['affiliation' => 'Customer Success']
        );
        $customerSuccessMembership->loadMissing('user');

        $marketingMembership = $this->context->ensureMember(
            $organization,
            'lila.martin@orion.test',
            'Lila Martin',
            MembershipRole::MEMBER,
            ['affiliation' => 'Marketing']
        );
        $marketingMembership->loadMissing('user');

        $engagement = Engagement::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'Revenue Intelligence Sprint',
            ],
            [
                'status' => EngagementStatus::IN_REVIEW,
                'starts_on' => now()->subMonth()->toDateString(),
                'review_cadence' => 'Monthly',
                'next_review_on' => now()->addWeeks(3)->toDateString(),
                'repository_driver' => 'confluence',
                'repository_reference' => 'https://orion.example.test/wiki/spaces/revintel',
                'stakeholders_json' => [
                    ['name' => 'Lia Price', 'email' => 'lia.price@orion.test', 'role' => 'member'],
                    ['name' => 'Anthony Reyes', 'email' => 'anthony.reyes@orion.test', 'role' => 'member'],
                    ['name' => 'Nora Kim', 'email' => 'nora.kim@orion.test', 'role' => 'admin'],
                ],
                'onboarding_checklist_json' => EngagementChecklist::merge([], [
                    'metrics_selected' => true,
                    'stakeholders_invited' => true,
                    'forms_bootstrapped' => true,
                    'discrepancy_engine_ready' => true,
                    'workshop_ready' => true,
                ]),
            ]
        );

        $financeParticipant = $this->createMembershipParticipant($engagement, $financeMembership, StakeholderRole::MEMBER);
        $revOpsParticipant = $this->createMembershipParticipant($engagement, $revOpsMembership, StakeholderRole::MEMBER);
        $approverParticipant = $this->createMembershipParticipant($engagement, $approverMembership, StakeholderRole::ADMIN);
        $customerSuccessParticipant = $this->createMembershipParticipant($engagement, $customerSuccessMembership, StakeholderRole::MEMBER);
        $marketingParticipant = $this->createMembershipParticipant($engagement, $marketingMembership, StakeholderRole::MEMBER);

        $scenarios = [
            [
                'key' => 'active-users-alignment',
                'name' => 'Active Users Definition',
                'slug' => 'active-users-alignment',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'Active Users Alignment Survey',
                'primary_field' => 'active_trigger_signal',
                'contributors' => [
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['Product Analytics'],
                        'answers' => [
                            'team_department' => 'Product Analytics',
                            'active_metrics_tracked' => ['dau', 'wau'],
                            'active_metrics_notes' => 'DAU watches daily adoption and WAU monitors activation cohorts.',
                            'active_trigger_signal' => 'key_action',
                            'active_trigger_notes' => 'Users are active once they create a project or send a message within 7 days.',
                            'deduplication_policy' => 'merge_accounts',
                            'deduplication_notes' => 'SSO IDs unify duplicates across workspaces.',
                            'bot_filtering_method' => 'exclude_known_bots',
                            'bot_filtering_notes' => 'We drop accounts flagged by the engineering heuristics list.',
                            'special_segments_notes' => 'Exclude employees, QA sandboxes, and demo tenants.',
                            'reporting_frequency' => 'daily_snapshot',
                            'reporting_frequency_notes' => 'Snapshots capture UTC calendar days and publish the next morning.',
                            'active_example' => 'June 3 DAU = unique user_ids with >=1 key action that calendar day.',
                            'active_pain_points' => 'Marketing counts paid seats as active even if no feature action occurred.',
                            'additional_notes' => 'Need agreement on when WAU rolls into MAU dashboards.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'active_metrics_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which active user windows do you track?',
                                    'raw' => ['dau', 'wau'],
                                    'normalized' => ['dau', 'wau'],
                                ],
                                'active_trigger_signal' => [
                                    'type' => 'select',
                                    'label' => 'What action or threshold marks someone as active?',
                                    'raw' => 'key_action',
                                    'normalized' => 'key_action',
                                ],
                                'deduplication_policy' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate multi-account users?',
                                    'raw' => 'merge_accounts',
                                    'normalized' => 'merge_accounts',
                                ],
                                'bot_filtering_method' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle bot or automated traffic?',
                                    'raw' => 'exclude_known_bots',
                                    'normalized' => 'exclude_known_bots',
                                ],
                                'reporting_frequency' => [
                                    'type' => 'select',
                                    'label' => 'How often do you refresh active user metrics?',
                                    'raw' => 'daily_snapshot',
                                    'normalized' => 'daily_snapshot',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Product Analytics',
                                    'normalized' => 'product analytics',
                                ],
                                'active_metrics_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context for the windows you selected (segments, carve-outs).',
                                    'raw' => 'DAU watches daily adoption and WAU monitors activation cohorts.',
                                    'normalized' => 'dau watches daily adoption and wau monitors activation cohorts.',
                                ],
                                'active_trigger_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe the precise rules or thresholds for the trigger you chose.',
                                    'raw' => 'Users are active once they create a project or send a message within 7 days.',
                                    'normalized' => 'users are active once they create a project or send a message within 7 days.',
                                ],
                                'deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or known exceptions for deduplication.',
                                    'raw' => 'SSO IDs unify duplicates across workspaces.',
                                    'normalized' => 'sso ids unify duplicates across workspaces.',
                                ],
                                'bot_filtering_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Share detection sources, thresholds, or exceptions.',
                                    'raw' => 'We drop accounts flagged by the engineering heuristics list.',
                                    'normalized' => 'we drop accounts flagged by the engineering heuristics list.',
                                ],
                                'special_segments_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special segments (read-only, API-only, free tiers) that change the count.',
                                    'raw' => 'Exclude employees, QA sandboxes, and demo tenants.',
                                    'normalized' => 'exclude employees, qa sandboxes, and demo tenants.',
                                ],
                                'reporting_frequency_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail cutoffs, timezone alignment, or offsets.',
                                    'raw' => 'Snapshots capture UTC calendar days and publish the next morning.',
                                    'normalized' => 'snapshots capture utc calendar days and publish the next morning.',
                                ],
                                'active_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide a short example calculation.',
                                    'raw' => 'June 3 DAU = unique user_ids with >=1 key action that calendar day.',
                                    'normalized' => 'june 3 dau = unique user_ids with >=1 key action that calendar day.',
                                ],
                                'active_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do definitions of active users diverge today?',
                                    'raw' => 'Marketing counts paid seats as active even if no feature action occurred.',
                                    'normalized' => 'marketing counts paid seats as active even if no feature action occurred.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'Need agreement on when WAU rolls into MAU dashboards.',
                                    'normalized' => 'need agreement on when wau rolls into mau dashboards.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(7),
                    ],
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
                        'answers' => [
                            'team_department' => 'Marketing',
                            'active_metrics_tracked' => ['wau', 'mau'],
                            'active_metrics_notes' => 'WAU powers lifecycle campaigns and MAU feeds board reporting.',
                            'active_trigger_signal' => 'seat_utilization',
                            'active_trigger_notes' => 'We call a user active if they hold a paid seat and logged in once during the month.',
                            'deduplication_policy' => 'count_accounts',
                            'deduplication_notes' => 'Separate workspaces stay separate so regional teams keep ownership.',
                            'bot_filtering_method' => 'flag_for_review',
                            'bot_filtering_notes' => 'Automation accounts are tagged but left in WAU until Ops reviews monthly.',
                            'special_segments_notes' => 'Free trials count for WAU but not MAU; sandbox workspaces excluded.',
                            'reporting_frequency' => 'monthly_snapshot',
                            'reporting_frequency_notes' => 'MAU locks at the last calendar day and we refresh WAU every Monday.',
                            'active_example' => 'July MAU = paid seats with a login in July; WAU uses ISO week logins.',
                            'active_pain_points' => 'Product wants feature usage, but marketing cares about paid seat utilization.',
                            'additional_notes' => 'Need to document if customer success sandboxes count anywhere.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'active_metrics_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which active user windows do you track?',
                                    'raw' => ['wau', 'mau'],
                                    'normalized' => ['wau', 'mau'],
                                ],
                                'active_trigger_signal' => [
                                    'type' => 'select',
                                    'label' => 'What action or threshold marks someone as active?',
                                    'raw' => 'seat_utilization',
                                    'normalized' => 'seat_utilization',
                                ],
                                'deduplication_policy' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate multi-account users?',
                                    'raw' => 'count_accounts',
                                    'normalized' => 'count_accounts',
                                ],
                                'bot_filtering_method' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle bot or automated traffic?',
                                    'raw' => 'flag_for_review',
                                    'normalized' => 'flag_for_review',
                                ],
                                'reporting_frequency' => [
                                    'type' => 'select',
                                    'label' => 'How often do you refresh active user metrics?',
                                    'raw' => 'monthly_snapshot',
                                    'normalized' => 'monthly_snapshot',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Marketing',
                                    'normalized' => 'marketing',
                                ],
                                'active_metrics_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context for the windows you selected (segments, carve-outs).',
                                    'raw' => 'WAU powers lifecycle campaigns and MAU feeds board reporting.',
                                    'normalized' => 'wau powers lifecycle campaigns and mau feeds board reporting.',
                                ],
                                'active_trigger_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe the precise rules or thresholds for the trigger you chose.',
                                    'raw' => 'We call a user active if they hold a paid seat and logged in once during the month.',
                                    'normalized' => 'we call a user active if they hold a paid seat and logged in once during the month.',
                                ],
                                'deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or known exceptions for deduplication.',
                                    'raw' => 'Separate workspaces stay separate so regional teams keep ownership.',
                                    'normalized' => 'separate workspaces stay separate so regional teams keep ownership.',
                                ],
                                'bot_filtering_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Share detection sources, thresholds, or exceptions.',
                                    'raw' => 'Automation accounts are tagged but left in WAU until Ops reviews monthly.',
                                    'normalized' => 'automation accounts are tagged but left in wau until ops reviews monthly.',
                                ],
                                'special_segments_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special segments (read-only, API-only, free tiers) that change the count.',
                                    'raw' => 'Free trials count for WAU but not MAU; sandbox workspaces excluded.',
                                    'normalized' => 'free trials count for wau but not mau; sandbox workspaces excluded.',
                                ],
                                'reporting_frequency_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail cutoffs, timezone alignment, or offsets.',
                                    'raw' => 'MAU locks at the last calendar day and we refresh WAU every Monday.',
                                    'normalized' => 'mau locks at the last calendar day and we refresh wau every monday.',
                                ],
                                'active_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide a short example calculation.',
                                    'raw' => 'July MAU = paid seats with a login in July; WAU uses ISO week logins.',
                                    'normalized' => 'july mau = paid seats with a login in july; wau uses iso week logins.',
                                ],
                                'active_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do definitions of active users diverge today?',
                                    'raw' => 'Product wants feature usage, but marketing cares about paid seat utilization.',
                                    'normalized' => 'product wants feature usage, but marketing cares about paid seat utilization.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'Need to document if customer success sandboxes count anywhere.',
                                    'normalized' => 'need to document if customer success sandboxes count anywhere.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(6),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'active_trigger_signal',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Product counts feature actions as active while Marketing counts paid seats with a recent login.',
                    'detected_at' => now()->subDays(5),
                ],
            ],
            [
                'key' => 'monthly-recurring-revenue',
                'name' => 'Monthly Recurring Revenue (MRR)',
                'slug' => 'monthly-recurring-revenue',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'MRR Definition Review',
                'primary_field' => 'annual_deal_handling',
                'contributors' => [
                    [
                        'membership' => $financeMembership,
                        'participant' => $financeParticipant,
                        'tags' => ['Finance'],
                        'answers' => [
                            'team_department' => 'Finance',
                            'mrr_description' => 'We pull subscription data from our billing platform (Stripe) each month. We sum up all active subscriptions’ monthly amounts on the last day of the month.',
                            'mrr_snapshot_timing' => 'last_calendar_day',
                            'mrr_snapshot_notes' => 'We lock reporting after the last calendar day so Finance close can reconcile.',
                            'annual_deal_handling' => 'full_invoice_in_invoice_month',
                            'annual_deal_notes' => 'Large enterprise deals hit Finance reporting upfront per accounting policy.',
                            'discount_handling' => 'net_recurring_price',
                            'discount_handling_notes' => 'Partial refunds or credits are deducted immediately inside the same month.',
                            'mid_month_changes' => 'wait_next_cycle',
                            'mid_month_changes_notes' => 'If a customer upgrades mid-month, we true up the MRR total the following month.',
                            'churn_timing' => 'immediate',
                            'churn_timing_notes' => 'We reverse MRR as soon as a cancellation posts, even if they still have service days left.',
                            'trial_handling' => 'exclude_until_payment',
                            'trial_handling_notes' => 'Trials and internal accounts never appear in Finance MRR until they pay.',
                            'included_revenue_streams' => [
                                'recurring_subscription_charges',
                            ],
                            'revenue_stream_notes' => 'Usage-based fees, implementation projects, and internal accounts stay out of MRR.',
                            'mrr_example' => 'If a customer signs an annual contract for $12,000 on June 1, we count $12,000 in June’s MRR; if that customer upgrades mid-June to $15,000 annually, we bump MRR to $15,000 in July.',
                            'mrr_pain_points' => 'We realize the Product team’s numbers don’t match ours if they’re prorating annual deals. Board decks sometimes use Product’s MRR figure, which is 10–20% lower than ours.',
                            'additional_notes' => 'We want to ensure we comply with Finance reporting, but we’re open to adjusting if the official MRR definition differs from GAAP recognition.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'mrr_description' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you measure MRR each month.',
                                    'raw' => 'We pull subscription data from our billing platform (Stripe) each month. We sum up all active subscriptions’ monthly amounts on the last day of the month.',
                                    'normalized' => 'we pull subscription data from our billing platform (stripe) each month. we sum up all active subscriptions’ monthly amounts on the last day of the month.',
                                ],
                                'mrr_snapshot_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you capture the official MRR snapshot?',
                                    'raw' => 'last_calendar_day',
                                    'normalized' => 'last_calendar_day',
                                ],
                                'annual_deal_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat annual or multi-year contracts?',
                                    'raw' => 'full_invoice_in_invoice_month',
                                    'normalized' => 'full_invoice_in_invoice_month',
                                ],
                                'discount_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do discounts, promotions, or credits impact MRR?',
                                    'raw' => 'net_recurring_price',
                                    'normalized' => 'net_recurring_price',
                                ],
                                'mid_month_changes' => [
                                    'type' => 'select',
                                    'label' => 'How do you reflect mid-month upgrades or downgrades?',
                                    'raw' => 'wait_next_cycle',
                                    'normalized' => 'wait_next_cycle',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you remove churned customers from MRR?',
                                    'raw' => 'immediate',
                                    'normalized' => 'immediate',
                                ],
                                'trial_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat trials or grace periods in MRR?',
                                    'raw' => 'exclude_until_payment',
                                    'normalized' => 'exclude_until_payment',
                                ],
                                'included_revenue_streams' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which charge types roll into MRR?',
                                    'raw' => ['recurring_subscription_charges'],
                                    'normalized' => ['recurring_subscription_charges'],
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Finance',
                                    'normalized' => 'finance',
                                ],
                                'mrr_snapshot_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain why you use that snapshot timing (optional).',
                                    'raw' => 'We lock reporting after the last calendar day so Finance close can reconcile.',
                                    'normalized' => 'we lock reporting after the last calendar day so finance close can reconcile.',
                                ],
                                'annual_deal_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or exceptions for annual deals.',
                                    'raw' => 'Large enterprise deals hit Finance reporting upfront per accounting policy.',
                                    'normalized' => 'large enterprise deals hit finance reporting upfront per accounting policy.',
                                ],
                                'discount_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or edge cases for discounts/credits.',
                                    'raw' => 'Partial refunds or credits are deducted immediately inside the same month.',
                                    'normalized' => 'partial refunds or credits are deducted immediately inside the same month.',
                                ],
                                'mid_month_changes_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain any mid-month change nuances.',
                                    'raw' => 'If a customer upgrades mid-month, we true up the MRR total the following month.',
                                    'normalized' => 'if a customer upgrades mid-month, we true up the mrr total the following month.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain churn timing exceptions.',
                                    'raw' => 'We reverse MRR as soon as a cancellation posts, even if they still have service days left.',
                                    'normalized' => 'we reverse mrr as soon as a cancellation posts, even if they still have service days left.',
                                ],
                                'trial_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add trial/grace period context.',
                                    'raw' => 'Trials and internal accounts never appear in Finance MRR until they pay.',
                                    'normalized' => 'trials and internal accounts never appear in finance mrr until they pay.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special charge cases (usage fees, internal accounts, etc.) that impact MRR.',
                                    'raw' => 'Usage-based fees, implementation projects, and internal accounts stay out of MRR.',
                                    'normalized' => 'usage-based fees, implementation projects, and internal accounts stay out of mrr.',
                                ],
                                'mrr_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example MRR calculation.',
                                    'raw' => 'If a customer signs an annual contract for $12,000 on June 1, we count $12,000 in June’s MRR; if that customer upgrades mid-June to $15,000 annually, we bump MRR to $15,000 in July.',
                                    'normalized' => 'if a customer signs an annual contract for $12,000 on june 1, we count $12,000 in june’s mrr; if that customer upgrades mid-june to $15,000 annually, we bump mrr to $15,000 in july.',
                                ],
                                'mrr_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do MRR numbers or rules conflict across teams?',
                                    'raw' => 'We realize the Product team’s numbers don’t match ours if they’re prorating annual deals. Board decks sometimes use Product’s MRR figure, which is 10–20% lower than ours.',
                                    'normalized' => 'we realize the product team’s numbers don’t match ours if they’re prorating annual deals. board decks sometimes use product’s mrr figure, which is 10–20% lower than ours.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional comments or disclaimers.',
                                    'raw' => 'We want to ensure we comply with Finance reporting, but we’re open to adjusting if the official MRR definition differs from GAAP recognition.',
                                    'normalized' => 'we want to ensure we comply with finance reporting, but we’re open to adjusting if the official mrr definition differs from gaap recognition.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(6),
                    ],
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['Product'],
                        'answers' => [
                            'team_department' => 'Product',
                            'mrr_description' => 'We have an internal dashboard that sums up subscription “plan amounts” for all active customers. We prefer to see a stable monthly figure that matches day-to-day usage.',
                            'mrr_snapshot_timing' => 'rolling_daily',
                            'mrr_snapshot_notes' => 'We keep a rolling snapshot because product analytics teams monitor MRR daily.',
                            'annual_deal_handling' => 'normalized_contract_value',
                            'annual_deal_notes' => 'Annual or expansion deals re-spread evenly beginning the day the contract changes.',
                            'discount_handling' => 'net_recurring_price',
                            'discount_handling_notes' => 'Refunds or credits flow through once the invoicing data syncs into our warehouse.',
                            'mid_month_changes' => 'update_immediately',
                            'mid_month_changes_notes' => 'We re-compute MRR on the day the plan amount changes so dashboards stay current.',
                            'churn_timing' => 'paid_through_date',
                            'churn_timing_notes' => 'Customers stay in MRR until their paid-through date because they still have access.',
                            'trial_handling' => 'exclude_until_payment',
                            'trial_handling_notes' => 'Trials stay separate unless they convert before the cycle ends.',
                            'included_revenue_streams' => [
                                'recurring_subscription_charges',
                            ],
                            'revenue_stream_notes' => 'Usage overages are tracked separately as non-recurring add-ons.',
                            'mrr_example' => 'For a $12,000 annual plan that starts June 1, we add $1,000 to MRR for June; if they upgrade mid-June to $15,000 annually, we recalculate the daily MRR from the 15th onward.',
                            'mrr_pain_points' => 'Finance logs the entire annual contract up-front, leading to big spikes in their MRR figure, and our real-time approach can produce different daily snapshots from what Finance sees at month-end.',
                            'additional_notes' => 'We’d prefer to keep our immediate updates for product analytics, but we can align with a standard monthly snapshot if that’s the group decision.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'mrr_description' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you measure MRR each month.',
                                    'raw' => 'We have an internal dashboard that sums up subscription “plan amounts” for all active customers. We prefer to see a stable monthly figure that matches day-to-day usage.',
                                    'normalized' => 'we have an internal dashboard that sums up subscription “plan amounts” for all active customers. we prefer to see a stable monthly figure that matches day-to-day usage.',
                                ],
                                'mrr_snapshot_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you capture the official MRR snapshot?',
                                    'raw' => 'rolling_daily',
                                    'normalized' => 'rolling_daily',
                                ],
                                'annual_deal_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat annual or multi-year contracts?',
                                    'raw' => 'normalized_contract_value',
                                    'normalized' => 'normalized_contract_value',
                                ],
                                'discount_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do discounts, promotions, or credits impact MRR?',
                                    'raw' => 'net_recurring_price',
                                    'normalized' => 'net_recurring_price',
                                ],
                                'mid_month_changes' => [
                                    'type' => 'select',
                                    'label' => 'How do you reflect mid-month upgrades or downgrades?',
                                    'raw' => 'update_immediately',
                                    'normalized' => 'update_immediately',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you remove churned customers from MRR?',
                                    'raw' => 'paid_through_date',
                                    'normalized' => 'paid_through_date',
                                ],
                                'trial_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat trials or grace periods in MRR?',
                                    'raw' => 'exclude_until_payment',
                                    'normalized' => 'exclude_until_payment',
                                ],
                                'included_revenue_streams' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which charge types roll into MRR?',
                                    'raw' => ['recurring_subscription_charges'],
                                    'normalized' => ['recurring_subscription_charges'],
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Product',
                                    'normalized' => 'product',
                                ],
                                'mrr_snapshot_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain why you use that snapshot timing (optional).',
                                    'raw' => 'We keep a rolling snapshot because product analytics teams monitor MRR daily.',
                                    'normalized' => 'we keep a rolling snapshot because product analytics teams monitor mrr daily.',
                                ],
                                'annual_deal_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or exceptions for annual deals.',
                                    'raw' => 'Annual or expansion deals re-spread evenly beginning the day the contract changes.',
                                    'normalized' => 'annual or expansion deals re-spread evenly beginning the day the contract changes.',
                                ],
                                'discount_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or edge cases for discounts/credits.',
                                    'raw' => 'Refunds or credits flow through once the invoicing data syncs into our warehouse.',
                                    'normalized' => 'refunds or credits flow through once the invoicing data syncs into our warehouse.',
                                ],
                                'mid_month_changes_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain any mid-month change nuances.',
                                    'raw' => 'We re-compute MRR on the day the plan amount changes so dashboards stay current.',
                                    'normalized' => 'we re-compute mrr on the day the plan amount changes so dashboards stay current.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain churn timing exceptions.',
                                    'raw' => 'Customers stay in MRR until their paid-through date because they still have access.',
                                    'normalized' => 'customers stay in mrr until their paid-through date because they still have access.',
                                ],
                                'trial_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add trial/grace period context.',
                                    'raw' => 'Trials stay separate unless they convert before the cycle ends.',
                                    'normalized' => 'trials stay separate unless they convert before the cycle ends.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special charge cases (usage fees, internal accounts, etc.) that impact MRR.',
                                    'raw' => 'Usage overages are tracked separately as non-recurring add-ons.',
                                    'normalized' => 'usage overages are tracked separately as non-recurring add-ons.',
                                ],
                                'mrr_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example MRR calculation.',
                                    'raw' => 'For a $12,000 annual plan that starts June 1, we add $1,000 to MRR for June; if they upgrade mid-June to $15,000 annually, we recalculate the daily MRR from the 15th onward.',
                                    'normalized' => 'for a $12,000 annual plan that starts june 1, we add $1,000 to mrr for june; if they upgrade mid-june to $15,000 annually, we recalculate the daily mrr from the 15th onward.',
                                ],
                                'mrr_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do MRR numbers or rules conflict across teams?',
                                    'raw' => 'Finance logs the entire annual contract up-front, leading to big spikes in their MRR figure, and our real-time approach can produce different daily snapshots from what Finance sees at month-end.',
                                    'normalized' => 'finance logs the entire annual contract up-front, leading to big spikes in their mrr figure, and our real-time approach can produce different daily snapshots from what finance sees at month-end.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional comments or disclaimers.',
                                    'raw' => 'We’d prefer to keep our immediate updates for product analytics, but we can align with a standard monthly snapshot if that’s the group decision.',
                                    'normalized' => 'we’d prefer to keep our immediate updates for product analytics, but we can align with a standard monthly snapshot if that’s the group decision.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
                        'answers' => [
                            'team_department' => 'Marketing',
                            'mrr_description' => 'We mostly look at “contract value” from new sign-ups, and we rely on a monthly pipeline spreadsheet that lumps annual deals into a single figure for the month they sign up.',
                            'mrr_snapshot_timing' => 'contract_close_bookings_view',
                            'mrr_snapshot_notes' => 'Pipeline snapshots are taken when deals close; we rarely revisit prior months.',
                            'annual_deal_handling' => 'full_invoice_in_invoice_month',
                            'annual_deal_notes' => 'We show the entire contract value the month it closes, no monthly spread.',
                            'discount_handling' => 'list_price_view',
                            'discount_handling_notes' => 'Campaign decks prefer list price so we emphasize potential value.',
                            'mid_month_changes' => 'wait_next_cycle',
                            'mid_month_changes_notes' => 'We generally do not adjust mid-month because our sheet is monthly.',
                            'churn_timing' => 'retained_pipeline_history',
                            'churn_timing_notes' => 'Once a deal closes it stays in the spreadsheet even if they later churn.',
                            'trial_handling' => 'trial_list_price_forecast_view',
                            'trial_handling_notes' => 'Trials under promotional campaigns are counted as potential MRR.',
                            'included_revenue_streams' => [
                                'recurring_subscription_charges',
                                'variable_usage_charges',
                                'one_time_setup_fees',
                            ],
                            'revenue_stream_notes' => 'We don’t differentiate usage-based overages or promotional add-ons; they stay in the same pipeline figure.',
                            'mrr_example' => 'If we close a $10,000 annual deal on July 10, we just mark $10,000 added to July’s MRR pipeline.',
                            'mrr_pain_points' => 'We know our MRR numbers resemble “pipeline MRR” rather than official recurring revenue, and we want clarity versus the official definition.',
                            'additional_notes' => 'We’re open to adopting the Finance or Product standard as long as we can keep a simpler pipeline view for new deals.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'mrr_description' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you measure MRR each month.',
                                    'raw' => 'We mostly look at “contract value” from new sign-ups, and we rely on a monthly pipeline spreadsheet that lumps annual deals into a single figure for the month they sign up.',
                                    'normalized' => 'we mostly look at “contract value” from new sign-ups, and we rely on a monthly pipeline spreadsheet that lumps annual deals into a single figure for the month they sign up.',
                                ],
                                'mrr_snapshot_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you capture the official MRR snapshot?',
                                    'raw' => 'contract_close_bookings_view',
                                    'normalized' => 'contract_close_bookings_view',
                                ],
                                'annual_deal_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat annual or multi-year contracts?',
                                    'raw' => 'full_invoice_in_invoice_month',
                                    'normalized' => 'full_invoice_in_invoice_month',
                                ],
                                'discount_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do discounts, promotions, or credits impact MRR?',
                                    'raw' => 'list_price_view',
                                    'normalized' => 'list_price_view',
                                ],
                                'mid_month_changes' => [
                                    'type' => 'select',
                                    'label' => 'How do you reflect mid-month upgrades or downgrades?',
                                    'raw' => 'wait_next_cycle',
                                    'normalized' => 'wait_next_cycle',
                                ],
                                'churn_timing' => [
                                    'type' => 'select',
                                    'label' => 'When do you remove churned customers from MRR?',
                                    'raw' => 'retained_pipeline_history',
                                    'normalized' => 'retained_pipeline_history',
                                ],
                                'trial_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you treat trials or grace periods in MRR?',
                                    'raw' => 'trial_list_price_forecast_view',
                                    'normalized' => 'trial_list_price_forecast_view',
                                ],
                                'included_revenue_streams' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which charge types roll into MRR?',
                                    'raw' => ['recurring_subscription_charges', 'variable_usage_charges', 'one_time_setup_fees'],
                                    'normalized' => ['recurring_subscription_charges', 'variable_usage_charges', 'one_time_setup_fees'],
                                ],
                            ],
                            'meta' => [
                                'mrr_snapshot_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain why you use that snapshot timing (optional).',
                                    'raw' => 'Pipeline snapshots are taken when deals close; we rarely revisit prior months.',
                                    'normalized' => 'pipeline snapshots are taken when deals close; we rarely revisit prior months.',
                                ],
                                'annual_deal_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or exceptions for annual deals.',
                                    'raw' => 'We show the entire contract value the month it closes, no monthly spread.',
                                    'normalized' => 'we show the entire contract value the month it closes, no monthly spread.',
                                ],
                                'discount_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context or edge cases for discounts/credits.',
                                    'raw' => 'Campaign decks prefer list price so we emphasize potential value.',
                                    'normalized' => 'campaign decks prefer list price so we emphasize potential value.',
                                ],
                                'mid_month_changes_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain any mid-month change nuances.',
                                    'raw' => 'We generally do not adjust mid-month because our sheet is monthly.',
                                    'normalized' => 'we generally do not adjust mid-month because our sheet is monthly.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain churn timing exceptions.',
                                    'raw' => 'Once a deal closes it stays in the spreadsheet even if they later churn.',
                                    'normalized' => 'once a deal closes it stays in the spreadsheet even if they later churn.',
                                ],
                                'trial_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add trial/grace period context.',
                                    'raw' => 'Trials under promotional campaigns are counted as potential MRR.',
                                    'normalized' => 'trials under promotional campaigns are counted as potential mrr.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special charge cases (usage fees, internal accounts, etc.) that impact MRR.',
                                    'raw' => 'We don’t differentiate usage-based overages or promotional add-ons; they stay in the same pipeline figure.',
                                    'normalized' => 'we don’t differentiate usage-based overages or promotional add-ons; they stay in the same pipeline figure.',
                                ],
                                'mrr_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example MRR calculation.',
                                    'raw' => 'If we close a $10,000 annual deal on July 10, we just mark $10,000 added to July’s MRR pipeline.',
                                    'normalized' => 'if we close a $10,000 annual deal on july 10, we just mark $10,000 added to july’s mrr pipeline.',
                                ],
                                'mrr_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do MRR numbers or rules conflict across teams?',
                                    'raw' => 'We know our MRR numbers resemble “pipeline MRR” rather than official recurring revenue, and we want clarity versus the official definition.',
                                    'normalized' => 'we know our mrr numbers resemble “pipeline mrr” rather than official recurring revenue, and we want clarity versus the official definition.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional comments or disclaimers.',
                                    'raw' => 'We’re open to adopting the Finance or Product standard as long as we can keep a simpler pipeline view for new deals.',
                                    'normalized' => 'we’re open to adopting the finance or product standard as long as we can keep a simpler pipeline view for new deals.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(4),
                    ],
                ],
                'discrepancy' => [
                    'field_key' => 'annual_deal_handling',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Teams disagree on whether annual deals should be booked upfront or prorated monthly.',
                    'detected_at' => now()->subDays(2),
                ],
            ],
            [
                'key' => 'ltv',
                'name' => 'Lifetime Value (LTV)',
                'slug' => 'lifetime-value',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'Lifetime Value (LTV) Measurement Form',
                'primary_field' => 'ltv_formula',
                'contributors' => [
                    [
                        'membership' => $financeMembership,
                        'participant' => $financeParticipant,
                        'tags' => ['Finance'],
                        'answers' => [
                            'team_department' => 'Finance',
                            'ltv_formula' => 'discounted_cash_flow',
                            'ltv_formula_notes' => 'We treat LTV as the discounted present value of future gross margin per customer using a fixed horizon and discount rate.',
                            'lifespan_assumption' => '36_months',
                            'lifespan_notes' => 'We assume a 3-year life for most cohorts based on historical churn curves.',
                            'revenue_streams_included' => [
                                'base_subscription',
                                'upsells_addons',
                            ],
                            'revenue_stream_notes' => 'We only include recurring subscription and expansion revenue; services stay out of Finance LTV.',
                            'discount_method' => 'discount_rate',
                            'discount_method_notes' => 'We apply a 10% annual discount rate to gross margin cash flows.',
                            'margin_basis' => 'gross_margin',
                            'margin_basis_notes' => 'COGS and direct support costs are subtracted; overhead and R&D are excluded.',
                            'one_time_fee_handling' => 'exclude_one_time',
                            'one_time_fee_notes' => 'Implementation and onboarding are treated as separate project revenue, not LTV.',
                            'ltv_example' => 'If a customer generates $500 in monthly gross margin with a 36-month horizon, we discount each month at 10% to compute LTV.',
                            'ltv_pain_points' => 'Other teams talk about LTV using billed revenue and ignore discounting, so their numbers are higher than our Finance definition.',
                            'additional_notes' => 'We want the official definition to match Finance reporting for board decks.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'ltv_formula' => [
                                    'type' => 'select',
                                    'label' => 'How do you calculate or conceptualize LTV?',
                                    'raw' => 'discounted_cash_flow',
                                    'normalized' => 'discounted_cash_flow',
                                ],
                                'lifespan_assumption' => [
                                    'type' => 'select',
                                    'label' => 'Which time horizon or lifespan do you assume?',
                                    'raw' => '36_months',
                                    'normalized' => '36_months',
                                ],
                                'revenue_streams_included' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which revenue streams are included?',
                                    'raw' => ['base_subscription', 'upsells_addons'],
                                    'normalized' => ['base_subscription', 'upsells_addons'],
                                ],
                                'discount_method' => [
                                    'type' => 'select',
                                    'label' => 'Do you discount future revenue to present value?',
                                    'raw' => 'discount_rate',
                                    'normalized' => 'discount_rate',
                                ],
                                'margin_basis' => [
                                    'type' => 'select',
                                    'label' => 'Is LTV based on revenue or margin?',
                                    'raw' => 'gross_margin',
                                    'normalized' => 'gross_margin',
                                ],
                                'one_time_fee_handling' => [
                                    'type' => 'select',
                                    'label' => 'Do you include one-time fees or onboarding charges?',
                                    'raw' => 'exclude_one_time',
                                    'normalized' => 'exclude_one_time',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Finance',
                                    'normalized' => 'finance',
                                ],
                                'ltv_formula_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe your LTV formula or key assumptions.',
                                    'raw' => 'We treat LTV as the discounted present value of future gross margin per customer using a fixed horizon and discount rate.',
                                    'normalized' => 'we treat ltv as the discounted present value of future gross margin per customer using a fixed horizon and discount rate.',
                                ],
                                'lifespan_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context on customer lifespan or churn assumptions.',
                                    'raw' => 'We assume a 3-year life for most cohorts based on historical churn curves.',
                                    'normalized' => 'we assume a 3-year life for most cohorts based on historical churn curves.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special revenue streams or exclusions (internal accounts, credits, etc.).',
                                    'raw' => 'We only include recurring subscription and expansion revenue; services stay out of Finance LTV.',
                                    'normalized' => 'we only include recurring subscription and expansion revenue; services stay out of finance ltv.',
                                ],
                                'discount_method_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on discount rates, timelines, or NPV setup.',
                                    'raw' => 'We apply a 10% annual discount rate to gross margin cash flows.',
                                    'normalized' => 'we apply a 10% annual discount rate to gross margin cash flows.',
                                ],
                                'margin_basis_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain which cost components are included or excluded.',
                                    'raw' => 'COGS and direct support costs are subtracted; overhead and R&D are excluded.',
                                    'normalized' => 'cogs and direct support costs are subtracted; overhead and r&d are excluded.',
                                ],
                                'one_time_fee_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Note special handling for setup, implementation, or onboarding fees.',
                                    'raw' => 'Implementation and onboarding are treated as separate project revenue, not LTV.',
                                    'normalized' => 'implementation and onboarding are treated as separate project revenue, not ltv.',
                                ],
                                'ltv_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide an example LTV calculation.',
                                    'raw' => 'If a customer generates $500 in monthly gross margin with a 36-month horizon, we discount each month at 10% to compute LTV.',
                                    'normalized' => 'if a customer generates $500 in monthly gross margin with a 36-month horizon, we discount each month at 10% to compute ltv.',
                                ],
                                'ltv_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do LTV assumptions differ between teams?',
                                    'raw' => 'Other teams talk about LTV using billed revenue and ignore discounting, so their numbers are higher than our Finance definition.',
                                    'normalized' => 'other teams talk about ltv using billed revenue and ignore discounting, so their numbers are higher than our finance definition.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We want the official definition to match Finance reporting for board decks.',
                                    'normalized' => 'we want the official definition to match finance reporting for board decks.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['Revenue Operations'],
                        'answers' => [
                            'team_department' => 'Revenue Operations',
                            'ltv_formula' => 'simple_revenue_lifespan',
                            'ltv_formula_notes' => 'We approximate LTV by multiplying average monthly recurring revenue per account by a 24-month lifespan without discounting.',
                            'lifespan_assumption' => '24_months',
                            'lifespan_notes' => 'We cap the window at two years because most customers either expand or churn by then.',
                            'revenue_streams_included' => [
                                'base_subscription',
                                'upsells_addons',
                                'professional_services_fees',
                                'usage_based',
                                'one_time_fees',
                            ],
                            'revenue_stream_notes' => 'RevOps includes one-time setup and services revenue in LTV so dashboards show total commercial value.',
                            'discount_method' => 'no_discounting',
                            'discount_method_notes' => 'We do not discount; we just take current ARPU and multiply by 24 months.',
                            'margin_basis' => 'revenue_only',
                            'margin_basis_notes' => 'We use billed revenue and do not adjust for margin or overhead.',
                            'one_time_fee_handling' => 'include_upfront_or_spread',
                            'one_time_fee_notes' => 'Onboarding fees are counted in the first month; larger implementation projects are spread over the first year.',
                            'ltv_example' => 'If an account pays $800 per month and stays for 24 months, we show LTV as $19,200 even if they churn earlier.',
                            'ltv_pain_points' => 'Finance’s discounted margin-based LTV is lower than what we show in revenue dashboards, which confuses executives.',
                            'additional_notes' => 'We are open to aligning with a discounted approach as long as we can keep a simple ARPU × months heuristic in RevOps tools.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'ltv_formula' => [
                                    'type' => 'select',
                                    'label' => 'How do you calculate or conceptualize LTV?',
                                    'raw' => 'simple_revenue_lifespan',
                                    'normalized' => 'simple_revenue_lifespan',
                                ],
                                'lifespan_assumption' => [
                                    'type' => 'select',
                                    'label' => 'Which time horizon or lifespan do you assume?',
                                    'raw' => '24_months',
                                    'normalized' => '24_months',
                                ],
                                'revenue_streams_included' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which revenue streams are included?',
                                    'raw' => ['base_subscription', 'upsells_addons', 'professional_services_fees', 'usage_based', 'one_time_fees'],
                                    'normalized' => ['base_subscription', 'upsells_addons', 'professional_services_fees', 'usage_based', 'one_time_fees'],
                                ],
                                'discount_method' => [
                                    'type' => 'select',
                                    'label' => 'Do you discount future revenue to present value?',
                                    'raw' => 'no_discounting',
                                    'normalized' => 'no_discounting',
                                ],
                                'margin_basis' => [
                                    'type' => 'select',
                                    'label' => 'Is LTV based on revenue or margin?',
                                    'raw' => 'revenue_only',
                                    'normalized' => 'revenue_only',
                                ],
                                'one_time_fee_handling' => [
                                    'type' => 'select',
                                    'label' => 'Do you include one-time fees or onboarding charges?',
                                    'raw' => 'include_upfront_or_spread',
                                    'normalized' => 'include_upfront_or_spread',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Revenue Operations',
                                    'normalized' => 'revenue operations',
                                ],
                                'ltv_formula_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe your LTV formula or key assumptions.',
                                    'raw' => 'We approximate LTV by multiplying average monthly recurring revenue per account by a 24-month lifespan without discounting.',
                                    'normalized' => 'we approximate ltv by multiplying average monthly recurring revenue per account by a 24-month lifespan without discounting.',
                                ],
                                'lifespan_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add context on customer lifespan or churn assumptions.',
                                    'raw' => 'We cap the window at two years because most customers either expand or churn by then.',
                                    'normalized' => 'we cap the window at two years because most customers either expand or churn by then.',
                                ],
                                'revenue_stream_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Special revenue streams or exclusions (internal accounts, credits, etc.).',
                                    'raw' => 'RevOps includes one-time setup and services revenue in LTV so dashboards show total commercial value.',
                                    'normalized' => 'revops includes one-time setup and services revenue in ltv so dashboards show total commercial value.',
                                ],
                                'discount_method_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on discount rates, timelines, or NPV setup.',
                                    'raw' => 'We do not discount; we just take current ARPU and multiply by 24 months.',
                                    'normalized' => 'we do not discount; we just take current arpu and multiply by 24 months.',
                                ],
                                'margin_basis_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Explain which cost components are included or excluded.',
                                    'raw' => 'We use billed revenue and do not adjust for margin or overhead.',
                                    'normalized' => 'we use billed revenue and do not adjust for margin or overhead.',
                                ],
                                'one_time_fee_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Note special handling for setup, implementation, or onboarding fees.',
                                    'raw' => 'Onboarding fees are counted in the first month; larger implementation projects are spread over the first year.',
                                    'normalized' => 'onboarding fees are counted in the first month; larger implementation projects are spread over the first year.',
                                ],
                                'ltv_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide an example LTV calculation.',
                                    'raw' => 'If an account pays $800 per month and stays for 24 months, we show LTV as $19,200 even if they churn earlier.',
                                    'normalized' => 'if an account pays $800 per month and stays for 24 months, we show ltv as $19,200 even if they churn earlier.',
                                ],
                                'ltv_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do LTV assumptions differ between teams?',
                                    'raw' => 'Finance’s discounted margin-based LTV is lower than what we show in revenue dashboards, which confuses executives.',
                                    'normalized' => 'finance’s discounted margin-based ltv is lower than what we show in revenue dashboards, which confuses executives.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We are open to aligning with a discounted approach as long as we can keep a simple ARPU × months heuristic in RevOps tools.',
                                    'normalized' => 'we are open to aligning with a discounted approach as long as we can keep a simple arpu × months heuristic in revops tools.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(4),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'ltv_formula',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'high',
                    'status' => 'Open',
                    'description' => 'Finance uses a discounted, margin-based LTV model while RevOps uses a simple revenue × lifespan approach, leading to conflicting LTV figures.',
                    'detected_at' => now()->subDays(2),
                ],
            ],
            [
                'key' => 'cac_cpl',
                'name' => 'CAC & CPL Measurement',
                'slug' => 'cac-cpl-measurement',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'CAC & CPL Measurement Form',
                'primary_field' => 'costs_included',
                'contributors' => [
                    [
                        'membership' => $financeMembership,
                        'participant' => $financeParticipant,
                        'tags' => ['Finance'],
                        'answers' => [
                            'team_department' => 'Finance',
                            'costs_included' => ['ad_spend', 'marketing_comp', 'sales_comp', 'overhead'],
                            'costs_included_notes' => 'We include all acquisition-related salaries, benefits, media, and shared RevOps overhead in CAC and CPL.',
                            'lead_definition' => 'marketing_qualified',
                            'lead_definition_notes' => 'Leads must meet our MQL scoring rules in the automation platform before entering the denominator.',
                            'customer_definition' => 'first_payment',
                            'customer_definition_notes' => 'We only count a new customer once the first invoice is successfully paid.',
                            'cost_timing' => 'cohort',
                            'cost_timing_notes' => 'We attribute spend to the cohort of customers that convert over the following 1–3 months.',
                            'deduplication_method' => 'unify_by_person',
                            'deduplication_notes' => 'We unify duplicate leads by CRM contact ID and email so one person appears once in CPL and CAC.',
                            'overhead_handling' => 'fully_loaded',
                            'overhead_handling_notes' => 'We allocate a share of G&A and RevOps salaries into the acquisition cost pool each quarter.',
                            'cac_example' => 'In Q1 we spent $120k on acquisition and added 60 new paying customers, so CAC is $2k per customer; $40k of that spend generated 800 MQLs, so CPL is $50.',
                            'cac_pain_points' => 'Marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their CAC and CPL much lower than Finance’s fully loaded view.',
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
                                    'raw' => 'We attribute spend to the cohort of customers that convert over the following 1–3 months.',
                                    'normalized' => 'we attribute spend to the cohort of customers that convert over the following 1–3 months.',
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
                                    'raw' => 'Marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their CAC and CPL much lower than Finance’s fully loaded view.',
                                    'normalized' => 'marketing excludes salaries and overhead and sometimes counts any form fill as a lead, which makes their cac and cpl much lower than finance’s fully loaded view.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'For board reporting we want CAC and CPL to reflect the fully loaded cost to acquire a paying customer, even if teams keep lighter internal views.',
                                    'normalized' => 'for board reporting we want cac and cpl to reflect the fully loaded cost to acquire a paying customer, even if teams keep lighter internal views.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(6),
                    ],
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
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
                            'cac_pain_points' => 'Finance’s fully loaded CAC is nearly double our campaign-only figure, and our broader lead definition makes CPL look lower than what Finance would report.',
                            'additional_notes' => 'We are open to labeling our numbers as “marketing CAC/CPL” as long as Finance owns the official board definition.',
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
                                    'raw' => 'Finance’s fully loaded CAC is nearly double our campaign-only figure, and our broader lead definition makes CPL look lower than what Finance would report.',
                                    'normalized' => 'finance’s fully loaded cac is nearly double our campaign-only figure, and our broader lead definition makes cpl look lower than what finance would report.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We are open to labeling our numbers as “marketing CAC/CPL” as long as Finance owns the official board definition.',
                                    'normalized' => 'we are open to labeling our numbers as “marketing cac/cpl” as long as finance owns the official board definition.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'costs_included',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'high',
                    'status' => 'Open',
                    'description' => 'Finance uses a fully loaded cost base and cohort timing while Marketing counts only direct campaign spend and same-month leads.',
                    'detected_at' => now()->subDays(3),
                ],
            ],
            [
                'key' => 'conversion-rate-definition',
                'name' => 'Conversion Rate Intake',
                'slug' => 'conversion-rate',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'Conversion Rate Definition Form',
                'primary_field' => 'conversion_denominator',
                'contributors' => [
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
                        'answers' => [
                            'team_department' => 'Marketing',
                            'conversion_actions' => ['form_completion', 'trial_activation'],
                            'conversion_actions_notes' => 'Our primary conversion is a net-new lead capture; trial activations are tracked as a secondary conversion metric.',
                            'conversion_denominator' => 'all_visitors',
                            'conversion_denominator_notes' => 'We look at sessions from paid and organic traffic combined; internal traffic and bots are filtered out in analytics.',
                            'conversion_timeframe' => 'within_30_days',
                            'conversion_timeframe_notes' => 'Most campaigns are evaluated on 30-day conversion from first touch; evergreen pages are also rolled into this view.',
                            'funnel_stage_tracked' => 'visitor_to_lead',
                            'funnel_stage_notes' => 'Our marketing funnel focuses on visitors → captured leads; downstream steps belong to Sales.',
                            'partial_conversion_tracking' => 'primary_only',
                            'partial_conversion_notes' => 'We only count the first qualifying conversion for a visitor in each campaign window.',
                            'attribution_model' => 'multi_touch',
                            'attribution_model_notes' => 'We use a multi-touch model in our attribution tool with a 90-day lookback for paid campaigns.',
                            'conversion_example' => 'If 10,000 sessions drive 500 net-new leads in 30 days, our visitor-to-lead conversion rate is 5%.',
                            'conversion_pain_points' => 'Product and Sales often reference trial-to-paid or opportunity close rates, which are much lower than our visitor-level conversion, causing confusion.',
                            'additional_notes' => 'For board slides we typically show visitor-to-lead plus trial activation rates separately.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'conversion_actions' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which actions count as a conversion?',
                                    'raw' => ['form_completion', 'trial_activation'],
                                    'normalized' => ['form_completion', 'trial_activation'],
                                ],
                                'conversion_denominator' => [
                                    'type' => 'select',
                                    'label' => 'Who or what forms the denominator for this rate?',
                                    'raw' => 'all_visitors',
                                    'normalized' => 'all_visitors',
                                ],
                                'conversion_timeframe' => [
                                    'type' => 'select',
                                    'label' => 'Over what time frame do you measure conversion?',
                                    'raw' => 'within_30_days',
                                    'normalized' => 'within_30_days',
                                ],
                                'funnel_stage_tracked' => [
                                    'type' => 'select',
                                    'label' => 'Which funnel step does this conversion represent?',
                                    'raw' => 'visitor_to_lead',
                                    'normalized' => 'visitor_to_lead',
                                ],
                                'partial_conversion_tracking' => [
                                    'type' => 'select',
                                    'label' => 'How are partial conversions or upgrades counted?',
                                    'raw' => 'primary_only',
                                    'normalized' => 'primary_only',
                                ],
                                'attribution_model' => [
                                    'type' => 'select',
                                    'label' => 'Which attribution model assigns conversions to sources?',
                                    'raw' => 'multi_touch',
                                    'normalized' => 'multi_touch',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Marketing',
                                    'normalized' => 'marketing',
                                ],
                                'conversion_actions_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or exceptions for the actions selected.',
                                    'raw' => 'Our primary conversion is a net-new lead capture; trial activations are tracked as a secondary conversion metric.',
                                    'normalized' => 'our primary conversion is a net-new lead capture; trial activations are tracked as a secondary conversion metric.',
                                ],
                                'conversion_denominator_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail filters, cohorts, or exclusions in the denominator.',
                                    'raw' => 'We look at sessions from paid and organic traffic combined; internal traffic and bots are filtered out in analytics.',
                                    'normalized' => 'we look at sessions from paid and organic traffic combined; internal traffic and bots are filtered out in analytics.',
                                ],
                                'conversion_timeframe_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Mention timezone handling, lag windows, or grace periods.',
                                    'raw' => 'Most campaigns are evaluated on 30-day conversion from first touch; evergreen pages are also rolled into this view.',
                                    'normalized' => 'most campaigns are evaluated on 30-day conversion from first touch; evergreen pages are also rolled into this view.',
                                ],
                                'funnel_stage_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Clarify segmentation, region splits, or multiple funnels.',
                                    'raw' => 'Our marketing funnel focuses on visitors → captured leads; downstream steps belong to Sales.',
                                    'normalized' => 'our marketing funnel focuses on visitors → captured leads; downstream steps belong to sales.',
                                ],
                                'partial_conversion_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Note thresholds or revenue minimums for counting upgrades.',
                                    'raw' => 'We only count the first qualifying conversion for a visitor in each campaign window.',
                                    'normalized' => 'we only count the first qualifying conversion for a visitor in each campaign window.',
                                ],
                                'attribution_model_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on the attribution weights, lookback windows, or tools.',
                                    'raw' => 'We use a multi-touch model in our attribution tool with a 90-day lookback for paid campaigns.',
                                    'normalized' => 'we use a multi-touch model in our attribution tool with a 90-day lookback for paid campaigns.',
                                ],
                                'conversion_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide a brief example conversion calculation.',
                                    'raw' => 'If 10,000 sessions drive 500 net-new leads in 30 days, our visitor-to-lead conversion rate is 5%.',
                                    'normalized' => 'if 10,000 sessions drive 500 net-new leads in 30 days, our visitor-to-lead conversion rate is 5%.',
                                ],
                                'conversion_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do conversion rate definitions differ across teams?',
                                    'raw' => 'Product and Sales often reference trial-to-paid or opportunity close rates, which are much lower than our visitor-level conversion, causing confusion.',
                                    'normalized' => 'product and sales often reference trial-to-paid or opportunity close rates, which are much lower than our visitor-level conversion, causing confusion.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'For board slides we typically show visitor-to-lead plus trial activation rates separately.',
                                    'normalized' => 'for board slides we typically show visitor-to-lead plus trial activation rates separately.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['Product'],
                        'answers' => [
                            'team_department' => 'Product',
                            'conversion_actions' => ['trial_activation', 'purchase_checkout'],
                            'conversion_actions_notes' => 'We track trial activations but our core conversion is trial-to-paid customers.',
                            'conversion_denominator' => 'trial_accounts',
                            'conversion_denominator_notes' => 'Only active trial or freemium accounts in the product count toward our denominator.',
                            'conversion_timeframe' => 'within_90_days',
                            'conversion_timeframe_notes' => 'We monitor conversion from trial start to paid within 90 days; late conversions are counted separately.',
                            'funnel_stage_tracked' => 'trial_to_paid',
                            'funnel_stage_notes' => 'Our funnel step is trial → paid subscription; we do not include pre-trial visitor behavior.',
                            'partial_conversion_tracking' => 'separate_events',
                            'partial_conversion_notes' => 'Upgrades and add-ons after the initial paid conversion are counted as separate product events, not conversion rate.',
                            'attribution_model' => 'no_model',
                            'attribution_model_notes' => 'We do not apply a formal marketing attribution model; everything is keyed off product events.',
                            'conversion_example' => 'If 1,000 trials start in a quarter and 200 convert to paid within 90 days, our trial-to-paid conversion rate is 20%.',
                            'conversion_pain_points' => 'Marketing cites visitor-to-lead conversion that looks healthy while our trial-to-paid rate is flat, so we disagree on whether conversion is improving.',
                            'additional_notes' => 'We are open to aligning on a shared conversion definition as long as we can keep our product lens for experimentation.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'conversion_actions' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which actions count as a conversion?',
                                    'raw' => ['trial_activation', 'purchase_checkout'],
                                    'normalized' => ['trial_activation', 'purchase_checkout'],
                                ],
                                'conversion_denominator' => [
                                    'type' => 'select',
                                    'label' => 'Who or what forms the denominator for this rate?',
                                    'raw' => 'trial_accounts',
                                    'normalized' => 'trial_accounts',
                                ],
                                'conversion_timeframe' => [
                                    'type' => 'select',
                                    'label' => 'Over what time frame do you measure conversion?',
                                    'raw' => 'within_90_days',
                                    'normalized' => 'within_90_days',
                                ],
                                'funnel_stage_tracked' => [
                                    'type' => 'select',
                                    'label' => 'Which funnel step does this conversion represent?',
                                    'raw' => 'trial_to_paid',
                                    'normalized' => 'trial_to_paid',
                                ],
                                'partial_conversion_tracking' => [
                                    'type' => 'select',
                                    'label' => 'How are partial conversions or upgrades counted?',
                                    'raw' => 'separate_events',
                                    'normalized' => 'separate_events',
                                ],
                                'attribution_model' => [
                                    'type' => 'select',
                                    'label' => 'Which attribution model assigns conversions to sources?',
                                    'raw' => 'no_model',
                                    'normalized' => 'no_model',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Product',
                                    'normalized' => 'product',
                                ],
                                'conversion_actions_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or exceptions for the actions selected.',
                                    'raw' => 'We track trial activations but our core conversion is trial-to-paid customers.',
                                    'normalized' => 'we track trial activations but our core conversion is trial-to-paid customers.',
                                ],
                                'conversion_denominator_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Detail filters, cohorts, or exclusions in the denominator.',
                                    'raw' => 'Only active trial or freemium accounts in the product count toward our denominator.',
                                    'normalized' => 'only active trial or freemium accounts in the product count toward our denominator.',
                                ],
                                'conversion_timeframe_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Mention timezone handling, lag windows, or grace periods.',
                                    'raw' => 'We monitor conversion from trial start to paid within 90 days; late conversions are counted separately.',
                                    'normalized' => 'we monitor conversion from trial start to paid within 90 days; late conversions are counted separately.',
                                ],
                                'funnel_stage_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Clarify segmentation, region splits, or multiple funnels.',
                                    'raw' => 'Our funnel step is trial → paid subscription; we do not include pre-trial visitor behavior.',
                                    'normalized' => 'our funnel step is trial → paid subscription; we do not include pre-trial visitor behavior.',
                                ],
                                'partial_conversion_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Note thresholds or revenue minimums for counting upgrades.',
                                    'raw' => 'Upgrades and add-ons after the initial paid conversion are counted as separate product events, not conversion rate.',
                                    'normalized' => 'upgrades and add-ons after the initial paid conversion are counted as separate product events, not conversion rate.',
                                ],
                                'attribution_model_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on the attribution weights, lookback windows, or tools.',
                                    'raw' => 'We do not apply a formal marketing attribution model; everything is keyed off product events.',
                                    'normalized' => 'we do not apply a formal marketing attribution model; everything is keyed off product events.',
                                ],
                                'conversion_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide a brief example conversion calculation.',
                                    'raw' => 'If 1,000 trials start in a quarter and 200 convert to paid within 90 days, our trial-to-paid conversion rate is 20%.',
                                    'normalized' => 'if 1,000 trials start in a quarter and 200 convert to paid within 90 days, our trial-to-paid conversion rate is 20%.',
                                ],
                                'conversion_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do conversion rate definitions differ across teams?',
                                    'raw' => 'Marketing cites visitor-to-lead conversion that looks healthy while our trial-to-paid rate is flat, so we disagree on whether conversion is improving.',
                                    'normalized' => 'marketing cites visitor-to-lead conversion that looks healthy while our trial-to-paid rate is flat, so we disagree on whether conversion is improving.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We are open to aligning on a shared conversion definition as long as we can keep our product lens for experimentation.',
                                    'normalized' => 'we are open to aligning on a shared conversion definition as long as we can keep our product lens for experimentation.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(4),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'conversion_denominator',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Marketing measures visitor-to-lead conversion on all visitors while Product focuses on trial-to-paid conversion on trial accounts, leading to very different conversion rates.',
                    'detected_at' => now()->subDays(2),
                ],
            ],
            [
                'key' => 'lead_quality',
                'name' => 'Leads & Lead Quality',
                'slug' => 'leads-lead-quality',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'Leads & Lead Quality Measurement Form',
                'primary_field' => 'lead_definition',
                'contributors' => [
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
                        'answers' => [
                            'team_department' => 'Marketing',
                            'lead_definition' => 'any_contact',
                            'lead_definition_notes' => 'Any net-new form fill, signup, or event scan is treated as a lead, even if they are not yet qualified.',
                            'lead_stages_tracked' => ['mql', 'sql', 'opportunity'],
                            'lead_stages_notes' => 'We use MQL when a contact hits our score threshold; SQL is when Sales accepts the handoff; Opportunities are accepted deals in the CRM.',
                            'lead_reporting_frequency' => 'monthly',
                            'lead_reporting_notes' => 'Marketing dashboards and campaign reviews use calendar-month lead volume as the main reporting window.',
                            'lead_deduplication' => 'count_all_touches',
                            'lead_deduplication_notes' => 'If the same person fills three different forms, we treat that as three leads so each campaign shows its own performance.',
                            'lead_quality_model' => 'numeric_score',
                            'lead_quality_notes' => 'We assign 0–100 scores based on ICP fit (title, company size, industry) plus engagement (emails, events, product usage).',
                            'lead_special_cases' => 'Free trials and product signups count as leads once they are net-new people in the automation tool; existing customer expansions are tagged separately.',
                            'lead_example' => 'Someone downloads a whitepaper (lead), hits score 70 after visiting pricing (MQL), and becomes an SQL once Sales schedules a discovery call.',
                            'lead_pain_points' => 'Sales sometimes ignores high-scoring leads from smaller accounts, while Marketing still counts them as “good” leads in our reports.',
                            'additional_notes' => 'We are open to tightening the lead definition if Sales wants fewer, higher-intent leads.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'lead_definition' => [
                                    'type' => 'select',
                                    'label' => 'What do you count as a lead?',
                                    'raw' => 'any_contact',
                                    'normalized' => 'any_contact',
                                ],
                                'lead_stages_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which lead stages do you track?',
                                    'raw' => ['mql', 'sql', 'opportunity'],
                                    'normalized' => ['mql', 'sql', 'opportunity'],
                                ],
                                'lead_reporting_frequency' => [
                                    'type' => 'select',
                                    'label' => 'How often do you report on leads?',
                                    'raw' => 'monthly',
                                    'normalized' => 'monthly',
                                ],
                                'lead_deduplication' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate leads across touchpoints?',
                                    'raw' => 'count_all_touches',
                                    'normalized' => 'count_all_touches',
                                ],
                                'lead_quality_model' => [
                                    'type' => 'select',
                                    'label' => 'How do you assess lead quality or scoring?',
                                    'raw' => 'numeric_score',
                                    'normalized' => 'numeric_score',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Marketing',
                                    'normalized' => 'marketing',
                                ],
                                'lead_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or qualifiers to your lead definition.',
                                    'raw' => 'Any net-new form fill, signup, or event scan is treated as a lead, even if they are not yet qualified.',
                                    'normalized' => 'any net-new form fill, signup, or event scan is treated as a lead, even if they are not yet qualified.',
                                ],
                                'lead_stages_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Clarify how stages map to your systems or processes.',
                                    'raw' => 'We use MQL when a contact hits our score threshold; SQL is when Sales accepts the handoff; Opportunities are accepted deals in the CRM.',
                                    'normalized' => 'we use mql when a contact hits our score threshold; sql is when sales accepts the handoff; opportunities are accepted deals in the crm.',
                                ],
                                'lead_reporting_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe cutoffs, timezone handling, or offsets.',
                                    'raw' => 'Marketing dashboards and campaign reviews use calendar-month lead volume as the main reporting window.',
                                    'normalized' => 'marketing dashboards and campaign reviews use calendar-month lead volume as the main reporting window.',
                                ],
                                'lead_deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on matching logic or IDs used for deduplication.',
                                    'raw' => 'If the same person fills three different forms, we treat that as three leads so each campaign shows its own performance.',
                                    'normalized' => 'if the same person fills three different forms, we treat that as three leads so each campaign shows its own performance.',
                                ],
                                'lead_quality_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe key signals, events, or thresholds in your model.',
                                    'raw' => 'We assign 0–100 scores based on ICP fit (title, company size, industry) plus engagement (emails, events, product usage).',
                                    'normalized' => 'we assign 0–100 scores based on icp fit (title, company size, industry) plus engagement (emails, events, product usage).',
                                ],
                                'lead_special_cases' => [
                                    'type' => 'textarea',
                                    'label' => 'Special cases (free trials, SMB vs enterprise, events) that change lead handling?',
                                    'raw' => 'Free trials and product signups count as leads once they are net-new people in the automation tool; existing customer expansions are tagged separately.',
                                    'normalized' => 'free trials and product signups count as leads once they are net-new people in the automation tool; existing customer expansions are tagged separately.',
                                ],
                                'lead_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Example of how a lead progresses through stages.',
                                    'raw' => 'Someone downloads a whitepaper (lead), hits score 70 after visiting pricing (MQL), and becomes an SQL once Sales schedules a discovery call.',
                                    'normalized' => 'someone downloads a whitepaper (lead), hits score 70 after visiting pricing (mql), and becomes an sql once sales schedules a discovery call.',
                                ],
                                'lead_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do definitions or quality thresholds differ?',
                                    'raw' => 'Sales sometimes ignores high-scoring leads from smaller accounts, while Marketing still counts them as “good” leads in our reports.',
                                    'normalized' => 'sales sometimes ignores high-scoring leads from smaller accounts, while marketing still counts them as “good” leads in our reports.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We are open to tightening the lead definition if Sales wants fewer, higher-intent leads.',
                                    'normalized' => 'we are open to tightening the lead definition if sales wants fewer, higher-intent leads.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(6),
                    ],
                    [
                        'membership' => $salesMembership ?? $revOpsMembership,
                        'participant' => $salesParticipant ?? $revOpsParticipant,
                        'tags' => ['Sales'],
                        'answers' => [
                            'team_department' => 'Sales',
                            'lead_definition' => 'sales_qualified',
                            'lead_definition_notes' => 'We only consider leads once they are accepted as SQLs with a real opportunity to close within the next few quarters.',
                            'lead_stages_tracked' => ['sql', 'opportunity'],
                            'lead_stages_notes' => 'Sales focuses on SQL and Opportunity stages; MQLs live in Marketing\'s queue until we accept them.',
                            'lead_reporting_frequency' => 'quarterly',
                            'lead_reporting_notes' => 'Our pipeline and lead quality reviews are tied to quarterly quota cycles and board updates.',
                            'lead_deduplication' => 'unify_by_person',
                            'lead_deduplication_notes' => 'Multiple touches from the same decision-maker are rolled into a single lead so we do not double count pipeline.',
                            'lead_quality_model' => 'tiered_rating',
                            'lead_quality_notes' => 'Reps apply an A/B/C rating based on deal size, authority, need, and timing; A-level SQLs map to our top priority pipeline.',
                            'lead_special_cases' => 'Existing customer expansions and renewals are treated as separate opportunities, not leads, even if Marketing touches them.',
                            'lead_example' => 'A webinar attendee is not considered a lead until they book a discovery call and we log an Opportunity linked to that account.',
                            'lead_pain_points' => 'Marketing celebrates large lead volume increases while our SQL and Opportunity counts barely move, making it hard to align on quality.',
                            'additional_notes' => 'We want fewer, better-qualified leads so SDRs can focus on the highest-intent accounts.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'lead_definition' => [
                                    'type' => 'select',
                                    'label' => 'What do you count as a lead?',
                                    'raw' => 'sales_qualified',
                                    'normalized' => 'sales_qualified',
                                ],
                                'lead_stages_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which lead stages do you track?',
                                    'raw' => ['sql', 'opportunity'],
                                    'normalized' => ['sql', 'opportunity'],
                                ],
                                'lead_reporting_frequency' => [
                                    'type' => 'select',
                                    'label' => 'How often do you report on leads?',
                                    'raw' => 'quarterly',
                                    'normalized' => 'quarterly',
                                ],
                                'lead_deduplication' => [
                                    'type' => 'select',
                                    'label' => 'How do you deduplicate leads across touchpoints?',
                                    'raw' => 'unify_by_person',
                                    'normalized' => 'unify_by_person',
                                ],
                                'lead_quality_model' => [
                                    'type' => 'select',
                                    'label' => 'How do you assess lead quality or scoring?',
                                    'raw' => 'tiered_rating',
                                    'normalized' => 'tiered_rating',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Sales',
                                    'normalized' => 'sales',
                                ],
                                'lead_definition_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or qualifiers to your lead definition.',
                                    'raw' => 'We only consider leads once they are accepted as SQLs with a real opportunity to close within the next few quarters.',
                                    'normalized' => 'we only consider leads once they are accepted as sqls with a real opportunity to close within the next few quarters.',
                                ],
                                'lead_stages_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Clarify how stages map to your systems or processes.',
                                    'raw' => 'Sales focuses on SQL and Opportunity stages; MQLs live in Marketing\'s queue until we accept them.',
                                    'normalized' => 'sales focuses on sql and opportunity stages; mqls live in marketing\'s queue until we accept them.',
                                ],
                                'lead_reporting_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe cutoffs, timezone handling, or offsets.',
                                    'raw' => 'Our pipeline and lead quality reviews are tied to quarterly quota cycles and board updates.',
                                    'normalized' => 'our pipeline and lead quality reviews are tied to quarterly quota cycles and board updates.',
                                ],
                                'lead_deduplication_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add detail on matching logic or IDs used for deduplication.',
                                    'raw' => 'Multiple touches from the same decision-maker are rolled into a single lead so we do not double count pipeline.',
                                    'normalized' => 'multiple touches from the same decision-maker are rolled into a single lead so we do not double count pipeline.',
                                ],
                                'lead_quality_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe key signals, events, or thresholds in your model.',
                                    'raw' => 'Reps apply an A/B/C rating based on deal size, authority, need, and timing; A-level SQLs map to our top priority pipeline.',
                                    'normalized' => 'reps apply an a/b/c rating based on deal size, authority, need, and timing; a-level sqls map to our top priority pipeline.',
                                ],
                                'lead_special_cases' => [
                                    'type' => 'textarea',
                                    'label' => 'Special cases (free trials, SMB vs enterprise, events) that change lead handling?',
                                    'raw' => 'Existing customer expansions and renewals are treated as separate opportunities, not leads, even if Marketing touches them.',
                                    'normalized' => 'existing customer expansions and renewals are treated as separate opportunities, not leads, even if marketing touches them.',
                                ],
                                'lead_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Example of how a lead progresses through stages.',
                                    'raw' => 'A webinar attendee is not considered a lead until they book a discovery call and we log an Opportunity linked to that account.',
                                    'normalized' => 'a webinar attendee is not considered a lead until they book a discovery call and we log an opportunity linked to that account.',
                                ],
                                'lead_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do definitions or quality thresholds differ?',
                                    'raw' => 'Marketing celebrates large lead volume increases while our sql and opportunity counts barely move, making it hard to align on quality.',
                                    'normalized' => 'marketing celebrates large lead volume increases while our sql and opportunity counts barely move, making it hard to align on quality.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We want fewer, better-qualified leads so sdrs can focus on the highest-intent accounts.',
                                    'normalized' => 'we want fewer, better-qualified leads so sdrs can focus on the highest-intent accounts.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'lead_definition',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Marketing counts any net-new contact as a lead while Sales only considers accepted SQLs as leads.',
                    'detected_at' => now()->subDays(2),
                ],
            ],
            [
                'key' => 'churn',
                'name' => 'Gross Churn Rate',
                'slug' => 'gross-churn',
                'status' => MetricStatus::IN_WORKSHOP,
                'form_title' => 'Gross Churn Audit',
                'primary_field' => 'churn_timing',
                'contributors' => [
                    [
                        'membership' => $financeMembership,
                        'participant' => $financeParticipant,
                        'tags' => ['Finance'],
                        'answers' => [
                            'team_department' => 'Finance',
                            'churn_types_tracked' => ['revenue', 'logo'],
                            'churn_types_notes' => 'Revenue churn is the official KPI; logos are tracked for comparison when teams discuss account movements.',
                            'churn_timeframe' => 'monthly',
                            'churn_timeframe_notes' => 'We calculate churn monthly, referencing the first day of each month\'s MRR.',
                            'downgrade_handling' => 'count_as_churn',
                            'downgrade_handling_notes' => 'Any lost recurring revenue is counted as churn—if a customer downgrades from $100 to $50, that $50 is churn for that month.',
                            'voluntary_treatment' => 'combined',
                            'voluntary_treatment_notes' => 'We lump voluntary and involuntary churn together; if a payment fails and is not resolved within the same month, it stays in churn.',
                            'churn_timing' => 'end_of_cycle',
                            'churn_timing_notes' => 'We only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                            'special_case_notes' => 'Freemium or trial users are never counted in our paying base, and we do not have an official pause option, so those states do not impact churn.',
                            'churn_example' => 'If we start the month with $100k MRR and lose $5k to cancellations or downgrades, that is 5% churn ($5k ÷ $100k).',
                            'churn_pain_points' => 'Product’s daily churn approach can create different numbers because it posts cancellations before the billing cycle ends, while Finance waits for end-of-cycle recognition.',
                            'additional_notes' => 'We want one consistent revenue churn metric for board reporting.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'churn_types_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which churn scopes do you track? (select canonical values)',
                                    'raw' => ['revenue', 'logo'],
                                    'normalized' => ['revenue', 'logo'],
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
                                'churn_types_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add context for churn scope selections (segments, filters, exclusions).',
                                    'raw' => 'Revenue churn is the official KPI; logos are tracked for comparison when teams discuss account movements.',
                                    'normalized' => 'revenue churn is the official kpi; logos are tracked for comparison when teams discuss account movements.',
                                ],
                                'churn_timeframe_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add detail for the churn reporting window (snapshot timing, offsets).',
                                    'raw' => 'We calculate churn monthly, referencing the first day of each month\'s MRR.',
                                    'normalized' => 'we calculate churn monthly, referencing the first day of each month\'s mrr.',
                                ],
                                'downgrade_handling_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add specifics or guardrails for downgrade handling.',
                                    'raw' => 'Any lost recurring revenue is counted as churn—if a customer downgrades from $100 to $50, that $50 is churn for that month.',
                                    'normalized' => 'any lost recurring revenue is counted as churn—if a customer downgrades from $100 to $50, that $50 is churn for that month.',
                                ],
                                'voluntary_treatment_notes' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you segment voluntary/involuntary churn.',
                                    'raw' => 'We lump voluntary and involuntary churn together; if a payment fails and is not resolved within the same month, it stays in churn.',
                                    'normalized' => 'we lump voluntary and involuntary churn together; if a payment fails and is not resolved within the same month, it stays in churn.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'text',
                                    'label' => 'Explain timing policies or exceptions.',
                                    'raw' => 'We only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                                    'normalized' => 'we only recognize churn at the end of the billing period when the revenue recognition cycle completes.',
                                ],
                                'special_case_notes' => [
                                    'type' => 'text',
                                    'label' => 'Special cases (trials, pauses, refunds) that affect churn.',
                                    'raw' => 'Freemium or trial users are never counted in our paying base, and we do not have an official pause option, so those states do not impact churn.',
                                    'normalized' => 'freemium or trial users are never counted in our paying base, and we do not have an official pause option, so those states do not impact churn.',
                                ],
                                'churn_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example churn calculation.',
                                    'raw' => 'If we start the month with $100k MRR and lose $5k to cancellations or downgrades, that is 5% churn ($5k ÷ $100k).',
                                    'normalized' => 'if we start the month with $100k mrr and lose $5k to cancellations or downgrades, that is 5% churn ($5k ÷ $100k).',
                                ],
                                'churn_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do churn definitions or numbers differ across teams?',
                                    'raw' => 'Product’s daily churn approach can create different numbers because it posts cancellations before the billing cycle ends, while Finance waits for end-of-cycle recognition.',
                                    'normalized' => 'product’s daily churn approach can create different numbers because it posts cancellations before the billing cycle ends, while finance waits for end-of-cycle recognition.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional notes or disclaimers.',
                                    'raw' => 'We want one consistent revenue churn metric for board reporting.',
                                    'normalized' => 'we want one consistent revenue churn metric for board reporting.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(8),
                    ],
                    [
                        'membership' => $customerSuccessMembership,
                        'participant' => $customerSuccessParticipant,
                        'tags' => ['Customer Success'],
                        'answers' => [
                            'team_department' => 'Customer Success',
                            'churn_types_tracked' => ['logo'],
                            'churn_types_notes' => 'Customer Success only tracks logo churn; Finance owns the revenue lens.',
                            'churn_timeframe' => 'monthly',
                            'churn_timeframe_notes' => 'We review churn monthly for operational metrics and also conduct quarterly deep dives.',
                            'downgrade_handling' => 'exclude_downgrades',
                            'downgrade_handling_notes' => 'We do not count a downgrade as churn; only a complete cancellation counts, though we flag downgrades as at-risk.',
                            'voluntary_treatment' => 'separate',
                            'voluntary_treatment_notes' => 'We track involuntary churn if the customer never returns; if they fix billing within a grace period, we do not mark it as churn.',
                            'churn_timing' => 'end_of_cycle',
                            'churn_timing_notes' => 'We typically mark churn at the end of the current billing cycle if they give notice, or 30 days after a failed invoice if they stop paying.',
                            'special_case_notes' => 'Trials that never convert are not considered churn, paused accounts are tracked separately until they resume, and large enterprise customers often have 60-day notice periods before we record churn.',
                            'churn_example' => 'Start with 300 paying customers on July 1; if 10 fully cancel in July, that is 3.33% churn, and we do not add downgrades to that number.',
                            'churn_pain_points' => 'Finance counts downgrades as churn and Product removes users immediately, while we prefer an end-of-billing-cycle view.',
                            'additional_notes' => 'We need a single authoritative definition so we can accurately track improvements in retention.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'churn_types_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which churn scopes do you track? (select canonical values)',
                                    'raw' => ['logo'],
                                    'normalized' => ['logo'],
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
                                    'raw' => 'exclude_downgrades',
                                    'normalized' => 'exclude_downgrades',
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
                                    'raw' => 'end_of_cycle',
                                    'normalized' => 'end_of_cycle',
                                ],
                            ],
                            'meta' => [
                                'churn_types_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add context for churn scope selections (segments, filters, exclusions).',
                                    'raw' => 'Customer Success only tracks logo churn; Finance owns the revenue lens.',
                                    'normalized' => 'customer success only tracks logo churn; finance owns the revenue lens.',
                                ],
                                'churn_timeframe_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add detail for the churn reporting window (snapshot timing, offsets).',
                                    'raw' => 'We review churn monthly for operational metrics and also conduct quarterly deep dives.',
                                    'normalized' => 'we review churn monthly for operational metrics and also conduct quarterly deep dives.',
                                ],
                                'downgrade_handling_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add specifics or guardrails for downgrade handling.',
                                    'raw' => 'We do not count a downgrade as churn; only a complete cancellation counts, though we flag downgrades as at-risk.',
                                    'normalized' => 'we do not count a downgrade as churn; only a complete cancellation counts, though we flag downgrades as at-risk.',
                                ],
                                'voluntary_treatment_notes' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you segment voluntary/involuntary churn.',
                                    'raw' => 'We track involuntary churn if the customer never returns; if they fix billing within a grace period, we do not mark it as churn.',
                                    'normalized' => 'we track involuntary churn if the customer never returns; if they fix billing within a grace period, we do not mark it as churn.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'text',
                                    'label' => 'Explain timing policies or exceptions.',
                                    'raw' => 'We typically mark churn at the end of the current billing cycle if they give notice, or 30 days after a failed invoice if they stop paying.',
                                    'normalized' => 'we typically mark churn at the end of the current billing cycle if they give notice, or 30 days after a failed invoice if they stop paying.',
                                ],
                                'special_case_notes' => [
                                    'type' => 'text',
                                    'label' => 'Special cases (trials, pauses, refunds) that affect churn.',
                                    'raw' => 'Trials that never convert are not considered churn, paused accounts are tracked separately until they resume, and large enterprise customers often have 60-day notice periods before we record churn.',
                                    'normalized' => 'trials that never convert are not considered churn, paused accounts are tracked separately until they resume, and large enterprise customers often have 60-day notice periods before we record churn.',
                                ],
                                'churn_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example churn calculation.',
                                    'raw' => 'Start with 300 paying customers on July 1; if 10 fully cancel in July, that is 3.33% churn, and we do not add downgrades to that number.',
                                    'normalized' => 'start with 300 paying customers on july 1; if 10 fully cancel in july, that is 3.33% churn, and we do not add downgrades to that number.',
                                ],
                                'churn_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do churn definitions or numbers differ across teams?',
                                    'raw' => 'Finance counts downgrades as churn and Product removes users immediately, while we prefer an end-of-billing-cycle view.',
                                    'normalized' => 'finance counts downgrades as churn and product removes users immediately, while we prefer an end-of-billing-cycle view.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional notes or disclaimers.',
                                    'raw' => 'We need a single authoritative definition so we can accurately track improvements in retention.',
                                    'normalized' => 'we need a single authoritative definition so we can accurately track improvements in retention.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(7),
                    ],
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['Product'],
                        'answers' => [
                            'team_department' => 'Product',
                            'churn_types_tracked' => ['logo'],
                            'churn_types_notes' => 'Product focuses on daily logo churn because it maps to in-product engagement dashboards.',
                            'churn_timeframe' => 'monthly',
                            'churn_timeframe_notes' => 'We look at monthly churn for official reporting, but our internal dashboard updates daily.',
                            'downgrade_handling' => 'track_as_contraction',
                            'downgrade_handling_notes' => 'Full cancellation equals churn, and downgrades are tracked as contraction MRR rather than churn.',
                            'voluntary_treatment' => 'separate',
                            'voluntary_treatment_notes' => 'We track involuntary churn separately (e.g., credit card failures) and remove those entries if the customer updates payment info within the grace period.',
                            'churn_timing' => 'immediate',
                            'churn_timing_notes' => 'Once a user clicks cancel, the subscription status updates in CRM and reports immediately in the analytics view.',
                            'special_case_notes' => 'We have a freemium plan that can convert, but freemium users who never pay are not counted as churn, and paused accounts are treated as a separate state until they resume.',
                            'churn_example' => 'Start with 500 paying customers; if 10 fully cancel by month-end, that is 2% logo churn, and downgrades appear in a separate contraction column.',
                            'churn_pain_points' => 'Finance calls downgrades churn while we label them contraction, and timing differences (immediate removal versus end of cycle) create mismatched totals.',
                            'additional_notes' => 'We want to preserve the daily usage metrics, but we can adapt final monthly figures if that’s the group decision.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'churn_types_tracked' => [
                                    'type' => 'checkbox_group',
                                    'label' => 'Which churn scopes do you track? (select canonical values)',
                                    'raw' => ['logo'],
                                    'normalized' => ['logo'],
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
                                'churn_types_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add context for churn scope selections (segments, filters, exclusions).',
                                    'raw' => 'Product focuses on daily logo churn because it maps to in-product engagement dashboards.',
                                    'normalized' => 'product focuses on daily logo churn because it maps to in-product engagement dashboards.',
                                ],
                                'churn_timeframe_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add detail for the churn reporting window (snapshot timing, offsets).',
                                    'raw' => 'We look at monthly churn for official reporting, but our internal dashboard updates daily.',
                                    'normalized' => 'we look at monthly churn for official reporting, but our internal dashboard updates daily.',
                                ],
                                'downgrade_handling_notes' => [
                                    'type' => 'text',
                                    'label' => 'Add specifics or guardrails for downgrade handling.',
                                    'raw' => 'Full cancellation equals churn, and downgrades are tracked as contraction MRR rather than churn.',
                                    'normalized' => 'full cancellation equals churn, and downgrades are tracked as contraction mrr rather than churn.',
                                ],
                                'voluntary_treatment_notes' => [
                                    'type' => 'text',
                                    'label' => 'Describe how you segment voluntary/involuntary churn.',
                                    'raw' => 'We track involuntary churn separately (e.g., credit card failures) and remove those entries if the customer updates payment info within the grace period.',
                                    'normalized' => 'we track involuntary churn separately (e.g., credit card failures) and remove those entries if the customer updates payment info within the grace period.',
                                ],
                                'churn_timing_notes' => [
                                    'type' => 'text',
                                    'label' => 'Explain timing policies or exceptions.',
                                    'raw' => 'Once a user clicks cancel, the subscription status updates in CRM and reports immediately in the analytics view.',
                                    'normalized' => 'once a user clicks cancel, the subscription status updates in crm and reports immediately in the analytics view.',
                                ],
                                'special_case_notes' => [
                                    'type' => 'text',
                                    'label' => 'Special cases (trials, pauses, refunds) that affect churn.',
                                    'raw' => 'We have a freemium plan that can convert, but freemium users who never pay are not counted as churn, and paused accounts are treated as a separate state until they resume.',
                                    'normalized' => 'we have a freemium plan that can convert, but freemium users who never pay are not counted as churn, and paused accounts are treated as a separate state until they resume.',
                                ],
                                'churn_example' => [
                                    'type' => 'text',
                                    'label' => 'Provide an example churn calculation.',
                                    'raw' => 'Start with 500 paying customers; if 10 fully cancel by month-end, that is 2% logo churn, and downgrades appear in a separate contraction column.',
                                    'normalized' => 'start with 500 paying customers; if 10 fully cancel by month-end, that is 2% logo churn, and downgrades appear in a separate contraction column.',
                                ],
                                'churn_pain_points' => [
                                    'type' => 'text',
                                    'label' => 'Where do churn definitions or numbers differ across teams?',
                                    'raw' => 'Finance calls downgrades churn while we label them contraction, and timing differences (immediate removal versus end of cycle) create mismatched totals.',
                                    'normalized' => 'finance calls downgrades churn while we label them contraction, and timing differences (immediate removal versus end of cycle) create mismatched totals.',
                                ],
                                'additional_notes' => [
                                    'type' => 'text',
                                    'label' => 'Additional notes or disclaimers.',
                                    'raw' => 'We want to preserve the daily usage metrics, but we can adapt final monthly figures if that’s the group decision.',
                                    'normalized' => 'we want to preserve the daily usage metrics, but we can adapt final monthly figures if that’s the group decision.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(6),
                    ],
                ],
                'discrepancy' => [
                    'field_key' => 'churn_timing',
                    'rule_type' => 'text_diff',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Teams disagree on when to mark churn (immediate vs. end of billing cycle).',
                    'detected_at' => now()->subDay(),
                ],
            ],
            [
                'key' => 'nps',
                'name' => 'Net Promoter Score (NPS)',
                'slug' => 'net-promoter-score',
                'status' => MetricStatus::IN_REVIEW,
                'form_title' => 'NPS (Net Promoter Score) Measurement Form',
                'primary_field' => 'survey_cadence',
                'contributors' => [
                    [
                        'membership' => $customerSuccessMembership,
                        'participant' => $customerSuccessParticipant,
                        'tags' => ['Customer Success'],
                        'answers' => [
                            'team_department' => 'Customer Success',
                            'survey_cadence' => 'quarterly_all_customers',
                            'survey_timing_notes' => 'We send NPS 90 days after go-live and then every quarter aligned to renewal month.',
                            'survey_audience_segment' => 'paying_customers_only',
                            'survey_audience_notes' => 'We email the main admin and champion contacts on each paying account; trials and free tiers are excluded.',
                            'passive_handling' => 'follow_up_passives',
                            'passive_handling_notes' => 'CSMs queue passives for outreach in the next QBR and review comments to uncover at-risk accounts.',
                            'response_volume_band' => '200_plus',
                            'sample_representativeness' => 'very_representative',
                            'sample_notes' => 'Most segments respond at similar rates; only very small accounts are under-represented.',
                            'bias_mitigation' => 'We avoid sending NPS immediately after major incidents and rotate which contacts receive the survey each quarter.',
                            'benchmarking' => 'We trend NPS quarterly and compare to a B2B SaaS benchmark from our survey vendor.',
                            'nps_example' => 'Last quarter we had 120 promoters, 50 passives, and 30 detractors out of 200 responses, for an NPS of 45.',
                            'nps_pain_points' => 'Marketing and RevOps run separate NPS-like surveys with different cadences, so leadership is unsure which score is “official.”',
                            'additional_notes' => 'We would like this relationship NPS to be the primary number for customer health.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'survey_cadence' => [
                                    'type' => 'select',
                                    'label' => 'How often do you send the NPS survey?',
                                    'raw' => 'quarterly_all_customers',
                                    'normalized' => 'quarterly_all_customers',
                                ],
                                'survey_audience_segment' => [
                                    'type' => 'select',
                                    'label' => 'Which customers or users do you primarily survey?',
                                    'raw' => 'paying_customers_only',
                                    'normalized' => 'paying_customers_only',
                                ],
                                'passive_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle passives (scores 7–8)?',
                                    'raw' => 'follow_up_passives',
                                    'normalized' => 'follow_up_passives',
                                ],
                                'response_volume_band' => [
                                    'type' => 'select',
                                    'label' => 'In a typical period, how many NPS responses do you collect?',
                                    'raw' => '200_plus',
                                    'normalized' => '200_plus',
                                ],
                                'sample_representativeness' => [
                                    'type' => 'select',
                                    'label' => 'Is your NPS sample representative of your overall user base?',
                                    'raw' => 'very_representative',
                                    'normalized' => 'very_representative',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Customer Success',
                                    'normalized' => 'customer success',
                                ],
                                'survey_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'When in the lifecycle do you send NPS (e.g., after onboarding, before renewal)?',
                                    'raw' => 'We send NPS 90 days after go-live and then every quarter aligned to renewal month.',
                                    'normalized' => 'we send nps 90 days after go-live and then every quarter aligned to renewal month.',
                                ],
                                'survey_audience_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe key segments, channels, or exclusions for your NPS survey.',
                                    'raw' => 'We email the main admin and champion contacts on each paying account; trials and free tiers are excluded.',
                                    'normalized' => 'we email the main admin and champion contacts on each paying account; trials and free tiers are excluded.',
                                ],
                                'passive_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or workflows for how you treat passives.',
                                    'raw' => 'CSMs queue passives for outreach in the next QBR and review comments to uncover at-risk accounts.',
                                    'normalized' => 'csms queue passives for outreach in the next qbr and review comments to uncover at-risk accounts.',
                                ],
                                'sample_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add notes on sample representativeness or key segment skews.',
                                    'raw' => 'Most segments respond at similar rates; only very small accounts are under-represented.',
                                    'normalized' => 'most segments respond at similar rates; only very small accounts are under-represented.',
                                ],
                                'bias_mitigation' => [
                                    'type' => 'textarea',
                                    'label' => 'How do you reduce response bias (if at all)?',
                                    'raw' => 'We avoid sending NPS immediately after major incidents and rotate which contacts receive the survey each quarter.',
                                    'normalized' => 'we avoid sending nps immediately after major incidents and rotate which contacts receive the survey each quarter.',
                                ],
                                'benchmarking' => [
                                    'type' => 'textarea',
                                    'label' => 'Do you benchmark or trend NPS over time?',
                                    'raw' => 'We trend NPS quarterly and compare to a B2B SaaS benchmark from our survey vendor.',
                                    'normalized' => 'we trend nps quarterly and compare to a b2b saas benchmark from our survey vendor.',
                                ],
                                'nps_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide an example NPS result (promoters/passives/detractors).',
                                    'raw' => 'Last quarter we had 120 promoters, 50 passives, and 30 detractors out of 200 responses, for an NPS of 45.',
                                    'normalized' => 'last quarter we had 120 promoters, 50 passives, and 30 detractors out of 200 responses, for an nps of 45.',
                                ],
                                'nps_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do NPS definitions or survey methods differ today?',
                                    'raw' => 'Marketing and RevOps run separate NPS-like surveys with different cadences, so leadership is unsure which score is “official.”',
                                    'normalized' => 'marketing and revops run separate nps-like surveys with different cadences, so leadership is unsure which score is “official.”',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We would like this relationship NPS to be the primary number for customer health.',
                                    'normalized' => 'we would like this relationship nps to be the primary number for customer health.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(5),
                    ],
                    [
                        'membership' => $marketingMembership,
                        'participant' => $marketingParticipant,
                        'tags' => ['Marketing'],
                        'answers' => [
                            'team_department' => 'Marketing',
                            'survey_cadence' => 'ongoing_triggered',
                            'survey_timing_notes' => 'We run NPS as an in-app prompt after users hit certain usage milestones rather than on a fixed calendar.',
                            'survey_audience_segment' => 'active_product_users',
                            'survey_audience_notes' => 'Any active in-product user can see the survey, including free and trial accounts.',
                            'passive_handling' => 'label_passives_only',
                            'passive_handling_notes' => 'We tag passives in the analytics tool but do not yet have a structured follow-up playbook.',
                            'response_volume_band' => '200_plus',
                            'sample_representativeness' => 'somewhat_representative',
                            'sample_notes' => 'The sample is skewed toward highly active users; quiet or disengaged accounts rarely see the prompt.',
                            'bias_mitigation' => 'We limit how often a user can see NPS and avoid showing it immediately after support interactions.',
                            'benchmarking' => 'We track NPS monthly in our lifecycle dashboard and compare against PLG benchmarks from our survey platform.',
                            'nps_example' => 'In a recent month we had 300 promoters, 180 passives, and 120 detractors out of 600 responses, for an NPS of 30.',
                            'nps_pain_points' => 'Our ongoing in-app NPS is often higher than the relationship NPS that Customer Success runs, which causes confusion in dashboards.',
                            'additional_notes' => 'We also experiment with campaign-specific NPS questions, which sometimes get lumped into the main NPS view.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'survey_cadence' => [
                                    'type' => 'select',
                                    'label' => 'How often do you send the NPS survey?',
                                    'raw' => 'ongoing_triggered',
                                    'normalized' => 'ongoing_triggered',
                                ],
                                'survey_audience_segment' => [
                                    'type' => 'select',
                                    'label' => 'Which customers or users do you primarily survey?',
                                    'raw' => 'active_product_users',
                                    'normalized' => 'active_product_users',
                                ],
                                'passive_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle passives (scores 7–8)?',
                                    'raw' => 'label_passives_only',
                                    'normalized' => 'label_passives_only',
                                ],
                                'response_volume_band' => [
                                    'type' => 'select',
                                    'label' => 'In a typical period, how many NPS responses do you collect?',
                                    'raw' => '200_plus',
                                    'normalized' => '200_plus',
                                ],
                                'sample_representativeness' => [
                                    'type' => 'select',
                                    'label' => 'Is your NPS sample representative of your overall user base?',
                                    'raw' => 'somewhat_representative',
                                    'normalized' => 'somewhat_representative',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Marketing',
                                    'normalized' => 'marketing',
                                ],
                                'survey_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'When in the lifecycle do you send NPS (e.g., after onboarding, before renewal)?',
                                    'raw' => 'We run NPS as an in-app prompt after users hit certain usage milestones rather than on a fixed calendar.',
                                    'normalized' => 'we run nps as an in-app prompt after users hit certain usage milestones rather than on a fixed calendar.',
                                ],
                                'survey_audience_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe key segments, channels, or exclusions for your NPS survey.',
                                    'raw' => 'Any active in-product user can see the survey, including free and trial accounts.',
                                    'normalized' => 'any active in-product user can see the survey, including free and trial accounts.',
                                ],
                                'passive_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or workflows for how you treat passives.',
                                    'raw' => 'We tag passives in the analytics tool but do not yet have a structured follow-up playbook.',
                                    'normalized' => 'we tag passives in the analytics tool but do not yet have a structured follow-up playbook.',
                                ],
                                'sample_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add notes on sample representativeness or key segment skews.',
                                    'raw' => 'The sample is skewed toward highly active users; quiet or disengaged accounts rarely see the prompt.',
                                    'normalized' => 'the sample is skewed toward highly active users; quiet or disengaged accounts rarely see the prompt.',
                                ],
                                'bias_mitigation' => [
                                    'type' => 'textarea',
                                    'label' => 'How do you reduce response bias (if at all)?',
                                    'raw' => 'We limit how often a user can see NPS and avoid showing it immediately after support interactions.',
                                    'normalized' => 'we limit how often a user can see nps and avoid showing it immediately after support interactions.',
                                ],
                                'benchmarking' => [
                                    'type' => 'textarea',
                                    'label' => 'Do you benchmark or trend NPS over time?',
                                    'raw' => 'We track NPS monthly in our lifecycle dashboard and compare against PLG benchmarks from our survey platform.',
                                    'normalized' => 'we track nps monthly in our lifecycle dashboard and compare against plg benchmarks from our survey platform.',
                                ],
                                'nps_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide an example NPS result (promoters/passives/detractors).',
                                    'raw' => 'In a recent month we had 300 promoters, 180 passives, and 120 detractors out of 600 responses, for an NPS of 30.',
                                    'normalized' => 'in a recent month we had 300 promoters, 180 passives, and 120 detractors out of 600 responses, for an nps of 30.',
                                ],
                                'nps_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do NPS definitions or survey methods differ today?',
                                    'raw' => 'Our ongoing in-app NPS is often higher than the relationship NPS that Customer Success runs, which causes confusion in dashboards.',
                                    'normalized' => 'our ongoing in-app nps is often higher than the relationship nps that customer success runs, which causes confusion in dashboards.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We also experiment with campaign-specific NPS questions, which sometimes get lumped into the main NPS view.',
                                    'normalized' => 'we also experiment with campaign-specific nps questions, which sometimes get lumped into the main nps view.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(4),
                    ],
                    [
                        'membership' => $revOpsMembership,
                        'participant' => $revOpsParticipant,
                        'tags' => ['RevOps'],
                        'answers' => [
                            'team_department' => 'Revenue Operations',
                            'survey_cadence' => 'biannual_all_customers',
                            'survey_timing_notes' => 'We run two NPS waves per year timed to renewal seasons for key accounts.',
                            'survey_audience_segment' => 'decision_makers_only',
                            'survey_audience_notes' => 'We send the survey to executive sponsors and economic buyers on top-tier accounts; end users are not included.',
                            'passive_handling' => 'ignore_passives',
                            'passive_handling_notes' => 'We primarily report the share of promoters versus detractors; passives do not trigger distinct workflows.',
                            'response_volume_band' => '50_to_199',
                            'sample_representativeness' => 'not_representative',
                            'sample_notes' => 'Responses mostly come from large enterprise customers, so NPS is biased toward strategic accounts.',
                            'bias_mitigation' => 'We avoid sending surveys to accounts in the middle of escalations, but otherwise do little randomization.',
                            'benchmarking' => 'We treat this executive NPS as the board-facing score and compare it to a small peer set.',
                            'nps_example' => 'In the last cycle we had 40 promoters, 10 passives, and 10 detractors (NPS 50) from 60 responses, mostly enterprise accounts.',
                            'nps_pain_points' => 'Because this survey targets only executive sponsors, the score is higher than product-level NPS, and teams often mix them up.',
                            'additional_notes' => 'We would like to reconcile relationship, in-app, and executive NPS into a single official definition with labeled variants.',
                        ],
                        'normalized' => [
                            'fields' => [
                                'survey_cadence' => [
                                    'type' => 'select',
                                    'label' => 'How often do you send the NPS survey?',
                                    'raw' => 'biannual_all_customers',
                                    'normalized' => 'biannual_all_customers',
                                ],
                                'survey_audience_segment' => [
                                    'type' => 'select',
                                    'label' => 'Which customers or users do you primarily survey?',
                                    'raw' => 'decision_makers_only',
                                    'normalized' => 'decision_makers_only',
                                ],
                                'passive_handling' => [
                                    'type' => 'select',
                                    'label' => 'How do you handle passives (scores 7–8)?',
                                    'raw' => 'ignore_passives',
                                    'normalized' => 'ignore_passives',
                                ],
                                'response_volume_band' => [
                                    'type' => 'select',
                                    'label' => 'In a typical period, how many NPS responses do you collect?',
                                    'raw' => '50_to_199',
                                    'normalized' => '50_to_199',
                                ],
                                'sample_representativeness' => [
                                    'type' => 'select',
                                    'label' => 'Is your NPS sample representative of your overall user base?',
                                    'raw' => 'not_representative',
                                    'normalized' => 'not_representative',
                                ],
                            ],
                            'meta' => [
                                'team_department' => [
                                    'type' => 'text',
                                    'label' => 'Which team or department do you represent?',
                                    'raw' => 'Revenue Operations',
                                    'normalized' => 'revenue operations',
                                ],
                                'survey_timing_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'When in the lifecycle do you send NPS (e.g., after onboarding, before renewal)?',
                                    'raw' => 'We run two NPS waves per year timed to renewal seasons for key accounts.',
                                    'normalized' => 'we run two nps waves per year timed to renewal seasons for key accounts.',
                                ],
                                'survey_audience_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Describe key segments, channels, or exclusions for your NPS survey.',
                                    'raw' => 'We send the survey to executive sponsors and economic buyers on top-tier accounts; end users are not included.',
                                    'normalized' => 'we send the survey to executive sponsors and economic buyers on top-tier accounts; end users are not included.',
                                ],
                                'passive_handling_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add nuance or workflows for how you treat passives.',
                                    'raw' => 'We primarily report the share of promoters versus detractors; passives do not trigger distinct workflows.',
                                    'normalized' => 'we primarily report the share of promoters versus detractors; passives do not trigger distinct workflows.',
                                ],
                                'sample_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Add notes on sample representativeness or key segment skews.',
                                    'raw' => 'Responses mostly come from large enterprise customers, so NPS is biased toward strategic accounts.',
                                    'normalized' => 'responses mostly come from large enterprise customers, so nps is biased toward strategic accounts.',
                                ],
                                'bias_mitigation' => [
                                    'type' => 'textarea',
                                    'label' => 'How do you reduce response bias (if at all)?',
                                    'raw' => 'We avoid sending surveys to accounts in the middle of escalations, but otherwise do little randomization.',
                                    'normalized' => 'we avoid sending surveys to accounts in the middle of escalations, but otherwise do little randomization.',
                                ],
                                'benchmarking' => [
                                    'type' => 'textarea',
                                    'label' => 'Do you benchmark or trend NPS over time?',
                                    'raw' => 'We treat this executive NPS as the board-facing score and compare it to a small peer set.',
                                    'normalized' => 'we treat this executive nps as the board-facing score and compare it to a small peer set.',
                                ],
                                'nps_example' => [
                                    'type' => 'textarea',
                                    'label' => 'Provide an example NPS result (promoters/passives/detractors).',
                                    'raw' => 'In the last cycle we had 40 promoters, 10 passives, and 10 detractors (NPS 50) from 60 responses, mostly enterprise accounts.',
                                    'normalized' => 'in the last cycle we had 40 promoters, 10 passives, and 10 detractors (nps 50) from 60 responses, mostly enterprise accounts.',
                                ],
                                'nps_pain_points' => [
                                    'type' => 'textarea',
                                    'label' => 'Where do NPS definitions or survey methods differ today?',
                                    'raw' => 'Because this survey targets only executive sponsors, the score is higher than product-level NPS, and teams often mix them up.',
                                    'normalized' => 'because this survey targets only executive sponsors, the score is higher than product-level nps, and teams often mix them up.',
                                ],
                                'additional_notes' => [
                                    'type' => 'textarea',
                                    'label' => 'Additional notes or context.',
                                    'raw' => 'We would like to reconcile relationship, in-app, and executive NPS into a single official definition with labeled variants.',
                                    'normalized' => 'we would like to reconcile relationship, in-app, and executive nps into a single official definition with labeled variants.',
                                ],
                            ],
                        ],
                        'submitted_at' => now()->subDays(3),
                    ],
                ],
                'approver' => [
                    'membership' => $approverMembership,
                    'participant' => $approverParticipant,
                ],
                'discrepancy' => [
                    'field_key' => 'survey_cadence',
                    'rule_type' => 'enum_mismatch',
                    'severity' => 'medium',
                    'status' => 'Open',
                    'description' => 'Customer Success runs quarterly relationship NPS while Marketing runs ongoing in-app NPS and RevOps runs biannual executive surveys, leading to multiple competing NPS figures.',
                    'detected_at' => now()->subDays(2),
                ],
            ],
        ];

        $scenarioTemplateKeys = [
            'active-users-alignment' => 'active_users',
            'monthly-recurring-revenue' => 'mrr',
            'ltv' => 'ltv',
            'lead_quality' => 'lead_quality',
            'conversion-rate-definition' => 'conversion_rate',
            'cac_cpl' => 'cac_cpl',
            'churn' => 'churn',
            'nps' => 'nps',
        ];

        $templatesByKey = MetricTemplate::query()
            ->whereIn('key', array_values($scenarioTemplateKeys))
            ->get()
            ->keyBy('key');

        foreach ($scenarios as $scenario) {
            $templateKey = $scenarioTemplateKeys[$scenario['key']] ?? null;
            $template = $templateKey ? $templatesByKey->get($templateKey) : null;

            $this->seedMetricScenario($organization->id, $engagement, $template, $scenario);
        }
    }

    /**
     * @param  array{
     *     key: string,
     *     name: string,
     *     slug: string,
     *     status?: MetricStatus|string,
     *     form_title: string,
     *     primary_field?: string,
     *     contributors: array<int, array{
     *         membership: Membership,
     *         participant: EngagementParticipant,
     *         tags?: array<int, string>,
     *         answers: array<string, mixed>,
     *         normalized: array<string, mixed>,
     *         submitted_at: Carbon,
     *     }>,
     *     approver?: array{membership: Membership, participant: EngagementParticipant},
     *     discrepancy?: array{
     *         field_key: string,
     *         rule_type: string,
     *         severity: int,
     *         status: string,
     *         description: string,
     *         detected_at?: Carbon,
     *         resolved_at?: Carbon|null,
     *         values_snapshot?: array<string, mixed>,
     *     },
     *     workshop?: array<string, mixed>,
     *     final_definition?: array<string, mixed>
     * }  $scenario
     */
    private function seedMetricScenario(
        int|string $organizationId,
        Engagement $engagement,
        $template,
        array $scenario
    ): void {
        $metric = Metric::updateOrCreate(
            [
                'engagement_id' => $engagement->id,
                'key' => $scenario['key'],
            ],
            [
                'organization_id' => $organizationId,
                'metric_template_id' => $template?->id,
                'name' => $scenario['name'],
                'slug' => $scenario['slug'],
                'status' => $scenario['status'] ?? MetricStatus::IN_REVIEW,
            ]
        );

        $this->clearPreparedFacilitationPlan($metric);

        $form = $this->ensureForm($metric, $engagement, [
            'title' => $scenario['form_title'],
        ]);

        $definitionSnapshots = [];

        foreach ($scenario['contributors'] as $contributor) {
            $this->attachMetricParticipant(
                $metric,
                $contributor['participant'],
                'contributor',
                $contributor['tags'] ?? [],
                $contributor['membership']
            );

            $submission = FormSubmission::updateOrCreate(
                [
                    'form_id' => $form->id,
                    'user_id' => $contributor['membership']->user->id,
                ],
                [
                    'answers_json' => $contributor['answers'],
                    'submitted_at' => $contributor['submitted_at'],
                ]
            );

            $definition = MetricDefinition::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'form_submission_id' => $submission->id,
                ],
                [
                    'user_id' => $contributor['membership']->user->id,
                    'source_type' => 'form_submission',
                    'status' => 'Submitted',
                    'definition_json' => $contributor['answers'],
                    'normalized_json' => $contributor['normalized'],
                    'submitted_at' => $submission->submitted_at,
                ]
            );

            $definitionSnapshots[] = array_filter([
                'definition_id' => (string) $definition->id,
                'user' => $contributor['membership']->user
                    ? $contributor['membership']->user->only(['id', 'name'])
                    : null,
                'raw' => Arr::get($contributor['answers'], $scenario['primary_field'] ?? ''),
                'normalized' => Arr::get($contributor['normalized'], $scenario['primary_field'] ?? ''),
                'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
            ]);
        }

        if (! empty($scenario['approver'])) {
            $this->attachMetricParticipant(
                $metric,
                $scenario['approver']['participant'],
                'approver',
                ['Executive'],
                $scenario['approver']['membership']
            );
        }

        if (! empty($scenario['discrepancy'])) {
            $details = $scenario['discrepancy'];

            $valuesSnapshot = $details['values_snapshot'] ?? [];
            $valuesSnapshot['label'] = $valuesSnapshot['label'] ?? Str::headline($details['field_key']);
            $valuesSnapshot['values'] = array_values(array_filter($definitionSnapshots));

            Discrepancy::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'field_key' => $details['field_key'],
                ],
                [
                    'engagement_id' => $engagement->id,
                    'rule_type' => $details['rule_type'],
                    'severity' => $details['severity'],
                    'status' => $details['status'],
                    'description' => $details['description'],
                    'values_snapshot' => $valuesSnapshot,
                    'detected_at' => $details['detected_at'] ?? now(),
                    'resolved_at' => $details['resolved_at'] ?? null,
                ]
            );
        }

        if (! empty($scenario['workshop'])) {
            $workshop = $scenario['workshop'];
            $facilitatorMembershipId = $scenario['approver']['membership']->id ?? null;

            ReconciliationWorkshop::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'engagement_id' => $engagement->id,
                ],
                [
                    'facilitator_id' => $facilitatorMembershipId,
                    'scheduled_at' => $workshop['scheduled_at'],
                    'participants' => $workshop['participants'] ?? [],
                    'decision_snapshot' => $workshop['decision_snapshot'] ?? [],
                    'notes' => $workshop['notes'] ?? null,
                ]
            );
        }

        if (! empty($scenario['final_definition'])) {
            $final = $scenario['final_definition'];

            FinalDefinition::updateOrCreate(
                [
                    'metric_id' => $metric->id,
                    'version' => Arr::get($final, 'version', 1),
                ],
                [
                    'user_id' => $final['user_id'],
                    'status' => $final['status'] ?? 'signed',
                    'definition_json' => $final['definition_json'] ?? [],
                    'notes' => $final['notes'] ?? null,
                    'signed_off_at' => $final['signed_off_at'] ?? null,
                    'signed_off_by' => $final['signed_off_by'] ?? null,
                ]
            );
        }
    }
}
