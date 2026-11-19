<?php

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\HandbookExport;
use App\Models\Membership;
use App\Models\Metric;
use App\Models\Organization;
use App\Support\Handbook\HandbookPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('generates handbook exports within the 15 second performance budget', function () {
    Storage::fake('handbooks');
    Config::set('metric-harmony.handbook_disk', 'handbooks');

    $organization = Organization::factory()->create();

    $engagement = Engagement::factory()->create([
        'organization_id' => $organization->id,
        'status' => EngagementStatus::IN_REVIEW,
        'title' => 'Atlas Growth Metrics',
    ]);

    $membership = Membership::factory()
        ->admin()
        ->create([
            'organization_id' => $organization->id,
        ]);

    $definitionTemplate = collect(range(1, 40))->mapWithKeys(function ($index) {
        $key = "section_{$index}";

        return [
            $key => [
                'label' => "Section {$index}",
                'value' => 'Detail '.Str::random(120),
            ],
        ];
    })->all();

    $metrics = Metric::factory()
        ->count(10)
        ->create([
            'organization_id' => $organization->id,
            'engagement_id' => $engagement->id,
            'status' => MetricStatus::FINALIZED,
        ]);

    $metrics->each(function (Metric $metric) use ($definitionTemplate, $membership) {
        FinalDefinition::factory()
            ->for($metric)
            ->withConsultantSignals([
                'alignment_summary' => sprintf('%s is aligned across finance and product.', $metric->name),
                'implementation_gaps' => [
                    'current_state' => sprintf('%s still reconciles in a workbook.', $metric->name),
                    'gap' => 'Warehouse rollup still needs one filter adjustment.',
                    'owner' => 'Revenue ops',
                    'suggested_next_step' => 'Update the warehouse rollup before the next board pack.',
                ],
            ])
            ->create([
                'definition_json' => collect($definitionTemplate)
                    ->map(function (array $field) use ($metric) {
                        return [
                            'label' => $field['label'],
                            'value' => sprintf('%s (%s)', $field['value'], $metric->name),
                        ];
                    })
                    ->all(),
                'notes' => 'Approved for export',
                'signed_off_at' => now()->subHour(),
                'signed_off_by' => $membership->user_id,
                'user_id' => $membership->user_id,
            ]);
    });

    $this->mock(HandbookPdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->andReturn("%PDF-1.7\nperformance handbook");
    });

    $start = microtime(true);

    $response = $this
        ->actingAs($membership->user)
        ->post(route('client.handbook.exports.store', $organization));

    $response->assertRedirect();

    $elapsedMs = (microtime(true) - $start) * 1000;

    expect($elapsedMs)->toBeLessThan(15000);

    $export = HandbookExport::query()
        ->where('organization_id', $organization->id)
        ->latest('queued_at')
        ->first();

    expect($export)->not()->toBeNull();
    Storage::disk('handbooks')->assertExists($export->storage_path);
    expect($export->completed_at)->not()->toBeNull();
    expect(Storage::disk('handbooks')->get($export->storage_path))
        ->toBe("%PDF-1.7\nperformance handbook");
});
