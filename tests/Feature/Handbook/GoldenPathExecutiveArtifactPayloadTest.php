<?php

use App\Actions\Handbook\BuildHandbookExportPayload;
use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Engagement;
use App\Models\Metric;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\User;
use Database\Seeders\MetricTemplateSeeder;
use Database\Seeders\StorySeeds\GoldenPathExecutiveArtifactStorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('builds the golden-path handbook payload from the seeded executive artifact state', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $bundle = app(BuildHandbookExportPayload::class)->build(
        $organization,
        $metrics,
        generatedAt: Carbon::parse('2026-05-15 09:00:00')
    );

    $payload = $bundle['payload'];
    $approvalTimes = [
        'CAC and CPL' => Carbon::parse('2026-05-14 14:30:00', config('app.timezone'))->toIso8601String(),
        'Gross Revenue Churn' => Carbon::parse('2026-05-14 14:15:00', config('app.timezone'))->toIso8601String(),
        'Monthly Recurring Revenue (MRR)' => Carbon::parse('2026-05-14 14:00:00', config('app.timezone'))->toIso8601String(),
    ];

    expect($payload['organization'])->toBe([
        'id' => (string) $organization->getKey(),
        'name' => 'Orion Insight Collective',
    ])
        ->and($payload['next_review_on'])->toBe('2026-08-01')
        ->and($payload['review_cadence'])->toBe('Quarterly')
        ->and($payload['executive_brief'])->toBe([
            'engagement_id' => (string) $metrics->firstOrFail()->engagement_id,
            'engagement_title' => 'Unit Economics Board Baseline',
            'starts_on' => '2026-05-01',
            'ends_on' => '2026-05-15',
            'total_metric_count' => 3,
            'approved_metric_count' => 3,
            'operational_ready_count' => 1,
            'needs_follow_up_count' => 2,
            'implementation_gap_count' => 2,
            'next_review_on' => '2026-08-01',
            'review_cadence' => 'Quarterly',
        ])
        ->and($payload['approvals'])->toHaveCount(3)
        ->and($payload['generated_at']->toIso8601String())->toBe(Carbon::parse('2026-05-15 09:00:00')->toIso8601String());

    expect($payload['approvals'][0])->toBe([
        'metric_name' => 'CAC and CPL',
        'signed_off_at' => $approvalTimes['CAC and CPL'],
        'signed_off_by_name' => 'Dana Whitfield',
    ])
        ->and($payload['approvals'][1])->toBe([
            'metric_name' => 'Gross Revenue Churn',
            'signed_off_at' => $approvalTimes['Gross Revenue Churn'],
            'signed_off_by_name' => 'Lia Price',
        ])
        ->and($payload['approvals'][2])->toBe([
            'metric_name' => 'Monthly Recurring Revenue (MRR)',
            'signed_off_at' => $approvalTimes['Monthly Recurring Revenue (MRR)'],
            'signed_off_by_name' => 'Anthony Reyes',
        ]);

    $mrrPayload = collect($payload['metrics'])->firstWhere('name', 'Monthly Recurring Revenue (MRR)');
    $grossChurnPayload = collect($payload['metrics'])->firstWhere('name', 'Gross Revenue Churn');
    $cacCplPayload = collect($payload['metrics'])->firstWhere('name', 'CAC and CPL');
    $cacCplFields = collect($cacCplPayload['fields'] ?? [])->keyBy('key');

    expect($mrrPayload)->not->toBeNull()
        ->and($grossChurnPayload)->not->toBeNull()
        ->and($cacCplPayload)->not->toBeNull();

    expect($mrrPayload['alignment_summary'])
        ->toBe('MRR is now aligned for board use: finance, RevOps, and leadership all read the same month-end recurring run-rate number.')
        ->and($mrrPayload['operational_status'])->toBe('operationally_ready')
        ->and($mrrPayload['implementation_gaps'])->toBeNull()
        ->and($mrrPayload['validation_steps'])->toContain('board pack MRR matches the locked finance close workbook')
        ->and($mrrPayload['official_definition_history'])->toHaveCount(1)
        ->and($mrrPayload['official_definition_history'][0]['version'])->toBe(1);

    expect($grossChurnPayload['operational_status'])->toBe('needs_follow_up')
        ->and($grossChurnPayload['implementation_gaps']['owner'])->toBe('Revenue Operations')
        ->and($grossChurnPayload['implementation_gaps']['suggested_next_step'])
        ->toContain('Update the churn model')
        ->and($grossChurnPayload['common_mismatch_causes'])
        ->toContain('downgrades are mixed into net retention logic');

    expect($cacCplPayload['operational_status'])->toBe('needs_follow_up')
        ->and($cacCplPayload['implementation_gaps']['owner'])->toBe('Finance and Growth Marketing')
        ->and($cacCplPayload['implementation_gaps']['suggested_next_step'])
        ->toBe('Publish one board-ready acquisition-efficiency model with shared acquisition-cost inclusion rules to both finance and marketing dashboards.')
        ->and($cacCplPayload['validation_steps'])->toContain('included spend categories match the approved package')
        ->and($cacCplPayload['validation_steps'])->toContain('first-paying customer counts')
        ->and($cacCplPayload['common_mismatch_causes'])
        ->toContain('signed contracts are mistaken for first-paying customers')
        ->and($cacCplFields->get('costs_included')['value'] ?? [])->toContain('overhead')
        ->and($cacCplFields->get('costs_included')['display_value'] ?? null)
        ->toBe('Direct advertising spend (paid media platforms), Marketing salaries / agency fees, Sales salaries / commissions, Overhead (office, software, general expenses)')
        ->and($cacCplFields->get('customer_definition')['value'] ?? null)->toBe('first_payment')
        ->and($cacCplFields->get('customer_definition')['display_value'] ?? null)
        ->toBe('First payment processed')
        ->and($cacCplPayload['alignment_summary'])
        ->toContain('one board definition');
});

