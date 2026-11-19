<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\EngagementParticipant;
use App\Models\EngagementWorkshopSession;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('includes staff-only consultant signal payload on the workshop page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $internalOrganization = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => 'Approved',
        'version' => 2,
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $user->id,
        'alignment_summary' => 'Metric-level consultant summary',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Metric-level operational follow-up note.',
        'validation_steps' => 'Metric-level validation steps.',
        'common_mismatch_causes' => 'Metric-level mismatch causes.',
        'executive_takeaway' => 'Metric-level executive takeaway',
        'implementation_gaps' => [
            'current_state' => 'Definition is approved but the report still uses an older filter.',
            'gap' => 'Dashboard filter needs to exclude internal accounts.',
            'owner' => 'Finance systems',
            'suggested_next_step' => 'Update the report before the next board deck.',
        ],
    ]);

    $officialDefinition = OrgFinalDefinition::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'source_metric_id' => $metric->id,
        'status' => 'Approved',
        'version' => 3,
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'alignment_summary' => 'Org-level official summary',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Published dashboard already matches the official logic.',
        'validation_steps' => 'Org-level validation steps.',
        'common_mismatch_causes' => 'Org-level mismatch causes.',
        'executive_takeaway' => 'Org-level executive takeaway',
        'implementation_gaps' => [
            'current_state' => 'Published dashboard matches the approved definition.',
            'gap' => 'No remaining implementation gap.',
            'owner' => 'Revenue systems',
            'suggested_next_step' => 'Review again at the next cadence checkpoint.',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('finalDefinition.alignment_summary', 'Metric-level consultant summary')
            ->where('finalDefinition.operational_status', 'needs_follow_up')
            ->where('finalDefinition.operational_status_notes', 'Metric-level operational follow-up note.')
            ->where('finalDefinition.validation_steps', 'Metric-level validation steps.')
            ->where('finalDefinition.common_mismatch_causes', 'Metric-level mismatch causes.')
            ->where('finalDefinition.executive_takeaway', 'Metric-level executive takeaway')
            ->where('finalDefinition.implementation_gaps.owner', 'Finance systems')
            ->where('officialSignals.id', (string) $officialDefinition->id)
            ->where('officialSignals.source', 'org_final_definition')
            ->where('officialSignals.alignment_summary', 'Org-level official summary')
            ->where('officialSignals.operational_status', 'operationally_ready')
            ->where('officialSignals.operational_status_notes', 'Published dashboard already matches the official logic.')
            ->where('officialSignals.validation_steps', 'Org-level validation steps.')
            ->where('officialSignals.common_mismatch_causes', 'Org-level mismatch causes.')
            ->where('officialSignals.executive_takeaway', 'Org-level executive takeaway')
            ->where('officialSignals.implementation_gaps.owner', 'Revenue systems')
        );
});

it('includes owner options in the workshop payload', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);
    $internalOrganization = makeInternalOrg();

    $membership = Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create([
            'affiliation' => 'Revenue Ops',
        ]);
    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $engagementParticipant = EngagementParticipant::factory()->create([
        'engagement_id' => $engagement->id,
        'membership_id' => $membership->id,
        'participant_type' => EngagementParticipant::TYPE_MEMBERSHIP,
        'membership_role' => MembershipRole::ADMIN,
        'stakeholder_role' => StakeholderRole::MEMBER,
    ]);

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $approver = MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'engagement_participant_id' => $engagementParticipant->id,
        'user_id' => $user->id,
        'responsibility' => 'approver',
    ]);

    $secondUser = User::factory()->create();
    $contributor = MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $secondUser->id,
        'responsibility' => 'contributor',
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create([
        'metric_id' => $metric->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('metric.owner_options', function ($options) use ($approver, $contributor) {
                $optionsById = collect($options)->keyBy('id');
                $approverOption = $optionsById->get((string) $approver->id);
                $contributorOption = $optionsById->get((string) $contributor->id);

                return $approverOption
                    && $contributorOption
                    && ($approverOption['affiliation'] ?? null) === 'Revenue Ops'
                    && ($contributorOption['affiliation'] ?? null) === null;
            })
        );
});

