<?php

use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds org metric owners and approvers from engagement participants', function () {
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
    $primaryTemplate = $templates->first();

    $payload = [
        'organization_id' => (string) $organization->id,
        'name' => 'Participant Seeding',
        'timeline_start' => now()->toDateString(),
        'timeline_end' => now()->addWeeks(4)->toDateString(),
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
                'email' => 'approver@example.com',
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
                    ['participant_id' => 'invite:approver@example.com', 'role' => 'Approver'],
                    ['participant_id' => 'invite:observer@example.com', 'role' => 'Observer'],
                ],
            ];
        })->values()->all(),
        'invited_emails' => ['approver@example.com', 'observer@example.com'],
    ];

    $this->actingAs($owner)
        ->postJson('/api/engagements', $payload)
        ->assertStatus(201);

    $engagement = Engagement::query()->latest()->firstOrFail();

    $metric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('metric_template_id', $primaryTemplate->id)
        ->firstOrFail();

    $orgMetric = OrgMetric::query()
        ->where('organization_id', $organization->id)
        ->where('canonical_metric_id', $primaryTemplate->canonical_metric_id)
        ->firstOrFail();

    $pendingApprover = PendingMembership::query()
        ->where('organization_id', $organization->id)
        ->where('email', 'approver@example.com')
        ->firstOrFail();

    $participants = OrgMetricParticipant::query()
        ->where('org_metric_id', $orgMetric->id)
        ->get()
        ->groupBy('responsibility');

    expect($participants->get('owner'))->toHaveCount(1);
    expect($participants->get('approver'))->toHaveCount(1);

    $owner = $participants->get('owner')?->first();

    expect($owner?->subject_type)->toBe(Membership::class)
        ->and(in_array(
            (string) $owner?->subject_id,
            [(string) $ownerMembership->id, (string) $memberMembership->id],
            true
        ))->toBeTrue()
        ->and($owner?->user_id)->not->toBeNull();

    $approver = $participants->get('approver')?->first();

    expect($approver?->subject_type)->toBe(PendingMembership::class)
        ->and($approver?->subject_id)->toBe($pendingApprover->id)
        ->and($approver?->user_id)->toBeNull();

    expect($metric->org_metric_id)->toBe($orgMetric->id);
});
