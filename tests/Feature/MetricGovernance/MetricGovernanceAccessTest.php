<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\ChangeProposal;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgMetric;
use App\Models\OrgMetricParticipant;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

dataset('broadStaffRoles', [
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

function setupSignOffReadyMetric(Organization $organization, Engagement $engagement): Metric
{
    $template = MetricTemplate::factory()->create(['form_schema_json' => ['fields' => []]]);
    $metric = Metric::factory()->for($organization)->for($engagement)->for($template, 'template')->create();

    $orgMetric = OrgMetric::factory()->for($organization)->create(['key' => $metric->key]);
    $metric->org_metric_id = $orgMetric->id;
    $metric->status = MetricStatus::IN_REVIEW;
    $metric->save();

    $user = User::factory()->create();

    $metricParticipant = MetricParticipant::factory()->for($metric)->for($user, 'user')->state(['responsibility' => 'owner'])->create();
    OrgMetricParticipant::factory()->for($orgMetric)->for($user, 'user')->state(['responsibility' => 'owner'])->create();

    FinalDefinition::factory()->for($metric)->create([
        'status' => 'Draft',
        'owner_participant_id' => $metricParticipant->id,
        'definition_json' => ['some_field' => 'value'],
    ]);

    return $metric;
}

it('lets broad staff sign off metrics without client membership', function (MembershipRole $role) {
    $actor = metricGovernanceMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = setupSignOffReadyMetric($organization, $engagement);

    // Enable manager override for staff admin to work without being an approver
    config(['metric-harmony.features.internal_first_manager_signoff_override' => true]);

    $this->actingAs($actor)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $metric->refresh();
    expect($metric->status)->toBe(MetricStatus::FINALIZED);
    expect($metric->finalDefinition->signed_off_at)->not->toBeNull();
    expect($metric->finalDefinition->signed_off_by)->toBe($actor->id);
})->with('broadStaffRoles');

it('denies broad staff from signing off if manager override is disabled', function (MembershipRole $role) {
    $actor = metricGovernanceMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = setupSignOffReadyMetric($organization, $engagement);

    config(['metric-harmony.features.internal_first_manager_signoff_override' => false]);

    $this->actingAs($actor)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]), [
            'decision' => 'approve',
        ])
        ->assertForbidden();
})->with('broadStaffRoles');

it('lets assigned operators sign off metrics inside their delegated organization', function () {
    $operator = metricGovernanceMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = setupSignOffReadyMetric($organization, $engagement);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $organization->id,
    ]);

    config(['metric-harmony.features.internal_first_manager_signoff_override' => true]);

    $this->actingAs($operator)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $metric->refresh();
    expect($metric->status)->toBe(MetricStatus::FINALIZED);
    expect($metric->finalDefinition->signed_off_at)->not->toBeNull();
    expect($metric->finalDefinition->signed_off_by)->toBe($operator->id);
});

it('denies assigned operators outside their delegated organization from signing off', function () {
    $operator = metricGovernanceMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignEngagement = Engagement::factory()->for($foreignOrganization)->create();
    $foreignMetric = setupSignOffReadyMetric($foreignOrganization, $foreignEngagement);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    config(['metric-harmony.features.internal_first_manager_signoff_override' => true]);

    $this->actingAs($operator)
        ->post(route('org.metrics.sign-off', [$foreignOrganization, $foreignEngagement, $foreignMetric]), [
            'decision' => 'approve',
        ])
        ->assertForbidden();
});

it('lets broad staff request changes for finalized metrics without client membership', function (MembershipRole $role) {
    config(['metric-harmony.features.change_proposals' => true]);

    $actor = metricGovernanceMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = setupSignOffReadyMetric($organization, $engagement);

    // Finalize it first
    $metric->status = MetricStatus::FINALIZED;
    $metric->save();
    $metric->finalDefinition->signed_off_at = now();
    $metric->finalDefinition->save();

    $approver = MetricParticipant::factory()->for($metric)->for(User::factory(), 'user')->state(['responsibility' => 'approver'])->create();

    // org.metrics.change-proposals.store
    $this->actingAs($actor)
        ->post(route('org.metrics.change-proposals.store', [$organization, $metric]), [
            'rationale' => 'Test rationale',
            'payload' => ['some_field' => ['value' => 'new value']],
            'approver_ids' => [$approver->id],
        ])
        ->assertRedirect();

    $proposal = ChangeProposal::where('metric_id', $metric->id)->first();
    expect($proposal)->not->toBeNull();
    expect($proposal->organization_id)->toBe($organization->id);
    expect($proposal->rationale)->toBe('Test rationale');
})->with('broadStaffRoles');

it('denies assigned operators outside their delegated organization from creating change proposals', function () {
    config(['metric-harmony.features.change_proposals' => true]);

    $operator = metricGovernanceMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);

    $foreignOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignEngagement = Engagement::factory()->for($foreignOrganization)->create();
    $foreignMetric = setupSignOffReadyMetric($foreignOrganization, $foreignEngagement);

    $foreignMetric->status = MetricStatus::FINALIZED;
    $foreignMetric->save();
    $foreignMetric->finalDefinition->signed_off_at = now();
    $foreignMetric->finalDefinition->save();

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    $approver = MetricParticipant::factory()->for($foreignMetric)->for(User::factory(), 'user')->state(['responsibility' => 'approver'])->create();

    $this->actingAs($operator)
        ->post(route('org.metrics.change-proposals.store', [$foreignOrganization, $foreignMetric]), [
            'rationale' => 'Test rationale',
            'payload' => ['some_field' => ['value' => 'new value']],
            'approver_ids' => [$approver->id],
        ])
        ->assertForbidden();
});

function metricGovernanceMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role->value,
    ]);

    return $user;
}
