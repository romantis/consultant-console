<?php

use App\Actions\Workshops\DispatchWorkshopSessionNotifications;
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
use App\Notifications\WorkshopSessionCanceledNotification;
use App\Notifications\WorkshopSessionInviteNotification;
use App\Notifications\WorkshopSessionReminderNotification;
use App\Notifications\WorkshopSessionUninvitedNotification;
use App\Notifications\WorkshopSessionUpdatedNotification;
use App\Support\Workshops\WorkshopSessionScheduleLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('sends update notifications to current recipients when schedule fields change', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA, $participantAUser] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA],
        $facilitatorMembership
    );

    Notification::fake();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopNotificationPayload(
                $session,
                $metric,
                [$participantA],
                $facilitatorMembership,
                startsAt: $session->starts_at->copy()->addHour(),
                endsAt: $session->ends_at->copy()->addHour(),
            )
        )
        ->assertOk();

    Notification::assertSentTo($staff, WorkshopSessionUpdatedNotification::class);
    Notification::assertSentTo($participantAUser, WorkshopSessionUpdatedNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionInviteNotification::class);
});

it('sends one time invite only to newly added participants when schedule is unchanged', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA, $participantAUser] = workshopNotificationParticipant($organization, $engagement, $metric);
    [$participantB, $participantBUser] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA],
        $facilitatorMembership
    );

    Notification::fake();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopNotificationPayload(
                $session,
                $metric,
                [$participantA, $participantB],
                $facilitatorMembership
            )
        )
        ->assertOk();

    Notification::assertSentTo($participantBUser, WorkshopSessionInviteNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionInviteNotification::class);
    Notification::assertNotSentTo($staff, WorkshopSessionUpdatedNotification::class);
});

it('does not treat split-input lineup edits as schedule updates in non-utc app timezone', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA, $participantAUser] = workshopNotificationParticipant($organization, $engagement, $metric);
    [$participantB, $participantBUser] = workshopNotificationParticipant($organization, $engagement, $metric);

    $startsAt = Carbon::parse('2030-02-16 12:00:00', 'UTC');
    $endsAt = Carbon::parse('2030-02-16 12:30:00', 'UTC');

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA],
        $facilitatorMembership,
        startsAt: $startsAt,
        endsAt: $endsAt,
        timezone: 'Europe/Kyiv',
    );

    $originalAppTimezone = (string) config('app.timezone');
    config()->set('app.timezone', 'Europe/Kyiv');

    try {
        Notification::fake();

        $this->actingAs($staff)
            ->patchJson(
                route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
                [
                    'session_date' => '2030-02-16',
                    'starts_time' => '14:00',
                    'ends_time' => '14:30',
                    'timezone' => 'Europe/Kyiv',
                    'facilitator_membership_id' => (string) $facilitatorMembership->id,
                    'metric_ids' => [(string) $metric->id],
                    'participant_ids' => [(string) $participantA->id, (string) $participantB->id],
                    'notes' => 'lineup only',
                ]
            )
            ->assertOk();

        Notification::assertSentTo($participantBUser, WorkshopSessionInviteNotification::class);
        Notification::assertNotSentTo($participantAUser, WorkshopSessionUpdatedNotification::class);
        Notification::assertNotSentTo($participantBUser, WorkshopSessionUpdatedNotification::class);
        Notification::assertNotSentTo($staff, WorkshopSessionUpdatedNotification::class);
    } finally {
        config()->set('app.timezone', $originalAppTimezone);
    }
});

it('does not send update notifications when schedule is unchanged and no participants are added', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA],
        $facilitatorMembership
    );

    Notification::fake();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopNotificationPayload(
                $session,
                $metric,
                [$participantA],
                $facilitatorMembership,
                notes: 'Notes updated only.'
            )
        )
        ->assertOk();

    Notification::assertNothingSent();
});

