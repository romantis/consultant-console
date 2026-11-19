<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\CanonicalMetric;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('metric-harmony.features.org_metric_overrides', true);
});

function createOrgMetricStaffUser(Organization $organization): User
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

it('shows org metric detail payload with alias and template variants', function () {
    $organization = Organization::factory()->create();
    $user = createOrgMetricStaffUser($organization);

    $canonical = CanonicalMetric::factory()->create([
        'key' => 'mrr',
        'name' => 'Monthly Recurring Revenue',
        'description' => 'The recurring revenue base per month.',
    ]);

    $orgMetric = OrgMetric::factory()->create([
        'organization_id' => $organization->id,
        'canonical_metric_id' => $canonical->id,
        'key' => $canonical->key,
        'name' => $canonical->name,
        'alias' => 'MRR',
    ]);

    $templatePrimary = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonical->id,
        'key' => 'mrr-growth',
        'name' => 'MRR Growth',
    ]);

    $templateSecondary = MetricTemplate::factory()->create([
        'canonical_metric_id' => $canonical->id,
        'key' => 'mrr-retention',
        'name' => 'MRR Retention',
    ]);

    $engagement = Engagement::factory()
        ->for($organization)
        ->create([
            'status' => EngagementStatus::AWAITING_FORMS->value,
            'title' => 'Q1 Engagement',
        ]);

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'org_metric_id' => $orgMetric->id,
            'metric_template_id' => $templatePrimary->id,
            'status' => MetricStatus::IN_REVIEW,
        ]);

    $approver = User::factory()->create(['name' => 'Ava Approver']);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $templatePrimary->id,
        'status' => 'Approved',
        'version' => 3,
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $approver->id,
    ]);

    $ownerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $approverMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'user_id' => $ownerMembership->user_id,
        'responsibility' => 'owner',
        'subject_type' => Membership::class,
        'subject_id' => $ownerMembership->id,
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'user_id' => $approverMembership->user_id,
        'responsibility' => 'approver',
        'subject_type' => Membership::class,
        'subject_id' => $approverMembership->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.org-metrics.show', [$organization, $orgMetric]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('OrgMetrics/Show')
            ->where('organization.id', (string) $organization->id)
            ->where('org_metric.id', (string) $orgMetric->id)
            ->where('org_metric.alias', 'MRR')
            ->where('org_metric.canonical.name', 'Monthly Recurring Revenue')
            ->where('active_engagement.id', (string) $engagement->id)
            ->where('active_engagement.metric.id', (string) $metric->id)
            ->has('template_variants', 2)
            ->where('template_variants', function ($variants) use ($templatePrimary, $templateSecondary) {
                if ($variants instanceof Collection) {
                    $variants = $variants->all();
                }

                $collection = collect($variants);
                $primary = $collection->firstWhere('id', (string) $templatePrimary->id);
                $secondary = $collection->firstWhere('id', (string) $templateSecondary->id);

                expect($primary['name'] ?? null)->toBe('MRR Growth')
                    ->and($secondary['name'] ?? null)->toBe('MRR Retention');

                return true;
            })
            ->has('participants.owners', 1)
            ->has('participants.approvers', 1)
            ->where('official_definition.signed_off_by.id', (string) $approver->id)
        );
});

it('updates org metric alias and logs audit event', function () {
    $organization = Organization::factory()->create();
    $user = createOrgMetricStaffUser($organization);

    $canonical = CanonicalMetric::factory()->create([
        'key' => 'cac',
        'name' => 'Customer Acquisition Cost',
    ]);

    $orgMetric = OrgMetric::factory()->create([
        'organization_id' => $organization->id,
        'canonical_metric_id' => $canonical->id,
        'key' => $canonical->key,
        'name' => $canonical->name,
        'alias' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('org.org-metrics.update', [$organization, $orgMetric]), [
            'alias' => 'CAC',
        ])
        ->assertRedirect(route('org.org-metrics.show', [$organization, $orgMetric]));

    $orgMetric->refresh();

    expect($orgMetric->alias)->toBe('CAC');
    expect(AuditLog::query()
        ->where('subject_id', $orgMetric->id)
        ->where('action', 'org-metric.alias.updated')
        ->exists()
    )->toBeTrue();
});
