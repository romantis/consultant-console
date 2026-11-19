<?php

use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('redirects members hitting the staff dashboard route to home', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::MEMBER->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('home'));
});

it('shares client navigation links for forms and handbook', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $nextReviewOn = now()->addMonth()->toDateString();
    Engagement::factory()->for($organization)->create([
        'review_cadence' => 'Quarterly',
        'next_review_on' => $nextReviewOn,
    ]);

    Membership::factory()->for($organization)->for($user, 'user')->role(MembershipRole::ADMIN)->create();

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('clientNavigation.organization.id', (string) $organization->id)
            ->where('clientNavigation.links.forms', null)
            ->where('clientNavigation.links.metrics', null)
            ->where('clientNavigation.links.handbook', route('home'))
            ->where('clientNavigation.engagements.0.reviewCadence', 'Quarterly')
            ->where('clientNavigation.engagements.0.nextReviewOn', $nextReviewOn)
            ->where('clientNavigation.links', function ($links) {
                if ($links instanceof Collection) {
                    $links = $links->toArray();
                }

                return is_array($links)
                    && ! array_key_exists('dashboard', $links)
                    && ! array_key_exists('nextSteps', $links)
                    && array_key_exists('handbook', $links)
                    && array_key_exists('support', $links);
            })
        );
});

it('exposes organization management flag for managers in sidebar data', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organizations', function ($organizations) use ($organization) {
                $entry = collect($organizations)->firstWhere('id', (string) $organization->id);

                return $entry !== null && (($entry['can_manage'] ?? false) === true);
            })
        );
});

it('hides organization management flag for non-managers', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::VIEWER->value,
    ]);

    $this->actingAs($user)
        ->get(route('client.handbook.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organizations', function ($organizations) use ($organization) {
                $entry = collect($organizations)->firstWhere('id', (string) $organization->id);

                return $entry !== null && (($entry['can_manage'] ?? false) === false);
            })
        );
});

it('preserves staff organization ownership flags when sharing sidebar data', function () {
    $user = User::factory()->create();
    $internal = Organization::factory()->create(['is_internal' => true]);
    $client = Organization::factory()->create();

    Membership::factory()->create([
        'organization_id' => $internal->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN->value,
    ]);

    Membership::factory()->create([
        'organization_id' => $client->id,
        'user_id' => $user->id,
        'role' => MembershipRole::OWNER->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organizations', function ($organizations) use ($client) {
                $entry = collect($organizations)->firstWhere('id', (string) $client->id);

                return $entry
                    && ($entry['owned'] ?? false) === true
                    && ($entry['can_manage'] ?? false) === true;
            })
        );
});
