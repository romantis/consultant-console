<?php

use App\Enums\EngagementStatus;
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
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
});

dataset('broadStaffRoles', [
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

it('lets broad staff list and mutate workshop sessions without client membership', function (MembershipRole $role) {
    $actor = workshopAccessMakeStaffActor($this->internalOrg, $role);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    [$metric, $participant] = workshopAccessMakeScopedMetricAndApprover($organization, $engagement);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $actor->id,
    ]);

    $this->actingAs($actor)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertOk();

    $createResponse = $this->actingAs($actor)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), workshopAccessSessionPayload(
            $metric,
            $participant,
            $facilitator,
            startsAt: workshopConsultantAccessFutureStart(2)
        ));

    $createResponse->assertCreated()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::SCHEDULED->value);

    $sessionId = (string) $createResponse->json('data.id');

    $this->actingAs($actor)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $sessionId]),
            workshopAccessSessionPayload(
                $metric,
                $participant,
                $facilitator,
                startsAt: workshopConsultantAccessFutureStart(3)
            )
        )
        ->assertOk()
        ->assertJsonPath('data.id', $sessionId);

    $session = EngagementWorkshopSession::query()->findOrFail($sessionId);
    Carbon::setTestNow($session->starts_at->copy()->subMinutes(5));

    $this->actingAs($actor)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    $this->actingAs($actor)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::COMPLETED->value);

    Carbon::setTestNow();

    $cancelSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $cancelSession->metrics()->create(['metric_id' => $metric->id]);
    $cancelSession->participants()->create(['engagement_participant_id' => $participant->id]);

    $this->actingAs($actor)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $cancelSession]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::CANCELED->value);
})->with('broadStaffRoles');

it('lets assigned operators list and mutate workshop sessions inside their delegated organization', function () {
    $operator = workshopAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    [$metric, $participant] = workshopAccessMakeScopedMetricAndApprover($organization, $engagement);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $organization->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertOk();

    $createResponse = $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), workshopAccessSessionPayload(
            $metric,
            $participant,
            $facilitator,
            startsAt: workshopConsultantAccessFutureStart(2)
        ));

    $createResponse->assertCreated()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::SCHEDULED->value);

    $sessionId = (string) $createResponse->json('data.id');

    $this->actingAs($operator)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $sessionId]),
            workshopAccessSessionPayload(
                $metric,
                $participant,
                $facilitator,
                startsAt: workshopConsultantAccessFutureStart(3)
            )
        )
        ->assertOk();

    $session = EngagementWorkshopSession::query()->findOrFail($sessionId);
    Carbon::setTestNow($session->starts_at->copy()->subMinutes(5));

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $sessionId]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::COMPLETED->value);

    $cancelSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $cancelSession->metrics()->create(['metric_id' => $metric->id]);
    $cancelSession->participants()->create(['engagement_participant_id' => $participant->id]);

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $cancelSession]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::CANCELED->value);

    Carbon::setTestNow();
});

it('denies assigned operators outside their delegated organization across workshop session routes', function () {
    $operator = workshopAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);
    $assignedOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignOrganization = Organization::factory()->create(['is_internal' => false]);
    $foreignEngagement = Engagement::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    [$foreignMetric, $foreignParticipant] = workshopAccessMakeScopedMetricAndApprover($foreignOrganization, $foreignEngagement);
    $foreignFacilitator = Membership::factory()->admin()->create([
        'organization_id' => $foreignOrganization->id,
    ]);

    StaffOperatorAssignment::factory()->create([
        'user_id' => $operator->id,
        'organization_id' => $assignedOrganization->id,
    ]);

    $foreignSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'engagement_id' => $foreignEngagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $foreignSession->metrics()->create(['metric_id' => $foreignMetric->id]);
    $foreignSession->participants()->create(['engagement_participant_id' => $foreignParticipant->id]);

    $this->actingAs($operator)
        ->get(route('org.engagements.workshop-sessions.index', [$foreignOrganization, $foreignEngagement]))
        ->assertForbidden();

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.store', [$foreignOrganization, $foreignEngagement]), workshopAccessSessionPayload(
            $foreignMetric,
            $foreignParticipant,
            $foreignFacilitator
        ))
        ->assertForbidden();

    $this->actingAs($operator)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$foreignOrganization, $foreignEngagement, $foreignSession]),
            workshopAccessSessionPayload($foreignMetric, $foreignParticipant, $foreignFacilitator, startsAt: workshopConsultantAccessFutureStart(3))
        )
        ->assertForbidden();

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.start', [$foreignOrganization, $foreignEngagement, $foreignSession]))
        ->assertForbidden();

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$foreignOrganization, $foreignEngagement, $foreignSession]))
        ->assertForbidden();

    $this->actingAs($operator)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$foreignOrganization, $foreignEngagement, $foreignSession]))
        ->assertForbidden();
});

