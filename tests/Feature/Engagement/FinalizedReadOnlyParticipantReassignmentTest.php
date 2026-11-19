<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks metric participant reassignment when engagement is finalized', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $originalMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);
    $targetMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $originalParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $originalMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);
    $targetParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $targetMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $metricParticipant = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $originalMembership->user_id,
        'subject_type' => Membership::class,
        'subject_id' => $originalMembership->id,
    ]);

    $response = $this->actingAs($admin)->patchJson(
        route('org.engagements.metric-participants.update', [
            $organization,
            $engagement,
            $metric,
            $metricParticipant,
        ]),
        [
            'engagement_participant_id' => $targetParticipant->id,
            'responsibility' => 'contributor',
            'role_tags' => [],
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    expect($metricParticipant->fresh()->engagement_participant_id)->toBe($originalParticipant->id);
});
