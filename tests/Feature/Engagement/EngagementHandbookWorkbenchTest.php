<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\ReconciliationWorkshop;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['console.internal_org_slug' => 'metric-harmony']);
});

it('forbids access to guest users', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $response = $this->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertRedirect(route('login'));
});

it('forbids access to unauthorized organization members (client users)', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(403);
});

it('allows access to staff owner and staff admin', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
});

it('allows access to assigned operators, but forbids non-assigned operators', function () {
    $operator = User::factory()->create();
    $otherOperator = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $operator->id,
        'role' => MembershipRole::MEMBER,
    ]);
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $otherOperator->id,
        'role' => MembershipRole::MEMBER,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $organization->id,
    ]);

    $response1 = $this->actingAs($operator)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));
    $response1->assertStatus(200);

    $response2 = $this->actingAs($otherOperator)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));
    $response2->assertStatus(403);
});

it('returns 404 on organization/engagement mismatch', function () {
    $user = User::factory()->create();
    $organization1 = Organization::factory()->create();
    $organization2 = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization2->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization1, $engagement]));

    $response->assertStatus(404);
});

it('includes metrics payload with correct readiness flags and official signals', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric1 = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric1 = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric1->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Metric A',
    ]);

    $orgMetric2 = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric2 = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric2->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Metric B',
    ]);

    $metric3 = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_WORKSHOP,
        'name' => 'Metric C',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric1->id,
        'source_metric_id' => $metric1->id,
        'signed_off_at' => now(),
        'executive_takeaway' => 'This is a sample takeaway for Metric A',
        'operational_status' => 'operationally_ready',
        'validation_steps' => 'Verify query outputs',
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('organization', fn (AssertableInertia $org) => $org
            ->where('id', $organization->id)
            ->where('name', $organization->name)
        )
        ->has('engagement', fn (AssertableInertia $eng) => $eng
            ->where('id', $engagement->id)
            ->where('title', $engagement->title)
            ->where('status', $engagement->status->value)
        )
        ->has('metrics', 3)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('id', $metric1->id)
            ->where('org_metric_id', $orgMetric1->id)
            ->where('name', 'Metric A')
            ->where('is_signed_off', true)
            ->where('signals.executive_takeaway', 'This is a sample takeaway for Metric A')
            ->where('signals.operational_status', 'operationally_ready')
            ->where('signals.validation_steps', 'Verify query outputs')
            ->etc()
        )
        ->has('metrics.1', fn (AssertableInertia $m) => $m
            ->where('id', $metric2->id)
            ->where('org_metric_id', $orgMetric2->id)
            ->where('name', 'Metric B')
            ->where('is_signed_off', false)
            ->where('signals', null)
            ->etc()
        )
        ->has('metrics.2', fn (AssertableInertia $m) => $m
            ->where('id', $metric3->id)
            ->where('org_metric_id', $metric3->org_metric_id)
            ->where('name', 'Metric C')
            ->where('is_signed_off', false)
            ->where('signals', null)
            ->etc()
        )
    );
});

