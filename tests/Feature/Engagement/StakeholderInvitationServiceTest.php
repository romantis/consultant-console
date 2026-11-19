<?php

use App\Enums\MembershipRole;
use App\Enums\StakeholderRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PendingMembership;
use App\Models\User;
use App\Services\Engagements\StakeholderInvitationService;
use Illuminate\Support\Collection;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('ignores organization members without stakeholder email and provisions pending memberships for unique invitees', function () {
    $service = app(StakeholderInvitationService::class);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $actor = User::factory()->create();

    $member = User::factory()->create([
        'email' => 'member@example.com',
    ]);

    Membership::factory()
        ->for($organization)
        ->for($member)
        ->create();

    $stakeholders = collect([
        [
            'email' => null,
            'name' => 'Missing Email',
        ],
        [
            'email' => 'member@example.com',
            'name' => 'Existing Member',
        ],
        [
            'email' => 'new-invitee@example.com',
            'name' => 'First Invitee',
            'role' => StakeholderRole::ADMIN->value,
            'affiliation' => 'Acme Corp',
        ],
        [
            'email' => 'NEW-INVITEE@example.com',
            'name' => 'Duplicate Invitee',
        ],
    ]);

    $participants = $service->syncForEngagement($engagement, $stakeholders, $actor);

    expect($participants)->toBeInstanceOf(Collection::class);
    expect($participants)->toHaveCount(1);
    expect($participants->keys())->toContain('invite:new-invitee@example.com');

    assertDatabaseCount('pending_memberships', 1);
    assertDatabaseHas('pending_memberships', [
        'organization_id' => $organization->id,
        'email' => 'new-invitee@example.com',
        'invited_by' => $actor->id,
        'engagement_id' => $engagement->id,
        'affiliation' => 'Acme Corp',
    ]);
    assertDatabaseMissing('pending_memberships', [
        'organization_id' => $organization->id,
        'email' => 'member@example.com',
    ]);

    assertDatabaseCount('engagement_participants', 1);
});

it('does not overwrite pending membership engagement ownership when re-inviting', function () {
    $service = app(StakeholderInvitationService::class);

    $organization = Organization::factory()->create();
    $originalEngagement = Engagement::factory()->for($organization)->create();
    $newEngagement = Engagement::factory()->for($organization)->create();
    $actor = User::factory()->create();

    $pending = PendingMembership::factory()
        ->for($organization)
        ->create([
            'email' => 'existing@example.com',
            'engagement_id' => $originalEngagement->id,
        ]);

    $participants = $service->syncForEngagement($newEngagement, collect([
        ['email' => 'existing@example.com'],
    ]), $actor);

    $pending->refresh();

    expect($participants)->toHaveCount(1);
    expect($pending->engagement_id)->toBe($originalEngagement->id);

    assertDatabaseHas('engagement_participants', [
        'engagement_id' => $newEngagement->id,
        'pending_membership_id' => $pending->id,
    ]);
});

it('returns an empty collection when no stakeholders are provided', function () {
    $service = app(StakeholderInvitationService::class);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $actor = User::factory()->create();

    $participants = $service->syncForEngagement($engagement, collect(), $actor);

    expect($participants)->toBeInstanceOf(Collection::class);
    expect($participants)->toBeEmpty();

    assertDatabaseCount('pending_memberships', 0);
    assertDatabaseCount('engagement_participants', 0);
});

it('persists pending membership roles that mirror stakeholder roles', function () {
    $service = app(StakeholderInvitationService::class);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $actor = User::factory()->create();

    $service->syncForEngagement($engagement, collect([
        ['email' => 'admin@example.com', 'role' => StakeholderRole::ADMIN->value],
        ['email' => 'member@example.com', 'role' => StakeholderRole::MEMBER->value],
        ['email' => 'viewer@example.com', 'role' => StakeholderRole::VIEWER->value],
    ]), $actor);

    $pending = $organization->pendingMemberships()->get()->keyBy('email');

    expect($pending['admin@example.com']->role)->toBe(MembershipRole::ADMIN)
        ->and($pending['member@example.com']->role)->toBe(MembershipRole::MEMBER)
        ->and($pending['viewer@example.com']->role)->toBe(MembershipRole::VIEWER);
});

it('accepts role labels regardless of casing or synonyms', function () {
    $service = app(StakeholderInvitationService::class);

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $actor = User::factory()->create();

    $service->syncForEngagement($engagement, collect([
        ['email' => 'approver@example.com', 'role' => 'Approver'],
        ['email' => 'observer@example.com', 'role' => 'Observer'],
        ['email' => 'contributor@example.com', 'role' => 'Contributor'],
    ]), $actor);

    $pending = $organization->pendingMemberships()->get()->keyBy('email');

    expect($pending['approver@example.com']->role)->toBe(MembershipRole::ADMIN)
        ->and($pending['observer@example.com']->role)->toBe(MembershipRole::VIEWER)
        ->and($pending['contributor@example.com']->role)->toBe(MembershipRole::MEMBER);
});
