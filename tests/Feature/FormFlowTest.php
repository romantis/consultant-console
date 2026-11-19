<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function seedTemplate(): void
{
    MetricTemplate::updateOrCreate(['key' => 'mrr'], [
        'name' => 'Monthly Recurring Revenue',
        'form_schema_json' => ['fields' => [
            ['name' => 'total_mrr', 'type' => 'number', 'label' => 'Total MRR', 'required' => true],
            ['name' => 'notes', 'type' => 'textarea', 'label' => 'Notes'],
        ]],
    ]);
}

it('allows mass assignment for required Form fields', function () {
    $org = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'mrr',
        'title' => 'MRR — Test',
        'schema_json' => ['fields' => []],
        'status' => 'Open',
    ]);

    expect($form->refresh()->template_key)->toBe('mrr');
});

it('assignee can view and autosave', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $assignee = User::factory()->create();

    // The user must belong to the org to view the form
    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $assignee->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    // Create the form from template
    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'mrr',
        'title' => 'MRR Form',
        'schema_json' => MetricTemplate::where('key', 'mrr')->value('form_schema_json'),
    ]);

    // 🔐 Authorize autosave: make the user the assignee
    $participant = MetricParticipant::factory()->for($metric)->for($assignee, 'user')->contributor()->create();

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($assignee, 'user')
        ->create();

    $metric->refresh();

    // Ensure draft exists (controller does firstOrCreate)
    $this->actingAs($assignee)
        ->get(route('forms.show', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Show')
            ->where('backLink.label', 'Back to metric')
            ->where('backLink.href', route('client.handbook.metrics.page', [
                'organization' => $org->id,
                'engagement' => $engagement->id,
                'orgMetric' => $metric->org_metric_id,
            ]))
        );

    // AUTOSAVE should redirect back with success (no 403 now)
    $this->actingAs($assignee)
        ->put(route('forms.autosave', $form), ['answers_json' => ['notes' => 'hello']])
        ->assertRedirect();

    $sub = FormSubmission::where('form_id', $form->id)
        ->where('user_id', $assignee->id)
        ->firstOrFail();

    expect($sub->answers_json['notes'] ?? null)->toBe('hello');
});

it('submit enforces required numeric field', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $u = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $u->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'mrr',
        'title' => 'MRR Form',
        'schema_json' => MetricTemplate::where('key', 'mrr')->value('form_schema_json'),
    ]);

    // 🔐 Authorize submit: assign the user to the form
    $participant = MetricParticipant::factory()->for($metric)->for($u, 'user')->contributor()->create();

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($u, 'user')
        ->create();

    // Missing required total_mrr -> validation error on submit
    $this->actingAs($u)
        ->post(route('forms.submit', $form), ['answers_json' => ['notes' => 'x']])
        ->assertSessionHasErrors(['answers_json.total_mrr']);
});

it('non-member cannot view form', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);
    $form = Form::create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'mrr',
        'title' => 'MRR Form',
        'schema_json' => MetricTemplate::where('key', 'mrr')->value('form_schema_json'),
    ]);

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('forms.show', $form))->assertForbidden();
});

it('shows read-only view for unassigned admins without creating submissions', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $admin = User::factory()->create();
    $staffOrg = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $staffOrg->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    $this->actingAs($admin)
        ->get(route('forms.show', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Show')
            ->where('readOnly', true)
            ->where('submission', null)
            ->where('backLink.label', 'Back to engagement')
            ->where('backLink.href', route('org.engagements.show', [
                'organization' => $org->id,
                'engagement' => $engagement->id,
            ]))
        );

    expect(FormSubmission::query()
        ->where('form_id', $form->id)
        ->where('user_id', $admin->id)
        ->exists()
    )->toBeFalse();
});

it('prevents approver assignments from submitting', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $approver = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $approver->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($approver, 'user')
        ->state(['responsibility' => 'Approver'])
        ->create();

    $this->actingAs($approver)
        ->get(route('forms.show', $form))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Show')
            ->where('readOnly', true)
        );

    $this->actingAs($approver)
        ->put(route('forms.autosave', $form), ['answers_json' => []])
        ->assertForbidden();

    $this->actingAs($approver)
        ->post(route('forms.submit', $form), ['answers_json' => ['notes' => 'x']])
        ->assertForbidden();

    expect(FormSubmission::query()
        ->where('form_id', $form->id)
        ->where('user_id', $approver->id)
        ->exists()
    )->toBeFalse();
});

it('non-assignee cannot autosave or submit', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $u = User::factory()->create();

    // user belongs to org but is not assigned to the form
    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $u->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    MetricParticipant::factory()->for($metric)->contributor()->create();

    // AUTOSAVE: empty payload should be allowed to validate, but policy must forbid
    $this->actingAs($u)
        ->put(route('forms.autosave', $form), ['answers_json' => []])
        ->assertForbidden();

    // SUBMIT: send a non-empty payload so it passes request validation, then policy forbids
    $this->actingAs($u)
        ->post(route('forms.submit', $form), ['answers_json' => ['notes' => 'x']])
        ->assertForbidden();
});

it('shares member affiliation on the form view', function () {
    seedTemplate();
    $org = Organization::factory()->create();
    $assignee = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $assignee->id,
        'role' => MembershipRole::MEMBER->value,
        'affiliation' => ' Revenue Ops ',
    ]);

    $engagement = Engagement::factory()->create(['organization_id' => $org->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::create([
        'organization_id' => $org->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
        'template_key' => 'mrr',
        'title' => 'MRR Form',
        'schema_json' => MetricTemplate::where('key', 'mrr')->value('form_schema_json'),
    ]);

    $participant = MetricParticipant::factory()->for($metric)->for($assignee, 'user')->contributor()->create();

    FormAssignment::factory()
        ->for($form, 'form')
        ->for($metric, 'metric')
        ->for($participant, 'participant')
        ->for($assignee, 'user')
        ->create();

    $this->actingAs($assignee)
        ->get(route('forms.show', $form))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forms/Show')
            ->where('memberAffiliation', 'Revenue Ops')
        );
});