it('sends removal notifications to removed participants when schedule is unchanged', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA, $participantAUser] = workshopNotificationParticipant($organization, $engagement, $metric);
    [$participantB, $participantBUser] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA, $participantB],
        $facilitatorMembership
    );

    Notification::fake();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopNotificationPayload(
                $session,
                $metric,
                [$participantA],
                $facilitatorMembership
            )
        )
        ->assertOk();

    Notification::assertSentTo($participantBUser, WorkshopSessionUninvitedNotification::class);
    Notification::assertNotSentTo($participantBUser, WorkshopSessionUpdatedNotification::class);
    Notification::assertNotSentTo($participantBUser, WorkshopSessionInviteNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionUninvitedNotification::class);
});

it('sends cancellation notifications to current recipients when a session is canceled', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA, $participantAUser] = workshopNotificationParticipant($organization, $engagement, $metric);
    [$participantB, $participantBUser] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA, $participantB],
        $facilitatorMembership
    );

    Notification::fake();

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $session]))
        ->assertOk();

    Notification::assertSentTo($staff, WorkshopSessionCanceledNotification::class);
    Notification::assertSentTo($participantAUser, WorkshopSessionCanceledNotification::class);
    Notification::assertSentTo($participantBUser, WorkshopSessionCanceledNotification::class);
    Notification::assertNotSentTo($staff, WorkshopSessionUpdatedNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionUpdatedNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionInviteNotification::class);
    Notification::assertNotSentTo($participantAUser, WorkshopSessionUninvitedNotification::class);
});

it('does not roll back writes when notification dispatch fails', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    [$participantA] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participantA],
        $facilitatorMembership
    );

    $mock = Mockery::mock(DispatchWorkshopSessionNotifications::class);
    $mock->shouldReceive('dispatchUpdated')
        ->once()
        ->andThrow(new RuntimeException('simulated notification failure'));
    app()->instance(DispatchWorkshopSessionNotifications::class, $mock);

    $sessionId = (string) $session->getKey();
    $currentStartsAt = Carbon::parse(
        (string) DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('starts_at'),
        config('app.timezone')
    )->utc();
    $currentEndsAt = Carbon::parse(
        (string) DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('ends_at'),
        config('app.timezone')
    )->utc();
    $newStartsAt = $currentStartsAt->copy()->addHours(2);
    $newEndsAt = $currentEndsAt->copy()->addHours(2);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            [
                'starts_at' => $newStartsAt->toIso8601String(),
                'ends_at' => $newEndsAt->toIso8601String(),
                'timezone' => $session->timezone,
                'facilitator_membership_id' => (string) $facilitatorMembership->id,
                'metric_ids' => [(string) $metric->id],
                'participant_ids' => [(string) $participantA->id],
                'notes' => 'schedule changed',
            ]
        )
        ->assertOk();

    $persistedStartsAt = Carbon::parse(
        (string) DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('starts_at'),
        config('app.timezone')
    )->utc();
    $persistedEndsAt = Carbon::parse(
        (string) DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('ends_at'),
        config('app.timezone')
    )->utc();

    expect($persistedStartsAt->toIso8601String())->toBe($newStartsAt->toIso8601String())
        ->and($persistedEndsAt->toIso8601String())->toBe($newEndsAt->toIso8601String());
});

