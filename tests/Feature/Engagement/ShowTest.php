<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\FinalDefinition;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\NotificationAttempt;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\ReminderSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('loads engagement insights for authorized members', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create([
        'settings_json' => [
            'locale' => 'en',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
        ],
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
        'starts_on' => now()->subWeek()->toDateString(),
        'ends_on' => now()->addWeek()->toDateString(),
    ]);

    $adminMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $finalizedMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'last_submitted_at' => now()->subDays(3),
    ]);
    $inReviewMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);
    $awaitingMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::AWAITING_FORMS,
    ]);

    $formOpen = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $awaitingMetric->id,
    ]);
    $formInProgress = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $inReviewMetric->id,
    ]);
    $formCompleted = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $finalizedMetric->id,
    ]);

    $contributorA = User::factory()->create();
    $contributorB = User::factory()->create();
    $approver = User::factory()->create();

    FormAssignment::factory()->create([
        'form_id' => $formOpen->id,
        'user_id' => $contributorA->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDays(2),
    ]);

    FormAssignment::factory()->create([
        'form_id' => $formInProgress->id,
        'user_id' => $contributorA->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDays(1),
    ]);
    FormAssignment::factory()->create([
        'form_id' => $formInProgress->id,
        'user_id' => $contributorB->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDays(3),
    ]);
    FormSubmission::factory()->submitted()->create([
        'form_id' => $formInProgress->id,
        'user_id' => $contributorA->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $formCompleted->id,
        'user_id' => $contributorA->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDays(1),
    ]);
    FormAssignment::factory()->create([
        'form_id' => $formCompleted->id,
        'user_id' => $contributorB->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDays(1),
    ]);
    FormSubmission::factory()->submitted()->create([
        'form_id' => $formCompleted->id,
        'user_id' => $contributorA->id,
    ]);
    FormSubmission::factory()->submitted()->create([
        'form_id' => $formCompleted->id,
        'user_id' => $contributorB->id,
    ]);
    FormAssignment::factory()->create([
        'form_id' => $formCompleted->id,
        'user_id' => $approver->id,
        'responsibility' => 'Approver',
    ]);

    $otherUser = User::factory()->create();
    $otherMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $otherUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $memberParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $otherMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $pendingMembership = PendingMembership::factory()->for($organization)->create([
        'engagement_id' => $engagement->id,
    ]);

    $pendingParticipant = EngagementParticipant::factory()->pending()->create([
        'engagement_id' => $engagement->id,
        'membership_id' => null,
        'pending_membership_id' => $pendingMembership->id,
        'membership_role' => MembershipRole::MEMBER,
        'active' => true,
    ]);

    MetricParticipant::factory()->contributor()->create([
        'metric_id' => $finalizedMetric->id,
        'engagement_participant_id' => $memberParticipant->id,
        'user_id' => $otherUser->id,
        'subject_type' => Membership::class,
        'subject_id' => $otherMembership->id,
        'role_tags_json' => ['lead'],
    ]);

    MetricParticipant::factory()->approver()->create([
        'metric_id' => $inReviewMetric->id,
        'engagement_participant_id' => $pendingParticipant->id,
        'user_id' => null,
        'subject_type' => PendingMembership::class,
        'subject_id' => $pendingMembership->id,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $finalizedMetric->id,
        'status' => 'Signed',
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $user->id,
    ]);

    Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $finalizedMetric->id,
        'status' => 'Open',
        'detected_at' => now()->subHours(6),
    ]);
    Discrepancy::factory()->create([
        'engagement_id' => $engagement->id,
        'metric_id' => $inReviewMetric->id,
        'status' => 'Resolved',
        'resolved_at' => now()->subHour(),
    ]);

    $workshopSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'facilitator_membership_id' => Membership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->value('id'),
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHour(),
        'timezone' => 'UTC',
    ]);
    $workshopSession->metrics()->create([
        'metric_id' => $finalizedMetric->id,
    ]);
    $workshopSession->participants()->create([
        'engagement_participant_id' => $memberParticipant->id,
    ]);

    AuditLog::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'action' => 'engagement.status.updated',
        'subject_type' => Engagement::class,
        'subject_id' => $engagement->id,
        'properties' => [
            'summary' => 'Promoted to Awaiting Forms',
        ],
    ]);

    $reminderRecipient = User::factory()->create([
        'email' => 'contributor@example.test',
    ]);
    $reminderAssignment = FormAssignment::factory()->create([
        'form_id' => $formOpen->id,
        'metric_id' => $finalizedMetric->id,
        'user_id' => $reminderRecipient->id,
        'responsibility' => 'Approver',
    ]);
    $reminderSchedule = ReminderSchedule::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $finalizedMetric->id,
        'form_assignment_id' => $reminderAssignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'deliver_at' => now()->subHours(2),
        'status' => ReminderSchedule::STATUS_SENT,
    ]);
    NotificationAttempt::query()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $finalizedMetric->id,
        'form_assignment_id' => $reminderAssignment->id,
        'notification_id' => $reminderSchedule->id,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'provider_response' => ['reason' => 'mailbox deferred'],
        'status' => NotificationAttempt::STATUS_FAILED,
        'attempted_at' => now()->subMinutes(5),
    ]);

    $otherOrganization = Organization::factory()->create();
    $otherEngagement = Engagement::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);
    $otherMetric = Metric::factory()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $otherEngagement->id,
    ]);
    $otherForm = Form::factory()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $otherEngagement->id,
        'metric_id' => $otherMetric->id,
    ]);
    $otherRecipient = User::factory()->create([
        'email' => 'outside@example.test',
    ]);
    $otherAssignment = FormAssignment::factory()->create([
        'form_id' => $otherForm->id,
        'metric_id' => $otherMetric->id,
        'user_id' => $otherRecipient->id,
        'responsibility' => 'Approver',
    ]);
    $otherSchedule = ReminderSchedule::query()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $otherEngagement->id,
        'metric_id' => $otherMetric->id,
        'form_assignment_id' => $otherAssignment->id,
        'window' => ReminderSchedule::WINDOW_T_MINUS_24H,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'deliver_at' => now()->subHours(1),
        'status' => ReminderSchedule::STATUS_SENT,
    ]);
    NotificationAttempt::query()->create([
        'organization_id' => $otherOrganization->id,
        'engagement_id' => $otherEngagement->id,
        'metric_id' => $otherMetric->id,
        'form_assignment_id' => $otherAssignment->id,
        'notification_id' => $otherSchedule->id,
        'channel' => ReminderSchedule::CHANNEL_EMAIL,
        'provider_response' => ['reason' => 'accepted'],
        'status' => NotificationAttempt::STATUS_SENT,
        'attempted_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Engagements/Show')
            ->where('organization.id', (string) $organization->id)
            ->where('organization.timezone', 'America/New_York')
            ->where('engagement.status', EngagementStatus::AWAITING_FORMS->value)
            ->where('forms.total', 3)
            ->where('forms.status_counts.Open', 1)
            ->where('forms.status_counts.InProgress', 1)
            ->where('forms.status_counts.Completed', 1)
            ->where('engagement.checklist.workshop_ready', false)
            ->where('forms.items', function ($items) use ($formOpen, $formInProgress, $formCompleted) {
                $rows = collect($items)->keyBy('id');
                $openRow = $rows->get((string) $formOpen->id);
                $progressRow = $rows->get((string) $formInProgress->id);
                $completeRow = $rows->get((string) $formCompleted->id);

                if (! $openRow || ! $progressRow || ! $completeRow) {
                    return false;
                }

                return $openRow['status'] === 'Open'
                    && $openRow['assignment_count'] === 1
                    && $openRow['submitted_count'] === 0
                    && ($openRow['viewer_can_submit'] ?? null) === false
                    && $progressRow['status'] === 'InProgress'
                    && $progressRow['assignment_count'] === 2
                    && $progressRow['submitted_count'] === 1
                    && $progressRow['draft_count'] === 1
                    && $completeRow['status'] === 'Completed'
                    && $completeRow['assignment_count'] === 2
                    && $completeRow['submitted_count'] === 2
                    && $completeRow['draft_count'] === 0;
            })
            ->where('metrics.total', 3)
            ->where('metrics.status_counts.Finalized', 1)
            ->where('metrics.status_counts.InReview', 1)
            ->where('metrics.status_counts.AwaitingForms', 1)
            ->where('workshops.0.id', fn ($id) => ! empty($id))
            ->where('metrics.items', function ($items) use ($finalizedMetric, $inReviewMetric, $otherUser) {
                $finalizedItem = collect($items)->firstWhere('id', (string) $finalizedMetric->id);
                $pendingItem = collect($items)->firstWhere('id', (string) $inReviewMetric->id);

                return $finalizedItem
                    && ! empty($finalizedItem['participants'])
                    && ($finalizedItem['participants'][0]['assignee']['name'] ?? null) === $otherUser->name
                    && ($finalizedItem['final_definition_progress']['state'] ?? null) === 'signed_off'
                    && $pendingItem
                    && ! empty($pendingItem['participants'])
                    && ($pendingItem['participants'][0]['assignee']['is_pending'] ?? false) === true
                    && ($pendingItem['final_definition_progress']['state'] ?? null) === 'missing';
            })
            ->where('metrics.assignment_options', function ($options) use ($adminMembership, $memberParticipant, $pendingParticipant) {
                $rows = collect($options);
                $ids = $rows->pluck('id');
                $memberRow = $rows->firstWhere('id', (string) $memberParticipant->id);
                $pendingRow = $rows->firstWhere('id', (string) $pendingParticipant->id);
                $staffCandidateId = 'membership:'.$adminMembership->id;
                $staffRow = $rows->firstWhere('id', $staffCandidateId);

                return $ids->contains((string) $memberParticipant->id)
                    && $ids->contains((string) $pendingParticipant->id)
                    && $ids->contains($staffCandidateId)
                    && ($staffRow['is_staff'] ?? false) === true
                    && array_key_exists('is_staff', $memberRow ?? [])
                    && array_key_exists('is_staff', $pendingRow ?? []);
            })
            ->has('timeline.forms', 3)
            ->has('activity', 1)
            ->where('deliverables.finalized_at', null)
            ->where('deliverables.total', 0)
            ->where('reminders.recent_attempts', function ($attempts) use ($finalizedMetric, $reminderRecipient) {
                $rows = collect($attempts);
                $first = $rows->first();

                return $rows->count() === 1
                    && ($first['metric_name'] ?? null) === $finalizedMetric->name
                    && ($first['recipient_email'] ?? null) === $reminderRecipient->email
                    && ($first['status'] ?? null) === NotificationAttempt::STATUS_FAILED
                    && ($first['window'] ?? null) === ReminderSchedule::WINDOW_T_MINUS_24H
                    && ($first['reason'] ?? null) === 'mailbox deferred';
            })
            ->where('rail_readiness.intake_ready', false)
            ->where('rail_readiness.forms_ready', false)
            ->where('rail_readiness.review_ready', true)
            ->where('rail_readiness.sign_off_ready', false)
            ->where('rail_readiness.handbook_ready', false)
            ->where('rail_readiness.blockers', fn ($blockers) => collect($blockers)->contains(
                'Complete all contributor form submissions before entering review.'
            ))
            ->where('permissions.can_manage_assignments', true)
            ->where('statusOptions', fn ($options) => count($options) > 1)
        );
});

