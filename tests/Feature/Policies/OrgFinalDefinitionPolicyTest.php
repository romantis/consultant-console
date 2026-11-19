<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use App\Policies\OrgFinalDefinitionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStaffUserForOrgFinalDefinitions(): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    return $user;
}

it('allows staff to view and update org final definitions', function () {
    $user = makeStaffUserForOrgFinalDefinitions();
    $definition = OrgFinalDefinition::factory()->create();
    $policy = app(OrgFinalDefinitionPolicy::class);

    expect($policy->view($user, $definition))->toBeTrue()
        ->and($policy->update($user, $definition))->toBeTrue();
});

it('allows org viewers to view but not update org final definitions', function () {
    $definition = OrgFinalDefinition::factory()->create();
    $orgMetric = $definition->orgMetric;
    $user = User::factory()->create();

    Membership::factory()->viewer()->create([
        'organization_id' => $orgMetric->organization_id,
        'user_id' => $user->id,
    ]);

    $policy = app(OrgFinalDefinitionPolicy::class);

    expect($policy->view($user, $definition))->toBeTrue()
        ->and($policy->update($user, $definition))->toBeFalse();
});
