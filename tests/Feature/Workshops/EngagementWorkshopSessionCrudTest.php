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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('persists independent metric and participant scope across multiple sessions', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    [$metricA, $participantA] = workshopCrudScopedMetricAndApprover($organization, $engagement);
    [$metricB, $participantB] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $sessionA = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metricA, $participantA, $actingMembership, workshopCrudFutureStart(2))
    );

    $sessionB = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metricB, $participantB, $actingMembership, workshopCrudFutureStart(3))
    );

    $sessionA->assertCreated();
    $sessionB->assertCreated();

    $sessionAId = (string) $sessionA->json('data.id');
    $sessionBId = (string) $sessionB->json('data.id');

    $this->assertDatabaseHas('engagement_workshop_session_metrics', [
        'session_id' => $sessionAId,
        'metric_id' => (string) $metricA->id,
    ]);
    $this->assertDatabaseHas('engagement_workshop_session_metrics', [
        'session_id' => $sessionBId,
        'metric_id' => (string) $metricB->id,
    ]);
    $this->assertDatabaseMissing('engagement_workshop_session_metrics', [
        'session_id' => $sessionAId,
        'metric_id' => (string) $metricB->id,
    ]);
    $this->assertDatabaseMissing('engagement_workshop_session_metrics', [
        'session_id' => $sessionBId,
        'metric_id' => (string) $metricA->id,
    ]);

    $this->assertDatabaseHas('engagement_workshop_session_participants', [
        'session_id' => $sessionAId,
        'engagement_participant_id' => (string) $participantA->id,
    ]);
    $this->assertDatabaseHas('engagement_workshop_session_participants', [
        'session_id' => $sessionBId,
        'engagement_participant_id' => (string) $participantB->id,
    ]);
    $this->assertDatabaseMissing('engagement_workshop_session_participants', [
        'session_id' => $sessionAId,
        'engagement_participant_id' => (string) $participantB->id,
    ]);
    $this->assertDatabaseMissing('engagement_workshop_session_participants', [
        'session_id' => $sessionBId,
        'engagement_participant_id' => (string) $participantA->id,
    ]);
});

it('defaults facilitator to the acting staff membership on create', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $response = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, null, workshopCrudFutureStart(2))
    );

    $response->assertCreated()
        ->assertJsonPath('data.facilitator_membership_id', (string) $actingMembership->id);
});

it('blocks session scheduling when engagement is finalized', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $response = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(2))
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');
});

it('blocks workshop session updates and transitions when engagement is finalized', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $createResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(2))
    );

    $createResponse->assertCreated();
    $session = EngagementWorkshopSession::query()->findOrFail($createResponse->json('data.id'));

    $engagement->forceFill(['status' => EngagementStatus::FINALIZED->value])->save();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                $session->starts_at->copy(),
                $session->ends_at->copy(),
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $session]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $session]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['engagement'])
        ->assertJsonPath('errors.engagement.0', 'Engagement is finalized and read-only.');
});

it('validates session create duration lead time and timezone constraints', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover(
        $organization,
        $engagement,
        withReadyContributorForm: false
    );

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: workshopCrudFutureStart(2),
                endsAt: workshopCrudFutureStart(2)->addMinutes(10),
                timezone: 'UTC'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 09:02', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 09:17', 'UTC'),
                timezone: 'UTC',
                normalizeToFiveMinuteIncrements: false,
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at', 'ends_at']);

    Carbon::setTestNow('2030-03-14 09:50:00 UTC');

    try {
        $this->actingAs($staff)
            ->postJson(
                route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
                workshopCrudPayload(
                    $metric,
                    $participant,
                    $actingMembership,
                    startsAt: now()->addMinutes(10),
                    endsAt: now()->addMinutes(70),
                    timezone: 'UTC'
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    } finally {
        Carbon::setTestNow();
    }

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 07:45', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 08:30', 'UTC'),
                timezone: 'UTC'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at']);

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 17:00', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 19:15', 'UTC'),
                timezone: 'UTC'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);

    $this->actingAs($staff)
        ->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: workshopCrudFutureStart(2),
                endsAt: workshopCrudFutureStart(2)->addHour(),
                timezone: 'Mars/Phobos'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('allows session scheduling inside lead time when local force start mode is enabled', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $originalForceStartEnabled = (bool) config('metric-harmony.workshops.force_start_enabled');
    config()->set('metric-harmony.workshops.force_start_enabled', true);

    Carbon::setTestNow('2030-03-14 09:50:00 UTC');

    try {
        $response = $this->actingAs($staff)
            ->postJson(
                route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
                workshopCrudPayload(
                    $metric,
                    $participant,
                    $actingMembership,
                    startsAt: now()->addMinutes(5),
                    endsAt: now()->addMinutes(45),
                    timezone: 'UTC'
                )
            );

        $response->assertCreated();
    } finally {
        config()->set('metric-harmony.workshops.force_start_enabled', $originalForceStartEnabled);
        Carbon::setTestNow();
    }
});