it('renders workshop schedule and metric workshop links in notifications', function () {
    [$staff, $organization, $facilitatorMembership] = workshopNotificationStaffContext();
    [$engagement, $metric] = workshopNotificationEngagementAndMetric($organization);
    $metricB = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'name' => 'Metric B',
    ]);
    [$participant] = workshopNotificationParticipant($organization, $engagement, $metric);

    $session = workshopNotificationCreateSession(
        $this,
        $staff,
        $organization,
        $engagement,
        $metric,
        [$participant],
        $facilitatorMembership,
        startsAt: Carbon::parse('2030-03-15 15:00:00', 'UTC'),
        endsAt: Carbon::parse('2030-03-15 16:00:00', 'UTC'),
        timezone: 'America/New_York'
    );
    $session->metrics()->create([
        'metric_id' => $metricB->id,
    ]);
    $session->refresh();

    $scheduleLabel = WorkshopSessionScheduleLabel::format(
        $session->starts_at->copy(),
        $session->ends_at->copy(),
        (string) $session->timezone,
    );
    $metricAWorkshopUrl = route('org.engagements.workshop', [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => (string) $metric->id,
    ]);
    $metricBWorkshopUrl = route('org.engagements.workshop', [
        'organization' => $organization,
        'engagement' => $engagement,
        'metric' => (string) $metricB->id,
    ]);

    $inviteMail = (new WorkshopSessionInviteNotification($session))->toMail($staff);
    $updateMail = (new WorkshopSessionUpdatedNotification($session))->toMail($staff);
    $reminderMail = (new WorkshopSessionReminderNotification($session, 'T-24h'))->toMail($staff);
    $canceledMail = (new WorkshopSessionCanceledNotification($session))->toMail($staff);
    $uninvitedMail = (new WorkshopSessionUninvitedNotification($session))->toMail($staff);
    $inviteLines = collect($inviteMail->introLines)->join(' ');
    $updateLines = collect($updateMail->introLines)->join(' ');
    $reminderLines = collect($reminderMail->introLines)->join(' ');
    $canceledLines = collect($canceledMail->introLines)->join(' ');
    $uninvitedLines = collect($uninvitedMail->introLines)->join(' ');

    expect($inviteLines)->toContain($scheduleLabel)
        ->toContain((string) $metric->name)
        ->toContain((string) $metricB->name)
        ->toContain('['.(string) $metric->name.'](')
        ->toContain($metricAWorkshopUrl)
        ->toContain($metricBWorkshopUrl)
        ->toContain('America/New_York');
    expect($updateLines)->toContain($scheduleLabel)
        ->toContain((string) $metric->name)
        ->toContain((string) $metricB->name)
        ->toContain($metricAWorkshopUrl)
        ->toContain($metricBWorkshopUrl)
        ->toContain('America/New_York');
    expect($reminderLines)->toContain($scheduleLabel)
        ->toContain((string) $metric->name)
        ->toContain((string) $metricB->name)
        ->toContain($metricAWorkshopUrl)
        ->toContain($metricBWorkshopUrl)
        ->toContain('America/New_York');
    expect($canceledLines)->toContain($scheduleLabel)
        ->toContain((string) $metric->name)
        ->toContain((string) $metricB->name)
        ->toContain('America/New_York');
    expect($uninvitedLines)->toContain('Your workshop invitation has been canceled.')
        ->toContain($scheduleLabel)
        ->toContain((string) $metric->name)
        ->toContain((string) $metricB->name)
        ->toContain('No action is required.');
    expect($canceledLines)->not->toContain($metricAWorkshopUrl)
        ->not->toContain($metricBWorkshopUrl)
        ->not->toContain('/workshop?metric=')
        ->not->toContain('](');
    expect($inviteMail->actionUrl)->toContain('/workshop?metric=');
    expect(in_array($inviteMail->actionUrl, [$metricAWorkshopUrl, $metricBWorkshopUrl], true))->toBeTrue();
    expect($updateMail->actionUrl)->toContain('/workshop?metric=');
    expect(in_array($updateMail->actionUrl, [$metricAWorkshopUrl, $metricBWorkshopUrl], true))->toBeTrue();
    expect($reminderMail->actionUrl)->toContain('/workshop?metric=');
    expect(in_array($reminderMail->actionUrl, [$metricAWorkshopUrl, $metricBWorkshopUrl], true))->toBeTrue();
    expect($canceledMail->actionUrl)->toBeNull();
    expect($uninvitedMail->actionUrl)->toBeNull();
});

/**
 * @return array{0: User, 1: Organization, 2: Membership}
 */
