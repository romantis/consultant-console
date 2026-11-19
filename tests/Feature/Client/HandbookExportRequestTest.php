<?php

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reuses the latest export when the handbook fingerprint is unchanged', function () {
    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->for($organization)->create();
    $member = User::factory()->create();

    Membership::factory()
        ->for($organization)
        ->for($member, 'user')
        ->role(MembershipRole::MEMBER)
        ->create();

    $metric = Metric::factory()
        ->for($organization)
        ->for($engagement)
        ->state(['status' => MetricStatus::FINALIZED, 'name' => 'ARR'])
        ->create();

    $ownerParticipant = MetricParticipant::factory()
        ->for($metric)
        ->for($member, 'user')
        ->approver()
        ->create();

    FinalDefinition::factory()
        ->for($metric)
        ->withConsultantSignals([
            'alignment_summary' => 'ARR is aligned for the current board pack.',
        ])
        ->create([
            'status' => 'Approved',
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Approved definition'],
            ],
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $member->id,
            'owner_participant_id' => $ownerParticipant->id,
        ]);

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED->value)
        ->with([
            'finalDefinition.signedOffBy:id,name',
            'finalDefinition.ownerParticipant.user:id,name,email',
        ])
        ->get();

    $checksum = app(BuildHandbookExportPayload::class)->sourceChecksum($organization, $metrics);

    HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'local',
        'storage_path' => "handbooks/{$organization->id}/existing.pdf",
        'status' => HandbookExport::STATUS_READY,
        'queued_at' => now()->subMinutes(5),
        'completed_at' => now()->subMinutes(4),
        'source_checksum' => $checksum,
    ]);

    $this->actingAs($member)
        ->from('/client/handbook')
        ->post(route('client.handbook.exports.store', $organization))
        ->assertRedirect('/client/handbook')
        ->assertSessionHas(
            'success',
            'No handbook changes detected. The latest export is ready to download.',
        );

    expect(HandbookExport::query()->count())->toBe(1);
});