it('selects the latest signed revision and correctly serializes structured signals', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Test Metric',
    ]);

    // Create an older signed definition (signed off 2 days ago)
    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'version' => 1,
        'signed_off_at' => now()->subDays(2),
        'signed_off_by' => $user->id,
        'executive_takeaway' => 'Old Takeaway',
        'operational_status' => 'needs_follow_up',
        'implementation_gaps' => [
            'current_state' => 'Old state',
            'gap' => 'Old gap',
            'owner' => 'Old owner',
            'suggested_next_step' => 'Old step',
        ],
    ]);

    // Create a newer signed definition (signed off 1 hour ago)
    $latestDefinition = OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'version' => 2,
        'signed_off_at' => now()->subHours(1),
        'signed_off_by' => $user->id,
        'executive_takeaway' => 'New Takeaway',
        'operational_status' => 'operationally_ready',
        'implementation_gaps' => [
            'current_state' => 'New state',
            'gap' => 'New gap',
            'owner' => 'New owner',
            'suggested_next_step' => 'New step',
        ],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('id', $metric->id)
            ->where('org_metric_id', $orgMetric->id)
            ->where('name', 'Test Metric')
            ->where('is_signed_off', true)
            ->where('signals.org_final_definition_id', $latestDefinition->id)
            ->where('signals.version', 2)
            ->where('signals.signed_off_by.name', 'John Doe')
            ->where('signals.executive_takeaway', 'New Takeaway')
            ->where('signals.operational_status', 'operationally_ready')
            ->where('signals.implementation_gaps.current_state', 'New state')
            ->where('signals.implementation_gaps.gap', 'New gap')
            ->where('signals.implementation_gaps.owner', 'New owner')
            ->where('signals.implementation_gaps.suggested_next_step', 'New step')
            ->etc()
        )
    );
});

it('correctly maps the readiness checklist for different metric states', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric1 = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric1 = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric1->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Metric 1',
    ]);

    // Fully ready definition
    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric1->id,
        'source_metric_id' => $metric1->id,
        'version' => 1,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'executive_takeaway' => 'Takeaway',
        'alignment_summary' => 'Alignment details',
        'operational_status' => 'operationally_ready',
        'validation_steps' => 'Steps',
        'common_mismatch_causes' => 'Causes',
    ]);

    $orgMetric2 = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric2 = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric2->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Metric 2',
    ]);

    // Partially missing definition
    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric2->id,
        'source_metric_id' => $metric2->id,
        'version' => 1,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'executive_takeaway' => '', // missing
        'alignment_summary' => '', // missing
        'operational_status' => 'needs_follow_up',
        'implementation_gaps' => [
            'current_state' => 'State',
            'gap' => 'Gap',
            // owner is missing
            'suggested_next_step' => 'Step',
        ],
        'validation_steps' => 'Steps',
        'common_mismatch_causes' => '', // missing
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'Metric 1')
            ->where('readiness_checklist.signed_definition.status', 'ready')
            ->where('readiness_checklist.approval_version.status', 'ready')
            ->where('readiness_checklist.executive_takeaway.status', 'ready')
            ->where('readiness_checklist.executive_alignment.status', 'ready')
            ->where('readiness_checklist.implementation_review.status', 'ready')
            ->where('readiness_checklist.validation_guidance.status', 'ready')
            ->etc()
        )
        ->has('metrics.1', fn (AssertableInertia $m) => $m
            ->where('name', 'Metric 2')
            ->where('readiness_checklist.signed_definition.status', 'ready')
            ->where('readiness_checklist.approval_version.status', 'ready')
            ->where('readiness_checklist.executive_takeaway.status', 'missing')
            ->where('readiness_checklist.executive_alignment.status', 'missing')
            ->where('readiness_checklist.implementation_review.status', 'missing')
            ->where('readiness_checklist.validation_guidance.status', 'missing')
            ->etc()
        )
    );
});

it('includes source_context with signed definition sections for signed metrics', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Revenue Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'version' => 1,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'notes' => 'Signed after workshop alignment.',
        'definition_json' => [
            'revenue_definition' => [
                'label' => 'Revenue Definition',
                'value' => 'Total recognized ARR excluding one-time fees.',
            ],
        ],
        'executive_takeaway' => 'Revenue is the primary north star.',
        'alignment_summary' => 'All functions aligned.',
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'Revenue Metric')
            ->where('is_signed_off', true)
            ->where('source_context.signed_definition.version', 1)
            ->where('source_context.signed_definition.signed_off_by.name', 'Jane Operator')
            ->where('source_context.signed_definition.notes', 'Signed after workshop alignment.')
            ->has('source_context.signed_definition.sections')
            ->etc()
        )
    );
});

