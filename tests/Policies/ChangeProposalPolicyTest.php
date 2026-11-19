<?php

use App\Enums\ChangeProposalStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\ChangeProposal;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ChangeProposalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->engagement = Engagement::factory()->create(['organization_id' => $this->organization->id]);
    $this->metric = Metric::factory()->create([
        'organization_id' => $this->organization->id,
        'engagement_id' => $this->engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $this->approverUser = User::factory()->create();
    Membership::factory()->member()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $this->approverUser->id,
    ]);

    $this->approverParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $this->metric->id,
        'user_id' => $this->approverUser->id,
    ]);

    $this->proposal = ChangeProposal::create([
        'organization_id' => $this->organization->id,
        'engagement_id' => $this->engagement->id,
        'metric_id' => $this->metric->id,
        'status' => ChangeProposalStatus::OPEN,
        'rationale' => 'Need to update the owner name.',
        'payload' => ['owner' => 'Product Ops'],
        'approver_ids' => [(string) $this->approverParticipant->getKey()],
    ]);
});

it('lets organization members view change proposals', function () {
    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $viewer->id,
    ]);

    $policy = app(ChangeProposalPolicy::class);

    expect($policy->view($viewer, $this->proposal))->toBeTrue();
});

it('allows assigned approvers to decide on change proposals even without elevated roles', function () {
    $policy = app(ChangeProposalPolicy::class);

    expect($policy->decide($this->approverUser, $this->proposal))->toBeTrue();
});

it('denies decisions for members who are not assigned approvers or admins', function () {
    $member = User::factory()->create();
    Membership::factory()->member()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $member->id,
    ]);

    $policy = app(ChangeProposalPolicy::class);

    expect($policy->decide($member, $this->proposal))->toBeFalse();
});

it('allows organization owners and admins to decide and merge proposals', function (MembershipRole $role) {
    $user = User::factory()->create();
    Membership::factory()->role($role)->create([
        'organization_id' => $this->organization->id,
        'user_id' => $user->id,
    ]);

    $policy = app(ChangeProposalPolicy::class);

    expect($policy->decide($user, $this->proposal))->toBeTrue()
        ->and($policy->merge($user, $this->proposal))->toBeTrue();
})->with([
    'owner' => MembershipRole::OWNER,
    'admin' => MembershipRole::ADMIN,
]);

it('denies merge access to viewers', function () {
    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create([
        'organization_id' => $this->organization->id,
        'user_id' => $viewer->id,
    ]);

    $policy = app(ChangeProposalPolicy::class);

    expect($policy->merge($viewer, $this->proposal))->toBeFalse();
});

it('allows consultant staff to merge proposals across orgs', function () {
    $internal = Organization::factory()->create();
    $internal->forceFill(['is_internal' => true])->save();

    $staff = User::factory()->create();
    Membership::factory()->admin()->create([
        'organization_id' => $internal->id,
        'user_id' => $staff->id,
    ]);

    $policy = app(ChangeProposalPolicy::class);

    expect($policy->merge($staff, $this->proposal))->toBeTrue()
        ->and($policy->decide($staff, $this->proposal))->toBeTrue();
});

it('blocks unrelated users from viewing proposals', function () {
    $stranger = User::factory()->create();
    $policy = app(ChangeProposalPolicy::class);

    expect($policy->view($stranger, $this->proposal))->toBeFalse();
});
