<?php

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\MetricParticipant;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Support\Handbook\HandbookPdfDocument;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;

uses(RefreshDatabase::class);

it('exports handbooks using canonical official definitions even when metric-level drafts exist', function () {
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');

    $organization = Organization::factory()->create();

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
        'title' => 'Northwind Metrics Reset',
    ]);

    $membership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Net Revenue Retention',
    ]);

    $ownerParticipant = MetricParticipant::factory()->approver()->create([
        'metric_id' => $metric->id,
        'user_id' => $membership->user_id,
    ]);

    FinalDefinition::factory()
        ->for($metric)
        ->withConsultantSignals([
            'alignment_summary' => 'Stale metric-level alignment summary',
            'implementation_gaps' => [
                'owner' => 'Finance ops',
            ],
        ])
        ->create([
            'definition_json' => [
                'summary' => [
                    'label' => 'Summary',
                    'value' => 'Metric-level stale summary that should not export',
                ],
                'owner' => [
                    'label' => 'Owner',
                    'value' => 'Metric-level stale owner',
                ],
            ],
            'notes' => 'Metric-level stale note',
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
            'owner_participant_id' => $ownerParticipant->id,
        ]);

    $orgOwnerParticipant = OrgMetricParticipant::factory()->create([
        'org_metric_id' => $metric->org_metric_id,
        'user_id' => $membership->user_id,
        'responsibility' => 'owner',
    ]);

    OrgFinalDefinition::factory()
        ->withConsultantSignals([
            'alignment_summary' => 'Canonical definition alignment summary',
            'operational_status' => 'operationally_ready',
            'operational_status_notes' => 'Signed dashboard already matches the approved logic.',
            'implementation_gaps' => [
                'owner' => 'Finance Ops',
                'gap' => 'No open implementation gap.',
            ],
        ])
        ->create([
            'org_metric_id' => $metric->org_metric_id,
            'source_metric_id' => $metric->id,
            'source_metric_template_id' => $metric->metric_template_id,
            'status' => 'Approved',
            'version' => 1,
            'definition_json' => [
                'summary' => [
                    'label' => 'Summary',
                    'value' => 'Canonical summary that matches signed-off record',
                ],
                'owner' => [
                    'label' => 'Owner',
                    'value' => 'Finance Ops',
                ],
            ],
            'notes' => 'Regulator-approved canonical wording',
            'owner_participant_id' => $orgOwnerParticipant->id,
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
        ]);

    MetricDefinition::factory()
        ->for($metric)
        ->create([
            'definition_json' => [
                'summary' => [
                    'label' => 'Summary',
                    'value' => 'Draft-only summary',
                ],
                'owner' => [
                    'label' => 'Owner',
                    'value' => 'Product Strategy',
                ],
            ],
            'normalized_json' => [
                'fields' => [
                    'summary' => [
                        'label' => 'Summary',
                        'value' => 'Draft-only summary',
                    ],
                ],
                'meta' => [],
            ],
        ]);

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock) use ($membership): void {
        $mock->shouldReceive('render')
            ->once()
            ->with('pdf.organization-handbook', Mockery::on(function (array $payload) use ($membership): bool {
                $document = HandbookPdfDocument::fromPayload($payload);
                $fieldValues = collect($document->metrics[0]['executive_sections'] ?? [])
                    ->flatMap(static fn (array $section): array => $section['items'] ?? [])
                    ->map(static fn (array $field): string => (string) ($field['value'] ?? ''))
                    ->all();
                $approvalValues = collect($document->metrics[0]['approval_version_rows'] ?? [])
                    ->mapWithKeys(static fn (array $row): array => [(string) ($row['label'] ?? '') => (string) ($row['value'] ?? '')])
                    ->all();

                Assert::assertContains('Canonical summary that matches signed-off record', $fieldValues);
                Assert::assertNotNull($approvalValues['Metric Owner'] ?? null);
                Assert::assertNotSame('Metric-level stale owner', $approvalValues['Metric Owner'] ?? null);
                Assert::assertNotSame('Product Strategy', $approvalValues['Metric Owner'] ?? null);
                Assert::assertNotContains('Metric-level stale summary that should not export', $fieldValues);
                Assert::assertNotContains('Metric-level stale owner', $fieldValues);
                Assert::assertNotContains('Draft-only summary', $fieldValues);
                Assert::assertNotContains('Product Strategy', $fieldValues);
                Assert::assertNotContains('Finance Ops', $fieldValues);
                Assert::assertSame($membership->user->name, $document->generatedBy);

                return true;
            }))
            ->andReturn("%PDF-1.7\ncanonical handbook");
    });

    $response = $this
        ->actingAs($membership->user)
        ->post(route('client.handbook.exports.store', $organization));

    $response->assertRedirect();

    $export = HandbookExport::query()
        ->where('organization_id', $organization->id)
        ->latest('queued_at')
        ->first();

    expect($export)->not()->toBeNull();
    Storage::disk('handbooks')->assertExists($export->storage_path);

    expect($export->completed_at)->not()->toBeNull();
    expect(Storage::disk('handbooks')->get($export->storage_path))
        ->toBe("%PDF-1.7\ncanonical handbook");
});

it('omits empty handbook fields while keeping filled validation guidance in the PDF export', function () {
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');

    $organization = Organization::factory()->create();

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::AWAITING_FORMS,
        'title' => 'Northwind Metrics Reset',
    ]);

    $membership = Membership::factory()->admin()->create([
        'organization_id' => $organization->id,
    ]);

    $metric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagement->id,
        'status' => MetricStatus::FINALIZED,
        'name' => 'Pipeline Coverage',
    ]);

    FinalDefinition::factory()
        ->for($metric)
        ->withConsultantSignals([
            'validation_steps' => 'Confirm the numerator and denominator use the same close-date window.',
            'common_mismatch_causes' => null,
        ])
        ->create([
            'definition_json' => [
                'summary' => [
                    'label' => 'Summary',
                    'value' => 'Pipeline coverage aligns sales and finance forecasting rules.',
                ],
            ],
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
        ]);

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->with('pdf.organization-handbook', Mockery::on(function (array $payload): bool {
                $document = HandbookPdfDocument::fromPayload($payload);

                Assert::assertSame([
                    [
                        'label' => 'Validation steps',
                        'value' => 'Confirm the numerator and denominator use the same close-date window.',
                    ],
                ], $document->metrics[0]['validation_guidance_rows'] ?? []);

                return true;
            }))
            ->andReturn("%PDF-1.7\nvalidation handbook");
    });

    $this
        ->actingAs($membership->user)
        ->post(route('client.handbook.exports.store', $organization))
        ->assertRedirect();

    $export = HandbookExport::query()
        ->where('organization_id', $organization->id)
        ->latest('queued_at')
        ->first();

    expect($export)->not()->toBeNull();
    Storage::disk('handbooks')->assertExists($export->storage_path);

    expect(Storage::disk('handbooks')->get($export->storage_path))
        ->toBe("%PDF-1.7\nvalidation handbook");
});