it('keeps validation guidance out of answer comparison payloads while exposing dedicated fields', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $internalOrganization = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'value',
                    'type' => 'text',
                    'label' => 'Metric Value',
                    'classification' => ['role' => 'definition'],
                ],
                [
                    'name' => 'source_system',
                    'type' => 'text',
                    'label' => 'Source System',
                    'meta' => true,
                    'classification' => ['role' => 'context'],
                ],
                [
                    'name' => 'validation_steps',
                    'type' => 'textarea',
                    'label' => 'Validation Steps',
                    'meta' => true,
                    'classification' => ['role' => 'governance'],
                ],
                [
                    'name' => 'common_mismatch_causes',
                    'type' => 'textarea',
                    'label' => 'Common Mismatch Causes',
                    'meta' => true,
                    'classification' => ['role' => 'diagnostic'],
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()->for($template, 'template')->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricDefinition::factory()->for($metric)->for($user)->create([
        'normalized_json' => [
            'fields' => [
                'value' => [
                    'type' => 'text',
                    'label' => 'Metric Value',
                    'raw' => 'North America',
                    'normalized' => 'North America',
                ],
            ],
            'meta' => [
                'source_system' => [
                    'type' => 'text',
                    'label' => 'Source System',
                    'raw' => 'Warehouse',
                    'normalized' => 'Warehouse',
                ],
                'validation_steps' => [
                    'type' => 'textarea',
                    'label' => 'Validation Steps',
                    'raw' => 'Compare workbook totals.',
                    'normalized' => 'Compare workbook totals.',
                ],
                'common_mismatch_causes' => [
                    'type' => 'textarea',
                    'label' => 'Common Mismatch Causes',
                    'raw' => 'Dashboard filters drift.',
                    'normalized' => 'Dashboard filters drift.',
                ],
            ],
        ],
    ]);

    FinalDefinition::factory()->for($metric)->create([
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'North America'],
        ],
        'validation_steps' => 'Compare workbook totals.',
        'common_mismatch_causes' => 'Dashboard filters drift.',
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('metric.matrix', fn ($rows) => collect($rows)->pluck('field')->all() === ['value'])
            ->where('metric.context_fields', fn ($rows) => collect($rows)->pluck('field')->all() === ['source_system'])
            ->where('metric.field_roles', function ($roles) {
                $keys = collect($roles)->pluck('key');

                return ! $keys->contains('validation_steps')
                    && ! $keys->contains('common_mismatch_causes');
            })
            ->where('finalDefinition.validation_steps', 'Compare workbook totals.')
            ->where('finalDefinition.common_mismatch_causes', 'Dashboard filters drift.')
        );
});

it('includes metric-scoped workshop session context on the staff workshop page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);
    $internalOrganization = makeInternalOrg();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $targetMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $otherMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $targetSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'InProgress',
    ]);
    $targetSession->metrics()->create([
        'metric_id' => $targetMetric->id,
    ]);

    $otherSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $otherSession->metrics()->create([
        'metric_id' => $otherMetric->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $targetMetric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop.session_context.selected_session_id', (string) $targetSession->id)
            ->where('workshop.session_context.sessions', function ($sessions) use ($targetSession, $otherSession) {
                $sessionIds = collect($sessions)->pluck('id')->all();

                return in_array((string) $targetSession->id, $sessionIds, true)
                    && ! in_array((string) $otherSession->id, $sessionIds, true);
            })
        );
});

it('keeps all engagement metrics visible on the workshop rail for staff users', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    $internalOrganization = Organization::factory()->create(['is_internal' => true]);
    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();
    Membership::factory()
        ->for($internalOrganization, 'organization')
        ->for($user, 'user')
        ->admin()
        ->create();

    $scheduledMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $unscheduledMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create([
        'metric_id' => $scheduledMetric->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $scheduledMetric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop.metrics', function ($metrics) use ($scheduledMetric, $unscheduledMetric) {
                $metricIds = collect($metrics)->pluck('id')->all();

                return in_array((string) $scheduledMetric->id, $metricIds, true)
                    && in_array((string) $unscheduledMetric->id, $metricIds, true);
            })
        );
});

