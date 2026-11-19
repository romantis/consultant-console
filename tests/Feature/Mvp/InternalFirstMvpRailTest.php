<?php

use App\Enums\EngagementStatus;
use App\Enums\FormAssignmentStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\EngagementWorkshopSession;
use App\Models\FinalDefinition;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('completes the internal-first MVP rail from launch to handbook visibility', function () {
    $consultant = User::factory()->create();
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()
        ->for($organization)
        ->for($consultant, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($internal)
        ->for($consultant, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($organization)
        ->for($viewer, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $engagement = Engagement::factory()
        ->for($organization)
        ->create([
            'status' => EngagementStatus::DRAFT->value,
            'title' => 'Northwind Internal-First Rail',
        ]);

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Net Revenue Retention',
            'status' => MetricStatus::IN_REVIEW->value,
        ])
        ->create();

    $form = Form::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'metric_id' => $metric->id,
        ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $metric->id,
        'user_id' => $consultant->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $consultant->id,
    ]);

    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($consultant, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $consultant->id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $consultant->id,
        'responsibility' => 'approver',
    ]);

    $finalDefinition = FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'PendingApproval',
            'definition_json' => [
                'value' => ['label' => 'Metric Value', 'value' => 'Retention is defined and aligned.'],
            ],
            'owner_participant_id' => $approverParticipant->id,
        ]);

    EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $showRoute = route('org.engagements.show', [$organization, $engagement]);

    $this->actingAs($consultant)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::AWAITING_FORMS->value,
        ])
        ->assertRedirect($showRoute);

    $this->actingAs($consultant)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_REVIEW->value,
        ])
        ->assertRedirect($showRoute);

    $this->actingAs($consultant)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_WORKSHOP->value,
        ])
        ->assertRedirect($showRoute);

    $metricShowRoute = route('org.metrics.show', [$organization, $metric]);

    $this->actingAs($consultant)
        ->from($metricShowRoute)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertRedirect($metricShowRoute);

    $this->actingAs($consultant)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::FINALIZED->value,
        ])
        ->assertRedirect($showRoute);

    $engagement->refresh();
    $metric->refresh();
    $finalDefinition->refresh();

    expect($engagement->status)->toBe(EngagementStatus::FINALIZED)
        ->and($engagement->finalized_at)->not()->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::FINALIZED)
        ->and($finalDefinition->signed_off_at)->not()->toBeNull();

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($viewer)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', (string) $engagement->id)
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                return ($row['engagement_metric_id'] ?? null) === (string) $metric->id
                    && ($row['status'] ?? null) === MetricStatus::FINALIZED->value
                    && ($row['urgency'] ?? null) === 'finalized';
            })
        );
});

it('blocks rail progression into workshop when no workshop session exists', function () {
    $consultant = User::factory()->create();
    $organization = Organization::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()
        ->for($organization)
        ->for($consultant, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($internal)
        ->for($consultant, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $engagement = Engagement::factory()
        ->for($organization)
        ->create([
            'status' => EngagementStatus::IN_REVIEW->value,
        ]);

    $showRoute = route('org.engagements.show', [$organization, $engagement]);

    $this->actingAs($consultant)
        ->from($showRoute)
        ->patch(route('org.engagements.status.update', [$organization, $engagement]), [
            'status' => EngagementStatus::IN_WORKSHOP->value,
        ])
        ->assertRedirect($showRoute)
        ->assertSessionHasErrors('status');

    expect($engagement->refresh()->status)->toBe(EngagementStatus::IN_REVIEW);
});
