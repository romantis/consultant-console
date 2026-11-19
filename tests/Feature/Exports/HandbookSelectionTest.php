<?php

use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\MetricTemplate;
use App\Models\Organization;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Assert;

uses(RefreshDatabase::class);

it('selects official metrics by template or key using the latest signed-off definitions', function () {
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');

    $organization = Organization::factory()->create();
    $engagementA = Engagement::factory()->for($organization)->create();
    $engagementB = Engagement::factory()->for($organization)->create();

    $membership = Membership::factory()->member()->create([
        'organization_id' => $organization->id,
    ]);

    $template = MetricTemplate::factory()->create();

    $templateOld = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementA->id,
        'metric_template_id' => $template->id,
        'name' => 'Template Old Metric',
        'status' => MetricStatus::FINALIZED,
    ]);

    $templateNew = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementB->id,
        'metric_template_id' => $template->id,
        'name' => 'Template Winner Metric',
        'status' => MetricStatus::FINALIZED,
    ]);

    FinalDefinition::factory()
        ->for($templateOld)
        ->withConsultantSignals([
            'alignment_summary' => 'Older template definition should lose selection.',
        ])
        ->create([
            'signed_off_at' => now()->subDays(10),
            'signed_off_by' => $membership->user_id,
        ]);

    FinalDefinition::factory()
        ->for($templateNew)
        ->withConsultantSignals([
            'alignment_summary' => 'Newest template definition should win export selection.',
        ])
        ->create([
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
        ]);

    $keyOld = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementA->id,
        'metric_template_id' => null,
        'key' => 'custom-key',
        'name' => 'Key Old Metric',
        'status' => MetricStatus::FINALIZED,
    ]);

    $keyNew = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementB->id,
        'metric_template_id' => null,
        'key' => 'custom-key',
        'name' => 'Key Winner Metric',
        'status' => MetricStatus::FINALIZED,
    ]);

    FinalDefinition::factory()
        ->for($keyOld)
        ->withConsultantSignals([
            'alignment_summary' => 'Older custom-key definition should lose selection.',
        ])
        ->create([
            'signed_off_at' => now()->subDays(5),
            'signed_off_by' => $membership->user_id,
        ]);

    FinalDefinition::factory()
        ->for($keyNew)
        ->withConsultantSignals([
            'alignment_summary' => 'Newest custom-key definition should win export selection.',
        ])
        ->create([
            'signed_off_at' => now()->subHours(6),
            'signed_off_by' => $membership->user_id,
        ]);

    $unsignedMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementA->id,
        'name' => 'Unsigned Metric',
        'status' => MetricStatus::FINALIZED,
    ]);

    FinalDefinition::factory()
        ->for($unsignedMetric)
        ->withConsultantSignals()
        ->create([
            'signed_off_at' => null,
        ]);

    $inReviewMetric = Metric::factory()->create([
        'organization_id' => $organization->id,
        'engagement_id' => $engagementA->id,
        'name' => 'In Review Metric',
        'status' => MetricStatus::IN_REVIEW,
    ]);

    FinalDefinition::factory()
        ->for($inReviewMetric)
        ->withConsultantSignals()
        ->create([
            'signed_off_at' => now()->subDay(),
            'signed_off_by' => $membership->user_id,
        ]);

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->with('pdf.organization-handbook', Mockery::on(function (array $payload): bool {
                $metricNames = array_map(
                    static fn (array $metric): string => (string) ($metric['name'] ?? ''),
                    $payload['metrics'] ?? []
                );
                sort($metricNames);

                Assert::assertSame(['Key Winner Metric', 'Template Winner Metric'], $metricNames);

                return true;
            }))
            ->andReturn("%PDF-1.7\nselected handbook");
    });

    $this->actingAs($membership->user)
        ->post(route('client.handbook.exports.store', $organization))
        ->assertRedirect();

    $export = HandbookExport::query()
        ->where('organization_id', $organization->id)
        ->latest('queued_at')
        ->first();

    expect($export)->not()->toBeNull();
    Storage::disk('handbooks')->assertExists($export->storage_path);

    expect(Storage::disk('handbooks')->get($export->storage_path))
        ->toBe("%PDF-1.7\nselected handbook");
});