it('returns null source_context for unsigned metrics', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_WORKSHOP,
        'name' => 'Unsigned Metric',
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'Unsigned Metric')
            ->where('is_signed_off', false)
            ->where('source_context', null)
            ->etc()
        )
    );
});

it('returns empty sections array when definition_json is empty', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Empty Def Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'Empty Def Metric')
            ->where('is_signed_off', true)
            ->where('source_context.signed_definition.sections', [])
            ->etc()
        )
    );
});

it('resolves option values to human-readable labels in source_context sections', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    // Create a template with option schemas
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'acquisition_point',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'sales_qualified', 'label' => 'Sales Qualified Lead'],
                        ['value' => 'ad_spend', 'label' => 'Advertising Spend'],
                    ],
                ],
                [
                    'name' => 'channels',
                    'type' => 'checkbox_group',
                    'options' => [
                        ['value' => 'google', 'label' => 'Google Ads'],
                        ['value' => 'facebook', 'label' => 'Facebook Ads'],
                    ],
                ],
            ],
        ],
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'metric_template_id' => $template->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [
            'acquisition_point' => [
                'label' => 'Customer Acquisition Point',
                'value' => 'sales_qualified',
            ],
            'channels' => [
                'label' => 'Marketing Channels',
                'value' => ['google', 'facebook'],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.signed_definition.sections.0.items.0.label', 'Customer Acquisition Point')
            ->where('source_context.signed_definition.sections.0.items.0.value', 'Sales Qualified Lead')
            ->where('source_context.signed_definition.sections.0.items.1.label', 'Marketing Channels')
            ->where('source_context.signed_definition.sections.0.items.1.value', 'Google Ads, Facebook Ads')
            ->etc()
        )
    );
});

it('includes workshop alignment evidence (decisions, discrepancies, notes) in source_context for signed metrics', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    // Create a workshop for this metric
    $workshop = ReconciliationWorkshop::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'notes' => 'Workshop notes content',
        'decision_snapshot' => [
            'consensus' => 'Consensus choice',
            'follow_up' => ['Task A', 'Task B'],
        ],
    ]);

    // Create a discrepancy for this metric
    $discrepancy = Discrepancy::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'field_key' => 'cac_formula',
        'severity' => 'medium',
        'status' => 'Resolved',
        'description' => 'Calculation mismatch on marketing spend.',
    ]);

    Discrepancy::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'field_key' => 'lead_definition',
        'severity' => 'high',
        'status' => 'Open',
        'resolved_at' => null,
        'description' => 'Unresolved lead definition mismatch.',
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.has_evidence', true)
            ->where('source_context.workshop_alignment.workshop.id', $workshop->id)
            ->where('source_context.workshop_alignment.workshop.notes', 'Workshop notes content')
            ->where('source_context.workshop_alignment.workshop.decisions.consensus', 'Consensus choice')
            ->where('source_context.workshop_alignment.workshop.decisions.follow_up', ['Task A', 'Task B'])
            ->where('source_context.workshop_alignment.workshop.workshop_url', route('org.engagements.workshop', [$organization, $engagement, 'metric' => $metric->id]))
            ->has('source_context.workshop_alignment.discrepancies', 1)
            ->where('source_context.workshop_alignment.discrepancies.0.field_key', 'cac_formula')
            ->where('source_context.workshop_alignment.discrepancies.0.severity', 'medium')
            ->where('source_context.workshop_alignment.discrepancies.0.status', 'Resolved')
            ->where('source_context.workshop_alignment.discrepancies.0.description', 'Calculation mismatch on marketing spend.')
            ->etc()
        )
    );
});

it('handles missing workshop alignment evidence in source_context when none exists', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    // Do NOT create workshops or discrepancies

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.has_evidence', false)
            ->where('source_context.workshop_alignment.workshop', null)
            ->where('source_context.workshop_alignment.discrepancies', [])
            ->etc()
        )
    );
});