it('denies non-staff users across workshop session routes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    [$metric, $participant] = workshopAccessMakeScopedMetricAndApprover($organization, $engagement);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);
    $session->participants()->create(['engagement_participant_id' => $participant->id]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), workshopAccessSessionPayload(
            $metric,
            $participant,
            $facilitator
        ))
        ->assertForbidden();

    $this->actingAs($user)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopAccessSessionPayload($metric, $participant, $facilitator, startsAt: workshopConsultantAccessFutureStart(3))
        )
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $session]))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $session]))
        ->assertForbidden();
});

it('preserves nested scope assertions with not-found responses', function () {
    $owner = workshopAccessMakeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $organization = Organization::factory()->create(['is_internal' => false]);
    $otherOrganization = Organization::factory()->create(['is_internal' => false]);
    $engagement = Engagement::factory()->create([
        'organization_id' => $otherOrganization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    [$metric, $participant] = workshopAccessMakeScopedMetricAndApprover($otherOrganization, $engagement);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);
    $session->participants()->create(['engagement_participant_id' => $participant->id]);

    $this->actingAs($owner)
        ->get(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), workshopAccessSessionPayload(
            $metric,
            $participant,
            $facilitator
        ))
        ->assertNotFound();

    $this->actingAs($owner)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $session]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $session]))
        ->assertNotFound();
});

it('returns not found when session belongs to different engagement under same organization', function () {
    $owner = workshopAccessMakeStaffActor($this->internalOrg, MembershipRole::OWNER);
    $organization = Organization::factory()->create(['is_internal' => false]);

    $engagementA = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);
    $engagementB = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP,
    ]);

    [$metricA, $participantA] = workshopAccessMakeScopedMetricAndApprover($organization, $engagementA);
    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $sessionA = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementA->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
    ]);
    $sessionA->metrics()->create(['metric_id' => $metricA->id]);
    $sessionA->participants()->create(['engagement_participant_id' => $participantA->id]);

    $this->actingAs($owner)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagementB, $sessionA]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagementB, $sessionA]),
            workshopAccessSessionPayload($metricA, $participantA, $facilitator, startsAt: workshopConsultantAccessFutureStart(3))
        )
        ->assertNotFound();
});

function workshopAccessMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role->value,
    ]);

    return $user;
}

/**
 * @return array{0: Metric, 1: EngagementParticipant}
 */
function workshopAccessMakeScopedMetricAndApprover(Organization $organization, Engagement $engagement): array
{
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
function workshopAccessSessionPayload(
    Metric $metric,
    EngagementParticipant $participant,
    Membership $facilitator,
    ?Carbon $startsAt = null
): array {
    $startsAt ??= workshopConsultantAccessFutureStart(2);
    $startsAt = workshopConsultantAccessAlignFiveMinuteSlot($startsAt);
    $endsAt = workshopConsultantAccessAlignFiveMinuteSlot(
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

function workshopConsultantAccessAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}

function workshopConsultantAccessFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}
