<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, orgMetric: OrgMetric, user: User}
 */
function makeOrgMetricOverridesContext(): array
{
    $orgMetric = OrgMetric::factory()->create();
    $organization = $orgMetric->organization;

    $membership = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    return [
        'organization' => $organization,
        'orgMetric' => $orgMetric,
        'user' => $membership->user,
    ];
}

it('returns not found for org metric override endpoints when the feature is disabled', function () {
    config()->set('metric-harmony.features.org_metric_overrides', false);

    ['organization' => $organization, 'orgMetric' => $orgMetric, 'user' => $user] = makeOrgMetricOverridesContext();

    $this->actingAs($user)
        ->patch(route('org.org-metrics.update', [$organization, $orgMetric]), [
            'alias' => 'Override Alias',
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('org.org-metrics.default-template.update', [$organization, $orgMetric]), [
            'default_metric_template_id' => null,
        ])
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('org.org-metrics.participants.update', [$organization, $orgMetric]), [
            'owners' => [],
            'approvers' => [],
        ])
        ->assertNotFound();
});

it('allows org metric alias updates when the feature is enabled', function () {
    config()->set('metric-harmony.features.org_metric_overrides', true);

    ['organization' => $organization, 'orgMetric' => $orgMetric, 'user' => $user] = makeOrgMetricOverridesContext();

    $this->actingAs($user)
        ->patch(route('org.org-metrics.update', [$organization, $orgMetric]), [
            'alias' => 'Enabled Alias',
        ])
        ->assertRedirect(route('org.org-metrics.show', [$organization, $orgMetric]));

    $orgMetric->refresh();

    expect($orgMetric->alias)->toBe('Enabled Alias');
});