it('requires metric contributor forms to be submitted before scheduling', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover(
        $organization,
        $engagement,
        withReadyContributorForm: false
    );
    $contributorMembership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);
    $payload = workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(2));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

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
    FormSubmission::factory()->create([
        'form_id' => $form->id,
        'user_id' => $contributorMembership->user_id,
        'submitted_at' => null,
    ]);

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

    FormSubmission::query()
        ->where('form_id', $form->id)
        ->where('user_id', $contributorMembership->user_id)
        ->update(['submitted_at' => now()]);

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.store', [$organization, $engagement]), $payload)
        ->assertCreated();
});

it('interprets split schedule inputs in the selected timezone without storage drift', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $originalAppTimezone = (string) config('app.timezone');
    config()->set('app.timezone', 'Europe/Kyiv');

    try {
        $response = $this->actingAs($staff)->postJson(
            route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
            [
                'session_date' => '2030-03-14',
                'starts_time' => '09:00',
                'ends_time' => '10:00',
                'timezone' => 'America/New_York',
                'facilitator_membership_id' => (string) $actingMembership->id,
                'metric_ids' => [(string) $metric->id],
                'participant_ids' => [(string) $participant->id],
            ]
        );

        $response->assertCreated();

        $sessionId = (string) $response->json('data.id');
        $session = EngagementWorkshopSession::query()->findOrFail($sessionId);
        $expectedStart = Carbon::createFromFormat(
            'Y-m-d H:i',
            '2030-03-14 09:00',
            'America/New_York'
        )->utc();
        $expectedEnd = Carbon::createFromFormat(
            'Y-m-d H:i',
            '2030-03-14 10:00',
            'America/New_York'
        )->utc();

        $rawStoredStart = DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('starts_at');
        $rawStoredEnd = DB::table('engagement_workshop_sessions')
            ->where('id', $sessionId)
            ->value('ends_at');
        $storageTimezone = config('app.timezone');

        expect(Carbon::parse((string) $rawStoredStart, $storageTimezone)->utc()->toIso8601String())->toBe(
            $expectedStart->toIso8601String()
        )
            ->and(Carbon::parse((string) $rawStoredEnd, $storageTimezone)->utc()->toIso8601String())->toBe(
                $expectedEnd->toIso8601String()
            )
            ->and($session->timezone)->toBe('America/New_York');
    } finally {
        config()->set('app.timezone', $originalAppTimezone);
    }
});

