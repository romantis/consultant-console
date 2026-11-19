<?php

namespace Database\Seeders;

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Support\EngagementChecklist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MetricTemplateSeeder::class);

        // Optional: local demo org/user here ONLY for dev
        if (app()->environment(['local', 'testing'])) {
            // internal consulting org
            $internal = Organization::firstOrCreate(
                ['slug' => 'metric-harmony'],
                [
                    'name' => 'Metric Harmony',
                    'is_internal' => true,
                    'settings_json' => ['locale' => 'en', 'currency' => 'USD'],
                ]
            );

            // staff/root user
            $owner = User::firstOrCreate(
                ['email' => 'consultant@example.test'],
                ['name' => 'Console Owner', 'password' => Hash::make('password')]
            );

            // Make them OWNER of the internal org
            $consultantMembership = Membership::firstOrCreate(
                ['organization_id' => $internal->id, 'user_id' => $owner->id],
                ['role' => MembershipRole::OWNER->value]
            );

            // Demo Organization
            $client = Organization::firstOrCreate(['slug' => 'acme'], [
                'name' => 'Acme, Inc.',
                'settings_json' => ['locale' => 'en', 'currency' => 'USD'],
            ]);

            $consultantClientMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $owner->id],
                ['role' => MembershipRole::OWNER->value, 'affiliation' => 'Consultant']
            );

            // Demo Organization (Acme) Owner
            $demoOrgOwn = User::firstOrCreate(
                ['email' => 'owner@acme.test'],
                ['name' => 'Owner Acme', 'password' => Hash::make('password')]
            );

            $acmeOwnerMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgOwn->id],
                ['role' => MembershipRole::OWNER->value, 'affiliation' => 'CEO']
            );

            // Demo Organization (Acme) Admin
            $demoOrgAdmin = User::firstOrCreate(
                ['email' => 'admin@acme.test'],
                ['name' => 'Admin Acme', 'password' => Hash::make('password')]
            );

            $acmeAdminMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgAdmin->id],
                ['role' => MembershipRole::ADMIN->value, 'affiliation' => 'CTO']
            );

            // Demo Organization (Acme) Finance
            $demoOrgFinance = User::firstOrCreate(
                ['email' => 'finance@acme.test'],
                ['name' => 'Finance Acme', 'password' => Hash::make('password')]
            );

            $acmeFinanceMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgFinance->id],
                ['role' => MembershipRole::MEMBER->value, 'affiliation' => 'Finance']
            );

            // Demo Organization (Acme) Marketing
            $demoOrgMarketing = User::firstOrCreate(
                ['email' => 'marketing@acme.test'],
                ['name' => 'Mark Acme', 'password' => Hash::make('password')]
            );

            $acmeMarketingMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgMarketing->id],
                ['role' => MembershipRole::MEMBER->value, 'affiliation' => 'Marketing']
            );

            // Demo Organization (Acme) Sales
            $demoOrgSales = User::firstOrCreate(
                ['email' => 'sales@acme.test'],
                ['name' => 'Sales Acme', 'password' => Hash::make('password')]
            );

            $acmeSalesMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgSales->id],
                ['role' => MembershipRole::MEMBER->value, 'affiliation' => 'Sales']
            );

            // Demo Organization (Acme) Viewer
            $demoOrgViewer = User::firstOrCreate(
                ['email' => 'viewer@acme.test'],
                ['name' => 'Viewer Acme', 'password' => Hash::make('password')]
            );

            $acmeViewerMembership = Membership::firstOrCreate(
                ['organization_id' => $client->id, 'user_id' => $demoOrgViewer->id],
                ['role' => MembershipRole::VIEWER->value, 'affiliation' => 'Guest']
            );

            $engagement = Engagement::firstOrCreate(
                ['organization_id' => $client->id, 'title' => 'Launch Readiness'],
                [
                    'status' => EngagementStatus::AWAITING_FORMS,
                    'starts_on' => now()->subMonth()->toDateString(),
                    'review_cadence' => 'Monthly',
                    'next_review_on' => now()->addWeeks(3)->toDateString(),
                    'repository_driver' => 'notion',
                    'repository_reference' => 'https://acme.example.test/launch-readiness',
                    'stakeholders_json' => [
                        ['name' => 'Console Owner', 'email' => $owner->email, 'role' => 'admin'],
                    ],
                    'onboarding_checklist_json' => EngagementChecklist::merge([], [
                        'metrics_selected' => true,
                        'stakeholders_invited' => true,
                        'repository_connected' => true,
                    ]),
                ]
            );

            // $templates = MetricTemplate::limit(3)->get();
            $templates = MetricTemplate::where('key', 'conversion_rate')->get();

            $metrics = collect();

            foreach ($templates as $template) {
                $metricKey = $template?->key ?? Str::slug($template?->name ?? Str::random(8));

                if (! $metricKey) {
                    $metricKey = 'metric-'.Str::lower(Str::random(6));
                }

                $metric = Metric::firstOrCreate(
                    [
                        'organization_id' => $client->id,
                        'engagement_id' => $engagement->id,
                        'key' => $metricKey,
                    ],
                    [
                        'metric_template_id' => $template->id,
                        'name' => $template->name,
                        'slug' => Str::slug($metricKey.'-'.Str::random(4)),
                    ]
                );

                if (! $metric->metric_template_id) {
                    $metric->forceFill(['metric_template_id' => $template->id])->save();
                }

                $metrics->push($metric);
            }

            $membershipsForParticipants = collect([
                $consultantClientMembership,
                $acmeOwnerMembership,
                $acmeAdminMembership,
                $acmeFinanceMembership,
                $acmeMarketingMembership,
                $acmeSalesMembership,
                $acmeViewerMembership,
            ])->filter();

            $engagementParticipants = $membershipsForParticipants->mapWithKeys(function (Membership $membership) use ($engagement) {
                $membershipRole = $membership->role instanceof MembershipRole
                    ? $membership->role
                    : MembershipRole::from($membership->role);
                $stakeholderRole = match ($membershipRole) {
                    MembershipRole::OWNER, MembershipRole::ADMIN => StakeholderRole::ADMIN,
                    MembershipRole::MEMBER => StakeholderRole::MEMBER,
                    default => StakeholderRole::VIEWER,
                };

                $participant = EngagementParticipant::firstOrCreate(
                    [
                        'engagement_id' => $engagement->id,
                        'membership_id' => $membership->id,
                    ],
                    [
                        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
                        'membership_role' => $membershipRole,
                        'stakeholder_role' => $stakeholderRole,
                        'active' => true,
                    ]
                );

                return [$membership->user_id => $participant];
            });

            $assignmentPlan = [
                'mrr' => [
                    [$acmeFinanceMembership, 'contributor', ['Finance']],
                    [$acmeAdminMembership, 'approver', ['Operations']],
                ],
                'churn' => [
                    [$acmeSalesMembership, 'contributor', ['Sales']],
                    [$acmeMarketingMembership, 'approver', ['Marketing']],
                ],
                'active_users' => [
                    [$acmeMarketingMembership, 'contributor', ['Product']],
                    [$acmeOwnerMembership, 'approver', ['Executive']],
                ],
            ];

            $metrics->each(function (Metric $metric) use ($assignmentPlan, $engagement, $engagementParticipants) {
                $metric->loadMissing('template');

                $template = $metric->template;

                $form = Form::firstOrCreate(
                    [
                        'engagement_id' => $engagement->id,
                        'metric_id' => $metric->id,
                    ],
                    [
                        'organization_id' => $engagement->organization_id,
                        'template_key' => $template?->key ?? 'custom',
                        'title' => ($template?->name ?? $metric->name).' — Monthly Update',
                        'schema_json' => $template?->form_schema_json ?? ['fields' => []],
                        'status' => 'Open',
                    ]
                );

                $assignments = $assignmentPlan[$template?->key ?? ''] ?? [];

                foreach ($assignments as $assignmentConfig) {
                    [$membership, $responsibility, $roleTags] = $assignmentConfig;

                    if (! $membership || ! $membership->user_id) {
                        continue;
                    }

                    $participant = $engagementParticipants->get($membership->user_id);

                    if (! $participant) {
                        continue;
                    }

                    $metricParticipant = MetricParticipant::firstOrCreate(
                        [
                            'metric_id' => $metric->id,
                            'engagement_participant_id' => $participant->id,
                        ],
                        [
                            'user_id' => $membership->user_id,
                            'subject_type' => Membership::class,
                            'subject_id' => $membership->id,
                            'responsibility' => $responsibility,
                            'role_tags_json' => $roleTags,
                        ]
                    );

                    if (! in_array($metricParticipant->responsibility, ['contributor', 'approver'], true)) {
                        continue;
                    }

                    FormAssignment::firstOrCreate(
                        [
                            'form_id' => $form->id,
                            'metric_participant_id' => $metricParticipant->id,
                        ],
                        [
                            'metric_id' => $metric->id,
                            'user_id' => $membership->user_id,
                            'responsibility' => ucfirst($metricParticipant->responsibility),
                            'role_tags_json' => $metricParticipant->role_tags_json ?? [],
                            'due_at' => now()->addWeek(),
                        ]
                    );

                    FormSubmission::firstOrCreate(
                        [
                            'form_id' => $form->id,
                            'user_id' => $membership->user_id,
                        ]
                    );
                }
            });

            $engagement->forceFill([
                'onboarding_checklist_json' => EngagementChecklist::merge(
                    $engagement->onboarding_checklist_json ?? [],
                    ['forms_bootstrapped' => true]
                ),
            ])->save();
            $this->call(StorySeedsSeeder::class);
        }
    }
}
