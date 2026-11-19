<?php

use App\Enums\EngagementStatus;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows owners to hard delete draft engagements', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => makeInternalOrg()->id,
        'user_id' => $owner->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT->value,
    ]);

    $this->actingAs($owner)
        ->from(route('org.engagements.index', $organization))
        ->delete(route('org.engagements.destroy', [$organization, $engagement]))
        ->assertRedirect(route('org.engagements.index', $organization))
        ->assertSessionHas('success', __('Engagement draft deleted.'));

    expect(Engagement::withTrashed()->find($engagement->id))
        ->toBeNull();

    $audit = AuditLog::query()
        ->where('organization_id', $organization->id)
        ->where('action', 'engagement.draft.deleted')
        ->where('subject_id', $engagement->id)
        ->latest('created_at')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->properties['status'] ?? null)->toBe(EngagementStatus::DRAFT->value);
});

it('rejects deleting non-draft engagements', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->owner()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);
    Membership::factory()->owner()->create([
        'organization_id' => makeInternalOrg()->id,
        'user_id' => $owner->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS->value,
    ]);

    $this->actingAs($owner)
        ->from(route('org.engagements.index', $organization))
        ->delete(route('org.engagements.destroy', [$organization, $engagement]))
        ->assertRedirect(route('org.engagements.index', $organization))
        ->assertSessionHasErrors('engagement');

    expect(Engagement::query()->whereKey($engagement->id)->exists())
        ->toBeTrue();
});

it('forbids non-owners from deleting engagement drafts', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();

    Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
        'user_id' => $admin->id,
    ]);
    Membership::factory()->admin()->create([
        'organization_id' => makeInternalOrg()->id,
        'user_id' => $admin->id,
    ]);

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::DRAFT->value,
    ]);

    $this->actingAs($admin)
        ->delete(route('org.engagements.destroy', [$organization, $engagement]))
        ->assertForbidden();

    expect(Engagement::query()->whereKey($engagement->id)->exists())
        ->toBeTrue();
});