it('marks workshop ready when strict criteria are met', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW,
        'onboarding_checklist_json' => [
            'metrics_selected' => true,
            'stakeholders_invited' => true,
            'forms_bootstrapped' => true,
            'repository_connected' => true,
            'workshop_ready' => false,
        ],
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $formOne = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => Metric::factory()->create([
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
            'status' => MetricStatus::IN_REVIEW,
            'last_submitted_at' => now()->subDay(),
        ])->id,
        'status' => 'Completed',
    ]);

    $contributor = User::factory()->create();
    FormAssignment::factory()->create([
        'form_id' => $formOne->id,
        'user_id' => $contributor->id,
        'responsibility' => 'Contributor',
        'due_at' => now()->addDay(),
    ]);
    FormSubmission::factory()->submitted()->create([
        'form_id' => $formOne->id,
        'user_id' => $contributor->id,
    ]);
    $metric = $formOne->metric()->firstOrFail();

    $member = User::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $participant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participant->id,
        'user_id' => $member->id,
        'subject_type' => Membership::class,
        'subject_id' => $membership->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Engagements/Show')
            ->where('engagement.checklist.workshop_ready', true)
            ->where('rail_readiness.intake_ready', true)
            ->where('rail_readiness.forms_ready', true)
            ->where('rail_readiness.review_ready', false)
        );
});