function workshopNotificationStaffContext(): array
{
    $organization = Organization::factory()->create();
    $staff = User::factory()->create([
        'email' => 'facilitator@example.test',
    ]);
    $internal = Organization::query()->where('slug', 'metric-harmony')->first() ?? makeInternalOrg();

    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staff->id,
    ]);

    $facilitatorMembership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $staff->id,
    ]);

    return [$staff, $organization, $facilitatorMembership];
}

/**
 * @return array{0: Engagement, 1: Metric}
 */
function workshopNotificationEngagementAndMetric(Organization $organization): array
{
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    return [$engagement, $metric];
}

/**
 * @return array{0: EngagementParticipant, 1: User}
 */
function workshopNotificationParticipant(
    Organization $organization,
    Engagement $engagement,
    Metric $metric
): array {
    $user = User::factory()->create();

    $membership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membership->id,
        'membership_role' => MembershipRole::MEMBER,
    ]);

    MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participant->id,
        'user_id' => $user->id,
    ]);

    $form = Form::query()->firstOrCreate(
        [
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
            'metric_id' => $metric->id,
        ],
        [
            'template_key' => 'workshop-definition',
            'title' => 'Workshop Definition',
            'schema_json' => ['fields' => []],
            'status' => 'Open',
        ]
    );

    FormAssignment::query()->firstOrCreate(
        [
            'form_id' => $form->id,
            'user_id' => $user->id,
        ],
        [
            'metric_id' => $metric->id,
            'responsibility' => 'Contributor',
            'role_tags_json' => [],
        ]
    );

    FormSubmission::query()->updateOrCreate(
        [
            'form_id' => $form->id,
            'user_id' => $user->id,
        ],
        [
            'answers_json' => ['notes' => 'ready'],
            'submitted_at' => now(),
        ]
    );

    return [$participant, $user];
}

function workshopNotificationCreateSession(
    TestCase $testCase,
    User $staff,
    Organization $organization,
    Engagement $engagement,
    Metric $metric,
    array $participants,
    Membership $facilitatorMembership,
    ?Carbon $startsAt = null,
    ?Carbon $endsAt = null,
    string $timezone = 'UTC'
): EngagementWorkshopSession {
    $startsAt ??= workshopNotificationFutureStart(2);
    $endsAt ??= $startsAt->copy()->addHour();
    $startsAt = workshopNotificationAlignFiveMinuteSlot($startsAt);
    $endsAt = workshopNotificationAlignFiveMinuteSlot($endsAt);

    $participantIds = collect($participants)
        ->map(fn (EngagementParticipant $participant) => (string) $participant->id)
        ->values()
        ->all();

    $response = $testCase->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'timezone' => $timezone,
            'facilitator_membership_id' => (string) $facilitatorMembership->id,
            'metric_ids' => [(string) $metric->id],
            'participant_ids' => $participantIds,
            'notes' => 'initial notes',
        ]
    );

    $response->assertCreated();

    return EngagementWorkshopSession::query()->findOrFail($response->json('data.id'));
}

/**
 * @param  array<int, EngagementParticipant>  $participants
 * @return array<string, mixed>
 */
function workshopNotificationPayload(
    EngagementWorkshopSession $session,
    Metric $metric,
    array $participants,
    Membership $facilitatorMembership,
    ?Carbon $startsAt = null,
    ?Carbon $endsAt = null,
    ?string $notes = null
): array {
    $startsAt ??= $session->starts_at->copy();
    $endsAt ??= $session->ends_at->copy();
    $startsAt = workshopNotificationAlignFiveMinuteSlot($startsAt);
    $endsAt = workshopNotificationAlignFiveMinuteSlot($endsAt);

    return [
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'timezone' => $session->timezone,
        'facilitator_membership_id' => (string) $facilitatorMembership->id,
        'metric_ids' => [(string) $metric->id],
        'participant_ids' => collect($participants)
            ->map(fn (EngagementParticipant $participant) => (string) $participant->id)
            ->values()
            ->all(),
        'notes' => $notes ?? (string) ($session->notes ?? 'updated notes'),
    ];
}

function workshopNotificationAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}

function workshopNotificationFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}
