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
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shows organization metrics with urgency metadata on the handbook', function () {
    $user = User::factory()->create(['name' => 'Alex Kim']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $needsAttentionMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Alpha Draft Metric',
            'status' => MetricStatus::IN_REVIEW,
        ])
        ->create();

    $needsAttentionOrgMetric = OrgMetric::query()
        ->find($needsAttentionMetric->org_metric_id);

    $needsAttentionOrgMetric?->forceFill(['alias' => 'Alpha Alias'])->save();

    $needsAttentionOwner = MetricParticipant::factory()
        ->for($needsAttentionMetric)
        ->for(User::factory(), 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($needsAttentionMetric)
        ->create([
            'status' => 'Draft',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Draft definition.'],
            ],
            'owner_participant_id' => $needsAttentionOwner->id,
        ]);

    $inProgressMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Beta Proposed Metric',
            'status' => MetricStatus::PROPOSED,
        ])
        ->create();

    $inProgressForm = Form::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'metric_id' => $inProgressMetric->id,
        ]);

    FormAssignment::factory()
        ->create([
            'form_id' => $inProgressForm->id,
            'metric_id' => $inProgressMetric->id,
        ]);

    FormAssignment::factory()
        ->create([
            'form_id' => $inProgressForm->id,
            'metric_id' => $inProgressMetric->id,
        ]);

    $finalizedMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Gamma Final Metric',
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    $finalOwner = MetricParticipant::factory()
        ->for($finalizedMetric)
        ->for(User::factory(), 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($finalizedMetric)
        ->create([
            'status' => 'Approved',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Monthly recurring revenue aligned with DataFlex.'],
                'owner' => ['label' => 'Metric Owner', 'value' => 'Alex Kim'],
                'data_sources' => ['label' => 'Data Sources', 'value' => 'Snowflake'],
            ],
            'owner_participant_id' => $finalOwner->id,
        ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', fn ($metrics) => count($metrics) === 3)
            ->where('metrics.0.id', (string) $needsAttentionMetric->org_metric_id)
            ->where('metrics.0.engagement_metric_id', (string) $needsAttentionMetric->id)
            ->where('metrics.0.alias', 'Alpha Alias')
            ->where('metrics.0.urgency', 'needs_attention')
            ->where('metrics.0.needs_attention', true)
            ->where('metrics.0.stage_label', 'Reconciling inputs')
            ->where('metrics.1.id', (string) $inProgressMetric->org_metric_id)
            ->where('metrics.1.engagement_metric_id', (string) $inProgressMetric->id)
            ->where('metrics.1.urgency', 'in_progress')
            ->where('metrics.1.needs_attention', false)
            ->where('metrics.1.stage_label', 'Collecting inputs 0/2')
            ->where('metrics.2.id', (string) $finalizedMetric->org_metric_id)
            ->where('metrics.2.engagement_metric_id', (string) $finalizedMetric->id)
            ->where('metrics.2.urgency', 'finalized')
            ->where('metrics.2.needs_attention', false)
            ->where('metrics.2.stage_label', 'Signed off')
            ->missing('metrics.0.fields')
            ->missing('metrics.0.variants')
        );
});

it('includes ordered action links for active engagement metrics', function () {
    $user = User::factory()->create(['name' => 'Alex Kim']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::ADMIN)
        ->create();

    $pendingMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Pending Approval Metric',
            'status' => MetricStatus::IN_REVIEW,
        ])
        ->create();

    $pendingParticipant = MetricParticipant::factory()
        ->for($pendingMetric)
        ->for($user, 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($pendingMetric)
        ->create([
            'status' => 'PendingApproval',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Ready for approval.'],
            ],
            'owner_participant_id' => $pendingParticipant->id,
        ]);

    $form = Form::factory()
        ->for($organization)
        ->for($engagement)
        ->create([
            'metric_id' => $pendingMetric->id,
        ]);

    FormAssignment::factory()->create([
        'form_id' => $form->id,
        'metric_id' => $pendingMetric->id,
        'user_id' => $user->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    $draftMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'name' => 'Draft Metric',
            'status' => MetricStatus::IN_REVIEW,
        ])
        ->create();

    $draftParticipant = MetricParticipant::factory()
        ->for($draftMetric)
        ->for($user, 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($draftMetric)
        ->create([
            'status' => 'Draft',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Draft definition.'],
            ],
            'owner_participant_id' => $draftParticipant->id,
        ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($draftMetric, $pendingMetric, $form, $organization, $engagement) {
                $rows = collect($metrics)->keyBy('id');
                $pendingRow = $rows->get((string) $pendingMetric->org_metric_id);
                $draftRow = $rows->get((string) $draftMetric->org_metric_id);

                if (! $pendingRow || ! $draftRow) {
                    return false;
                }

                $pendingActions = collect($pendingRow['actions'] ?? [])->pluck('label')->all();
                $expectedPending = ['Open form', 'Reassign owner'];
                if ($pendingActions !== $expectedPending) {
                    return false;
                }

                $pendingLinks = collect($pendingRow['actions'])->keyBy('label');
                $pendingMetricUrl = route('client.handbook.metrics.page', [
                    'organization' => $organization->id,
                    'engagement' => $engagement->id,
                    'orgMetric' => $pendingMetric->org_metric_id,
                ]);

                if (($pendingLinks['Open form']['href'] ?? null) !== route('forms.show', ['form' => $form->id])) {
                    return false;
                }
                if (($pendingLinks['Reassign owner']['href'] ?? null) !== $pendingMetricUrl.'#role-actions') {
                    return false;
                }
                $draftActions = collect($draftRow['actions'] ?? [])->pluck('label')->all();
                $expectedDraft = ['Reassign owner'];
                if ($draftActions !== $expectedDraft) {
                    return false;
                }

                $draftMetricUrl = route('client.handbook.metrics.page', [
                    'organization' => $organization->id,
                    'engagement' => $engagement->id,
                    'orgMetric' => $draftMetric->org_metric_id,
                ]);
                $draftLinks = collect($draftRow['actions'])->keyBy('label');

                if (($draftLinks['Reassign owner']['href'] ?? null) !== $draftMetricUrl.'#role-actions') {
                    return false;
                }

                return true;
            })
        );
});

