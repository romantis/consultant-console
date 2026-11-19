<?php

namespace Database\Seeders\StorySeeds;

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Enums\StakeholderRole;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\FormSubmission;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FocusTimeAlignmentStorySeeder extends BaseStorySeeder
{
    public function run(): void
    {
        $staffAdmin = $this->context->staffAdmin();

        $organization = $this->context->ensureClientOrganization(
            'focus-time-alignment',
            'Focus Time Studio',
            ['timezone' => 'Europe/London']
        );

        $this->context->ensureConsultantAccess($organization, $staffAdmin, MembershipRole::ADMIN);

        $engineerMembership = $this->context->ensureMember(
            $organization,
            'avery.lee@focus.test',
            'Avery Lee',
            MembershipRole::MEMBER,
            ['affiliation' => 'Engineering']
        );
        $engineerMembership->loadMissing('user');

        $productMembership = $this->context->ensureMember(
            $organization,
            'jordan.park@focus.test',
            'Jordan Park',
            MembershipRole::MEMBER,
            ['affiliation' => 'Product']
        );
        $productMembership->loadMissing('user');

        $approverMembership = $this->context->ensureMember(
            $organization,
            'marie.cho@focus.test',
            'Marie Cho',
            MembershipRole::OWNER,
            ['affiliation' => 'Engineering Manager']
        );
        $approverMembership->loadMissing('user');

        $engagement = Engagement::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'Focus Time Alignment Lab',
            ],
            [
                'status' => EngagementStatus::IN_REVIEW,
                'starts_on' => now()->subWeeks(2)->toDateString(),
                'review_cadence' => 'Monthly',
                'next_review_on' => now()->addWeeks(2)->toDateString(),
                'repository_driver' => 'notion',
                'repository_reference' => 'https://focus.example.test/workspace/focus-time',
            ]
        );

        $engineerParticipant = $this->createMembershipParticipant($engagement, $engineerMembership, StakeholderRole::MEMBER);
        $productParticipant = $this->createMembershipParticipant($engagement, $productMembership, StakeholderRole::MEMBER);
        $approverParticipant = $this->createMembershipParticipant($engagement, $approverMembership, StakeholderRole::ADMIN);

        /** @var MetricTemplate|null $template */
        $template = MetricTemplate::where('key', 'field_classification_demo')->first();

        if (! $template) {
            return;
        }

        $metric = Metric::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'engagement_id' => $engagement->id,
                'key' => 'focus-time-alignment',
            ],
            [
                'metric_template_id' => $template->id,
                'name' => 'Focus Time Alignment',
                'slug' => Str::slug('focus-time-alignment-'.Str::random(4)),
                'status' => MetricStatus::IN_REVIEW,
            ]
        );

        if (! $metric->metric_template_id) {
            $metric->forceFill(['metric_template_id' => $template->id])->save();
        }

        $this->clearPreparedFacilitationPlan($metric);

        $form = $this->ensureForm($metric, $engagement, [
            'title' => 'Focus Time Alignment Survey',
        ]);

        $contributors = [
            [
                'membership' => $engineerMembership,
                'participant' => $engineerParticipant,
                'tags' => ['Engineering'],
                'answers' => [
                    'focus_hours_target' => 4,
                    'focus_window_preference' => 'morning',
                    'meeting_rules' => 'No meetings 9–11am; 1:1s after 3pm.',
                    'notification_policy' => 'Slack on DND; only @here/@channel for emergencies.',
                    'common_interruptions' => 'Drive-by questions, ad hoc status pings, noisy channels.',
                    'impact_when_missed' => 'Code quality drops and reviews keep slipping to the next day.',
                ],
                'normalized' => [
                    'fields' => [
                        'focus_hours_target' => [
                            'type' => 'number',
                            'label' => 'Ideal focus hours per workday',
                            'raw' => 4,
                            'normalized' => 4,
                        ],
                    ],
                    'meta' => [
                        'focus_window_preference' => [
                            'type' => 'select',
                            'label' => 'When do you prefer your main focus block?',
                            'raw' => 'morning',
                            'normalized' => 'morning',
                        ],
                        'meeting_rules' => [
                            'type' => 'textarea',
                            'label' => 'What are your personal meeting rules during focus time?',
                            'raw' => 'No meetings 9–11am; 1:1s after 3pm.',
                            'normalized' => 'no meetings 9–11am; 1:1s after 3pm.',
                        ],
                        'notification_policy' => [
                            'type' => 'textarea',
                            'label' => 'How do you handle notifications during focus time?',
                            'raw' => 'Slack on DND; only @here/@channel for emergencies.',
                            'normalized' => 'slack on dnd; only @here/@channel for emergencies.',
                        ],
                        'common_interruptions' => [
                            'type' => 'textarea',
                            'label' => 'What typically interrupts your focus time?',
                            'raw' => 'Drive-by questions, ad hoc status pings, noisy channels.',
                            'normalized' => 'drive-by questions, ad hoc status pings, noisy channels.',
                        ],
                        'impact_when_missed' => [
                            'type' => 'textarea',
                            'label' => 'What happens when you do not get enough focus time?',
                            'raw' => 'Code quality drops and reviews keep slipping to the next day.',
                            'normalized' => 'code quality drops and reviews keep slipping to the next day.',
                        ],
                    ],
                ],
                'submitted_at' => now()->subDays(5),
            ],
            [
                'membership' => $productMembership,
                'participant' => $productParticipant,
                'tags' => ['Product'],
                'answers' => [
                    'focus_hours_target' => 2,
                    'focus_window_preference' => 'afternoon',
                    'meeting_rules' => 'Limit meetings to 3 hours per day and avoid back-to-back afternoons.',
                    'notification_policy' => 'Keep Slack on but mute non-project channels during focus blocks.',
                    'common_interruptions' => 'Roadmap questions, urgent pings from stakeholders, and thread replies.',
                    'impact_when_missed' => 'PRDs slip and I end up working late to catch up.',
                ],
                'normalized' => [
                    'fields' => [
                        'focus_hours_target' => [
                            'type' => 'number',
                            'label' => 'Ideal focus hours per workday',
                            'raw' => 2,
                            'normalized' => 2,
                        ],
                    ],
                    'meta' => [
                        'focus_window_preference' => [
                            'type' => 'select',
                            'label' => 'When do you prefer your main focus block?',
                            'raw' => 'afternoon',
                            'normalized' => 'afternoon',
                        ],
                        'meeting_rules' => [
                            'type' => 'textarea',
                            'label' => 'What are your personal meeting rules during focus time?',
                            'raw' => 'Limit meetings to 3 hours per day and avoid back-to-back afternoons.',
                            'normalized' => 'limit meetings to 3 hours per day and avoid back-to-back afternoons.',
                        ],
                        'notification_policy' => [
                            'type' => 'textarea',
                            'label' => 'How do you handle notifications during focus time?',
                            'raw' => 'Keep Slack on but mute non-project channels during focus blocks.',
                            'normalized' => 'keep slack on but mute non-project channels during focus blocks.',
                        ],
                        'common_interruptions' => [
                            'type' => 'textarea',
                            'label' => 'What typically interrupts your focus time?',
                            'raw' => 'Roadmap questions, urgent pings from stakeholders, and thread replies.',
                            'normalized' => 'roadmap questions, urgent pings from stakeholders, and thread replies.',
                        ],
                        'impact_when_missed' => [
                            'type' => 'textarea',
                            'label' => 'What happens when you do not get enough focus time?',
                            'raw' => 'PRDs slip and I end up working late to catch up.',
                            'normalized' => 'prds slip and i end up working late to catch up.',
                        ],
                    ],
                ],
                'submitted_at' => now()->subDays(4),
            ],
        ];

        $definitionSnapshots = [];

        foreach ($contributors as $contributor) {
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
                'raw' => Arr::get($contributor['answers'], 'focus_hours_target'),
                'normalized' => Arr::get(
                    $contributor['normalized']['fields']['focus_hours_target'] ?? [],
                    'normalized'
                ),
                'submitted_at' => optional($definition->submitted_at)->toIso8601String(),
            ]);
        }

        $this->attachMetricParticipant(
            $metric,
            $approverParticipant,
            'approver',
            ['Manager'],
            $approverMembership
        );

        Discrepancy::updateOrCreate(
            [
                'metric_id' => $metric->id,
                'field_key' => 'focus_hours_target',
            ],
            [
                'engagement_id' => $engagement->id,
                'rule_type' => 'range_mismatch',
                'severity' => 'medium',
                'status' => 'Open',
                'description' => 'The team disagrees on how many hours of uninterrupted focus time are realistic in a day.',
                'values_snapshot' => [
                    'label' => 'Ideal focus hours per workday',
                    'values' => array_values(array_filter($definitionSnapshots)),
                ],
                'detected_at' => now()->subDays(3),
            ]
        );
    }
}
