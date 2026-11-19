<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prevents updates after sign off', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $membership->user_id,
    ]);

    $this->actingAs($membership->user)
        ->put(route('org.metrics.final-definition.update', [$organization, $metric]), [
            'definition' => [
                ['field' => 'summary', 'label' => 'Summary', 'value' => 'New value'],
            ],
        ])
        ->assertForbidden();
});