it('validates split schedule business hours with non-utc app timezone', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $originalAppTimezone = (string) config('app.timezone');
    config()->set('app.timezone', 'Europe/Kyiv');

    try {
        $this->actingAs($staff)
            ->postJson(
                route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
                [
                    'session_date' => '2030-03-14',
                    'starts_time' => '07:55',
                    'ends_time' => '08:30',
                    'timezone' => 'America/New_York',
                    'facilitator_membership_id' => (string) $actingMembership->id,
                    'metric_ids' => [(string) $metric->id],
                    'participant_ids' => [(string) $participant->id],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    } finally {
        config()->set('app.timezone', $originalAppTimezone);
    }
});

it('enforces business-hour scheduling constraints when updating sessions', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $created = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload(
            $metric,
            $participant,
            $actingMembership,
            startsAt: Carbon::parse('2030-03-14 09:00', 'UTC'),
            endsAt: Carbon::parse('2030-03-14 10:00', 'UTC'),
            timezone: 'UTC'
        )
    );
    $created->assertCreated();
    $session = EngagementWorkshopSession::query()->findOrFail($created->json('data.id'));

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 07:30', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 08:30', 'UTC'),
                timezone: 'UTC'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at']);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 17:00', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 19:30', 'UTC'),
                timezone: 'UTC'
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ends_at']);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                startsAt: Carbon::parse('2030-03-14 10:01', 'UTC'),
                endsAt: Carbon::parse('2030-03-14 10:16', 'UTC'),
                timezone: 'UTC',
                normalizeToFiveMinuteIncrements: false,
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at', 'ends_at']);
});

it('allows same-organization staff facilitator reassignment and denies cross-organization facilitator ids', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);
    [$alternateFacilitatorUser, $alternateFacilitatorMembership] = workshopCrudStaffMembership($organization);

    $createResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(2))
    );

    $createResponse->assertCreated();

    $session = EngagementWorkshopSession::query()->findOrFail($createResponse->json('data.id'));

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload(
                $metric,
                $participant,
                $alternateFacilitatorMembership,
                $session->starts_at->copy()
            )
        )
        ->assertOk()
        ->assertJsonPath('data.facilitator_membership_id', (string) $alternateFacilitatorMembership->id);

    $otherOrganization = Organization::factory()->create();
    [$crossOrgUser, $crossOrgMembership] = workshopCrudStaffMembership($otherOrganization);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            workshopCrudPayload($metric, $participant, $crossOrgMembership, $session->starts_at->copy())
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['facilitator_membership_id']);

    expect($alternateFacilitatorUser)->not->toBeNull()
        ->and($crossOrgUser)->not->toBeNull();
});

it('rejects facilitator metric scope and participant updates once a session is in progress', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metricA, $participantA] = workshopCrudScopedMetricAndApprover($organization, $engagement);
    [$metricB, $participantB] = workshopCrudScopedMetricAndApprover($organization, $engagement);
    [$alternateFacilitatorUser, $alternateFacilitatorMembership] = workshopCrudStaffMembership($organization);

    $createResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metricA, $participantA, $actingMembership, workshopCrudFutureStart(2))
    );

    $createResponse->assertCreated();

    $session = EngagementWorkshopSession::query()->findOrFail($createResponse->json('data.id'));
    Carbon::setTestNow($session->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertOk()
        ->assertJsonPath('data.status', EngagementWorkshopSessionStatus::IN_PROGRESS->value);

    Carbon::setTestNow();

    $basePayload = workshopCrudPayload(
        $metricA,
        $participantA,
        $actingMembership,
        $session->starts_at->copy(),
        $session->ends_at->copy(),
    );

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            array_merge($basePayload, [
                'facilitator_membership_id' => (string) $alternateFacilitatorMembership->id,
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['facilitator_membership_id']);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            array_merge($basePayload, [
                'metric_ids' => [(string) $metricA->id, (string) $metricB->id],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metric_ids']);

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            array_merge($basePayload, [
                'participant_ids' => [(string) $participantA->id, (string) $participantB->id],
            ])
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['participant_ids']);

    Carbon::setTestNow();

    expect($alternateFacilitatorUser)->not->toBeNull();
});

it('rejects edits for completed, canceled, and missed sessions', function () {
    [$staff, $organization, $actingMembership] = workshopCrudStaffContext();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    [$metric, $participant] = workshopCrudScopedMetricAndApprover($organization, $engagement);

    $created = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(2))
    );

    $created->assertCreated();
    $session = EngagementWorkshopSession::query()->findOrFail($created->json('data.id'));
    Carbon::setTestNow($session->starts_at->copy()->subMinutes(5));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.start', [$organization, $engagement, $session]))
        ->assertOk();
    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.complete', [$organization, $engagement, $session]))
        ->assertOk();

    Carbon::setTestNow();

    $completedPayload = workshopCrudPayload(
        $metric,
        $participant,
        $actingMembership,
        $session->starts_at->copy(),
        $session->ends_at->copy(),
    );

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $session]),
            $completedPayload
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session']);

    $canceledResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(3))
    );

    $canceledResponse->assertCreated();
    $canceledSession = EngagementWorkshopSession::query()->findOrFail($canceledResponse->json('data.id'));

    $this->actingAs($staff)
        ->postJson(route('org.engagements.workshop-sessions.cancel', [$organization, $engagement, $canceledSession]))
        ->assertOk();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $canceledSession]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                $canceledSession->starts_at->copy(),
                $canceledSession->ends_at->copy(),
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session']);

    $missedResponse = $this->actingAs($staff)->postJson(
        route('org.engagements.workshop-sessions.store', [$organization, $engagement]),
        workshopCrudPayload($metric, $participant, $actingMembership, workshopCrudFutureStart(5))
    );

    $missedResponse->assertCreated();
    $missedSession = EngagementWorkshopSession::query()->findOrFail($missedResponse->json('data.id'));
    $missedSession->forceFill([
        'status' => EngagementWorkshopSessionStatus::MISSED,
    ])->save();

    $this->actingAs($staff)
        ->patchJson(
            route('org.engagements.workshop-sessions.update', [$organization, $engagement, $missedSession]),
            workshopCrudPayload(
                $metric,
                $participant,
                $actingMembership,
                $missedSession->starts_at->copy(),
                $missedSession->ends_at->copy(),
            )
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['session']);

    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Organization, 2: Membership}
 */
