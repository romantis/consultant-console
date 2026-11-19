<?php

use App\Enums\EngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Jobs\ProcessAssignmentReminders;
use App\Mail\FormDueReminderMail;
use App\Models\Engagement;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\ReminderSchedule;
use App\Models\User;
use App\Support\Notifications\ReminderDispatcher;
use Illuminate\Support\Facades\Mail;

it('schedules reminders only for contributor assignments in awaiting forms engagements', function () {
    $organization = Organization::factory()->create();
    $awaitingForms = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $draft = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT->value,
    ]);

    $eligible = createReminderAssignment($organization, $awaitingForms);
    $nonContributor = createReminderAssignment($organization, $awaitingForms, [
        'responsibility' => 'Approver',
    ]);
    $draftContributor = createReminderAssignment($organization, $draft);

    app(ProcessAssignmentReminders::class)->handle();

    $windowCount = count(ReminderSchedule::defaultWindowOffsets());

    expect(ReminderSchedule::query()->where('form_assignment_id', $eligible->id)->count())->toBe($windowCount)
        ->and(ReminderSchedule::query()->where('form_assignment_id', $nonContributor->id)->count())->toBe(0)
        ->and(ReminderSchedule::query()->where('form_assignment_id', $draftContributor->id)->count())->toBe(0)
        ->and(ReminderSchedule::query()
            ->where('form_assignment_id', $eligible->id)
            ->pluck('channel')
            ->unique()
            ->values()
            ->all())->toBe([ReminderSchedule::CHANNEL_EMAIL]);
});

it('cancels pending schedules when an engagement leaves awaiting forms', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $assignment = createReminderAssignment($organization, $engagement);
    $windowCount = count(ReminderSchedule::defaultWindowOffsets());

    app(ProcessAssignmentReminders::class)->handle();

    expect(ReminderSchedule::query()
        ->where('form_assignment_id', $assignment->id)
        ->where('status', ReminderSchedule::STATUS_PENDING)
        ->count())->toBe($windowCount);

    $engagement->forceFill(['status' => EngagementStatus::IN_REVIEW->value])->save();

    app(ProcessAssignmentReminders::class)->handle();

    expect(ReminderSchedule::query()
        ->where('form_assignment_id', $assignment->id)
        ->where('status', ReminderSchedule::STATUS_PENDING)
        ->count())->toBe(0)
        ->and(ReminderSchedule::query()
            ->where('form_assignment_id', $assignment->id)
            ->where('status', ReminderSchedule::STATUS_CANCELLED)
            ->count())->toBe($windowCount);
});

it('normalizes pending schedules to email channel', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $assignment = createReminderAssignment($organization, $engagement, [
        'due_at' => now()->addDays(3),
    ]);

    $schedule = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => (string) $assignment->metric_id,
        'form_assignment_id' => $assignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => 'slack',
        'deliver_at' => now()->addDay(),
        'status' => ReminderSchedule::STATUS_PENDING,
    ]);

    app(ProcessAssignmentReminders::class)->handle();

    expect($schedule->fresh()->channel)->toBe(ReminderSchedule::CHANNEL_EMAIL);
});

it('does not rewrite non-pending schedule channels', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $assignment = createReminderAssignment($organization, $engagement, [
        'due_at' => now()->addDays(3),
    ]);

    $schedule = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => (string) $assignment->metric_id,
        'form_assignment_id' => $assignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => 'slack',
        'deliver_at' => now()->addDay(),
        'status' => ReminderSchedule::STATUS_SENT,
    ]);

    app(ProcessAssignmentReminders::class)->handle();

    expect($schedule->fresh()->channel)->toBe('slack');
});

it('sends overdue reminders only to the assigned contributor', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $owner = User::factory()->create();
    Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);

    $assignment = createReminderAssignment($organization, $engagement);
    $assignment->loadMissing('user');

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $assignment->user_id,
    ]);

    $schedule = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => (string) $assignment->metric_id,
        'form_assignment_id' => $assignment->id,
        'window' => ReminderSchedule::WINDOW_T_PLUS_24H,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'deliver_at' => now()->subMinute(),
        'status' => ReminderSchedule::STATUS_PENDING,
    ]);

    app(ReminderDispatcher::class)->dispatch($schedule->fresh());

    $schedule->refresh();

    expect($schedule->status)->toBe(ReminderSchedule::STATUS_SENT);

    Mail::assertSent(FormDueReminderMail::class, function (FormDueReminderMail $mail) use ($assignment, $owner) {
        return $mail->hasTo($assignment->user->email)
            && ! $mail->hasTo($owner->email)
            && ! $mail->hasCc($owner->email);
    });
});

it('cancels reminders when assignee is outside schedule organization', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $assignment = createReminderAssignment($organization, $engagement);
    $assignment->loadMissing('user');

    Membership::factory()->create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $assignment->user_id,
    ]);

    $schedule = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => (string) $assignment->metric_id,
        'form_assignment_id' => $assignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'deliver_at' => now()->subMinute(),
        'status' => ReminderSchedule::STATUS_PENDING,
    ]);

    app(ReminderDispatcher::class)->dispatch($schedule->fresh());

    $schedule->refresh();

    expect($schedule->status)->toBe(ReminderSchedule::STATUS_CANCELLED);

    Mail::assertNothingSent();
});

function createReminderAssignment(Organization $organization, Engagement $engagement, array $overrides = []): FormAssignment
{
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    return FormAssignment::factory()
        ->for($form, 'form')
        ->create(array_merge([
            'metric_id' => $metric->id,
            'responsibility' => 'Contributor',
            'status' => FormAssignmentStatus::OPEN->value,
            'due_at' => now()->addDays(7),
        ], $overrides));
}