it('does not show sign-off actions when the owner is not an approver', function () {
    $user = User::factory()->create(['name' => 'Alex Kim']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::IN_REVIEW])
        ->create();

    $ownerParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->contributor()
        ->create();

    MetricParticipant::factory()
        ->for($metric)
        ->for(User::factory(), 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'Draft',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Draft definition.'],
            ],
            'owner_participant_id' => $ownerParticipant->id,
        ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $labels = collect($row['actions'] ?? [])->pluck('label')->all();
                $blocked = ['Sign off now', 'Request sign-off', 'Approve sign-off', 'Request changes'];

                return array_intersect($blocked, $labels) === [];
            })
        );
});

it('does not show sign-off actions when the definition is not ready', function () {
    $user = User::factory()->create(['name' => 'Alex Kim']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::IN_REVIEW])
        ->create();

    $ownerParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($user, 'user')
        ->approver()
        ->create();

    MetricParticipant::factory()
        ->for($metric)
        ->for(User::factory(), 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($metric)
        ->create([
            'status' => 'Draft',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => null],
            ],
            'owner_participant_id' => $ownerParticipant->id,
        ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $labels = collect($row['actions'] ?? [])->pluck('label')->all();
                $blocked = ['Sign off now', 'Request sign-off', 'Approve sign-off', 'Request changes'];

                return array_intersect($blocked, $labels) === [];
            })
        );
});

it('hides contributor form actions once submitted', function () {
    $user = User::factory()->create(['name' => 'Alex Kim']);
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
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
        'user_id' => $user->id,
        'status' => FormAssignmentStatus::OPEN->value,
        'responsibility' => 'Contributor',
    ]);

    FormSubmission::factory()->submitted()->create([
        'form_id' => $form->id,
        'user_id' => $user->id,
    ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $actions = collect($row['actions'] ?? [])->pluck('label')->all();

                return $actions === [];
            })
        );
});

it('shows workshop actions for non-staff only after a valid metric workshop session exists', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);
    $timestamp = now();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'recompute_finished_at' => $timestamp,
            'definitions_changed_at' => $timestamp,
        ])
        ->create();

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $workshop = collect($row['actions'] ?? [])->firstWhere('key', 'workshop');

                return $workshop === null;
            })
        );

    $engagement->forceFill(['status' => EngagementStatus::IN_WORKSHOP->value])->save();

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $workshop = collect($row['actions'] ?? [])->firstWhere('key', 'workshop');

                return $workshop === null;
            })
        );

    $otherMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'recompute_finished_at' => $timestamp,
            'definitions_changed_at' => $timestamp,
        ])
        ->create();

    $otherSession = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $otherSession->metrics()->create(['metric_id' => $otherMetric->id]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                return collect($row['actions'] ?? [])->firstWhere('key', 'workshop') === null;
            })
        );

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $workshop = collect($row['actions'] ?? [])->firstWhere('key', 'workshop');

                return $workshop
                    && ($workshop['disabled'] ?? null) === false
                    && ($workshop['disabled_reason'] ?? null) === null;
            })
        );
});

