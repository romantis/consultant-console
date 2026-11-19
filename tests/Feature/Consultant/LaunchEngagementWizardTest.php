<?php

use App\Enums\EngagementStatus;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

it('launches an engagement via the wizard with seeded metrics, participants, assignments, and invites', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00'));
    Notification::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);
    $contributorUser = User::factory()->create(['email' => 'contributor@example.com']);
    $contributorMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $contributorUser->id,
    ]);

    $secondaryMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $templates = MetricTemplate::factory()->count(5)->create();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Northwind Rollout',
        'timeline_start' => Carbon::now()->toDateString(),
        'timeline_end' => Carbon::now()->addWeeks(8)->toDateString(),
        'handbook_repository' => [
            'driver' => 'notion',
            'identifier' => 'https://notion.example.com/northwind',
        ],
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
                'department' => 'Consulting',
            ],
            [
                'participant_id' => (string) $contributorMembership->id,
                'email' => $contributorUser->email,
                'role' => 'Member',
                'department' => 'Operations',
            ],
            [
                'email' => 'observer@example.com',
                'role' => 'Viewer',
                'department' => 'Finance',
            ],
        ],
        'metrics' => $templates->map(function (MetricTemplate $template, int $index) use ($ownerMembership, $contributorMembership) {
            return [
                'template_id' => (string) $template->id,
                'canonical_metric_id' => (string) $template->canonical_metric_id,
                'key' => sprintf('metric-%02d', $index + 1),
                'name' => 'Metric '.($index + 1),
                'description' => 'Launch metric '.($index + 1),
                'required_departments' => ['Finance'],
                'due_date' => Carbon::now()->addWeeks($index + 2)->toDateString(),
                'participants' => [
                    [
                        'participant_id' => (string) $ownerMembership->id,
                        'role' => 'Contributor',
                    ],
                    [
                        'participant_id' => (string) $contributorMembership->id,
                        'role' => 'Contributor',
                    ],
                    [
                        'participant_id' => (string) $ownerMembership->id,
                        'role' => 'Approver',
                    ],
                    [
                        'participant_id' => 'invite:observer@example.com',
                        'role' => 'Observer',
                    ],
                ],
            ];
        })->values()->all(),
        'invited_emails' => ['observer@example.com'],
    ];

    $this->actingAs($owner);

    $response = $this->postJson('/api/engagements', $payload);

    $response->assertStatus(201)->assertJson(fn (AssertableJson $json) => $json
        ->where('organization_id', (string) $organization->id)
        ->where('status', EngagementStatus::DRAFT->value)
        ->where('name', 'Northwind Rollout')
        ->etc()
    );

    $engagement = Engagement::query()->latest()->firstOrFail();

    expect($engagement->organization_id)->toBe($organization->id);
    expect($engagement->status)->toBe(EngagementStatus::DRAFT);

    $storedName = $engagement->name ?? $engagement->title;
    expect($storedName)->toBe('Northwind Rollout');

    $stakeholders = $engagement->stakeholders_json ?? [];
    expect($stakeholders)->toBeArray();
    expect(data_get($stakeholders, '0.department'))->toBe('Consulting');
    expect(data_get($stakeholders, '1.department'))->toBe('Operations');

    $checklist = $engagement->checklist ?? $engagement->onboarding_checklist_json ?? [];
    expect(data_get($checklist, 'metrics_selected'))->toBeTrue();
    expect(data_get($checklist, 'stakeholders_invited'))->toBeTrue();
    expect(data_get($checklist, 'forms_bootstrapped'))->toBeTrue();

    $metrics = Metric::query()->where('engagement_id', $engagement->id)->get();
    expect($metrics)->toHaveCount(5);

    $metricNames = $metrics->pluck('name')->all();
    expect($metricNames)->toContain('Metric 1');
    expect($metricNames)->toContain('Metric 5');

    $metricParticipantCount = MetricParticipant::query()->whereIn('metric_id', $metrics->pluck('id'))->count();
    expect($metricParticipantCount)->toBeGreaterThanOrEqual(15);

    $assignmentCount = FormAssignment::query()->whereIn('metric_id', $metrics->pluck('id'))->count();
    expect($assignmentCount)->toBeGreaterThanOrEqual(5);

    $participants = EngagementParticipant::query()->where('engagement_id', $engagement->id)->get();
    expect($participants)->toHaveCount(3);
    expect($participants->whereNull('pending_membership_id'))->toHaveCount(2);

    $pending = PendingMembership::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'observer@example.com')
        ->first();

    expect($pending)->not->toBeNull();
    expect($pending->engagement_id)->toBe($engagement->id);
    expect($pending->affiliation)->toBe('Finance');

    $pendingParticipant = $participants->firstWhere('pending_membership_id', $pending->id);
    expect($pendingParticipant)->not->toBeNull();

    Carbon::setTestNow();
});

