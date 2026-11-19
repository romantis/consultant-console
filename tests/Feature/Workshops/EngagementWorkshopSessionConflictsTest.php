<?php

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

it('blocks scheduling a metric when another session for that metric is scheduled or in progress', function () {
    [$staff, $organization, $facilitator] = workshopConflictStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    [$metric, $participant] = workshopConflictScopedMetricAndApprover($organization, $engagement);

    $startsAt = Carbon::parse('2030-03-14 10:00:00', 'UTC');
    $endsAt = $startsAt->copy()->addHour();

    $baseline = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload($metric, $participant, $facilitator, $startsAt, $endsAt)
    );
    $baseline->assertCreated();
    $baselineSession = EngagementWorkshopSession::query()->findOrFail($baseline->json('data.id'));

    $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload(
            $metric,
            $participant,
            $facilitator,
            $startsAt->copy()->addDays(1),
            $endsAt->copy()->addDays(1)
        )
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

    Carbon::setTestNow($baselineSession->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $baselineSession]))
        ->assertOk();

    $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload(
            $metric,
            $participant,
            $facilitator,
            $startsAt->copy()->addDays(2),
            $endsAt->copy()->addDays(2)
        )
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $baselineSession]))
        ->assertOk();

    Carbon::setTestNow();

    $afterComplete = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload(
            $metric,
            $participant,
            $facilitator,
            $startsAt->copy()->addDays(3),
            $endsAt->copy()->addDays(3)
        )
    );
    $afterComplete->assertCreated();
    $cancelableSession = EngagementWorkshopSession::query()->findOrFail($afterComplete->json('data.id'));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $cancelableSession]))
        ->assertOk();

    $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload(
            $metric,
            $participant,
            $facilitator,
            $startsAt->copy()->addDays(4),
            $endsAt->copy()->addDays(4)
        )
    )->assertCreated();
});

it('saves overlapping sessions for different metrics and returns advisory warning payloads', function () {
    [$staff, $organization, $facilitator] = workshopConflictStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    [$metricA, $participant] = workshopConflictScopedMetricAndApprover($organization, $engagement);
    $metricB = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $participantMembership = Membership::query()->findOrFail($participant->membership_id);

    MetricParticipant::factory()->approver()->create([
        'metric_id' => $metricB->id,
        'engagement_participant_id' => $participant->id,
        'user_id' => $participantMembership->user_id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metricB->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metricB->id,
        'user_id' => $participantMembership->user_id,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $participantMembership->user_id,
    ]);

    $startsAt = Carbon::parse('2030-03-14 10:00:00', 'UTC');
    $endsAt = $startsAt->copy()->addHour();

    $baseline = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload($metricA, $participant, $facilitator, $startsAt, $endsAt)
    );

    $baseline->assertCreated();
    $existingSessionId = (string) $baseline->json('data.id');

    $overlapResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopConflictPayload(
            $metricB,
            $participant,
            $facilitator,
            $startsAt->copy()->addMinutes(15),
            $endsAt->copy()->addMinutes(15)
        )
    );

    $overlapResponse->assertCreated()
        ->assertJsonPath('warnings.0.session_id', $existingSessionId)
        ->assertJsonPath('warnings.0.type', 'facilitator_overlap');

    $warnings = collect($overlapResponse->json('warnings'));

    expect($warnings->pluck('type')->all())
        ->toContain('facilitator_overlap')
        ->toContain('participant_overlap')
        ->and($warnings->where('type', 'participant_overlap')->first()['engagement_participant_id'] ?? null)
        ->toBe((string) $participant->id);

    $this->assertDatabaseCount('engagement_workshop_sessions', 2);
});

/**
 * @return array{0: User, 1: Organization, 2: Membership}
 */
function workshopConflictStaffContext(): array
{
    $staff = User::factory()->create();
    $organization = Organization::factory()->create();
    $internal = Organization::query()->where('slug', 'metric-harmony')->first() ?? makeInternalOrg();

    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staff->id,
    ]);

    $facilitator = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $staff->id,
    ]);

    return [$staff, $organization, $facilitator];
}

/**
 * @return array{0: Metric, 1: EngagementParticipant}
 */
function workshopConflictScopedMetricAndApprover(
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
function workshopConflictPayload(
    Metric $metric,
    EngagementParticipant $participant,
    Membership $facilitator,
    Carbon $startsAt,
    Carbon $endsAt,
): array {
    return [
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'timezone' => 'UTC',
        'facilitator_membership_id' => (string) $facilitator->id,
        'metric_ids' => [(string) $metric->id],
        'participant_ids' => [(string) $participant->id],
    ];
}
