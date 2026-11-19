<?php

use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');
    Config::set('metric-harmony.features.public_handbook_links', true);
});

it('allows administrators to publish exports and serve them by slug', function () {
    $organization = Organization::factory()->create();
    $membership = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $path = sprintf('handbooks/%s/%s.pdf', $organization->id, Str::random(12));
    Storage::disk('handbooks')->put($path, '%PDF-1.7 handbook payload');

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => $path,
        'status' => HandbookExport::STATUS_READY,
    ]);

    expect($export->public_slug)->not()->toBeEmpty();

    $this->actingAs($membership->user)
        ->post(route('org.handbook-exports.publish', [$organization, $export]))
        ->assertRedirect();

    $export->refresh();

    expect($export->isPublished())->toBeTrue();
    expect($export->published_by)->toBe($membership->user_id);

    $response = $this->get(route('handbook-exports.public', $export->public_slug));
    $response->assertOk();
    expect($response->streamedContent())->toContain('handbook payload');

    $this->actingAs($membership->user)
        ->delete(route('org.handbook-exports.unpublish', [$organization, $export]))
        ->assertRedirect();

    $export->refresh();
    expect($export->isPublished())->toBeFalse();

    $this->get(route('handbook-exports.public', $export->public_slug))->assertForbidden();
});

it('prevents viewers from publishing handbook exports', function () {
    $organization = Organization::factory()->create();
    $viewer = Membership::factory()
        ->viewer()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/test.pdf',
        'status' => HandbookExport::STATUS_READY,
    ]);

    $this->actingAs($viewer->user)
        ->post(route('org.handbook-exports.publish', [$organization, $export]))
        ->assertForbidden();
});

it('prevents viewers from unpublishing handbook exports', function () {
    $organization = Organization::factory()->create();

    $viewer = Membership::factory()
        ->viewer()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/test.pdf',
        'status' => HandbookExport::STATUS_READY,
        'published_at' => now(),
        'published_by' => $viewer->user_id,
    ]);

    $this->actingAs($viewer->user)
        ->delete(route('org.handbook-exports.unpublish', [$organization, $export]))
        ->assertForbidden();
});

it('blocks publish attempts when the export is not scoped to the organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $admin = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $export = HandbookExport::query()->create([
        'organization_id' => $otherOrganization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/test.pdf',
        'status' => HandbookExport::STATUS_READY,
    ]);

    $this->actingAs($admin->user)
        ->post(route('org.handbook-exports.publish', [$organization, $export]))
        ->assertNotFound();
});

it('blocks access to unpublished slugs', function () {
    $organization = Organization::factory()->create();

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'local',
        'storage_path' => 'handbooks/missing.pdf',
        'status' => HandbookExport::STATUS_READY,
    ]);

    $this->get(route('handbook-exports.public', $export->public_slug))
        ->assertForbidden();
});

it('blocks publish and unpublish actions when public handbook links are disabled', function () {
    Config::set('metric-harmony.features.public_handbook_links', false);

    $organization = Organization::factory()->create();
    $admin = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/disabled.pdf',
        'status' => HandbookExport::STATUS_READY,
        'published_at' => now(),
        'published_by' => $admin->user_id,
    ]);

    $this->actingAs($admin->user)
        ->post(route('org.handbook-exports.publish', [$organization, $export]))
        ->assertForbidden();

    $this->actingAs($admin->user)
        ->delete(route('org.handbook-exports.unpublish', [$organization, $export]))
        ->assertForbidden();
});

it('blocks public slug access when public handbook links are disabled', function () {
    Config::set('metric-harmony.features.public_handbook_links', false);

    $organization = Organization::factory()->create();

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'handbooks',
        'storage_path' => 'handbooks/public-disabled.pdf',
        'status' => HandbookExport::STATUS_READY,
        'published_at' => now(),
    ]);

    $this->get(route('handbook-exports.public', $export->public_slug))
        ->assertForbidden();
});