it('includes metric-scoped workshop session context on the client workshop page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $targetMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $otherMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    $targetSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'InProgress',
    ]);
    $targetSession->metrics()->create([
        'metric_id' => $targetMetric->id,
    ]);

    $otherSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $otherSession->metrics()->create([
        'metric_id' => $otherMetric->id,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            $organization,
            $engagement,
            'metric' => $targetMetric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop.session_context.selected_session_id', (string) $targetSession->id)
            ->where('workshop.session_context.sessions', function ($sessions) use ($targetSession, $otherSession) {
                $sessionIds = collect($sessions)->pluck('id')->all();

                return in_array((string) $targetSession->id, $sessionIds, true)
                    && ! in_array((string) $otherSession->id, $sessionIds, true);
            })
        );
});

it('omits consultant-only signals from the client workshop payload', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'status' => 'Approved',
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $user->id,
        'alignment_summary' => 'Metric-level consultant summary',
        'operational_status' => 'needs_follow_up',
        'operational_status_notes' => 'Metric-level operational follow-up note.',
        'executive_takeaway' => 'Metric-level executive takeaway',
        'implementation_gaps' => [
            'current_state' => 'Definition is approved but the report still uses an older filter.',
            'gap' => 'Dashboard filter needs to exclude internal accounts.',
            'owner' => 'Finance systems',
            'suggested_next_step' => 'Update the report before the next board deck.',
        ],
    ]);

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'source_metric_id' => $metric->id,
        'status' => 'Approved',
        'signed_off_at' => now(),
        'signed_off_by' => $user->id,
        'alignment_summary' => 'Org-level official summary',
        'operational_status' => 'operationally_ready',
        'operational_status_notes' => 'Published dashboard already matches the official logic.',
        'executive_takeaway' => 'Org-level executive takeaway',
        'implementation_gaps' => [
            'current_state' => 'Published dashboard matches the approved definition.',
            'gap' => 'No remaining implementation gap.',
            'owner' => 'Revenue systems',
            'suggested_next_step' => 'Review again at the next cadence checkpoint.',
        ],
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create([
        'metric_id' => $metric->id,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('officialSignals', null)
            ->missing('finalDefinition.alignment_summary')
            ->missing('finalDefinition.operational_status')
            ->missing('finalDefinition.operational_status_notes')
            ->missing('finalDefinition.implementation_gaps')
            ->missing('finalDefinition.validation_steps')
            ->missing('finalDefinition.common_mismatch_causes')
            ->missing('finalDefinition.executive_takeaway')
        );
});

it('blocks non-staff workshop access before the engagement is in workshop', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->get(route('org.engagements.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertForbidden();
});

it('allows non-staff workshop access once the engagement is in workshop', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);
    $otherMetric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create([
        'metric_id' => $metric->id,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop.metrics', function ($metrics) use ($metric, $otherMetric) {
                $metricIds = collect($metrics)->pluck('id')->all();

                return in_array((string) $metric->id, $metricIds, true)
                    && ! in_array((string) $otherMetric->id, $metricIds, true);
            })
        );
});

it('allows non-staff workshop access after the engagement is finalized', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
        ]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workshops/MetricWorkshop')
            ->where('workshop_closed', true)
        );
});

it('returns engagement status for workshop status probes', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()->inReview()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $user->id,
        'responsibility' => 'contributor',
    ]);

    $this->actingAs($user)
        ->getJson(route('client.handbook.workshop', [
            $organization,
            $engagement,
            'metric' => $metric->id,
            'probe' => 'status',
        ]))
        ->assertOk()
        ->assertJson([
            'engagement_status' => EngagementStatus::FINALIZED->value,
        ]);
});
