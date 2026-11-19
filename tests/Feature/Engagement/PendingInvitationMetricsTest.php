<?php

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('retains metric assignments when a pending invite is accepted', function () {
    $organization = Organization::factory()->create();
    Membership::factory()->for($organization)->role(MembershipRole::OWNER)->create();

    $engagement = Engagement::factory()->for($organization)->create();

    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'email' => 'accept@example.com',
        'role' => MembershipRole::ADMIN,
        'affiliation' => 'Finance Guild',
    ]);

    $participant = EngagementParticipant::create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_PENDING,
        'pending_membership_id' => $pending->id,
        'membership_role' => $pending->role->value,
        'stakeholder_role' => StakeholderRole::MEMBER->value,
        'active' => true,
    ]);

    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $metricParticipant = MetricParticipant::factory()->for($metric)->contributor()->create([
        'engagement_participant_id' => $participant->id,
        'user_id' => null,
        'subject_type' => PendingMembership::class,
        'subject_id' => $pending->id,
        'role_tags_json' => ['finance'],
    ]);

    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
    ]);

    $user = User::factory()->create(['email' => 'accept@example.com']);

    $response = $this->actingAs($user)->get(
        URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id])
    );

    $response
        ->assertRedirect(route('home'))
        ->assertSessionHas('success', 'Invite accepted.');

    $membership = Membership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    $participant->refresh();
    $metricParticipant->refresh();

    expect($participant->participant_type)->toBe(EngagementParticipant::TYPE_MEMBERSHIP)
        ->and($participant->membership_id)->toBe($membership->id)
        ->and($participant->pending_membership_id)->toBeNull()
        ->and($participant->active)->toBeTrue();

    expect($metricParticipant->user_id)->toBe($user->id)
        ->and($metricParticipant->subject_type)->toBe(Membership::class)
        ->and($metricParticipant->subject_id)->toBe($membership->id)
        ->and($metricParticipant->engagement_participant_id)->toBe($participant->id)
        ->and($metricParticipant->role_tags_json)->toBe(['finance']);

    $assignment = FormAssignment::query()
        ->where('metric_participant_id', $metricParticipant->id)
        ->where('user_id', $user->id)
        ->first();

    expect($assignment)->not->toBeNull()
        ->and($assignment?->responsibility)->toBe('Contributor')
        ->and($assignment?->role_tags_json)->toBe(['finance']);

    expect(FormSubmission::query()
        ->where('form_id', $form->id)
        ->where('user_id', $user->id)
        ->exists())->toBeTrue();
});

it('clears metric assignments when a pending invite is declined', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();

    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'email' => 'decline@example.com',
        'role' => MembershipRole::MEMBER,
    ]);

    $participant = EngagementParticipant::create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_PENDING,
        'pending_membership_id' => $pending->id,
        'membership_role' => $pending->role->value,
        'stakeholder_role' => StakeholderRole::MEMBER->value,
        'active' => true,
    ]);

    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $assignee = User::factory()->create();

    $metricParticipant = MetricParticipant::factory()->for($metric)->approver()->create([
        'engagement_participant_id' => $participant->id,
        'user_id' => $assignee->id,
        'subject_type' => PendingMembership::class,
        'subject_id' => $pending->id,
    ]);

    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $assignee->id,
        'responsibility' => 'Approver',
    ]);

    $response = $this->post(
        URL::temporarySignedRoute('invites.accept', now()->addMinutes(5), ['pendingMembership' => $pending->id]),
        ['intent' => 'decline']
    );

    $response
        ->assertRedirect(route('home'))
        ->assertSessionHas('success', 'Invitation declined.');

    $metricParticipant->refresh();

    expect(EngagementParticipant::query()
        ->whereKey($participant->id)
        ->where('active', true)
        ->exists())->toBeFalse();

    expect($metricParticipant->engagement_participant_id)->toBeNull()
        ->and($metricParticipant->user_id)->toBeNull()
        ->and($metricParticipant->subject_type)->toBeNull()
        ->and($metricParticipant->subject_id)->toBeNull();

    expect(FormAssignment::query()
        ->where('metric_participant_id', $metricParticipant->id)
        ->exists())->toBeFalse();

    expect(PendingMembership::query()->whereKey($pending->id)->exists())->toBeFalse();
});

it('clears metric assignments when a pending invite is revoked by an admin', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();

    Membership::factory()->for($organization)->for($admin, 'user')->role(MembershipRole::OWNER)->create();

    $engagement = Engagement::factory()->for($organization)->create();

    $pending = PendingMembership::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'email' => 'revoke@example.com',
        'role' => MembershipRole::ADMIN,
    ]);

    $participant = EngagementParticipant::create([
        'engagement_id' => $engagement->id,
        'participant_type' => EngagementParticipant::TYPE_PENDING,
        'pending_membership_id' => $pending->id,
        'membership_role' => $pending->role->value,
        'stakeholder_role' => StakeholderRole::MEMBER->value,
        'active' => true,
    ]);

    $metric = Metric::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
    ]);

    $assignee = User::factory()->create();

    $metricParticipant = MetricParticipant::factory()->for($metric)->contributor()->create([
        'engagement_participant_id' => $participant->id,
        'user_id' => $assignee->id,
        'subject_type' => PendingMembership::class,
        'subject_id' => $pending->id,
    ]);

    $form = Form::factory()->for($engagement)->create([
        'organization_id' => $organization->id,
        'metric_id' => $metric->id,
    ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'metric_participant_id' => $metricParticipant->id,
        'user_id' => $assignee->id,
        'responsibility' => 'Contributor',
    ]);

    $response = $this->actingAs($admin)->delete(
        route('org.pending-members.destroy', [$organization, $pending])
    );

    $response
        ->assertRedirect()
        ->assertSessionHas('success', 'Invite removed.');

    $metricParticipant->refresh();

    expect(EngagementParticipant::query()
        ->whereKey($participant->id)
        ->where('active', true)
        ->exists())->toBeFalse();

    expect($metricParticipant->engagement_participant_id)->toBeNull()
        ->and($metricParticipant->user_id)->toBeNull()
        ->and($metricParticipant->subject_type)->toBeNull()
        ->and($metricParticipant->subject_id)->toBeNull();

    expect(FormAssignment::query()
        ->where('metric_participant_id', $metricParticipant->id)
        ->exists())->toBeFalse();

    expect(PendingMembership::query()->whereKey($pending->id)->exists())->toBeFalse();
});
