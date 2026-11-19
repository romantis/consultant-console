<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\FinalDefinition;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('syncs metric participants in one save and updates form assignments', function () {
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

    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $userA = User::factory()->create();
    $membershipA = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $userA->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $userB = User::factory()->create();
    $membershipB = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $userB->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $userC = User::factory()->create();
    $membershipC = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $userC->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $userD = User::factory()->create();
    $membershipD = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $userD->id,
        'role' => MembershipRole::MEMBER,
    ]);

    $participantA = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membershipA->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);
    $participantB = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membershipB->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);
    $participantC = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membershipC->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);
    $participantD = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membershipD->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $metricParticipantA = MetricParticipant::factory()->contributor()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participantA->id,
        'user_id' => $userA->id,
        'subject_type' => Membership::class,
        'subject_id' => $membershipA->id,
    ]);
    $metricParticipantB = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $participantB->id,
        'user_id' => $userB->id,
        'subject_type' => Membership::class,
        'subject_id' => $membershipB->id,
    ]);

    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipantA->id,
        'user_id' => $userA->id,
        'responsibility' => 'Contributor',
    ]);
    FormAssignment::create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipantB->id,
        'user_id' => $userB->id,
        'responsibility' => 'Approver',
    ]);

    $this->actingAs($admin)
        ->patch(route('org.engagements.metrics.participants.update', [$organization, $engagement, $metric]), [
            'participants' => [
                [
                    'engagement_participant_id' => (string) $participantA->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $participantB->id,
                    'responsibility' => 'observer',
                ],
                [
                    'engagement_participant_id' => (string) $participantC->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $participantD->id,
                    'responsibility' => 'approver',
                ],
            ],
        ])
        ->assertRedirect();

    $rolesByParticipant = MetricParticipant::query()
        ->where('metric_id', $metric->id)
        ->get()
        ->mapWithKeys(fn (MetricParticipant $participant) => [
            (string) $participant->engagement_participant_id => $participant->responsibility,
        ]);

    expect($rolesByParticipant->count())->toBe(4)
        ->and($rolesByParticipant->get((string) $participantA->id))->toBe('contributor')
        ->and($rolesByParticipant->get((string) $participantB->id))->toBe('observer')
        ->and($rolesByParticipant->get((string) $participantC->id))->toBe('contributor')
        ->and($rolesByParticipant->get((string) $participantD->id))->toBe('approver');

    $this->assertDatabaseHas('form_assignment', [
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $userA->id,
        'responsibility' => 'Contributor',
    ]);
    $this->assertDatabaseHas('form_assignment', [
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $userC->id,
        'responsibility' => 'Contributor',
    ]);
    $this->assertDatabaseHas('form_assignment', [
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $userD->id,
        'responsibility' => 'Approver',
    ]);
    $this->assertDatabaseMissing('form_assignment', [
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $userB->id,
    ]);

    $this->assertDatabaseHas('form_submissions', [
        'form_id' => $form->id,
        'user_id' => $userD->id,
    ]);
});

it('rejects bulk participant edits when metric is already signed off', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'signed_off_at' => now(),
    ]);

    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $userA = User::factory()->create();
    $membershipA = Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $userA->id,
        'role' => MembershipRole::MEMBER,
    ]);
    $participantA = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_id' => $membershipA->id,
        'pending_membership_id' => null,
        'active' => true,
    ]);

    $response = $this->actingAs($admin)->patchJson(
        route('org.engagements.metrics.participants.update', [$organization, $engagement, $metric]),
        [
            'participants' => [
                [
                    'engagement_participant_id' => (string) $participantA->id,
                    'responsibility' => 'approver',
                ],
            ],
        ],
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['participants'])
        ->assertJsonPath(
            'errors.participants.0',
            'Participant assignments are locked after sign-off.'
        );
});

