<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\ReconciliationWorkshop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('records workshop executions with same-engagement session linkage and updates metric status', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $facilitatorMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $participantMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $participantMembership->id,
        'membership_role' => MembershipRole::MEMBER,
    ]);

    $scheduledAt = now()->addDay()->toIso8601String();

    $response = $this->actingAs($user)->postJson(
        route('api.metrics.workshop-executions.store', $metric),
        [
            'session_id' => (string) $session->id,
            'scheduled_at' => $scheduledAt,
            'facilitator_id' => (string) $facilitatorMembership->id,
            'participants' => [(string) $participant->id],
            'notes' => 'Reviewed revenue attribution deltas.',
            'decisions' => [
                'consensus' => 'Adopt GAAP timeline',
                'follow_up' => ['Finance to share updated workbook'],
            ],
        ]
    );

    $response->assertCreated()
        ->assertJsonPath('data.metric_id', (string) $metric->id)
        ->assertJsonPath('data.session_id', (string) $session->id)
        ->assertJsonPath('data.facilitator.id', (string) $facilitatorMembership->id)
        ->assertJsonPath('data.participants.0.id', (string) $participant->id)
        ->assertJsonPath('data.decisions.consensus', 'Adopt GAAP timeline');

    $workshop = ReconciliationWorkshop::query()->firstOrFail();
    expect((string) $workshop->metric_id)->toBe((string) $metric->id)
        ->and((string) $workshop->engagement_id)->toBe((string) $engagement->id)
        ->and((string) $workshop->session_id)->toBe((string) $session->id)
        ->and($workshop->participants)->toHaveCount(1);

    $expectedScheduledAt = Carbon::parse($scheduledAt)->utc();
    $actualScheduledAt = $workshop->scheduled_at?->copy()->utc();
    expect($actualScheduledAt?->toIso8601String())->toBe($expectedScheduledAt->toIso8601String());

    $metric->refresh();
    expect($metric->status)->toBe(MetricStatus::IN_WORKSHOP);

    $log = AuditLog::query()
        ->where('action', 'metric.workshop.recorded')
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metric_id)->toBe((string) $metric->id)
        ->and($log->engagement_id)->toBe((string) $engagement->id)
        ->and($log->properties['workshop_id'] ?? null)->toBe((string) $workshop->id)
        ->and($log->properties['session_id'] ?? null)->toBe((string) $session->id)
        ->and($log->subject_type)->toBe(ReconciliationWorkshop::class);
});

it('requires review permission to record workshop executions', function () {
    $user = User::factory()->create();
    $metric = Metric::factory()->create();

    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $metric->organization_id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $metric->organization_id,
        'engagement_id' => $metric->engagement_id,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.metrics.workshop-executions.store', $metric), [
            'session_id' => (string) $session->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'facilitator_id' => (string) $facilitator->id,
        ])
        ->assertForbidden();
});

it('requires session id when recording workshop executions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.metrics.workshop-executions.store', $metric), [
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'facilitator_id' => (string) $facilitator->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id']);
});

it('rejects session ids from a different engagement', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $otherEngagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $crossEngagementSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $otherEngagement->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('api.metrics.workshop-executions.store', $metric), [
            'session_id' => (string) $crossEngagementSession->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'facilitator_id' => (string) $facilitator->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session_id']);
});
