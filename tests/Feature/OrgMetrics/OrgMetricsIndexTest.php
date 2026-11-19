<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createOrgMetricsStaffUser(Organization $organization): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

it('shows org metrics list rollups for staff', function () {
    $organization = Organization::factory()->create();
    $user = createOrgMetricsStaffUser($organization);

    $canonical = CanonicalMetric::factory()->create([
        'key' => 'mrr',
        'name' => 'Monthly Recurring Revenue',
    ]);

    $orgMetric = OrgMetric::factory()->create([
        'organization_id' => $organization->id,
        'canonical_metric_id' => $canonical->id,
        'key' => $canonical->key,
        'name' => $canonical->name,
        'alias' => 'MRR',
    ]);

    $template = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonical->id,
        'key' => $canonical->key,
        'name' => $canonical->name,
    ]);

    $activeEngagement = Engagement::factory()
        ->for($organization)
        ->create([
            'status' => EngagementStatus::AWAITING_FORMS->value,
            'title' => 'Q1 Engagement',
        ]);

    $metric = Metric::factory()
        ->for($organization)
        ->for($activeEngagement)
        ->create([
            'org_metric_id' => $orgMetric->id,
            'metric_template_id' => $template->id,
            'key' => $canonical->key,
            'name' => $canonical->name,
        ]);

    $approver = User::factory()->create(['name' => 'Ava Approver']);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $template->id,
        'status' => 'Approved',
        'version' => 2,
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $approver->id,
    ]);

    $canonicalSecond = CanonicalMetric::factory()->create([
        'key' => 'nrr',
        'name' => 'Net Revenue Retention',
    ]);

    $orgMetricSecond = OrgMetric::factory()->create([
        'organization_id' => $organization->id,
        'canonical_metric_id' => $canonicalSecond->id,
        'key' => $canonicalSecond->key,
        'name' => $canonicalSecond->name,
        'alias' => null,
    ]);

    $templateSecond = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonicalSecond->id,
        'key' => $canonicalSecond->key,
        'name' => $canonicalSecond->name,
    ]);

    $pastEngagement = Engagement::factory()
        ->for($organization)
        ->create([
            'status' => EngagementStatus::FINALIZED->value,
            'title' => 'Legacy Engagement',
        ]);

    Metric::factory()
        ->for($organization)
        ->for($pastEngagement)
        ->create([
            'org_metric_id' => $orgMetricSecond->id,
            'metric_template_id' => $templateSecond->id,
            'key' => $canonicalSecond->key,
            'name' => $canonicalSecond->name,
        ]);

    $this->actingAs($user)
        ->get(route('org.org-metrics.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrgMetrics/Index')
            ->where('organization.id', (string) $organization->id)
            ->has('metrics', 2)
            ->where('metrics', function ($metrics) use ($orgMetric, $orgMetricSecond, $activeEngagement, $approver) {
                if ($metrics instanceof Collection) {
                    $metrics = $metrics->all();
                }

                $collection = collect($metrics);
                $primary = $collection->firstWhere('id', (string) $orgMetric->id);
                $secondary = $collection->firstWhere('id', (string) $orgMetricSecond->id);

                expect($primary['alias'] ?? null)->toBe('MRR')
                    ->and($primary['canonical_name'] ?? null)->toBe('Monthly Recurring Revenue')
                    ->and($primary['active_engagement']['id'] ?? null)->toBe((string) $activeEngagement->id)
                    ->and($primary['latest_sign_off']['signed_off_by']['id'] ?? null)->toBe((string) $approver->id)
                    ->and($primary['past_engagements_count'] ?? null)->toBe(0);

                expect($secondary['alias'] ?? null)->toBeNull()
                    ->and($secondary['canonical_name'] ?? null)->toBe('Net Revenue Retention')
                    ->and($secondary['active_engagement'] ?? null)->toBeNull()
                    ->and($secondary['past_engagements_count'] ?? null)->toBe(1)
                    ->and($secondary['latest_sign_off'] ?? null)->toBeNull();

                return true;
            })
        );
});