it('includes workshop scheduling readiness per metric', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $readyMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $pendingMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $missingFormsMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $readyContributor = User::factory()->create();
    $pendingContributor = User::factory()->create();

    $readyForm = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $readyMetric->id,
    ]);
    FormAssignment::factory()->create([
        'form_id' => $readyForm->id,
        'metric_id' => $readyMetric->id,
        'user_id' => $readyContributor->id,
        'responsibility' => 'Contributor',
    ]);
    FormSubmission::factory()->submitted()->create([
        'form_id' => $readyForm->id,
        'user_id' => $readyContributor->id,
    ]);

    $pendingForm = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $pendingMetric->id,
    ]);
    FormAssignment::factory()->create([
        'form_id' => $pendingForm->id,
        'metric_id' => $pendingMetric->id,
        'user_id' => $pendingContributor->id,
        'responsibility' => 'Contributor',
    ]);
    FormSubmission::factory()->create([
        'form_id' => $pendingForm->id,
        'user_id' => $pendingContributor->id,
        'submitted_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Engagements/Show')
            ->where('metrics.items', function ($items) use ($readyMetric, $pendingMetric, $missingFormsMetric) {
                $readyItem = collect($items)->firstWhere('id', (string) $readyMetric->id);
                $pendingItem = collect($items)->firstWhere('id', (string) $pendingMetric->id);
                $missingItem = collect($items)->firstWhere('id', (string) $missingFormsMetric->id);

                if (! $readyItem || ! $pendingItem || ! $missingItem) {
                    return false;
                }

                return ($readyItem['workshop_schedule_readiness']['state'] ?? null) === 'ready'
                    && ($readyItem['workshop_schedule_readiness']['forms_total'] ?? null) === 1
                    && ($readyItem['workshop_schedule_readiness']['forms_ready'] ?? null) === 1
                    && ($pendingItem['workshop_schedule_readiness']['state'] ?? null) === 'pending_submissions'
                    && ($pendingItem['workshop_schedule_readiness']['forms_total'] ?? null) === 1
                    && ($pendingItem['workshop_schedule_readiness']['forms_ready'] ?? null) === 0
                    && ($missingItem['workshop_schedule_readiness']['state'] ?? null) === 'missing_forms'
                    && ($missingItem['workshop_schedule_readiness']['forms_total'] ?? null) === 0
                    && ($missingItem['workshop_schedule_readiness']['forms_ready'] ?? null) === 0;
            })
        );
});

