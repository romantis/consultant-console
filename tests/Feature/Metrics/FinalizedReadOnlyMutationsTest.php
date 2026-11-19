<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks final definition updates when engagement is finalized', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
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

    $response = $this->actingAs($membership->user)->putJson(
        route('org.metrics.final-definition.update', [$organization, $metric]),
        [
            'definition' => [
                ['field' => 'summary', 'label' => 'Summary', 'value' => 'Locked'],
            ],
        ]
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    expect(FinalDefinition::query()->where('metric_id', $metric->id)->exists())->toBeFalse();
});

it('blocks metric sign off when engagement is finalized', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);

    MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    $response = $this->actingAs($membership->user)->postJson(
        route('org.metrics.sign-off', [$organization, $engagement, $metric])
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');
});