it('hides workshop actions when engagement or metric is finalized for non-staff users', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::IN_WORKSHOP->value,
    ]);
    $timestamp = now();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'status' => MetricStatus::IN_REVIEW->value,
            'recompute_finished_at' => $timestamp,
            'definitions_changed_at' => $timestamp,
        ])
        ->create();

    $session = EngagementWorkshopSession::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => 'Scheduled',
    ]);
    $session->metrics()->create(['metric_id' => $metric->id]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                $workshop = collect($row['actions'] ?? [])->firstWhere('key', 'workshop');

                return $workshop
                    && ($workshop['disabled'] ?? null) === false
                    && ($workshop['disabled_reason'] ?? null) === null;
            })
        );

    $finalizedMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'status' => MetricStatus::FINALIZED->value,
            'recompute_finished_at' => $timestamp,
            'definitions_changed_at' => $timestamp,
        ])
        ->create();

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($finalizedMetric) {
                $row = collect($metrics)->firstWhere('id', (string) $finalizedMetric->org_metric_id);

                if (! $row) {
                    return false;
                }

                return collect($row['actions'] ?? [])->firstWhere('key', 'workshop') === null;
            })
        );

    $engagement->forceFill(['status' => EngagementStatus::FINALIZED->value])->save();

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                if (! $row) {
                    return false;
                }

                return collect($row['actions'] ?? [])->firstWhere('key', 'workshop') === null;
            })
        );
});

it('hides org metrics without active engagement context or signed-off org definitions', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $inactiveEngagement = Engagement::factory()
        ->for($organization)
        ->create(['status' => EngagementStatus::FINALIZED->value]);

    Metric::factory()
        ->for($organization)
        ->for($inactiveEngagement)
        ->state(['name' => 'Dormant Metric'])
        ->create();

    OrgMetric::factory()
        ->for($organization)
        ->create(['name' => 'Empty Metric']);

    $signedOffOrgMetric = OrgMetric::factory()
        ->for($organization)
        ->create(['name' => 'Signed Off Metric']);

    OrgFinalDefinition::factory()
        ->for($signedOffOrgMetric)
        ->create([
            'status' => 'Approved',
            'signed_off_at' => now()->subDay(),
        ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', fn ($metrics) => count($metrics) === 1)
            ->where('metrics.0.id', (string) $signedOffOrgMetric->id)
            ->where('metrics.0.engagement_metric_id', null)
        );
});

it('shows finalized engagement metrics with engagement context when org definitions are signed off', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::FINALIZED])
        ->create();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $metric->metric_template_id,
        'status' => 'Approved',
        'signed_off_at' => now()->subDay(),
    ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('metrics', function ($metrics) use ($metric, $engagement) {
                $row = collect($metrics)->firstWhere('id', (string) $metric->org_metric_id);

                return $row
                    && ($row['engagement_metric_id'] ?? null) === (string) $metric->id
                    && ($row['status'] ?? null) === MetricStatus::FINALIZED->value
                    && (($row['engagement']['id'] ?? null) === (string) $engagement->id);
            })
        );
});

it('keeps signed-off metrics visible when filtering a finalized engagement', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::FINALIZED->value,
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $signedOffMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::FINALIZED])
        ->create();

    $unsignedMetric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::FINALIZED])
        ->create();

    FinalDefinition::factory()->create([
        'metric_id' => $signedOffMetric->id,
        'status' => 'Approved',
        'signed_off_at' => now()->subDay(),
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $unsignedMetric->id,
        'status' => 'Draft',
        'signed_off_at' => null,
    ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $engagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', (string) $engagement->id)
            ->where('metrics', function ($metrics) use ($signedOffMetric, $unsignedMetric) {
                $signedOffRow = collect($metrics)->firstWhere(
                    'engagement_metric_id',
                    (string) $signedOffMetric->id
                );
                $unsignedRow = collect($metrics)->firstWhere(
                    'engagement_metric_id',
                    (string) $unsignedMetric->id
                );

                return $signedOffRow
                    && ($signedOffRow['stage_label'] ?? null) === 'Signed off'
                    && $unsignedRow !== null;
            })
        );
});

