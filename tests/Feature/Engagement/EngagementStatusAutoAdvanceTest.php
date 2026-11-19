<?php

use App\Actions\Audit\LogEngagementEvent;
use App\Actions\FormAssignments\CompleteAssignment;
use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Jobs\NotifyEngagementStatusAutoAdvance;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EngagementStatusAutoAdvanced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function createAssignmentForForm(Form $form, User $user): array
{
    $assignment = FormAssignment::factory()->create([
        'form_id' => $form->id,
        'user_id' => $user->id,
    ]);

    $submission = FormSubmission::factory()->create([
        'form_id' => $form->id,
        'user_id' => $user->id,
    ]);

    return [$assignment, $submission];
}

test('auto-advances awaiting forms engagement when first metric becomes workshop-ready', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $user = User::factory()->create();
    $metricOne = Metric::factory()->for($engagement)->create(['organization_id' => $organization->id]);
    $metricTwo = Metric::factory()->for($engagement)->create(['organization_id' => $organization->id]);

    $formOne = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metricOne->id,
        'schema_json' => ['fields' => []],
    ]);
    $formTwo = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metricTwo->id,
        'schema_json' => ['fields' => []],
    ]);

    [$assignmentOne, $submissionOne] = createAssignmentForForm($formOne, $user);
    [$assignmentTwo, $submissionTwo] = createAssignmentForForm($formTwo, $user);

    $completeAssignment = app(CompleteAssignment::class);

    $completeAssignment->handle(
        assignment: $assignmentOne,
        submission: $submissionOne,
        actor: $user,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);

    $completeAssignment->handle(
        assignment: $assignmentTwo,
        submission: $submissionTwo,
        actor: $user,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);
});

test('auto-advances even if a form was already marked completed before submission', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $user = User::factory()->create();
    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
        'schema_json' => ['fields' => []],
        'status' => 'Completed',
    ]);

    [$assignment, $submission] = createAssignmentForForm($form, $user);

    app(CompleteAssignment::class)->handle(
        assignment: $assignment,
        submission: $submission,
        actor: $user,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);
});

test('auto-advances when contributor submissions complete even if approver assignment remains open', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $user = User::factory()->create();
    $approver = User::factory()->create();
    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
        'schema_json' => ['fields' => []],
    ]);

    [$assignmentOne, $submissionOne] = createAssignmentForForm($form, $user);
    [$assignmentTwo, $submissionTwo] = createAssignmentForForm($form, User::factory()->create());

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'user_id' => $approver->id,
        'responsibility' => 'Approver',
    ]);

    $completeAssignment = app(CompleteAssignment::class);

    $completeAssignment->handle(
        assignment: $assignmentOne,
        submission: $submissionOne,
        actor: $user,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    $completeAssignment->handle(
        assignment: $assignmentTwo,
        submission: $submissionTwo,
        actor: $assignmentTwo->user,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);
});

test('does not auto-advance when a metric still has incomplete contributor assignments', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $metric = Metric::factory()->for($engagement)->create(['organization_id' => $organization->id]);
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
        'schema_json' => ['fields' => []],
    ]);

    [$assignment, $submission] = createAssignmentForForm($form, $userOne);
    createAssignmentForForm($form, $userTwo);

    app(CompleteAssignment::class)->handle(
        assignment: $assignment,
        submission: $submission,
        actor: $userOne,
        payload: [],
        attestation: null,
        submissionToken: null
    );

    expect($engagement->refresh()->status)->toBe(EngagementStatus::AWAITING_FORMS);
});

test('notifies engagement drivers when status auto-advances', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    $internalOrganization = Organization::factory()->create();
    $internalOrganization->forceFill(['is_internal' => true])->save();

    $ownerMembership = Membership::factory()->owner()->for($organization)->create();
    $adminMembership = Membership::factory()->admin()->for($organization)->create();
    $memberMembership = Membership::factory()->member()->for($organization)->create();

    Membership::factory()
        ->owner()
        ->for($internalOrganization)
        ->for($ownerMembership->user, 'user')
        ->create();

    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $ownerMembership->id,
        'membership_role' => MembershipRole::OWNER,
        'active' => true,
    ]);
    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $adminMembership->id,
        'membership_role' => MembershipRole::ADMIN,
        'active' => true,
    ]);
    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $memberMembership->id,
        'membership_role' => MembershipRole::MEMBER,
        'active' => true,
    ]);

    $job = new NotifyEngagementStatusAutoAdvance(
        (string) $organization->id,
        (string) $engagement->id,
        EngagementStatus::AWAITING_FORMS->value,
        EngagementStatus::IN_REVIEW->value,
        2
    );

    $job->handle(app(LogEngagementEvent::class));

    Notification::assertSentTo($ownerMembership->user, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($adminMembership->user, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($memberMembership->user, EngagementStatusAutoAdvanced::class);
});

test('notifies org staff owners/admins when no staff participants are present', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    $internalOrganization = makeInternalOrg();

    $orgStaffUser = User::factory()->create(['email' => 'org-staff@example.com']);
    Membership::factory()
        ->owner()
        ->for($internalOrganization)
        ->for($orgStaffUser, 'user')
        ->create();
    Membership::factory()
        ->owner()
        ->for($organization)
        ->for($orgStaffUser, 'user')
        ->create();

    $internalOnlyStaff = User::factory()->create(['email' => 'internal-only@example.com']);
    Membership::factory()
        ->owner()
        ->for($internalOrganization)
        ->for($internalOnlyStaff, 'user')
        ->create();

    $ownerMembership = Membership::factory()->owner()->for($organization)->create();
    $adminMembership = Membership::factory()->admin()->for($organization)->create();

    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $ownerMembership->id,
        'membership_role' => MembershipRole::OWNER,
        'active' => true,
    ]);
    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $adminMembership->id,
        'membership_role' => MembershipRole::ADMIN,
        'active' => true,
    ]);

    $job = new NotifyEngagementStatusAutoAdvance(
        (string) $organization->id,
        (string) $engagement->id,
        EngagementStatus::AWAITING_FORMS->value,
        EngagementStatus::IN_REVIEW->value,
        2
    );

    $job->handle(app(LogEngagementEvent::class));

    Notification::assertSentTo($orgStaffUser, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($internalOnlyStaff, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($ownerMembership->user, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($adminMembership->user, EngagementStatusAutoAdvanced::class);
});

test('notifies staff fallback when no staff participants are present', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    $internalOrganization = makeInternalOrg();
    $staffUser = User::factory()->create(['email' => 'staff@example.com']);
    Membership::factory()
        ->owner()
        ->for($internalOrganization)
        ->for($staffUser, 'user')
        ->create();

    $ownerMembership = Membership::factory()->owner()->for($organization)->create();
    $adminMembership = Membership::factory()->admin()->for($organization)->create();

    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $ownerMembership->id,
        'membership_role' => MembershipRole::OWNER,
        'active' => true,
    ]);
    EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $adminMembership->id,
        'membership_role' => MembershipRole::ADMIN,
        'active' => true,
    ]);

    $job = new NotifyEngagementStatusAutoAdvance(
        (string) $organization->id,
        (string) $engagement->id,
        EngagementStatus::AWAITING_FORMS->value,
        EngagementStatus::IN_REVIEW->value,
        2
    );

    $job->handle(app(LogEngagementEvent::class));

    Notification::assertSentTo($staffUser, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($ownerMembership->user, EngagementStatusAutoAdvanced::class);
    Notification::assertNotSentTo($adminMembership->user, EngagementStatusAutoAdvanced::class);
});
