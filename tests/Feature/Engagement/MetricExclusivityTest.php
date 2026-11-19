<?php

use App\Enums\EngagementStatus;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('blocks engagement launch when a metric is already in an in-progress engagement', function () {
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);
    $memberMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $canonical = CanonicalMetric::factory()->create([
        'key' => 'mrr',
        'name' => 'Monthly Recurring Revenue',
    ]);
    $template = MetricTemplate::factory()
        ->for($canonical, 'canonicalMetric')
        ->create([
            'key' => 'mrr-template',
            'name' => 'MRR Standard',
        ]);

    $orgMetric = OrgMetric::factory()->create([
        'organization_id' => $organization->id,
        'canonical_metric_id' => $canonical->id,
        'key' => $canonical->key,
        'name' => $canonical->name,
        'slug' => Str::slug($canonical->name),
    ]);

    $inProgressEngagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    Metric::factory()->for($inProgressEngagement)->create([
        'organization_id' => $organization->id,
        'metric_template_id' => $template->id,
        'org_metric_id' => $orgMetric->id,
        'key' => 'mrr',
    ]);

    $otherTemplates = MetricTemplate::factory()->count(2)->create();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Conflict Launch',
        'timeline_start' => now()->toDateString(),
        'timeline_end' => now()->addWeek()->toDateString(),
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
            ],
            [
                'participant_id' => (string) $memberMembership->id,
                'email' => $memberMembership->user?->email,
                'role' => 'Member',
            ],
            [
                'email' => 'observer@example.com',
                'role' => 'Viewer',
            ],
        ],
        'metrics' => [
            [
                'canonical_metric_id' => (string) $canonical->id,
                'template_id' => (string) $template->id,
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $memberMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ],
            [
                'canonical_metric_id' => (string) $otherTemplates[0]->canonical_metric_id,
                'template_id' => (string) $otherTemplates[0]->id,
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $memberMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ],
            [
                'canonical_metric_id' => (string) $otherTemplates[1]->canonical_metric_id,
                'template_id' => (string) $otherTemplates[1]->id,
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $memberMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ],
        ],
        'invited_emails' => ['observer@example.com'],
    ];

    $this->actingAs($owner)
        ->postJson('/api/engagements', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['metrics.0.canonical_metric_id']);
});

it('upserts org metrics for newly selected canonical metrics on launch', function () {
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);
    $memberMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $templates = MetricTemplate::factory()->count(3)->create();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Org Metric Upsert',
        'timeline_start' => now()->toDateString(),
        'timeline_end' => now()->addWeeks(2)->toDateString(),
        'stakeholders' => [
            [
                'participant_id' => (string) $ownerMembership->id,
                'email' => $owner->email,
                'role' => 'Admin',
            ],
            [
                'participant_id' => (string) $memberMembership->id,
                'email' => $memberMembership->user?->email,
                'role' => 'Member',
            ],
            [
                'email' => 'observer@example.com',
                'role' => 'Viewer',
            ],
        ],
        'metrics' => $templates->map(function (MetricTemplate $template) use ($ownerMembership, $memberMembership) {
            return [
                'canonical_metric_id' => (string) $template->canonical_metric_id,
                'template_id' => (string) $template->id,
                'participants' => [
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $memberMembership->id, 'role' => 'Contributor'],
                    ['participant_id' => (string) $ownerMembership->id, 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ];
        })->values()->all(),
        'invited_emails' => ['observer@example.com'],
    ];

    $this->actingAs($owner)
        ->postJson('/api/engagements', $payload)
        ->assertStatus(201);

    $engagement = Engagement::query()->latest()->firstOrFail();

    $orgMetrics = OrgMetric::query()
        ->where('organization_id', $organization->id)
        ->get()
        ->keyBy(fn (OrgMetric $orgMetric) => (string) $orgMetric->canonical_metric_id);

    expect($orgMetrics)->toHaveCount(3);

    $metrics = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->with('orgMetric')
        ->get();

    expect($metrics)->toHaveCount(3);

    $metrics->each(function (Metric $metric) use ($orgMetrics) {
        expect($metric->org_metric_id)->not->toBeNull();
        expect($orgMetrics)->toHaveKey((string) $metric->orgMetric?->canonical_metric_id);
    });
});