it('defaults template selection when omitted and persists org defaults', function () {
    Carbon::setTestNow(Carbon::parse('2025-02-01 10:00:00'));

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);
    $contributorUser = User::factory()->create(['email' => 'contributor@example.com']);
    $contributorMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $contributorUser->id,
    ]);

    $canonicalPrimary = CanonicalMetric::factory()->create([
        'key' => 'lead_quality',
        'name' => 'Lead Quality',
    ]);
    $templatePrimary = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalPrimary->id,
        'key' => 'lead_quality_v1',
        'name' => 'Lead Quality v1',
    ]);
    $templateSecondary = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalPrimary->id,
        'key' => 'lead_quality_v2',
        'name' => 'Lead Quality v2',
    ]);
    $canonicalPrimary->forceFill([
        'default_metric_template_id' => $templateSecondary->id,
    ])->save();

    $canonicalSecondary = CanonicalMetric::factory()->create([
        'key' => 'mrr',
        'name' => 'Monthly Recurring Revenue',
    ]);
    $templateSecondaryMetric = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalSecondary->id,
        'key' => 'mrr-core',
        'name' => 'MRR Core',
    ]);

    $canonicalTertiary = CanonicalMetric::factory()->create([
        'key' => 'churn',
        'name' => 'Churn',
    ]);
    $templateTertiaryMetric = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalTertiary->id,
        'key' => 'churn-core',
        'name' => 'Churn Core',
    ]);

    $participantAssignments = [
        ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
        ['participant_id' => (string) $contributorMembership->id, 'role' => 'Contributor'],
        ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
        ['participant_id' => (string) $ownerMembership->id, 'role' => 'Observer'],
    ];

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Default Template Launch',
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
            ],
        ],
        'metrics' => [
            [
                'canonical_metric_id' => (string) $canonicalPrimary->id,
                'key' => 'lead-quality',
                'name' => 'Lead Quality',
                'participants' => $participantAssignments,
            ],
            [
                'canonical_metric_id' => (string) $canonicalSecondary->id,
                'key' => 'mrr',
                'name' => 'MRR',
                'participants' => $participantAssignments,
            ],
            [
                'canonical_metric_id' => (string) $canonicalTertiary->id,
                'key' => 'churn',
                'name' => 'Churn',
                'participants' => $participantAssignments,
            ],
        ],
    ];

    $this->actingAs($owner);

    $response = $this->postJson('/api/engagements', $payload);

    $response->assertStatus(201);

    $engagement = Engagement::query()->latest()->firstOrFail();

    $orgMetric = OrgMetric::query()
        ->where('organization_id', $organization->id)
        ->where('canonical_metric_id', $canonicalPrimary->id)
        ->firstOrFail();

    expect((string) $orgMetric->default_metric_template_id)
        ->toBe((string) $templateSecondary->id);

    $metric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('org_metric_id', $orgMetric->id)
        ->firstOrFail();

    expect((string) $metric->metric_template_id)
        ->toBe((string) $templateSecondary->id);

    $secondaryOrgMetric = OrgMetric::query()
        ->where('organization_id', $organization->id)
        ->where('canonical_metric_id', $canonicalSecondary->id)
        ->firstOrFail();

    $secondaryMetric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('org_metric_id', $secondaryOrgMetric->id)
        ->firstOrFail();

    expect((string) $secondaryMetric->metric_template_id)
        ->toBe((string) $templateSecondaryMetric->id);

    $tertiaryOrgMetric = OrgMetric::query()
        ->where('organization_id', $organization->id)
        ->where('canonical_metric_id', $canonicalTertiary->id)
        ->firstOrFail();

    $tertiaryMetric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('org_metric_id', $tertiaryOrgMetric->id)
        ->firstOrFail();

    expect((string) $tertiaryMetric->metric_template_id)
        ->toBe((string) $templateTertiaryMetric->id);

    Carbon::setTestNow();
});