it('ensures workshop alignment evidence from other engagements or orgs is not leaked (tenant isolation)', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);

    // Target tenant
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    // Create a separate tenant with separate workshop / discrepancy data
    $otherOrganization = Organization::factory()->create();
    $otherEngagement = Engagement::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherOrgMetric = OrgMetric::factory()->create(['organization_id' => $otherOrganization->id]);
    $otherMetric = Metric::factory()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $otherEngagement->id,
        'org_metric_id' => $otherOrgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Other CAC Metric',
    ]);

    // Create workshop and discrepancy on OTHER metric / OTHER engagement
    ReconciliationWorkshop::factory()->create([
        'metric_id' => $otherMetric->id,
        'engagement_id' => $otherEngagement->id,
        'notes' => 'Other notes content',
        'decision_snapshot' => [
            'consensus' => 'Other choice',
        ],
    ]);

    Discrepancy::factory()->create([
        'metric_id' => $otherMetric->id,
        'engagement_id' => $otherEngagement->id,
        'field_key' => 'cac_formula',
        'severity' => 'medium',
        'status' => 'Resolved',
        'description' => 'Other discrepancy',
    ]);

    // Request targets the primary tenant
    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.has_evidence', false)
            ->where('source_context.workshop_alignment.workshop', null)
            ->where('source_context.workshop_alignment.discrepancies', [])
            ->etc()
        )
    );
});

it('handles empty workshop with no notes/decisions and treats it as having no evidence', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    // Create an empty workshop with only participants (no notes, no decisions)
    ReconciliationWorkshop::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'notes' => null,
        'decision_snapshot' => null,
        'participants' => [
            ['name' => 'John Doe', 'role' => 'Consultant'],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.has_evidence', false)
            ->where('source_context.workshop_alignment.workshop', null)
            ->where('source_context.workshop_alignment.discrepancies', [])
            ->etc()
        )
    );
});

it('treats field-level workshop decisions as source context evidence', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    ReconciliationWorkshop::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'notes' => null,
        'decision_snapshot' => [
            'field_decisions' => [
                'lead_definition' => [
                    'selected' => 'sales_qualified',
                    'rationale' => 'SQL is the agreed sales-accepted demand unit for board CPL.',
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.has_evidence', true)
            ->where('source_context.workshop_alignment.workshop.decisions.consensus', null)
            ->where('source_context.workshop_alignment.workshop.decisions.follow_up', [])
            ->where('source_context.workshop_alignment.workshop.decisions.field_decisions.lead_definition.selected', 'sales_qualified')
            ->where('source_context.workshop_alignment.workshop.decisions.field_decisions.lead_definition.rationale', 'SQL is the agreed sales-accepted demand unit for board CPL.')
            ->etc()
        )
    );
});

it('normalizes workshop decisions follow_ups plural key and drops invalid decision values', function () {
    $user = User::factory()->create(['name' => 'Jane Operator']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $internalOrg = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $orgMetric = OrgMetric::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'org_metric_id' => $orgMetric->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'CAC Metric',
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $orgMetric->id,
        'source_metric_id' => $metric->id,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'definition_json' => [],
    ]);

    // Create a workshop using follow_ups plural key in decision_snapshot
    ReconciliationWorkshop::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
        'notes' => 'Workshop notes',
        'decision_snapshot' => [
            'consensus' => ['unexpected' => 'array'],
            'follow_ups' => ['Task C', ['invalid'], '   ', 123],
        ],
    ]);

    $response = $this->actingAs($user)->get(route('org.engagements.handbook-workbench', [$organization, $engagement]));

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Engagements/HandbookWorkbench')
        ->has('metrics', 1)
        ->has('metrics.0', fn (AssertableInertia $m) => $m
            ->where('name', 'CAC Metric')
            ->where('source_context.workshop_alignment.workshop.decisions.consensus', null)
            ->where('source_context.workshop_alignment.workshop.decisions.follow_up', ['Task C', '123'])
            ->etc()
        )
    );
});
