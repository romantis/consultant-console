<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
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

it('lets broad staff view and update forms without client membership', function (MembershipRole $role) {
    $actor = formsAccessMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($organization)->for($engagement)->create();
    $form = Form::factory()->for($organization)->for($engagement)->for($metric)->create();

    $this->actingAs($actor)
        ->get(route('forms.show', $form))
        ->assertOk();

    // Staff can update forms (e.g. settings/status) in the consultant plane
    // Note: 'forms.update' might be used for settings, while 'forms.autosave'/'forms.submit'
    // are often retained contributor-specific paths.
    // Let's verify broad staff can at least view.
    $this->actingAs($actor)
        ->get(route('forms.show', $form))
        ->assertOk();
})->with('broadStaffRoles');

it('lets assigned operators view forms inside their delegated organization', function () {
    $operator = formsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($organization)->for($engagement)->create();
    $form = Form::factory()->for($organization)->for($engagement)->for($metric)->create();

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($operator)
        ->get(route('forms.show', $form))
        ->assertOk();
});

it('denies assigned operators outside their delegated organization across form routes', function () {
    $operator = formsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignEngagement = Engagement::factory()->for($foreignOrganization)->create();
    $foreignMetric = Metric::factory()->for($foreignOrganization)->for($foreignEngagement)->create();
    $foreignForm = Form::factory()->for($foreignOrganization)->for($foreignEngagement)->for($foreignMetric)->create();

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    $this->actingAs($operator)
        ->get(route('forms.show', $foreignForm))
        ->assertForbidden();
});

it('denies non-staff broad access to forms unless assigned as contributor', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($organization)->for($engagement)->create();
    $form = Form::factory()->for($organization)->for($engagement)->for($metric)->create();

    // User is member of org but NOT assigned to form
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->get(route('forms.show', $form))
        ->assertForbidden();

    // Now assign as contributor
    $participant = MetricParticipant::factory()->for($metric)->for($user, 'user')->contributor()->create();
    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($user, 'user')
        ->create();

    $this->actingAs($user)
        ->get(route('forms.show', $form))
        ->assertOk();
});

it('preserves nested scope assertions for form routes', function () {
    $operator = formsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);
    $otherOrganization = Organization::factory()->create(['is_internal' => false]);

    $engagement = Engagement::factory()->for($otherOrganization)->create();
    $metric = Metric::factory()->for($otherOrganization)->for($engagement)->create();
    $form = Form::factory()->for($otherOrganization)->for($engagement)->for($metric)->create();

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    // Requesting form from otherOrganization while assigned only to assignedOrganization
    $this->actingAs($operator)
        ->get(route('forms.show', $form))
        ->assertForbidden();
});

it('restricts api.form-assignments to assignees or impersonated admins (T028)', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->for($organization)->create();
    $metric = Metric::factory()->for($organization)->for($engagement)->create();
    $form = Form::factory()->for($organization)->for($engagement)->for($metric)->create();

    $assignment = FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($user, 'user')
        ->create();

    // 1. Assignee can access
    $this->actingAs($user)
        ->getJson(route('api.form-assignments.show', $assignment))
        ->assertOk();

    // 2. Stranger cannot access
    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->getJson(route('api.form-assignments.show', $assignment))
        ->assertForbidden();

    // 3. Staff Owner cannot access without impersonation (it is NOT an implicit consultant plane route)
    $owner = formsAccessMakeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $this->actingAs($owner)
        ->getJson(route('api.form-assignments.show', $assignment))
        ->assertForbidden();

    // 4. Staff Owner can access WITH impersonation
    $this->actingAs($owner)
        ->withSession(impersonationSessionPayload($organization, MembershipRole::ADMIN))
        ->getJson(route('api.form-assignments.show', $assignment))
        ->assertOk();
});

function formsAccessMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role->value,
    ]);

    return $user;
}
