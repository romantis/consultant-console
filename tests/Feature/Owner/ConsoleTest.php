<?php

use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createConsoleOwner(): User
{
    $user = User::factory()->create();
    $internal = Organization::factory()->create([
        'is_internal' => true,
        'name' => 'Metric Harmony',
        'slug' => 'metric-harmony',
    ]);

    Membership::factory()
        ->for($internal)
        ->for($user, 'user')
        ->role(MembershipRole::OWNER)
        ->create();

    return $user;
}

it('allows staff owners to view all organizations', function () {
    $user = createConsoleOwner();

    $alpha = Organization::factory()->create(['name' => 'Alpha Ventures']);
    $beta = Organization::factory()->create(['name' => 'Beta Logistics']);

    Engagement::factory()->for($alpha)->create([
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);
    Engagement::factory()->for($beta)->create([
        'status' => EngagementStatus::DRAFT,
    ]);

    $this->actingAs($user)
        ->get(route('owner.console'))
        ->assertOk()
        ->assertSee('Owner console')
        ->assertSee('Alpha Ventures')
        ->assertSee('Beta Logistics');
});

it('supports searching organizations by name', function () {
    $user = createConsoleOwner();

    $beacon = Organization::factory()->create(['name' => 'Beacon Analytics']);
    Organization::factory()->create(['name' => 'Gamma Works']);

    Engagement::factory()->for($beacon)->create([
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    $this->actingAs($user)
        ->get(route('owner.console', ['search' => 'beac']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Console')
            ->where('filters.search', 'beac')
            ->where('consoleOrganizations', function ($organizations) use ($beacon) {
                if ($organizations instanceof Collection) {
                    $organizations = $organizations->all();
                }

                expect($organizations)->toHaveCount(1);

                expect($organizations[0])->toMatchArray([
                    'id' => (string) $beacon->id,
                    'name' => $beacon->name,
                ]);

                return true;
            })
        );
});

it('shares consultant navigation props with staff workspace metadata', function () {
    $user = createConsoleOwner();
    $client = Organization::factory()->create(['name' => 'Client Org']);

    Engagement::factory()->for($client)->create([
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.is_staff', true)
            ->where('staffWorkspace.name', 'Metric Harmony')
            ->where('organizations', function ($organizations) use ($client) {
                $items = collect($organizations);

                expect($items->contains(fn ($org) => $org['id'] === 'impersonation-off'))->toBeTrue();
                expect($items->contains(fn ($org) => $org['name'] === $client->name))->toBeTrue();

                return true;
            })
        );
});
