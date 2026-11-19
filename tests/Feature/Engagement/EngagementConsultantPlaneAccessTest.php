<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Jobs\ProcessAssignmentReminders;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

dataset('broadStaffRoles', [
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

it('lets broad staff access engagement shell routes without client membership', function (MembershipRole $role) {
    $actor = makeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT,
    ]);

    $this->actingAs($actor)
        ->get(route('org.engagements.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.id', (string) $organization->id)
            ->where('organization.can_launch', true)
        );

    $this->actingAs($actor)
        ->get(route('org.engagements.create', $organization))
        ->assertRedirect(route('org.engagements.launch', $organization));

    $this->actingAs($actor)
        ->get(route('org.engagements.launch', $organization))
        ->assertOk();

    $this->actingAs($actor)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertOk();
})->with('broadStaffRoles');

it('lets broad staff update engagement status and queue reminders without client membership', function (MembershipRole $role) {
    Queue::fake();

    $actor = makeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    $showRoute = route('org.engagements.show', [$organization, $engagement]);

    $this->actingAs($actor)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ])
        ->assertRedirect($showRoute)
        ->assertSessionHas('success', __('Engagement status updated.'));

    $this->actingAs($actor)
        ->from($showRoute)
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]))
        ->assertRedirect($showRoute)
        ->assertSessionHas('success', __('Reminder jobs queued; assignments will be evaluated shortly.'));

    Queue::assertPushed(ProcessAssignmentReminders::class, 1);
})->with('broadStaffRoles');

it('lets assigned operators access engagement shell routes inside their delegated organization', function () {
    $operator = makeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT,
    ]);

    assignOperatorToOrganization($operator, $organization);

    $this->actingAs($operator)
        ->get(route('org.engagements.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.id', (string) $organization->id)
            ->where('organization.can_launch', true)
        );

    $this->actingAs($operator)
        ->get(route('org.engagements.create', $organization))
        ->assertRedirect(route('org.engagements.launch', $organization));

    $this->actingAs($operator)
        ->get(route('org.engagements.launch', $organization))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertOk();
});

it('lets assigned operators update engagement status and queue reminders inside their delegated organization', function () {
    Queue::fake();

    $operator = makeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    assignOperatorToOrganization($operator, $organization);

    $showRoute = route('org.engagements.show', [$organization, $engagement]);

    $this->actingAs($operator)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ])
        ->assertRedirect($showRoute)
        ->assertSessionHas('success', __('Engagement status updated.'));

    $this->actingAs($operator)
        ->from($showRoute)
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]))
        ->assertRedirect($showRoute)
        ->assertSessionHas('success', __('Reminder jobs queued; assignments will be evaluated shortly.'));

    Queue::assertPushed(ProcessAssignmentReminders::class, 1);
});

it('lets a staff owner delete a draft engagement without client membership', function () {
    $owner = makeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT,
    ]);

    $this->actingAs($owner)
        ->from(route('org.engagements.index', $organization))
        ->delete(route('org.engagements.destroy', [$organization, $engagement]))
        ->assertRedirect(route('org.engagements.index', $organization))
        ->assertSessionHas('success', __('Engagement draft deleted.'));
});

it('denies assigned operators outside their delegated organization across engagement shell and status routes', function () {
    $operator = makeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignEngagement = Engagement::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    assignOperatorToOrganization($operator, $assignedOrganization);

    $this->actingAs($operator)
        ->get(route('org.engagements.index', $foreignOrganization))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('org.engagements.create', $foreignOrganization))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('org.engagements.launch', $foreignOrganization))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('org.engagements.show', [$foreignOrganization, $foreignEngagement]))
        ->assertForbidden();

    $this->actingAs($operator)
        ->patch(route('org.engagements.status.update', [$foreignOrganization, $foreignEngagement]), [
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->post(route('org.engagements.reminders.store', [$foreignOrganization, $foreignEngagement]))
        ->assertForbidden();

    $this->actingAs($operator)
        ->delete(route('org.engagements.destroy', [$foreignOrganization, $foreignEngagement]))
        ->assertForbidden();
});

it('denies non-staff users across consultant engagement shell and status routes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.index', $organization))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('org.engagements.create', $organization))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('org.engagements.launch', $organization))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]))
        ->assertForbidden();
});

it('preserves nested engagement scope assertions with not-found responses', function () {
    $owner = makeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $otherOrganization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $otherOrganization->id,
        'status' => EngagementStatus::DRAFT,
    ]);

    $this->actingAs($owner)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::DRAFT->value,
        ])
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('org.engagements.reminders.store', [$organization, $engagement]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('org.engagements.destroy', [$organization, $engagement]))
        ->assertNotFound();
});

function makeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role->value,
    ]);

    return $user;
}

function assignOperatorToOrganization(User $user, Organization $organization): void
{
    StaffOperatorAssignment::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
    ]);
}
