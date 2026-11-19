<?php

use App\Enums\EngagementWorkshopSessionStatus;
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

it('allows scoped staff users to list and mutate workshop sessions', function () {
    [$staff, $organization] = makeStaffForOrganization();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = makeScopedMetricAndApprover($organization, $engagement);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertOk();

    $createResponse = $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), sessionPayload(
            $metric,
            $participant,
            $facilitator,
            startsAt: workshopSessionAccessFutureStart(2)
        ));

    $createResponse->assertCreated()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::SCHEDULED->value);

    $sessionId = (string) $createResponse->json('data.id');

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $sessionId]),
            sessionPayload(
                $metric,
                $participant,
                $facilitator,
                startsAt: workshopSessionAccessFutureStart(3)
            )
        )
        ->assertOk()
        ->assertJsonPath('data.id', $sessionId);

    $session = EngagementWorkshopSession::query()->findOrFail($sessionId);
    Carbon::setTestNow($session->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::COMPLETED->value);

    Carbon::setTestNow();

    $cancelSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $cancelSession->metrics()->create([
        'metric_id' => $metric->id,
    ]);
    $cancelSession->participants()->create([
        'engagement_participant_id' => $participant->id,
    ]);

    $this->actingAs($staff)
        ->withImpersonationSession($organization, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $cancelSession]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::CANCELED->value);
});

it('denies workshop session view access outside organization/engagement permissions', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertForbidden();
});

it('denies non-staff users for create update and transition abilities', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = makeScopedMetricAndApprover($organization, $engagement);

    $nonStaffAdmin = User::factory()->create();
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $nonStaffAdmin->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $facilitator->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $session->metrics()->create([
        'metric_id' => $metric->id,
    ]);
    $session->participants()->create([
        'engagement_participant_id' => $participant->id,
    ]);

    $this->actingAs($nonStaffAdmin)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), sessionPayload(
            $metric,
            $participant,
            $facilitator
        ))
        ->assertForbidden();

    $this->actingAs($nonStaffAdmin)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            sessionPayload($metric, $participant, $facilitator, startsAt: workshopSessionAccessFutureStart(4))
        )
        ->assertForbidden();

    $this->actingAs($nonStaffAdmin)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertForbidden();

    $this->actingAs($nonStaffAdmin)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $session]))
        ->assertForbidden();

    $this->actingAs($nonStaffAdmin)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $session]))
        ->assertForbidden();
});

it('returns not found for organization engagement and session scope mismatches', function () {
    [$staff, $organizationA] = makeStaffForOrganization();
    $organizationB = Organization::factory()->create();

    $engagementA = Engagement::factory()->create([
        'organization_id' => $organizationA->id,
    ]);
    $engagementB = Engagement::factory()->create([
        'organization_id' => $organizationB->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organizationA->id,
        'engagement_id' => $engagementA->id,
    ]);

    $this->actingAs($staff)
        ->withImpersonationSession($organizationA, MembershipRole::ADMIN)
        ->get(route('org.engagements.workshop-sessions.index', [$organizationA, $engagementB]))
        ->assertNotFound();

    $this->actingAs($staff)
        ->withImpersonationSession($organizationA, MembershipRole::ADMIN)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organizationA, $engagementB, $session]))
        ->assertNotFound();
});

/**
 * @return array{0: User, 1: Organization}
 */
function makeStaffForOrganization(): array
{
    $staff = User::factory()->create();
    $internal = makeInternalOrg();
    $organization = Organization::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staff->id,
    ]);

    return [$staff, $organization];
}

/**
 * @return array{0: Metric, 1: EngagementParticipant}
 */
function makeScopedMetricAndApprover(
    Organization $organization,
    Engagement $engagement
): array {
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $participantMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $participantMembership->id,
        'membership_role' => MembershipRole::MEMBER,
    ]);

    MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participant->id,
        'user_id' => $participantMembership->user_id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $participantMembership->user_id,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $participantMembership->user_id,
    ]);

    return [$metric, $participant];
}

/**
 * @return array<string, mixed>
 */
function sessionPayload(
    Metric $metric,
    EngagementParticipant $participant,
    Membership $facilitator,
    ?Carbon $startsAt = null
): array {
    $startsAt ??= workshopSessionAccessFutureStart(2);
    $startsAt = workshopSessionAccessAlignFiveMinuteSlot($startsAt);
    $endsAt = workshopSessionAccessAlignFiveMinuteSlot(
        $startsAt->copy()->addHour()
    );

    return [
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'timezone' => 'UTC',
        'facilitator_membership_id' => (string) $facilitator->id,
        'metric_ids' => [(string) $metric->id],
        'participant_ids' => [(string) $participant->id],
    ];
}

function workshopSessionAccessAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}

function workshopSessionAccessFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}
