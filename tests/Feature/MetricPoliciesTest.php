<?php

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\User;

it('allows metric participants to view metrics within their organization', function () {
    $membership = Membership::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $membership->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $membership->organization_id,
        'engagement_id' => $engagement->id,
    ]);

    expect($membership->user->can('view', $metric))->toBeFalse();

    MetricParticipant::factory()->for($metric)->for($membership->user, 'user')->contributor()->create();

    expect($membership->user->can('view', $metric))->toBeTrue();
});

it('denies viewing metrics outside an organization', function () {
    $metric = Metric::factory()->create();
    $outsider = User::factory()->create();

    expect($outsider->can('view', $metric))->toBeFalse();
});

it('allows admins to update final definitions and blocks members', function () {
    $membership = Membership::factory()->create(['role' => MembershipRole::ADMIN]);
    $engagement = Engagement::factory()->create(['organization_id' => $membership->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $membership->organization_id,
        'engagement_id' => $engagement->id,
    ]);
    $final = FinalDefinition::factory()->create(['metric_id' => $metric->id]);

    expect($membership->user->can('update', $final))->toBeTrue();

    $member = Membership::factory()->create(['organization_id' => $membership->organization_id, 'role' => MembershipRole::MEMBER]);
    expect($member->user->can('update', $final))->toBeFalse();
});

it('allows metric owners to update final definitions', function () {
    $membership = Membership::factory()->create(['role' => MembershipRole::MEMBER]);
    $engagement = Engagement::factory()->create(['organization_id' => $membership->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $membership->organization_id,
        'engagement_id' => $engagement->id,
    ]);

    $owner = MetricParticipant::factory()->create([
        'metric_id' => $metric->id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    $final = FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'owner_participant_id' => $owner->id,
    ]);

    expect($membership->user->can('update', $final))->toBeTrue();
});

it('denies resolving discrepancies for all roles', function () {
    $admin = Membership::factory()->create(['role' => MembershipRole::ADMIN]);
    $engagement = Engagement::factory()->create(['organization_id' => $admin->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $admin->organization_id,
        'engagement_id' => $engagement->id,
    ]);
    $discrepancy = Discrepancy::factory()->create([
        'metric_id' => $metric->id,
        'engagement_id' => $engagement->id,
    ]);

    expect($admin->user->can('resolve', $discrepancy))->toBeFalse();

    $viewer = Membership::factory()->create(['organization_id' => $admin->organization_id, 'role' => MembershipRole::VIEWER]);
    expect($viewer->user->can('resolve', $discrepancy))->toBeFalse();
});

it('respects membership when reading metric definitions', function () {
    $membership = Membership::factory()->create();
    $engagement = Engagement::factory()->create(['organization_id' => $membership->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $membership->organization_id,
        'engagement_id' => $engagement->id,
    ]);
    $definition = MetricDefinition::factory()->create(['metric_id' => $metric->id]);

    expect($membership->user->can('view', $definition))->toBeTrue();

    $outsider = User::factory()->create();
    expect($outsider->can('view', $definition))->toBeFalse();
});

it('restricts audit log visibility to admins', function () {
    $admin = Membership::factory()->create(['role' => MembershipRole::ADMIN]);
    $log = AuditLog::factory()->create(['organization_id' => $admin->organization_id]);

    expect($admin->user->can('view', $log))->toBeTrue();

    $member = Membership::factory()->create(['organization_id' => $admin->organization_id, 'role' => MembershipRole::MEMBER]);
    expect($member->user->can('view', $log))->toBeFalse();
});

it('only allows assigned metric approvers to sign off metrics', function () {
    $approverMembership = Membership::factory()->create(['role' => MembershipRole::MEMBER]);
    $engagement = Engagement::factory()->create(['organization_id' => $approverMembership->organization_id]);
    $metric = Metric::factory()->create([
        'organization_id' => $approverMembership->organization_id,
        'engagement_id' => $engagement->id,
    ]);

    MetricParticipant::factory()
        ->for($metric)
        ->for($approverMembership->user, 'user')
        ->approver()
        ->create();

    expect($approverMembership->user->can('signOff', $metric))->toBeTrue();

    $admin = Membership::factory()->create([
        'organization_id' => $approverMembership->organization_id,
        'role' => MembershipRole::ADMIN,
    ]);

    expect($admin->user->can('signOff', $metric))->toBeFalse();

    $contributor = Membership::factory()->create([
        'organization_id' => $approverMembership->organization_id,
        'role' => MembershipRole::MEMBER,
    ]);

    MetricParticipant::factory()
        ->for($metric)
        ->for($contributor->user, 'user')
        ->contributor()
        ->create();

    expect($contributor->user->can('signOff', $metric))->toBeFalse();
});