it('blocks access for non-staff users', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.show', [$organization, $engagement]))
        ->assertForbidden();
});

it('allows admins to reassign metric participants', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    $adminMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $originalUser = User::factory()->create();
    $originalMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $originalUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $targetUser = User::factory()->create();
    $targetMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $targetUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $originalParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $originalMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $targetParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $targetMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $metricParticipant = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $originalUser->id,
        'subject_type' => Membership::class,
        'subject_id' => $originalMembership->id,
        'role_tags_json' => ['lead'],
    ]);

    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $originalUser->id,
        'responsibility' => 'Contributor',
        'role_tags_json' => ['lead'],
    ]);

    $this->actingAs($admin)
        ->from(route('org.engagements.show', [$organization, $engagement]))
        ->patch(route('org.engagements.metric-participants.update', [$organization, $engagement, $metric, $metricParticipant]), [
            'engagement_participant_id' => $targetParticipant->id,
            'responsibility' => 'contributor',
            'role_tags' => ['lead'],
        ])
        ->assertRedirect(route('org.engagements.show', [$organization, $engagement]));

    $metricParticipant->refresh();

    expect($metricParticipant->engagement_participant_id)->toBe($targetParticipant->id);
    expect($metricParticipant->user_id)->toBe($targetUser->id);
    expect($metricParticipant->subject_type)->toBe(Membership::class);
    expect($metricParticipant->subject_id)->toBe($targetMembership->id);

    $this->assertDatabaseHas('form_assignment', [
        'form_id' => $form->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $targetUser->id,
        'responsibility' => 'Contributor',
    ]);

    $this->assertDatabaseMissing('form_assignment', [
        'form_id' => $form->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $originalUser->id,
    ]);
});