it('requires exactly one approver in bulk participant edits', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $participants = collect(range(1, 3))->map(function () use ($organization, $engagement) {
        $user = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => MembershipRole::MEMBER,
        ]);

        return EngagementParticipant::factory()->create([
            'engagement_id' => $engagement->id,
            'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
            'membership_id' => $membership->id,
            'pending_membership_id' => null,
            'active' => true,
        ]);
    });

    $response = $this->actingAs($admin)->patchJson(
        route('org.engagements.metrics.participants.update', [$organization, $engagement, $metric]),
        [
            'participants' => [
                [
                    'engagement_participant_id' => (string) $participants[0]->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $participants[1]->id,
                    'responsibility' => 'approver',
                ],
                [
                    'engagement_participant_id' => (string) $participants[2]->id,
                    'responsibility' => 'approver',
                ],
            ],
        ],
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['participants'])
        ->assertJsonPath(
            'errors.participants.0',
            'Each metric must include at least two contributors.'
        )
        ->assertJsonPath(
            'errors.participants.1',
            'Each metric must include exactly one approver.'
        );
});

it('allows assigning a staff membership candidate as approver in one save', function () {
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

    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $contributorMemberships = collect(range(1, 2))->map(function () use ($organization, $engagement) {
        $user = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => MembershipRole::MEMBER,
        ]);

        $participant = EngagementParticipant::factory()->create([
            'engagement_id' => $engagement->id,
            'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
            'membership_id' => $membership->id,
            'pending_membership_id' => null,
            'active' => true,
        ]);

        return [
            'user' => $user,
            'membership' => $membership,
            'participant' => $participant,
        ];
    });

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

    $this->actingAs($admin)->patch(
        route('org.engagements.metrics.participants.update', [$organization, $engagement, $metric]),
        [
            'participants' => [
                [
                    'engagement_participant_id' => (string) $contributorMemberships[0]['participant']->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $contributorMemberships[1]['participant']->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => 'membership:'.$staffMembership->id,
                    'responsibility' => 'approver',
                ],
            ],
        ],
    )->assertRedirect();

    $staffEngagementParticipant = EngagementParticipant::query()
        ->where('engagement_id', $engagement->id)
        ->where('membership_id', $staffMembership->id)
        ->first();

    expect($staffEngagementParticipant)->not->toBeNull();
    $staffEngagementParticipantId = (string) $staffEngagementParticipant?->id;

    $this->assertDatabaseHas('metric_participants', [
        'metric_id' => $metric->id,
        'engagement_participant_id' => $staffEngagementParticipantId,
        'user_id' => $staffUser->id,
        'responsibility' => 'approver',
    ]);

    $this->assertDatabaseHas('form_assignment', [
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $staffUser->id,
        'responsibility' => 'Approver',
    ]);
});

it('rejects assigning staff participants as contributors', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $admin = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $contributorsAndApprover = collect(range(1, 3))->map(function () use ($organization, $engagement) {
        $user = User::factory()->create();
        $membership = Membership::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => MembershipRole::MEMBER,
        ]);

        $participant = EngagementParticipant::factory()->create([
            'engagement_id' => $engagement->id,
            'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
            'membership_id' => $membership->id,
            'pending_membership_id' => null,
            'active' => true,
        ]);

        return $participant;
    });

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

    $response = $this->actingAs($admin)->patchJson(
        route('org.engagements.metrics.participants.update', [$organization, $engagement, $metric]),
        [
            'participants' => [
                [
                    'engagement_participant_id' => (string) $contributorsAndApprover[0]->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $contributorsAndApprover[1]->id,
                    'responsibility' => 'contributor',
                ],
                [
                    'engagement_participant_id' => (string) $contributorsAndApprover[2]->id,
                    'responsibility' => 'approver',
                ],
                [
                    'engagement_participant_id' => 'membership:'.$staffMembership->id,
                    'responsibility' => 'contributor',
                ],
            ],
        ],
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['participants.3.responsibility'])
        ->assertJsonFragment([
            'Staff participants can only be assigned as observers or approvers.',
        ]);
});
