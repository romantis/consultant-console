<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shares new internal-first mvp flags in inertia props', function () {
    config()->set('metric-harmony.features.public_handbook_links', false);
    config()->set('metric-harmony.features.change_proposals', false);
    config()->set('metric-harmony.features.org_metric_overrides', false);

    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flags.public_handbook_links', false)
            ->where('flags.change_proposals', false)
            ->where('flags.org_metric_overrides', false)
        );
});

it('shares overridden internal-first mvp flags from config', function () {
    config()->set('metric-harmony.features.public_handbook_links', true);
    config()->set('metric-harmony.features.change_proposals', true);
    config()->set('metric-harmony.features.org_metric_overrides', true);

    $user = User::factory()->create();
    $internal = makeInternalOrg();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flags.public_handbook_links', true)
            ->where('flags.change_proposals', true)
            ->where('flags.org_metric_overrides', true)
        );
});