function workshopCrudStaffContext(): array
{
    $organization = Organization::factory()->create();
    [$staff, $membership] = workshopCrudStaffMembership($organization);

    return [$staff, $organization, $membership];
}

/**
 * @return array{0: User, 1: Membership}
 */
function workshopCrudStaffMembership(Organization $organization): array
{
    $staff = User::factory()->create();
    $internal = Organization::query()->where('slug', 'metric-harmony')->first() ?? makeInternalOrg();

    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staff->id,
    ]);

    $membership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $staff->id,
    ]);

    return [$staff, $membership];
}

/**
 * @return array{0: Metric, 1: EngagementParticipant}
 */
function workshopCrudScopedMetricAndApprover(
    Organization $organization,
    Engagement $engagement,
    bool $withReadyContributorForm = true
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

    if ($withReadyContributorForm) {
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
    }

    return [$metric, $participant];
}

/**
 * @return array<string, mixed>
 */
function workshopCrudPayload(
    Metric $metric,
    EngagementParticipant $participant,
    ?Membership $facilitatorMembership = null,
    ?Carbon $startsAt = null,
    ?Carbon $endsAt = null,
    string $timezone = 'UTC',
    bool $normalizeToFiveMinuteIncrements = true,
): array {
    $startsAt ??= workshopCrudFutureStart(2);
    $endsAt ??= $startsAt->copy()->addHour();

    if ($normalizeToFiveMinuteIncrements) {
        $startsAt = workshopCrudAlignFiveMinuteSlot($startsAt);
        $endsAt = workshopCrudAlignFiveMinuteSlot($endsAt);
    }

    return array_filter([
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'timezone' => $timezone,
        'notes' => 'Session notes',
        'facilitator_membership_id' => $facilitatorMembership?->id ? (string) $facilitatorMembership->id : null,
        'metric_ids' => [(string) $metric->id],
        'participant_ids' => [(string) $participant->id],
    ], fn ($value) => $value !== null);
}

function workshopCrudFutureStart(int $daysFromNow): Carbon
{
    return Carbon::now('UTC')
        ->addDays($daysFromNow)
        ->setTime(10, 0)
        ->second(0)
        ->microsecond(0);
}

function workshopCrudAlignFiveMinuteSlot(Carbon $value): Carbon
{
    $aligned = $value->copy()->second(0)->microsecond(0);
    $minute = (int) $aligned->format('i');

    return $aligned->setMinute(intdiv($minute, 5) * 5);
}
