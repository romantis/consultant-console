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

it('prevents moving engagements to awaiting forms when org metrics conflict with in-progress usage', function () {
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();
    $owner = User::factory()->create();
    $ownerMembership = Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => $internal->id,
        'user_id' => $owner->id,
    ]);

    $canonical = CanonicalMetric::factory()->create([
        'key' => 'retention',
        'name' => 'Retention',
    ]);
    $template = MetricTemplate::factory()
        ->for($canonical, 'canonicalMetric')
        ->create([
            'key' => 'retention-template',
            'name' => 'Retention Standard',
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
        'key' => 'retention',
    ]);

    $draftEngagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::DRAFT,
    ]);

    Metric::factory()->for($draftEngagement)->create([
        'organization_id' => $organization->id,
        'metric_template_id' => $template->id,
        'org_metric_id' => $orgMetric->id,
        'key' => 'retention-draft',
    ]);

    $this->actingAs($ownerMembership->user)
        ->patchJson(
            route('org.engagements.status.update', [$organization, $draftEngagement]),
            ['status' => EngagementStatus::AWAITING_FORMS->value]
        )
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    expect($draftEngagement->refresh()->status)->toBe(EngagementStatus::DRAFT);
});
