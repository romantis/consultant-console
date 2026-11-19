<?php

use App\Enums\MembershipRole;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\StaffOperatorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalOrg = makeInternalOrg();
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');
    Config::set('metric-harmony.features.public_handbook_links', true);
});

dataset('broadStaffRoles', [
    'owner' => [MembershipRole::OWNER],
    'admin' => [MembershipRole::ADMIN],
]);

function exportsAccessMakeStaffActor(Organization $internalOrg, MembershipRole $role): User
{
    $user = User::factory()->create();
    Membership::factory()->create([
        'organization_id' => $internalOrg->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return $user;
}

it('shows handbook exports history to broad staff', function (MembershipRole $role) {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $user = exportsAccessMakeStaffActor($this->internalOrg, $role);

    $readyExport = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/ready.pdf',
        'status' => HandbookExport::STATUS_READY,
        'queued_at' => now()->subDay(),
        'completed_at' => now()->subDay()->addMinute(),
    ]);

    $publishedExport = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/published.pdf',
        'status' => HandbookExport::STATUS_READY,
        'queued_at' => now()->subHour(),
        'completed_at' => now()->subHour()->addMinute(),
        'published_at' => now()->subMinutes(50),
        'published_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('org.handbook-exports.index', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/HandbookExports')
            ->where('organization.id', (string) $organization->id)
            ->has('exports', 2)
            ->where('exports', function ($exports) use ($readyExport, $publishedExport) {
                if ($exports instanceof Collection) {
                    $exports = $exports->all();
                }

                $collection = collect($exports);
                $readyRow = $collection->firstWhere('id', (string) $readyExport->id);
                $publishedRow = $collection->firstWhere('id', (string) $publishedExport->id);

                expect($readyRow['is_published'] ?? null)->toBeFalse()
                    ->and($readyRow['public_url'] ?? null)->toBeNull()
                    ->and($readyRow['can_publish'] ?? null)->toBeTrue();

                expect($publishedRow['is_published'] ?? null)->toBeTrue()
                    ->and($publishedRow['public_url'] ?? null)
                    ->toBe(route('handbook-exports.public', $publishedExport->public_slug))
                    ->and($publishedRow['can_unpublish'] ?? null)->toBeTrue();

                return true;
            })
        );
})->with('broadStaffRoles');

it('shows handbook exports history to delegated operators', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $operator = exportsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.handbook-exports.index', $organization))
        ->assertOk();
});

it('denies access to operator outside their delegated organization', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $operator = exportsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);

    $otherOrg = Organization::factory()->create(['is_internal' => false]);
    StaffOperatorAssignment::factory()->create([
        'organization_id' => $otherOrg->id,
        'user_id' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('org.handbook-exports.index', $organization))
        ->assertForbidden();
});

it('blocks non-staff members from the handbook exports page', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $user = User::factory()->create();

    Membership::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $this->actingAs($user)
        ->get(route('org.handbook-exports.index', $organization))
        ->assertForbidden();
});

it('allows broad staff to publish and unpublish exports', function (MembershipRole $role) {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $user = exportsAccessMakeStaffActor($this->internalOrg, $role);

    $readyExport = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/ready.pdf',
        'status' => HandbookExport::STATUS_READY,
        'queued_at' => now()->subDay(),
        'completed_at' => now()->subDay()->addMinute(),
    ]);

    Storage::disk('handbooks')->put($readyExport->storage_path, 'dummy content');

    $this->actingAs($user)
        ->post(route('org.handbook-exports.publish', [$organization, $readyExport]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($readyExport->refresh()->isPublished())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('org.handbook-exports.unpublish', [$organization, $readyExport]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($readyExport->refresh()->isPublished())->toBeFalse();
})->with('broadStaffRoles');

it('allows delegated operators to publish exports', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $operator = exportsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);

    StaffOperatorAssignment::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
    ]);

    $readyExport = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/ready.pdf',
        'status' => HandbookExport::STATUS_READY,
        'queued_at' => now()->subDay(),
        'completed_at' => now()->subDay()->addMinute(),
    ]);

    Storage::disk('handbooks')->put($readyExport->storage_path, 'dummy content');

    $this->actingAs($operator)
        ->post(route('org.handbook-exports.publish', [$organization, $readyExport]))
        ->assertRedirect();
});

it('denies operators outside their delegated org from publishing exports', function () {
    $organization = Organization::factory()->create(['is_internal' => false]);
    $operator = exportsAccessMakeStaffActor($this->internalOrg, MembershipRole::MEMBER);

    $readyExport = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/ready.pdf',
        'status' => HandbookExport::STATUS_READY,
    ]);

    $this->actingAs($operator)
        ->post(route('org.handbook-exports.publish', [$organization, $readyExport]))
        ->assertForbidden();
});
