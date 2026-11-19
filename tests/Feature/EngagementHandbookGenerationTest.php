<?php

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Actions\Handbook\SelectOfficialMetrics;
use App\Enums\EngagementStatus;
use App\Enums\MembershipRole;
use App\Enums\MetricStatus;
use App\Jobs\GenerateHandbookExport;
use App\Models\AuditLog;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('generates a handbook PDF for an organization', function () {
    Storage::fake('public');
    Config::set('metric-harmony.handbook_disk', 'public');

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->with('pdf.organization-handbook', Mockery::type('array'))
            ->andReturn("%PDF-1.7\nmock handbook");
    });

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'user_id' => $membership->user_id,
    ]);

    $final = FinalDefinition::factory()
        ->withConsultantSignals([
            'alignment_summary' => 'Finance and operations aligned on one export-ready statement.',
            'implementation_gaps' => [
                'owner' => 'Finance',
                'suggested_next_step' => 'Move the workbook logic into the board-pack source query.',
            ],
        ])
        ->create([
            'metric_id' => $metric->id,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Aligned statement'],
                'owner' => ['label' => 'Owner', 'value' => 'Finance'],
            ],
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
            'owner_participant_id' => $ownerParticipant->id,
        ]);

    $response = $this->actingAs($membership->user)
        ->post(route('client.handbook.exports.store', $organization));

    $response->assertRedirect();

    $metric->refresh();
    $final->refresh();

    $export = HandbookExport::query()
        ->where('organization_id', $organization->id)
        ->latest('queued_at')
        ->first();

    expect($export)->not()->toBeNull()
        ->and($export->storage_disk)->toBe('public')
        ->and($export->completed_at)->not()->toBeNull();

    Storage::disk('public')->assertExists($export->storage_path);

    $pdf = Storage::disk('public')->get($export->storage_path);
    expect($pdf)->toStartWith('%PDF');

    expect(
        AuditLog::where('subject_id', $organization->id)
            ->where('action', 'organization.handbook.generated')
            ->exists()
    )->toBeTrue();
});

it('marks the export failed when the renderer throws', function () {
    Storage::fake('public');
    Config::set('metric-harmony.handbook_disk', 'public');

    $organization = Organization::factory()->create();
    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
    ]);
    $membership = Membership::factory()->create([
        'organization_id' => $organization->id,
        'role' => MembershipRole::ADMIN,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'user_id' => $membership->user_id,
    ]);

    FinalDefinition::factory()->create([
        'metric_id' => $metric->id,
        'definition_json' => [
            'summary' => ['label' => 'Summary', 'value' => 'Aligned statement'],
        ],
        'signed_off_at' => now()->subDay(),
        'signed_off_by' => $membership->user_id,
        'owner_participant_id' => $ownerParticipant->id,
    ]);

    $export = HandbookExport::query()->create([
        'organization_id' => $organization->id,
        'format' => 'pdf',
        'storage_disk' => 'public',
        'storage_path' => 'handbooks/'.$organization->id.'/failed.pdf',
        'status' => HandbookExport::STATUS_QUEUED,
        'queued_at' => now(),
    ]);

    $renderer = $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->andThrow(new RuntimeException('Cloudflare API token: secret-token'));
    });

    $job = new GenerateHandbookExport(
        (string) $export->getKey(),
        (string) $membership->user_id,
        (string) $organization->getKey(),
        null
    );

    expect(fn () => $job->handle(
        app(BuildHandbookExportPayload::class),
        app(SelectOfficialMetrics::class),
        $renderer
    ))->toThrow(RuntimeException::class);

    $export->refresh();

    expect($export->status)->toBe(HandbookExport::STATUS_FAILED)
        ->and($export->failed_at)->not()->toBeNull()
        ->and($export->failure_reason)->toBe('Handbook PDF generation failed: Cloudflare API token [redacted]');

    Storage::disk('public')->assertMissing($export->storage_path);
});
