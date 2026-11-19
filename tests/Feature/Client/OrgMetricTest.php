<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('renders the org metric page for engagement members', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::IN_REVIEW])
        ->create();

    FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Signed-off definition'],
            ],
        ]);

    OrgFinalDefinition::factory()
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => 1,
            'alignment_summary' => 'Prior alignment summary.',
            'operational_status' => 'operationally_ready',
            'operational_status_notes' => 'Prior note.',
            'implementation_gaps' => null,
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $user->id,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Prior official definition'],
            ],
        ]);

    $orgDefinition = OrgFinalDefinition::factory()
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => 2,
            'alignment_summary' => 'Finance and operations aligned on one signed story.',
            'operational_status' => 'needs_follow_up',
            'operational_status_notes' => 'Dashboard filter still needs a tenant exclusion fix.',
            'validation_steps' => 'Compare workbook totals to the signed board pack.',
            'common_mismatch_causes' => 'Late backfills and tenant filters can drift.',
            'implementation_gaps' => [
                'current_state' => 'Board pack still comes from a workbook export.',
                'gap' => 'Internal tenants remain in the dashboard.',
                'owner' => 'Revenue ops',
                'suggested_next_step' => 'Ship the exclusion before the next close.',
            ],
            'signed_off_at' => now()->subHour(),
            'signed_off_by' => $user->id,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Signed-off definition'],
            ],
        ]);

    $this->actingAs($user)
        ->get(route('client.handbook.metrics.page', [$organization, $engagement, $metric->org_metric_id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Metric')
            ->where('detail.org_metric.id', (string) $metric->org_metric_id)
            ->where('detail.engagement_metric.metric.id', (string) $metric->id)
            ->where('detail.engagement_metric.metric.status', MetricStatus::IN_REVIEW->value)
            ->where('detail.engagement_metric.metric.last_submitted_at', optional($metric->last_submitted_at)->toIso8601String())
            ->has('detail.engagement_metric.metric.field_roles')
            ->missing('detail.engagement_metric.metric.matrix')
            ->missing('detail.engagement_metric.metric.context_fields')
            ->missing('detail.engagement_metric.permissions')
            ->missing('detail.engagement_metric.form_assignments')
            ->where('detail.official_definition.id', (string) $orgDefinition->id)
            ->where('detail.official_definition.alignment_summary', 'Finance and operations aligned on one signed story.')
            ->where('detail.official_definition.operational_status', 'needs_follow_up')
            ->where('detail.official_definition.operational_status_notes', 'Dashboard filter still needs a tenant exclusion fix.')
            ->where('detail.official_definition.validation_steps', 'Compare workbook totals to the signed board pack.')
            ->where('detail.official_definition.common_mismatch_causes', 'Late backfills and tenant filters can drift.')
            ->where('detail.official_definition.implementation_gaps.owner', 'Revenue ops')
            ->has('detail.official_definition_history', 1)
            ->where('detail.official_definition_history.0.version', 1)
            ->where('detail.official_definition_history.0.signed_off_by.name', $user->name)
            ->where('detail.executive_sections.0.key', 'official_definition')
            ->where('detail.executive_sections.0.items.0.value', 'Signed-off definition')
            // The private facilitator readiness pack is operator-only and must
            // never reach a client-facing metric surface (spec 037 Task 4).
            ->missing('facilitator_readiness_pack')
        );
});

it('returns not found when the engagement does not belong to the organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($otherOrganization)->create();
    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for(Engagement::factory()->for($organization))
        ->create();

    $this->actingAs($user)
        ->get(route('client.handbook.metrics.page', [$organization, $engagement, $metric->org_metric_id]))
        ->assertNotFound();
});

it('omits empty official definition fields from the client handbook metric view', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $user = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::IN_REVIEW])
        ->create();

    FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Signed-off definition'],
                'validation_steps' => ['label' => 'Validation Steps', 'value' => ''],
                'common_mismatch_causes' => ['label' => 'Common Mismatch Causes', 'value' => null],
            ],
        ]);

    $orgDefinition = OrgFinalDefinition::factory()
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'signed_off_at' => now()->subHour(),
            'validation_steps' => '',
            'common_mismatch_causes' => null,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Signed-off definition'],
                'validation_steps' => ['label' => 'Validation Steps', 'value' => ''],
                'common_mismatch_causes' => ['label' => 'Common Mismatch Causes', 'value' => null],
            ],
        ]);

    $this->actingAs($user)
        ->get(route('client.handbook.metrics.page', [$organization, $engagement, $metric->org_metric_id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Metric')
            ->where('detail.official_definition.id', (string) $orgDefinition->id)
            ->where('detail.executive_sections.0.items.0.key', 'summary')
            ->missing('detail.executive_sections.0.items.1')
        );
});
