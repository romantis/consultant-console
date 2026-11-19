<?php

use App\Actions\Engagements\UpdateEngagementStatus;
use App\Enums\EngagementStatus;
use App\Enums\EngagementWorkshopSessionStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EngagementKickoffAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('notifies contributors with pending form assignments when engagement kicks off', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT->value,
    ]);
    $contributor = User::factory()->create(['email' => 'contributor@example.com']);

    Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $contributor->id,
    ]);

    $metric = Metric::factory()->for($engagement)->create();
    $form = Form::factory()->for($engagement)->create([
        'metric_id' => $metric->id,
        'title' => 'Revenue Intake',
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $contributor->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::AWAITING_FORMS);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::AWAITING_FORMS);

    Notification::assertSentTo(
        $contributor,
        EngagementKickoffAssignments::class,
        fn (EngagementKickoffAssignments $notification) => count($notification->formLinks) === 1
            && ($notification->formLinks[0]['url'] ?? null) === route('forms.show', ['form' => $form->id])
    );
});

it('skips kickoff notifications when assignments are not eligible', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT->value,
    ]);
    $contributor = User::factory()->create(['email' => 'contributor@example.com']);
    $observer = User::factory()->create(['email' => 'observer@example.com']);

    Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $contributor->id,
    ]);
    Membership::factory()->member()->create([
        'organization_id' => $organization->id,
        'user_id' => $observer->id,
    ]);

    $metric = Metric::factory()->for($engagement)->create();
    $form = Form::factory()->for($engagement)->create([
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $contributor->id,
        'status' => FormAssignmentStatus::COMPLETED->value,
        'responsibility' => 'Contributor',
    ]);
    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $observer->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Observer',
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::AWAITING_FORMS);

    Notification::assertNothingSent();
});

it('allows manual finalize when all metrics are finalized or deprecated', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Metric::factory()->for($engagement)->create([
        'status' => MetricStatus::FINALIZED->value,
    ]);
    Metric::factory()->for($engagement)->create([
        'status' => MetricStatus::DEPRECATED->value,
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::FINALIZED);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::FINALIZED)
        ->and($engagement->finalized_at)->not->toBeNull();
});

it('blocks manual finalize when the engagement has no metrics', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    try {
        app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::FINALIZED);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP)
        ->and($engagement->finalized_at)->toBeNull();
});

it('blocks manual finalize when any metric remains in workshop', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Metric::factory()->for($engagement)->create([
        'status' => MetricStatus::FINALIZED->value,
    ]);
    Metric::factory()->for($engagement)->create([
        'status' => MetricStatus::IN_WORKSHOP->value,
    ]);

    try {
        app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::FINALIZED);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP)
        ->and($engagement->finalized_at)->toBeNull();
});

it('blocks moving an engagement into workshop when no valid session exists', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    try {
        app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::IN_WORKSHOP);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }

    $audit = AuditLog::query()
        ->where('action', 'engagement.status.transition_blocked')
        ->latest('created_at')
        ->first();

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW)
        ->and($audit)->not->toBeNull()
        ->and($audit?->properties['reason'] ?? null)->toBe('missing_valid_session');
});

it('allows scheduled sessions to satisfy the workshop gate even if start time has passed', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    EngagementWorkshopSession::factory()->create([
        'organization_id' => $engagement->organization_id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->utc()->subMinutes(5),
        'ends_at' => now()->utc()->addMinutes(55),
        'timezone' => 'UTC',
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::IN_WORKSHOP);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP);
});

it('allows moving an engagement into workshop when a future scheduled session exists', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    EngagementWorkshopSession::factory()->create([
        'organization_id' => $engagement->organization_id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
        'timezone' => 'UTC',
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::IN_WORKSHOP);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_WORKSHOP);
});

it('blocks transitions that skip the rail order', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::DRAFT->value,
    ]);

    try {
        app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::FINALIZED);
        $this->fail('Expected validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }

    expect($engagement->refresh()->status)->toBe(EngagementStatus::DRAFT)
        ->and($engagement->finalized_at)->toBeNull();
});

it('progresses through the full consultant rail when prerequisites are met', function () {
    $engagement = Engagement::factory()->create([
        'status' => EngagementStatus::DRAFT->value,
    ]);

    $metric = Metric::factory()->for($engagement)->create([
        'status' => MetricStatus::IN_REVIEW->value,
    ]);

    EngagementWorkshopSession::factory()->create([
        'organization_id' => $engagement->organization_id,
        'engagement_id' => $engagement->id,
        'status' => EngagementWorkshopSessionStatus::SCHEDULED,
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'timezone' => 'UTC',
    ]);

    app(UpdateEngagementStatus::class)->handle($engagement, EngagementStatus::AWAITING_FORMS);
    app(UpdateEngagementStatus::class)->handle($engagement->refresh(), EngagementStatus::IN_REVIEW);
    app(UpdateEngagementStatus::class)->handle($engagement->refresh(), EngagementStatus::IN_WORKSHOP);

    $metric->forceFill([
        'status' => MetricStatus::FINALIZED->value,
    ])->save();

    app(UpdateEngagementStatus::class)->handle($engagement->refresh(), EngagementStatus::FINALIZED);

    expect($engagement->refresh()->status)->toBe(EngagementStatus::FINALIZED)
        ->and($engagement->finalized_at)->not->toBeNull();
});