it('allows selecting existing pending members as participants', function () {
    Carbon::setTestNow(Carbon::parse('2025-04-01 09:00:00'));

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);

    $pendingContributors = PendingMembership::factory()
        ->count(3)
        ->sequence(
            ['email' => 'alpha@example.com', 'name' => 'Alpha Pending'],
            ['email' => 'beta@example.com', 'name' => 'Beta Pending'],
            ['email' => 'gamma@example.com', 'name' => 'Gamma Pending'],
        )
        ->create([
            'organization_id' => $organization->id,
            'engagement_id' => null,
            'affiliation' => 'Finance Guild',
        ])
        ->values();

    [$firstPending, $secondPending, $thirdPending] = $pendingContributors->all();

    $templates = MetricTemplate::factory()->count(3)->create();

    $stakeholders = [
        [
            'participant_id' => (string) $ownerMembership->id,
            'email' => $owner->email,
            'role' => 'Admin',
        ],
    ];

    $pendingContributors->each(function (PendingMembership $pending) use (&$stakeholders) {
        $stakeholders[] = [
            'participant_id' => 'pending:'.$pending->id,
            'email' => $pending->email,
            'name' => $pending->name,
            'role' => 'Member',
            'department' => 'Finance',
        ];
    });

    $metrics = $templates->map(function (MetricTemplate $template) use ($firstPending, $secondPending, $thirdPending, $ownerMembership) {
        return [
            'template_id' => (string) $template->id,
            'canonical_metric_id' => (string) $template->canonical_metric_id,
            'key' => $template->key,
            'name' => $template->name,
            'participants' => [
                ['participant_id' => 'pending:'.$firstPending->id, 'role' => 'Contributor'],
                ['participant_id' => 'pending:'.$secondPending->id, 'role' => 'Contributor'],
                ['participant_id' => 'pending:'.$thirdPending->id, 'role' => 'Approver'],
                ['participant_id' => (string) $ownerMembership->id, 'role' => 'Observer'],
            ],
        ];
    })->values()->all();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Pending Member Launch',
        'timeline_start' => Carbon::now()->toDateString(),
        'timeline_end' => Carbon::now()->addWeeks(6)->toDateString(),
        'stakeholders' => $stakeholders,
        'metrics' => $metrics,
    ];

    $this->actingAs($owner);

    $response = $this->postJson('/api/engagements', $payload);

    $response->assertStatus(201);

    $engagement = Engagement::query()->latest()->firstOrFail();
    $participantCount = EngagementParticipant::query()
        ->where('engagement_id', $engagement->id)
        ->count();
    expect($participantCount)->toBe(4); // owner + three pending

    $pendingParticipants = EngagementParticipant::query()
        ->where('engagement_id', $engagement->id)
        ->whereNotNull('pending_membership_id')
        ->get();

    expect($pendingParticipants)->toHaveCount(3);
    $pendingParticipants->each(function (EngagementParticipant $participant) {
        expect($participant->participant_type)->toBe(EngagementParticipant::TYPE_PENDING);
    });

    $metricParticipants = MetricParticipant::query()
        ->where('engagement_participant_id', $pendingParticipants->first()?->id)
        ->count();

    expect($metricParticipants)->toBeGreaterThanOrEqual(1);

    Carbon::setTestNow();
});

it('allows launching without selecting a handbook repository yet', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00'));
    config()->set('metric-harmony.features.repository_drivers', false);

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);

    $secondaryMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $templates = MetricTemplate::factory()->count(5)->create();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Northwind Placeholder Repository',
        'timeline_start' => Carbon::now()->toDateString(),
        'timeline_end' => Carbon::now()->addWeeks(4)->toDateString(),
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
            ],
            [
                'participant_id' => (string) $secondaryMembership->id,
                'email' => $secondaryMembership->user?->email,
                'role' => 'Member',
            ],
        ],
        'metrics' => $templates->map(function (MetricTemplate $template, int $index) use ($ownerMembership, $secondaryMembership) {
            return [
                'template_id' => (string) $template->id,
                'canonical_metric_id' => (string) $template->canonical_metric_id,
                'key' => sprintf('metric-%02d', $index + 1),
                'name' => 'Metric '.($index + 1),
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $secondaryMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ];
        })->values()->all(),
        'invited_emails' => ['observer@example.com'],
    ];

    $this->actingAs($owner);

    $this->postJson('/api/engagements', $payload)
        ->assertStatus(201);

    $engagement = Engagement::query()->latest()->firstOrFail();
    expect($engagement->repository_driver)->toBe('manual');
    expect($engagement->repository_reference)->toBeNull();
    expect($engagement->repository_notes)->toBeNull();

    Carbon::setTestNow();
});

