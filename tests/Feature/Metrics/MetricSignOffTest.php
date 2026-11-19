<?php

use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('signs off a metric and locks the final definition', function () {
    Storage::fake('public');

    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'approver',
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'Agreed value'],
        ],
        'owner_participant_id' => $approverParticipant->id,
    ]);

    $response = $this->actingAs($membership->user)->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]));

    $response->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->not()->toBeNull()
        ->and($final->signed_off_by)->toBe($membership->user_id)
        ->and($metric->status)->toBe(MetricStatus::FINALIZED);
});

it('requires an approver to sign off metrics', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);

    $this->actingAs($membership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertForbidden();
});

it('does not allow org managers to sign off without approver assignment when override is disabled', function () {
    config()->set('metric-harmony.features.internal_first_manager_signoff_override', false);

    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);

    $this->actingAs($membership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertForbidden();
});

it('allows org managers to sign off without approver assignment when override is enabled', function () {
    config()->set('metric-harmony.features.internal_first_manager_signoff_override', true);

    $organization = Organization::factory()->create();
    $managerMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $approverMembership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);
    $managerParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($managerMembership->user, 'user')
        ->contributor()
        ->create();

    MetricParticipant::factory()
        ->for($metric)
        ->for($approverMembership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $managerMembership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $approverMembership->user_id,
        'responsibility' => 'approver',
    ]);

    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'Aligned value'],
        ],
        'owner_participant_id' => $managerParticipant->id,
    ]);

    $this->actingAs($managerMembership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->not()->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::FINALIZED);
});

it('rejects sign off when a required definition field is missing', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'summary',
                    'type' => 'text',
                    'label' => 'Summary',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'target',
                    'type' => 'text',
                    'label' => 'Target',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
        'metric_template_id' => $template->id,
    ]);
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'approver',
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Aligned'],
            'target' => ['label' => 'Target', 'value' => null],
        ],
        'owner_participant_id' => $approverParticipant->id,
    ]);

    $response = $this->actingAs($membership->user)->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]));

    $response->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::IN_REVIEW)
        ->and(
            OrgFinalDefinition::query()
                ->where('source_metric_id', $metric->id)
                ->exists()
        )->toBeFalse();
});

it('allows sign off when definition-surface context companion fields are empty', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);
    $template = MetricTemplate::factory()->create([
        'form_schema_json' => [
            'fields' => [
                [
                    'name' => 'summary',
                    'type' => 'text',
                    'label' => 'Summary',
                    'required' => true,
                    'classification' => [
                        'role' => 'definition',
                    ],
                ],
                [
                    'name' => 'summary_notes',
                    'type' => 'textarea',
                    'label' => 'Summary notes',
                    'classification' => [
                        'role' => 'context',
                    ],
                ],
            ],
        ],
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
        'metric_template_id' => $template->id,
    ]);
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'approver',
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Aligned'],
            'summary_notes' => ['label' => 'Summary notes', 'value' => null],
        ],
        'owner_participant_id' => $approverParticipant->id,
    ]);

    $response = $this->actingAs($membership->user)->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]));

    $response->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->not()->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::FINALIZED);
});

it('requires a metric owner before approving sign off', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'approver',
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'Aligned'],
        ],
    ]);

    $this->actingAs($membership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::IN_REVIEW);
});

it('allows sign off without an implementation gap selection', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::IN_REVIEW,
    ]);
    $approverParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($membership->user, 'user')
        ->approver()
        ->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'approver',
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'value' => ['label' => 'Metric Value', 'value' => 'Aligned statement'],
        ],
        'owner_participant_id' => $approverParticipant->id,
    ]);

    $this->actingAs($membership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertRedirect();

    $final->refresh();
    $metric->refresh();

    expect($final->signed_off_at)->not()->toBeNull()
        ->and($metric->status)->toBe(MetricStatus::FINALIZED);
});

it('prevents members who are not approvers from signing off', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::MEMBER,
    ]);
    $engagement = Engagement::factory()->create(['organization_id' => $organization->id]);

    $metric = Metric::factory()->create(['organization_id' => $organization->id, 'engagement_id' => $engagement->id]);

    $this->actingAs($membership->user)
        ->post(route('org.metrics.sign-off', [$organization, $engagement, $metric]))
        ->assertForbidden();
});

it('records audit logs when the metric owner changes on the final definition', function () {
    $metric = Metric::factory()->create();
    $firstParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);
    $secondParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);
    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'owner_participant_id' => $firstParticipant->id,
    ]);

    $final->forceFill([
        'owner_participant_id' => $secondParticipant->id,
    ])->save();

    $log = AuditLog::query()
        ->where('action', 'metric.owner.changed')
        ->latest('created_at')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log?->metric_id)->toBe((string) $metric->id)
        ->and($log->properties['from_participant_id'] ?? null)->toBe((string) $firstParticipant->id)
        ->and($log->properties['to_participant_id'] ?? null)->toBe((string) $secondParticipant->id);
});

it('syncs org metric owners when the final definition owner changes', function () {
    $metric = Metric::factory()->create();
    $previousParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);
    $nextParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);

    $orgOwner = OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $previousParticipant->user_id,
        'responsibility' => 'owner',
    ]);

    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'owner_participant_id' => $previousParticipant->id,
    ]);

    $final->forceFill([
        'owner_participant_id' => $nextParticipant->id,
    ])->save();

    $orgOwner->refresh();

    expect($orgOwner->user_id)->toBe($nextParticipant->user_id);
});

it('does not override org metric owners when they diverge from the previous final definition owner', function () {
    $metric = Metric::factory()->create();
    $previousParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);
    $nextParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
    ]);
    $externalOwner = User::factory()->create();

    OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $externalOwner->id,
        'responsibility' => 'owner',
    ]);

    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'owner_participant_id' => $previousParticipant->id,
    ]);

    $final->forceFill([
        'owner_participant_id' => $nextParticipant->id,
    ])->save();

    $owner = OrgMetricParticipant::query()
        ->where('org_metric_id', $metric->org_metric_id)
        ->where('responsibility', 'owner')
        ->first();

    expect($owner)->not->toBeNull()
        ->and($owner?->user_id)->toBe($externalOwner->id);
});