it('excludes unrelated organization metrics even when a mixed finalized metric collection is passed in', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $foreignOrganization = Organization::factory()->create([
        'name' => 'Foreign Board Pack Org',
        'slug' => 'foreign-board-pack-org',
    ]);

    $foreignEngagement = Engagement::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'status' => EngagementStatus::FINALIZED,
        'title' => 'Foreign Investor Pack',
    ]);

    $foreignMetric = Metric::factory()->create([
        'organization_id' => $foreignOrganization->id,
        'engagement_id' => $foreignEngagement->id,
        'status' => MetricStatus::FINALIZED,
        'key' => 'foreign-arr',
        'name' => 'Foreign ARR',
        'slug' => 'foreign-arr',
    ]);

    $foreignApprover = User::factory()->create(['name' => 'Foreign Approver']);

    OrgFinalDefinition::factory()
        ->withConsultantSignals([
            'alignment_summary' => 'Foreign ARR should never leak into the Orion payload.',
        ])
        ->create([
            'org_metric_id' => $foreignMetric->org_metric_id,
            'source_metric_id' => $foreignMetric->id,
            'source_metric_template_id' => $foreignMetric->metric_template_id,
            'status' => 'Approved',
            'version' => 1,
            'definition_json' => [
                'summary' => ['label' => 'Summary', 'value' => 'Foreign official wording'],
            ],
            'signed_off_at' => Carbon::parse('2026-05-10 12:00:00'),
            'signed_off_by' => $foreignApprover->id,
        ]);

    $mixedMetrics = Metric::query()
        ->where('status', MetricStatus::FINALIZED)
        ->get();

    $payload = app(BuildHandbookExportPayload::class)->build($organization, $mixedMetrics)['payload'];
    $metricNames = collect($payload['metrics'])->pluck('name')->all();

    expect($metricNames)->toBe([
        'CAC and CPL',
        'Gross Revenue Churn',
        'Monthly Recurring Revenue (MRR)',
    ])
        ->and($payload['executive_brief']['total_metric_count'])->toBe(3)
        ->and(collect($payload['metrics'])->firstWhere('name', 'Foreign ARR'))->toBeNull()
        ->and(collect($payload['approvals'])->firstWhere('metric_name', 'Foreign ARR'))->toBeNull();
});