it('allows assigning staff participants as observers and approvers', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $originalUser = User::factory()->create();
    $originalMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $originalUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $staffUser = User::factory()->create();
    $staffMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $internal = makeInternalOrg();
    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $staffUser->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $originalParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $originalMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $staffParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $staffMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $contributorMetricParticipant = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $originalUser->id,
        'subject_type' => Membership::class,
        'subject_id' => $originalMembership->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('org.engagements.metric-participants.update', [$organization, $engagement, $metric, $contributorMetricParticipant]), [
            'engagement_participant_id' => $staffParticipant->id,
            'responsibility' => 'observer',
        ])
        ->assertRedirect();

    expect($contributorMetricParticipant->fresh()->engagement_participant_id)->toBe($staffParticipant->id)
        ->and($contributorMetricParticipant->fresh()->user_id)->toBe($staffUser->id)
        ->and($contributorMetricParticipant->fresh()->responsibility)->toBe('observer');

    $approverMetricParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $originalUser->id,
        'subject_type' => Membership::class,
        'subject_id' => $originalMembership->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('org.engagements.metric-participants.update', [$organization, $engagement, $metric, $approverMetricParticipant]), [
            'engagement_participant_id' => $staffParticipant->id,
            'responsibility' => 'approver',
        ])
        ->assertRedirect();

    expect($approverMetricParticipant->fresh()->engagement_participant_id)->toBe($staffParticipant->id)
        ->and($approverMetricParticipant->fresh()->user_id)->toBe($staffUser->id)
        ->and($approverMetricParticipant->fresh()->subject_type)->toBe(Membership::class)
        ->and($approverMetricParticipant->fresh()->subject_id)->toBe($staffMembership->id);
});

it('removes assignments when reassigning to a pending participant', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $form = Form::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'metric_id' => $metric->id,
    ]);

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $originalUser = User::factory()->create();
    $originalMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $originalUser->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $pendingMembership = PendingMembership::factory()->for($organization)->create([
        'engagement_id' => $engagement->id,
    ]);

    $originalParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $originalMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $pendingParticipant = EngagementParticipant::factory()->pending()->create([
        'engagement_id' => $engagement->id,
        'membership_id' => null,
        'pending_membership_id' => $pendingMembership->id,
        'active' => true,
    ]);

    $metricParticipant = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $originalUser->id,
        'subject_type' => Membership::class,
        'subject_id' => $originalMembership->id,
        'role_tags_json' => [],
    ]);

    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $originalUser->id,
        'responsibility' => 'Contributor',
    ]);

    $this->actingAs($admin)
        ->patch(route('org.engagements.metric-participants.update', [$organization, $engagement, $metric, $metricParticipant]), [
            'engagement_participant_id' => $pendingParticipant->id,
            'responsibility' => 'observer',
            'role_tags' => [],
        ])
        ->assertRedirect();

    $metricParticipant->refresh();

    expect($metricParticipant->engagement_participant_id)->toBe($pendingParticipant->id);
    expect($metricParticipant->user_id)->toBeNull();
    expect($metricParticipant->subject_type)->toBe(PendingMembership::class);
    expect($metricParticipant->subject_id)->toBe($pendingMembership->id);

    $this->assertDatabaseMissing('form_assignment', [
        'metric_participant_id' => $metricParticipant->id,
    ]);
});

it('prevents members from reassigning participants without permission', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $member->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $targetMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $originalParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $targetParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $targetMembership->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $metricParticipant = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $originalParticipant->id,
        'user_id' => $member->id,
        'subject_type' => Membership::class,
        'subject_id' => $membership->id,
    ]);

    $this->actingAs($member)
        ->patch(route('org.engagements.metric-participants.update', [$organization, $engagement, $metric, $metricParticipant]), [
            'engagement_participant_id' => $targetParticipant->id,
            'responsibility' => 'contributor',
        ])
        ->assertForbidden();
});
