<?php

use App\Enums\MetricStatus;
use App\Models\ChangeProposal;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, metric: Metric, user: User}
 */
function makeChangeProposalFeatureContext(): array
{
    $organization = Organization::factory()->create();

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $membership = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    return [
        'organization' => $organization,
        'metric' => $metric,
        'user' => $membership->user,
    ];
}

it('returns not found for proposal creation when the feature is disabled', function () {
    config()->set('metric-harmony.features.change_proposals', false);

    ['organization' => $organization, 'metric' => $metric, 'user' => $user] = makeChangeProposalFeatureContext();

    $this->actingAs($user)
        ->post(route('org.metrics.change-proposals.store', [$organization, $metric]), [
            'rationale' => 'Need updated narrative',
            'payload' => ['summary' => ['label' => 'Summary', 'value' => 'Updated']],
            'approver_ids' => ['approver-1'],
        ])
        ->assertNotFound();
});

it('returns not found for proposal decision when the feature is disabled', function () {
    config()->set('metric-harmony.features.change_proposals', false);

    ['metric' => $metric, 'user' => $user] = makeChangeProposalFeatureContext();

    $proposal = ChangeProposal::factory()
        ->for($metric)
        ->create();

    $this->actingAs($user)
        ->postJson("/api/change-proposals/{$proposal->getKey()}/decision", [
            'decision' => 'approve',
        ])
        ->assertNotFound();
});

it('returns not found for proposal merge when the feature is disabled', function () {
    config()->set('metric-harmony.features.change_proposals', false);

    ['metric' => $metric, 'user' => $user] = makeChangeProposalFeatureContext();

    $proposal = ChangeProposal::factory()
        ->for($metric)
        ->approved()
        ->create();

    $this->actingAs($user)
        ->postJson("/api/change-proposals/{$proposal->getKey()}/merge")
        ->assertNotFound();
});

it('allows proposal endpoints to proceed when the feature is enabled', function () {
    config()->set('metric-harmony.features.change_proposals', true);

    ['metric' => $metric, 'user' => $user] = makeChangeProposalFeatureContext();

    $this->actingAs($user)
        ->postJson("/api/metrics/{$metric->getKey()}/change-proposals", [])
        ->assertStatus(422);
});
