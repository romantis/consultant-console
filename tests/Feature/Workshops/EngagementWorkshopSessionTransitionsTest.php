<?php

use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Enums\MembershipRole;
use App\Jobs\ProcessWorkshopSessionReminders;
use App\Models\AuditLog;
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
use App\Notifications\WorkshopSessionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('allows scheduled sessions to transition to in progress and canceled', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $scheduled = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    Carbon::setTestNow($scheduled->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $scheduled]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    [$metricForCancel, $participantForCancel] = workshopTransitionScopedMetricAndApprover(
        $organization,
        $engagement
    );

    $scheduledForCancel = workshopTransitionCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metricForCancel,
        $participantForCancel,
        $facilitator,
        workshopTransitionFutureStart(3)
    );

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $scheduledForCancel]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::CANCELED->value);

    Carbon::setTestNow();
});

it('blocks starting a workshop session before the start window opens', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $scheduled = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    Carbon::setTestNow($scheduled->starts_at->copy()->subMinutes(11));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $scheduled]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($scheduled->fresh()->status)->toBe(EngagementWorkshopSessionStatus::SCHEDULED);

    Carbon::setTestNow();
});

it('allows local force start outside the start window and logs a dedicated audit event', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $scheduled = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    config()->set('metric-harmony.workshops.force_start_enabled', true);
    Carbon::setTestNow($scheduled->starts_at->copy()->subMinutes(30));

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.start', [$organization, $engagement, $scheduled]),
            ['force_start' => true]
        )
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    expect($scheduled->fresh()->status)->toBe(EngagementWorkshopSessionStatus::IN_PROGRESS)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.force_started')
                ->where('subject_id', (string) $scheduled->getKey())
                ->count()
        )->toBeGreaterThan(0)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.started')
                ->where('subject_id', (string) $scheduled->getKey())
                ->count()
        )->toBe(0);

    Carbon::setTestNow();
});

it('keeps force start gated when local force-start mode is disabled', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $scheduled = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    config()->set('metric-harmony.workshops.force_start_enabled', false);
    Carbon::setTestNow($scheduled->starts_at->copy()->subMinutes(30));

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.start', [$organization, $engagement, $scheduled]),
            ['force_start' => true]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($scheduled->fresh()->status)->toBe(EngagementWorkshopSessionStatus::SCHEDULED)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.force_started')
                ->where('subject_id', (string) $scheduled->getKey())
                ->count()
        )->toBe(0);

    Carbon::setTestNow();
});

it('does not allow force start outside scheduled status', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $session = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);
    $session->forceFill([
        'status' => EngagementWorkshopSessionStatus::CANCELED,
    ])->save();

    config()->set('metric-harmony.workshops.force_start_enabled', true);

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]),
            ['force_start' => true]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($session->fresh()->status)->toBe(EngagementWorkshopSessionStatus::CANCELED);
});

it('marks scheduled sessions as missed when start is attempted after the start window', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $scheduled = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    Carbon::setTestNow($scheduled->starts_at->copy()->addMinutes(16));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $scheduled]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    expect($scheduled->fresh()->status)->toBe(EngagementWorkshopSessionStatus::MISSED)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.missed')
                ->where('subject_id', (string) $scheduled->getKey())
                ->count()
        )->toBeGreaterThan(0);

    Carbon::setTestNow();
});

it('allows in progress sessions to transition to completed and canceled', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $completable = workshopTransitionCreateSession($this, $staff, $organization, $engagement, $metric, $participant, $facilitator);

    Carbon::setTestNow($completable->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $completable]))
        ->assertOk();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $completable]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::COMPLETED->value);

    $cancelable = workshopTransitionCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        $participant,
        $facilitator,
        workshopTransitionFutureStart(4)
    );

    Carbon::setTestNow($cancelable->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $cancelable]))
        ->assertOk();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $cancelable]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::CANCELED->value);

    Carbon::setTestNow();
});

it('auto-marks overdue scheduled sessions as missed without emitting started audit events', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $facilitator->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(5),
        'timezone' => 'UTC',
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);
    $session->participants()->create(['engagement_participant_id' => $participant->id]);

    $this->actingAs($staff)
        ->getJson(route('org.engagements.workshop-sessions.index', [$organization, $engagement]))
        ->assertOk()
        ->assertJsonPath('data.0.status', EngagementWorkshopSessionStatus::SCHEDULED->value);

    app(ProcessWorkshopSessionReminders::class)->handle();

    expect($session->fresh()->status)->toBe(EngagementWorkshopSessionStatus::MISSED)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.started')
                ->where('subject_id', (string) $session->getKey())
                ->count()
        )->toBe(0)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.missed')
                ->where('subject_id', (string) $session->getKey())
                ->count()
        )->toBeGreaterThan(0);
});

it('does not auto-mark overdue scheduled sessions as missed when engagement is finalized', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $facilitator->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(5),
        'timezone' => 'UTC',
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);
    $session->participants()->create(['engagement_participant_id' => $participant->id]);

    app(ProcessWorkshopSessionReminders::class)->handle();

    expect($session->fresh()->status)->toBe(EngagementWorkshopSessionStatus::SCHEDULED)
        ->and(
            AuditLog::query()
                ->where('action', 'engagement.workshop_session.missed')
                ->where('subject_id', (string) $session->getKey())
                ->count()
        )->toBe(0);
});

it('does not send workshop reminders when engagement is finalized', function () {
    [$staff, $organization, $facilitator] = workshopTransitionStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);
    [$metric, $participant] = workshopTransitionScopedMetricAndApprover($organization, $engagement);

    Carbon::setTestNow(now()->startOfMinute());

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => $facilitator->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->addDay()->addMinutes(2),
        'ends_at' => now()->addDay()->addMinutes(62),
        'timezone' => 'UTC',
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);
    $session->participants()->create(['engagement_participant_id' => $participant->id]);

    Notification::fake();
    app(ProcessWorkshopSessionReminders::class)->handle();

    Notification::assertNothingSent();
    Notification::assertNotSentTo($staff, WorkshopSessionReminderNotification::class);

    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Organization, 2: Membership}
 */
function workshopTransitionStaffContext(): array
{
    $organization = Organization::factory()->create();
    $staff = User::factory()->create();
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
function workshopTransitionScopedMetricAndApprover(
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

function workshopTransitionCreateSession(
    TestCase $testCase,
    User $staff,
    Organization $organization,
    Engagement $engagement,
    Metric $metric,
    EngagementParticipant $participant,
    Membership $facilitator,
    ?Carbon $startsAt = null
): EngagementWorkshopSession {
    $startsAt ??= workshopTransitionFutureStart(2);
    $startsAt = workshopTransitionAlignFiveMinuteSlot($startsAt);
    $endsAt = workshopTransitionAlignFiveMinuteSlot(
        $startsAt->copy()->addHour()
    );

    $response = $testCase->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'timezone' => 'UTC',
            'facilitator_membership_id' => (string) $facilitator->id,
            'metric_ids' => [(string) $metric->id],
            'participant_ids' => [(string) $participant->id],
        ]
    );

    $response->assertCreated();

    return EngagementWorkshopSession::query()->findOrFail($response->json('data.id'));
}

function workshopTransitionAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}

function workshopTransitionFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}