it('requires between three and ten metrics when launching an engagement', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00'));

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);

    $templates = MetricTemplate::factory()->count(2)->create();
    $secondaryMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Too Few Metrics',
        'timeline_start' => Carbon::now()->toDateString(),
        'timeline_end' => Carbon::now()->addWeeks(4)->toDateString(),
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
            ],
            [
                'participant_id' => (string) $secondaryMembership->id,
                'email' => $secondaryMembership->user?->email,
                'role' => 'Member',
            ],
        ],
        'metrics' => $templates->map(function (MetricTemplate $template, int $index) use ($ownerMembership, $secondaryMembership) {
            return [
                'template_id' => (string) $template->id,
                'canonical_metric_id' => (string) $template->canonical_metric_id,
                'key' => sprintf('metric-%02d', $index + 1),
                'name' => 'Metric '.($index + 1),
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $secondaryMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ];
        })->values()->all(),
        'invited_emails' => ['observer@example.com'],
    ];

    $this->actingAs($owner);

    $this->postJson('/api/engagements', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['metrics']);

    Carbon::setTestNow();
});

it('rejects metrics that lack contributors or exceed observer capacity', function () {
    Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00'));

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->create();

    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);

    $secondaryMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $templates = MetricTemplate::factory()->count(5)->create();

    $observerEmails = collect(range(1, 6))->map(fn (int $index) => "observer{$index}@example.com");

    $stakeholders = $observerEmails->map(fn (string $email) => [
        'email' => $email,
        'role' => 'Viewer',
    ])->prepend([
        'participant_id' => (string) $ownerMembership->id,
        'email' => $owner->email,
        'role' => 'Admin',
    ])->values()->all();

    $metrics = [];

    $metrics[] = [
        'template_id' => (string) $templates[0]->id,
        'canonical_metric_id' => (string) $templates[0]->canonical_metric_id,
        'key' => 'metric-01',
        'name' => 'Metric 1',
        'participants' => [
            ['participant_id' => (string) $ownerMembership->id, 'role' => 'Observer'],
        ],
    ];

    $metrics[] = [
        'template_id' => (string) $templates[1]->id,
        'canonical_metric_id' => (string) $templates[1]->canonical_metric_id,
        'key' => 'metric-02',
        'name' => 'Metric 2',
        'participants' => collect($observerEmails)->map(fn (string $email) => [
            'participant_id' => "invite:{$email}",
            'role' => 'Observer',
        ])->prepend([
            'participant_id' => (string) $ownerMembership->id,
            'role' => 'Contributor',
        ])->prepend([
            'participant_id' => (string) $secondaryMembership->id,
            'role' => 'Contributor',
        ])->prepend([
            'participant_id' => (string) $ownerMembership->id,
            'role' => 'Approver',
        ])->values()->all(),
    ];

    foreach ($templates->slice(2)->values() as $offset => $template) {
        $metrics[] = [
            'template_id' => (string) $template->id,
            'canonical_metric_id' => (string) $template->canonical_metric_id,
            'key' => sprintf('metric-%02d', $offset + 3),
            'name' => 'Metric '.($offset + 3),
            'participants' => [
                ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                ['participant_id' => (string) $secondaryMembership->id, 'role' => 'Contributor'],
                ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                ['participant_id' => 'invite:'.$observerEmails->first(), 'role' => 'Observer'],
            ],
        ];
    }

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Invalid Participants',
        'timeline_start' => Carbon::now()->toDateString(),
        'timeline_end' => Carbon::now()->addWeeks(8)->toDateString(),
        'stakeholders' => $stakeholders,
        'metrics' => $metrics,
    ];

    $this->actingAs($owner);

    $this->postJson('/api/engagements', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'metrics.0.participants',
            'metrics.1.participants',
        ]);

    Carbon::setTestNow();
});