it('includes selected engagement summary for official metrics when an engagement is selected', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $selectedEngagement = Engagement::factory()->for($organization)->create([
        'title' => 'Board Pack Alignment',
        'status' => EngagementStatus::FINALIZED->value,
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);
    $otherEngagement = Engagement::factory()->for($organization)->create([
        'title' => 'Ops Alignment',
        'status' => EngagementStatus::FINALIZED->value,
        'review_cadence' => 'Monthly',
        'next_review_on' => '2026-04-15',
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $readyMetric = Metric::factory()
        ->for($organization)
        ->for($selectedEngagement)
        ->state([
            'name' => 'ARR',
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $readyMetric->org_metric_id,
        'source_metric_id' => $readyMetric->id,
        'source_metric_template_id' => $readyMetric->metric_template_id,
        'status' => 'Approved',
        'signed_off_at' => '2026-03-05 15:00:00',
        'operational_status' => 'operationally_ready',
        'implementation_gaps' => null,
    ]);

    $followUpMetric = Metric::factory()
        ->for($organization)
        ->for($selectedEngagement)
        ->state([
            'name' => 'Gross Margin',
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $followUpMetric->org_metric_id,
        'source_metric_id' => $followUpMetric->id,
        'source_metric_template_id' => $followUpMetric->metric_template_id,
        'status' => 'Approved',
        'signed_off_at' => '2026-03-06 12:00:00',
        'operational_status' => 'needs_follow_up',
        'implementation_gaps' => [
            'current_state' => 'Finance still reconciles the board pack in a workbook.',
            'gap' => 'Dashboard logic still includes internal tenants.',
            'owner' => 'Revenue ops',
            'suggested_next_step' => 'Update dashboard filters before the next review.',
        ],
    ]);

    $unsignedMetric = Metric::factory()
        ->for($organization)
        ->for($selectedEngagement)
        ->state([
            'name' => 'Unsigned Metric',
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    FinalDefinition::factory()->create([
        'metric_id' => $unsignedMetric->id,
        'status' => 'Draft',
        'signed_off_at' => null,
    ]);

    $otherEngagementMetric = Metric::factory()
        ->for($organization)
        ->for($otherEngagement)
        ->state([
            'name' => 'Pipeline Coverage',
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $otherEngagementMetric->org_metric_id,
        'source_metric_id' => $otherEngagementMetric->id,
        'source_metric_template_id' => $otherEngagementMetric->metric_template_id,
        'status' => 'Approved',
        'signed_off_at' => '2026-03-07 09:00:00',
        'operational_status' => 'needs_follow_up',
        'implementation_gaps' => [
            'current_state' => 'Ops keeps a separate export.',
            'gap' => 'The handbook definition is not yet wired into reporting.',
            'owner' => 'Operations',
            'suggested_next_step' => 'Retire the duplicate export.',
        ],
    ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index', ['engagement' => $selectedEngagement->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', (string) $selectedEngagement->id)
            ->where('selected_engagement_summary', [
                'engagement_id' => (string) $selectedEngagement->id,
                'engagement_title' => 'Board Pack Alignment',
                'next_review_on' => '2026-06-30',
                'review_cadence' => 'Quarterly',
                'operational_ready_count' => 1,
                'needs_follow_up_count' => 1,
                'implementation_gap_count' => 1,
            ])
        );
});

it('returns a null selected engagement summary when all engagements are shown', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create([
        'status' => EngagementStatus::FINALIZED->value,
        'review_cadence' => 'Quarterly',
        'next_review_on' => '2026-06-30',
    ]);

    Membership::factory()
        ->for($organization)
        ->for($user, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state([
            'status' => MetricStatus::FINALIZED,
        ])
        ->create();

    OrgFinalDefinition::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'source_metric_id' => $metric->id,
        'source_metric_template_id' => $metric->metric_template_id,
        'status' => 'Approved',
        'signed_off_at' => '2026-03-05 15:00:00',
        'operational_status' => 'operationally_ready',
        'implementation_gaps' => null,
    ]);

    $this->withCookie('active_organization_id', (string) $organization->id)
        ->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', null)
            ->where('selected_engagement_summary', null)
        );
});

it('keeps handbook engagement filters scoped to the organization, not participation', function () {
    $organization = Organization::factory()->create();
    $engagementA = Engagement::factory()->for($organization)->create([
        'title' => 'Alpha Engagement',
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);
    $engagementB = Engagement::factory()->for($organization)->create([
        'title' => 'Beta Engagement',
        'status' => EngagementStatus::IN_REVIEW->value,
    ]);
    $viewer = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($viewer, 'user')
        ->role(MembershipRole::VIEWER)
        ->create();

    $metricA = Metric::factory()
        ->for($organization)
        ->for($engagementA)
        ->state(['name' => 'Alpha Metric'])
        ->create();

    $metricB = Metric::factory()
        ->for($organization)
        ->for($engagementB)
        ->state(['name' => 'Beta Metric'])
        ->create();

    $this->actingAs($viewer)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', null)
            ->where('engagements', fn ($engagements) => count($engagements) === 2)
            ->where('metrics', function ($metrics) use ($metricA, $metricB) {
                $ids = collect($metrics)->pluck('id')->all();

                return in_array((string) $metricA->org_metric_id, $ids, true)
                    && in_array((string) $metricB->org_metric_id, $ids, true);
            })
        );

    $this->actingAs($viewer)
        ->get(route('client.handbook.index', ['engagement' => $engagementB->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Client/Handbook')
            ->where('activeEngagementId', (string) $engagementB->id)
            ->where('metrics', fn ($metrics) => count($metrics) === 1)
            ->where('metrics.0.id', (string) $metricB->org_metric_id)
            ->where('metrics.0.engagement_metric_id', (string) $metricB->id)
        );
});
