<?php

use App\Enums\EngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('blocks member workshop access before readiness gate', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    scheduleWorkshopSessionForMetric($engagement, $metric);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertForbidden();
});

it('allows members to access workshops when forms are complete even if recompute is pending', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'definitions_changed_at' => now(),
        'recompute_finished_at' => now()->subHour(),
    ]);
    scheduleWorkshopSessionForMetric($engagement, $metric);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'status' => FormAssignmentStatus::COMPLETED->value,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertOk();
});

it('allows members without metric participation to access workshops once forms are complete', function () {
    $user = User::factory()->create();
    $participantUser = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'definitions_changed_at' => now()->subHour(),
        'recompute_finished_at' => now(),
    ]);
    scheduleWorkshopSessionForMetric($engagement, $metric);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $participantUser->id,
        'responsibility' => 'contributor',
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $participantUser->id,
        'status' => FormAssignmentStatus::COMPLETED->value,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertOk();
});

it('blocks member workshop access when no session is scheduled for the metric', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'definitions_changed_at' => now()->subHour(),
        'recompute_finished_at' => now(),
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => User::factory()->create()->id,
        'status' => FormAssignmentStatus::COMPLETED->value,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertForbidden();
});

it('auto-advances engagement to in workshop when scheduling the first workshop session', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    $staffMembership = Membership::factory()
        ->for($organization)
        ->for($staff, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $contributor = User::factory()->create();
    $contributorMembership = Membership::factory()
        ->for($organization)
        ->for($contributor, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'membership_id' => $contributorMembership->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participant->id,
        'user_id' => $contributor->id,
        'responsibility' => 'approver',
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $contributor->id,
        'status' => FormAssignmentStatus::COMPLETED->value,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'user_id' => $contributor->id,
        'submitted_at' => now(),
    ]);

    $startsAt = workshopAccessAlignFiveMinuteSlot(
        now()->utc()->addDay()->setTime(10, 0)
    );
    $endsAt = workshopAccessAlignFiveMinuteSlot(
        $startsAt->copy()->addHour()
    );

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'timezone' => 'UTC',
            'facilitator_membership_id' => (string) $staffMembership->id,
            'metric_ids' => [(string) $metric->id],
            'participant_ids' => [(string) $participant->id],
        ])
        ->assertCreated();

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP);
});

it('allows staff to access workshops even when readiness gate fails', function () {
    $staff = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()
        ->for($internal, 'organization')
        ->for($staff, 'user')
        ->admin()
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => User::factory()->create()->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertOk();
});

it('allows members to access workshops after engagement is finalized', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop_closed', true)
        );
});

it('returns engagement status for client workshop probes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->getJson(route('client.handbook.workshop', [
            'organization' => $organization,
            'engagement' => $engagement,
            'metric' => $metric->id,
            'probe' => 'status',
        ]))
        ->assertOk()
        ->assertJson([
            'engagement_status' => EngagementStatus::FINALIZED->value,
        ]);
});

function scheduleWorkshopSessionForMetric(
    Engagement $engagement,
    Metric $metric
): EngagementWorkshopSession {
    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $engagement->organization_id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);

    $session->metrics()->create([
        'metric_id' => $metric->id,
    ]);

    return $session;
}

function workshopAccessAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}
