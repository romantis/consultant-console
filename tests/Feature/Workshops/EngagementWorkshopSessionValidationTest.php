<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
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

it('rejects empty metric scope when creating workshop sessions', function () {
    [$staff, $organization, $facilitator] = workshopValidationStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    [$metric, $approverParticipant] = workshopValidationScopedMetricParticipants(
        $organization,
        $engagement
    );
    $startsAt = workshopValidationAlignFiveMinuteSlot(workshopValidationFutureStart(2));
    $endsAt = workshopValidationAlignFiveMinuteSlot(
        $startsAt->copy()->addHour()
    );

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'timezone' => 'UTC',
            'facilitator_membership_id' => (string) $facilitator->id,
            'metric_ids' => [],
            'participant_ids' => [(string) $approverParticipant->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

    expect($metric)->not->toBeNull();
});

it('rejects participant scope that lacks required approver or owner coverage', function () {
    [$staff, $organization, $facilitator] = workshopValidationStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    [$metric, $approverParticipant, $contributorParticipant] = workshopValidationScopedMetricParticipants(
        $organization,
        $engagement
    );
    $startsAt = workshopValidationAlignFiveMinuteSlot(workshopValidationFutureStart(2));
    $endsAt = workshopValidationAlignFiveMinuteSlot(
        $startsAt->copy()->addHour()
    );

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'timezone' => 'UTC',
            'facilitator_membership_id' => (string) $facilitator->id,
            'metric_ids' => [(string) $metric->id],
            'participant_ids' => [(string) $contributorParticipant->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['participant_ids']);

    expect($approverParticipant)->not->toBeNull();
});

/**
 * @return array{0: User, 1: Organization, 2: Membership}
 */
function workshopValidationStaffContext(): array
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
 * @return array{0: Metric, 1: EngagementParticipant, 2: EngagementParticipant}
 */
function workshopValidationScopedMetricParticipants(
    Organization $organization,
    Engagement $engagement
): array {
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $approverMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $contributorMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $approverParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $approverMembership->id,
        'membership_role' => MembershipRole::MEMBER,
    ]);

    $contributorParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $contributorMembership->id,
        'membership_role' => MembershipRole::MEMBER,
    ]);

    MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $approverParticipant->id,
        'user_id' => $approverMembership->user_id,
    ]);

    MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $contributorParticipant->id,
        'user_id' => $contributorMembership->user_id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $contributorMembership->user_id,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $contributorMembership->user_id,
    ]);

    return [$metric, $approverParticipant, $contributorParticipant];
}

function workshopValidationAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}

function workshopValidationFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}
