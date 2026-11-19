<?php

use App\Enums\EngagementStatus;
use App\Enums\MetricStatus;
use App\Models\Discrepancy;
use App\Models\Engagement;
use App\Models\FinalDefinition;
use App\Models\FormSubmission;
use App\Models\Metric;
use App\Models\MetricDefinition;
use App\Models\Organization;
use App\Models\OrgFinalDefinition;
use App\Models\OrgMetricParticipant;
use App\Models\ReconciliationWorkshop;
use Database\Seeders\MetricTemplateSeeder;
use Database\Seeders\StorySeeds\GoldenPathExecutiveArtifactStorySeeder;
use Database\Seeders\StorySeedsSeeder;

it('seeds the golden path organization engagement and metrics idempotently', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $engagements = Engagement::query()
        ->where('organization_id', $organization->id)
        ->where('title', 'Unit Economics Board Baseline')
        ->get();

    expect($organization->name)->toBe('Orion Insight Collective')
        ->and($engagements)->toHaveCount(1);

    $engagement = $engagements->firstOrFail();

    expect($engagement->status)->toBe(EngagementStatus::FINALIZED)
        ->and($engagement->starts_on->toDateString())->toBe('2026-05-01')
        ->and($engagement->ends_on->toDateString())->toBe('2026-05-15')
        ->and($engagement->next_review_on->toDateString())->toBe('2026-08-01')
        ->and($engagement->finalized_at?->toDateTimeString())->toBe('2026-05-14 14:30:00')
        ->and($engagement->participants)->toHaveCount(4);

    $metrics = Metric::query()
        ->with(['finalDefinition', 'orgMetric.finalDefinitions', 'orgMetric.participants', 'participants'])
        ->where('engagement_id', $engagement->id)
        ->get();

    expect($metrics)->toHaveCount(3)
        ->and($metrics->pluck('key')->sort()->values()->all())->toBe([
            'golden-cac-cpl',
            'golden-gross-churn',
            'golden-mrr',
        ])
        ->and($metrics->pluck('name')->sort()->values()->all())->toBe([
            'CAC and CPL',
            'Gross Revenue Churn',
            'Monthly Recurring Revenue (MRR)',
        ])
        ->and($metrics->pluck('organization_id')->unique()->values()->all())->toBe([$organization->id]);

    $metrics->each(function (Metric $metric): void {
        expect($metric->status)->toBe(MetricStatus::FINALIZED)
            ->and($metric->org_metric_id)->not->toBeNull()
            ->and($metric->participants->pluck('responsibility')->all())->toContain('owner')
            ->and($metric->participants->pluck('responsibility')->all())->toContain('approver')
            ->and($metric->orgMetric->participants->pluck('responsibility')->all())->toContain('owner')
            ->and($metric->orgMetric->participants->pluck('responsibility')->all())->toContain('approver')
            ->and($metric->finalDefinition)->not->toBeNull()
            ->and($metric->finalDefinition->status)->toBe('Approved')
            ->and($metric->finalDefinition->signed_off_at)->not->toBeNull()
            ->and(
                $metric->orgMetric->finalDefinitions
                    ->where('source_metric_id', $metric->id)
                    ->whereNotNull('signed_off_at')
                    ->count()
            )->toBeGreaterThanOrEqual(1);
    });

    expect(
        OrgMetricParticipant::query()
            ->whereIn('org_metric_id', $metrics->pluck('org_metric_id'))
            ->count()
    )->toBe(6);

    expect(
        FinalDefinition::query()
            ->whereIn('metric_id', $metrics->pluck('id'))
            ->whereNotNull('signed_off_at')
            ->count()
    )->toBe(4)
        ->and(
            OrgFinalDefinition::query()
                ->whereIn('source_metric_id', $metrics->pluck('id'))
                ->whereNotNull('signed_off_at')
                ->count()
        )->toBe(4);

    $orgOfficialDefinitions = OrgFinalDefinition::query()
        ->whereIn('source_metric_id', $metrics->pluck('id'))
        ->whereNotNull('signed_off_at')
        ->get()
        ->keyBy('source_metric_id');

    $mrrMetric = $metrics->firstWhere('key', 'golden-mrr');
    $grossChurnMetric = $metrics->firstWhere('key', 'golden-gross-churn');
    $cacCplMetric = $metrics->firstWhere('key', 'golden-cac-cpl');
    $cacCplDefinition = $cacCplMetric instanceof Metric
        ? ($orgOfficialDefinitions[(string) $cacCplMetric->id]->definition_json ?? [])
        : [];
    $grossChurnDefinition = $grossChurnMetric instanceof Metric
        ? ($orgOfficialDefinitions[(string) $grossChurnMetric->id]->definition_json ?? [])
        : [];

    expect($mrrMetric)->not->toBeNull()
        ->and($grossChurnMetric)->not->toBeNull()
        ->and($cacCplMetric)->not->toBeNull()
        ->and($orgOfficialDefinitions[(string) $mrrMetric->id]->alignment_summary)
        ->toBe('MRR is now aligned for board use: finance, RevOps, and leadership all read the same month-end recurring run-rate number.')
        ->and($orgOfficialDefinitions[(string) $mrrMetric->id]->operational_status)->toBe('operationally_ready')
        ->and($orgOfficialDefinitions[(string) $mrrMetric->id]->implementation_gaps)->toBeNull()
        ->and($orgOfficialDefinitions[(string) $grossChurnMetric->id]->operational_status)->toBe('needs_follow_up')
        ->and($orgOfficialDefinitions[(string) $grossChurnMetric->id]->implementation_gaps['owner'] ?? null)->toBe('Revenue Operations')
        ->and($orgOfficialDefinitions[(string) $cacCplMetric->id]->operational_status)->toBe('needs_follow_up')
        ->and($orgOfficialDefinitions[(string) $cacCplMetric->id]->implementation_gaps['owner'] ?? null)->toBe('Finance and Growth Marketing')
        ->and($orgOfficialDefinitions[(string) $cacCplMetric->id]->validation_steps)
        ->toContain('included spend categories match the approved package')
        ->and($orgOfficialDefinitions[(string) $cacCplMetric->id]->common_mismatch_causes)
        ->toContain('payroll costs or overhead are excluded from one view')
        ->and($cacCplDefinition['costs_included']['value'] ?? [])->toContain('overhead')
        ->and($cacCplDefinition['customer_definition']['value'] ?? null)->toBe('first_payment')
        ->and($cacCplDefinition['overhead_handling']['value'] ?? null)->toBe('fully_loaded')
        ->and($grossChurnDefinition['churn_types_tracked']['value'] ?? [])->toBe(['revenue'])
        ->and($grossChurnDefinition['churn_timeframe']['value'] ?? null)->toBe('monthly')
        ->and($grossChurnDefinition['downgrade_handling']['value'] ?? null)->toBe('count_as_churn')
        ->and($grossChurnDefinition['voluntary_treatment']['value'] ?? null)->toBe('combined')
        ->and($grossChurnDefinition['churn_timing']['value'] ?? null)->toBe('end_of_cycle')
        ->and(
            FinalDefinition::query()
                ->where('metric_id', $mrrMetric->id)
                ->whereNotNull('signed_off_at')
                ->count()
        )->toBe(2)
        ->and(
            OrgFinalDefinition::query()
                ->where('source_metric_id', $mrrMetric->id)
                ->whereNotNull('signed_off_at')
                ->count()
        )->toBe(2)
        ->and($mrrMetric->finalDefinition?->version)->toBe(2);

    $workshops = ReconciliationWorkshop::query()
        ->where('metric_id', $cacCplMetric->id)
        ->where('engagement_id', $engagement->id)
        ->get();

    expect($workshops)->toHaveCount(1);

    $workshop = $workshops->first();

    expect($workshop)->not->toBeNull()
        ->and($workshop->scheduled_at->toDateTimeString())->toBe('2026-05-13 15:00:00')
        ->and($workshop->participants)->toBe([
            'Nora Kim',
            'Lia Price',
            'Dana Whitfield',
            'Anthony Reyes',
        ])
        ->and($workshop->decision_snapshot['consensus'])->toBe('Board CAC/CPL uses fully loaded acquisition spend, SQL leads, first-paying customers, and cohort timing.')
        ->and($workshop->decision_snapshot['field_decisions']['lead_definition']['selected'])->toBe('sales_qualified')
        ->and($workshop->decision_snapshot['field_decisions']['overhead_handling']['selected'])->toBe('fully_loaded')
        ->and($workshop->notes)->toBe('Marketing campaign CPL remains an operating view. It is not the board CAC/CPL baseline.');

    $cacCplSubmissionCount = FormSubmission::query()
        ->whereHas('form', fn ($query) => $query->where('metric_id', $cacCplMetric->id))
        ->count();

    $cacCplDefinitions = MetricDefinition::query()
        ->with('user')
        ->where('metric_id', $cacCplMetric->id)
        ->get();

    $financeDefinition = $cacCplDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'lia.price.golden@orion.test');
    $marketingDefinition = $cacCplDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'dana.whitfield.golden@orion.test');

    expect($cacCplSubmissionCount)->toBe(2)
        ->and($cacCplDefinitions)->toHaveCount(2)
        ->and($financeDefinition?->normalized_json['fields']['lead_definition']['normalized'] ?? null)->toBe('marketing_qualified')
        ->and($marketingDefinition?->normalized_json['fields']['lead_definition']['normalized'] ?? null)->toBe('any_contact')
        ->and($financeDefinition?->normalized_json['fields']['overhead_handling']['normalized'] ?? null)->toBe('fully_loaded')
        ->and($marketingDefinition?->normalized_json['fields']['overhead_handling']['normalized'] ?? null)->toBe('direct_only');

    $discrepancies = Discrepancy::query()
        ->where('metric_id', $cacCplMetric->id)
        ->get()
        ->keyBy('field_key');

    expect($discrepancies)->toHaveCount(6)
        ->and($discrepancies->keys()->sort()->values()->all())->toBe([
            'cost_timing',
            'costs_included',
            'customer_definition',
            'deduplication_method',
            'lead_definition',
            'overhead_handling',
        ])
        ->and($discrepancies['lead_definition']->status)->toBe('Resolved')
        ->and($discrepancies['lead_definition']->resolved_at?->toDateTimeString())->toBe('2026-05-13 16:30:00');

    $leadDefinitionValues = collect($discrepancies['lead_definition']->values_snapshot['values'] ?? []);
    $costValues = collect($discrepancies['costs_included']->values_snapshot['values'] ?? [])
        ->pluck('raw')
        ->flatten()
        ->values()
        ->all();

    expect($leadDefinitionValues->pluck('normalized')->all())->toBe([
        'marketing_qualified',
        'any_contact',
    ])
        ->and($leadDefinitionValues->pluck('normalized')->all())->not->toContain('sales_qualified')
        ->and($discrepancies['lead_definition']->values_snapshot['resolution']['notes'] ?? null)
        ->toContain('Workshop chose SQL')
        ->and($costValues)
        ->toContain('marketing_comp')
        ->toContain('sales_comp')
        ->toContain('overhead')
        ->toContain('direct_campaign_only');

    // Gross Churn assertions
    $grossChurnMetric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('key', 'golden-gross-churn')
        ->firstOrFail();

    $grossChurnWorkshops = ReconciliationWorkshop::query()
        ->where('metric_id', $grossChurnMetric->id)
        ->where('engagement_id', $engagement->id)
        ->get();

    expect($grossChurnWorkshops)->toHaveCount(1);
    $grossChurnWorkshop = $grossChurnWorkshops->first();

    expect($grossChurnWorkshop)->not->toBeNull()
        ->and($grossChurnWorkshop->scheduled_at->toDateTimeString())->toBe('2026-05-13 15:00:00')
        ->and($grossChurnWorkshop->participants)->toBe([
            'Nora Kim',
            'Anthony Reyes',
            'Lia Price',
        ])
        ->and($grossChurnWorkshop->decision_snapshot['consensus'])->toBe('Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; downgrade reductions are included to remain conservative.')
        ->and($grossChurnWorkshop->decision_snapshot['field_decisions']['downgrade_handling']['selected'])->toBe('count_as_churn')
        ->and($grossChurnWorkshop->decision_snapshot['field_decisions']['churn_timing']['selected'])->toBe('end_of_cycle')
        ->and($grossChurnWorkshop->notes)->toBe('Conceptually aligned, but implementation follow-up is active for RevOps to adjust BI dashboards.');

    $grossChurnSubmissionCount = FormSubmission::query()
        ->whereHas('form', fn ($query) => $query->where('metric_id', $grossChurnMetric->id))
        ->count();

    $grossChurnDefinitions = MetricDefinition::query()
        ->with('user')
        ->where('metric_id', $grossChurnMetric->id)
        ->get();

    $churnFinanceDefinition = $grossChurnDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'lia.price.golden@orion.test');
    $churnRevOpsDefinition = $grossChurnDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'anthony.reyes.golden@orion.test');

    expect($grossChurnSubmissionCount)->toBe(2)
        ->and($grossChurnDefinitions)->toHaveCount(2)
        ->and($churnFinanceDefinition?->normalized_json['fields']['downgrade_handling']['normalized'] ?? null)->toBe('count_as_churn')
        ->and($churnRevOpsDefinition?->normalized_json['fields']['downgrade_handling']['normalized'] ?? null)->toBe('track_as_contraction')
        ->and($churnFinanceDefinition?->normalized_json['fields']['churn_timing']['normalized'] ?? null)->toBe('end_of_cycle')
        ->and($churnRevOpsDefinition?->normalized_json['fields']['churn_timing']['normalized'] ?? null)->toBe('immediate');

    $grossChurnDiscrepancies = Discrepancy::query()
        ->where('metric_id', $grossChurnMetric->id)
        ->get()
        ->keyBy('field_key');

    expect($grossChurnDiscrepancies)->toHaveCount(4)
        ->and($grossChurnDiscrepancies->keys()->sort()->values()->all())->toBe([
            'churn_timing',
            'churn_types_tracked',
            'downgrade_handling',
            'voluntary_treatment',
        ])
        ->and($grossChurnDiscrepancies['churn_timing']->status)->toBe('Resolved')
        ->and($grossChurnDiscrepancies['churn_timing']->resolved_at?->toDateTimeString())->toBe('2026-05-13 16:00:00');

    $churnTimingValues = collect($grossChurnDiscrepancies['churn_timing']->values_snapshot['values'] ?? []);
    expect($churnTimingValues->pluck('normalized')->all())->toBe([
        'end_of_cycle',
        'immediate',
    ]);

    // MRR assertions
    $mrrMetric = Metric::query()
        ->where('engagement_id', $engagement->id)
        ->where('key', 'golden-mrr')
        ->firstOrFail();

    $mrrWorkshops = ReconciliationWorkshop::query()
        ->where('metric_id', $mrrMetric->id)
        ->where('engagement_id', $engagement->id)
        ->get();

    expect($mrrWorkshops)->toHaveCount(1);
    $mrrWorkshop = $mrrWorkshops->first();

    expect($mrrWorkshop)->not->toBeNull()
        ->and($mrrWorkshop->scheduled_at->toDateTimeString())->toBe('2026-05-13 15:00:00')
        ->and($mrrWorkshop->participants)->toBe([
            'Nora Kim',
            'Lia Price',
            'Anthony Reyes',
        ])
        ->and($mrrWorkshop->decision_snapshot['consensus'])->toBe('Board MRR baseline is defined as the locked month-end close pack, with annual deals prorated and usage/overage fees excluded.')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['mrr_snapshot_timing']['selected'])->toBe('last_calendar_day')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['annual_deal_handling']['selected'])->toBe('normalized_contract_value')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['discount_handling']['selected'])->toBe('net_recurring_price')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['mid_month_changes']['selected'])->toBe('wait_next_cycle')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['churn_timing']['selected'])->toBe('paid_through_date')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['trial_handling']['selected'])->toBe('exclude_until_payment')
        ->and($mrrWorkshop->decision_snapshot['field_decisions']['included_revenue_streams']['selected'])->toBe([
            'recurring_subscription_charges',
        ])
        ->and($mrrWorkshop->notes)->toBe('No implementation gaps; RevOps dashboards already fetch the approved baseline for board reports.');

    $mrrSubmissionCount = FormSubmission::query()
        ->whereHas('form', fn ($query) => $query->where('metric_id', $mrrMetric->id))
        ->count();

    $mrrDefinitions = MetricDefinition::query()
        ->with('user')
        ->where('metric_id', $mrrMetric->id)
        ->get();

    $mrrFinanceDefinition = $mrrDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'lia.price.golden@orion.test');
    $mrrRevOpsDefinition = $mrrDefinitions
        ->first(fn (MetricDefinition $definition): bool => $definition->user?->email === 'anthony.reyes.golden@orion.test');

    expect($mrrSubmissionCount)->toBe(2)
        ->and($mrrDefinitions)->toHaveCount(2)
        ->and($mrrFinanceDefinition?->normalized_json['fields']['annual_deal_handling']['normalized'] ?? null)->toBe('full_invoice_in_invoice_month')
        ->and($mrrRevOpsDefinition?->normalized_json['fields']['annual_deal_handling']['normalized'] ?? null)->toBe('normalized_contract_value');

    // Verify metadata was saved to normalized.meta
    expect($mrrFinanceDefinition?->normalized_json['meta']['team_department']['normalized'] ?? null)->toBe('finance')
        ->and($mrrRevOpsDefinition?->normalized_json['meta']['team_department']['normalized'] ?? null)->toBe('revenue_operations');

    $mrrDiscrepancies = Discrepancy::query()
        ->where('metric_id', $mrrMetric->id)
        ->get()
        ->keyBy('field_key');

    expect($mrrDiscrepancies)->toHaveCount(4)
        ->and($mrrDiscrepancies->keys()->sort()->values()->all())->toBe([
            'annual_deal_handling',
            'included_revenue_streams',
            'mid_month_changes',
            'mrr_snapshot_timing',
        ])
        ->and($mrrDiscrepancies['mrr_snapshot_timing']->status)->toBe('Resolved')
        ->and($mrrDiscrepancies['mrr_snapshot_timing']->resolved_at?->toDateTimeString())->toBe('2026-05-13 15:30:00')
        ->and($mrrDiscrepancies['annual_deal_handling']->status)->toBe('Resolved')
        ->and($mrrDiscrepancies['annual_deal_handling']->resolved_at?->toDateTimeString())->toBe('2026-05-13 15:30:00')
        ->and($mrrDiscrepancies['mid_month_changes']->status)->toBe('Resolved')
        ->and($mrrDiscrepancies['mid_month_changes']->resolved_at?->toDateTimeString())->toBe('2026-05-13 15:30:00')
        ->and($mrrDiscrepancies['included_revenue_streams']->status)->toBe('Resolved')
        ->and($mrrDiscrepancies['included_revenue_streams']->resolved_at?->toDateTimeString())->toBe('2026-05-13 15:30:00');

    $mrrSnapshotTimingValues = collect($mrrDiscrepancies['mrr_snapshot_timing']->values_snapshot['values'] ?? []);
    expect($mrrSnapshotTimingValues->pluck('normalized')->all())->toBe([
        'last_calendar_day',
        'rolling_daily',
    ]);

    // Check final signed definition matches workshop
    $mrrOfficialDef = $orgOfficialDefinitions[(string) $mrrMetric->id]->definition_json ?? [];
    expect($mrrOfficialDef['mrr_snapshot_timing']['value'] ?? null)->toBe('last_calendar_day')
        ->and($mrrOfficialDef['annual_deal_handling']['value'] ?? null)->toBe('normalized_contract_value')
        ->and($mrrOfficialDef['discount_handling']['value'] ?? null)->toBe('net_recurring_price')
        ->and($mrrOfficialDef['mid_month_changes']['value'] ?? null)->toBe('wait_next_cycle')
        ->and($mrrOfficialDef['churn_timing']['value'] ?? null)->toBe('paid_through_date')
        ->and($mrrOfficialDef['trial_handling']['value'] ?? null)->toBe('exclude_until_payment')
        ->and($mrrOfficialDef['included_revenue_streams']['value'] ?? [])->toBe([
            'recurring_subscription_charges',
        ]);
});

it('registers the golden path story in the aggregate story seeder', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(StorySeedsSeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->first();

    expect($organization)->not->toBeNull();

    $engagement = Engagement::query()
        ->where('organization_id', $organization->id)
        ->where('title', 'Unit Economics Board Baseline')
        ->first();

    expect($engagement)->not->toBeNull()
        ->and(
            Metric::query()
                ->where('engagement_id', $engagement->id)
                ->count()
        )->toBe(3);
});

it('patches executive_takeaway on existing signed-off definitions when running the seeder again', function () {
    $this->seed(MetricTemplateSeeder::class);
    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $organization = Organization::query()
        ->where('slug', 'orion-golden-path')
        ->firstOrFail();

    $metrics = Metric::query()
        ->where('organization_id', $organization->id)
        ->get();

    $expectedTakeaways = [
        'golden-mrr' => 'Board MRR now has one approved month-end recurring run-rate baseline across Finance, RevOps, and leadership.',
        'golden-gross-churn' => 'Gross Revenue Churn is approved as an end-of-cycle revenue-loss baseline; dashboard timing remains the only implementation follow-up.',
        'golden-cac-cpl' => 'CAC/CPL is approved as a fully loaded cohort-based acquisition efficiency package for board review.',
    ];

    // Simulate a prior local seed run that had stale authored takeaways.
    FinalDefinition::query()
        ->whereIn('metric_id', $metrics->pluck('id'))
        ->update(['executive_takeaway' => 'Stale executive takeaway.']);

    OrgFinalDefinition::query()
        ->whereIn('source_metric_id', $metrics->pluck('id'))
        ->update(['executive_takeaway' => 'Stale executive takeaway.']);

    FinalDefinition::query()
        ->whereIn('metric_id', $metrics->pluck('id'))
        ->each(function (FinalDefinition $definition): void {
            $payload = $definition->definition_json ?? [];
            unset($payload['overhead_handling']);
            $definition->forceFill(['definition_json' => $payload])->save();
        });

    OrgFinalDefinition::query()
        ->whereIn('source_metric_id', $metrics->pluck('id'))
        ->each(function (OrgFinalDefinition $definition): void {
            $payload = $definition->definition_json ?? [];
            unset($payload['overhead_handling']);
            $definition->forceFill(['definition_json' => $payload])->save();
        });

    $this->seed(GoldenPathExecutiveArtifactStorySeeder::class);

    $metrics->each(function (Metric $metric) use ($expectedTakeaways): void {
        $currentVersion = $metric->key === 'golden-mrr' ? 2 : 1;

        $finalDef = FinalDefinition::query()
            ->where('metric_id', $metric->id)
            ->where('version', $currentVersion)
            ->whereNotNull('signed_off_at')
            ->firstOrFail();

        $orgDef = OrgFinalDefinition::query()
            ->where('source_metric_id', $metric->id)
            ->where('version', $currentVersion)
            ->whereNotNull('signed_off_at')
            ->firstOrFail();

        expect($finalDef->executive_takeaway)->toBe($expectedTakeaways[$metric->key])
            ->and($orgDef->executive_takeaway)->toBe($expectedTakeaways[$metric->key]);

        if ($metric->key === 'golden-cac-cpl') {
            expect($finalDef->definition_json['overhead_handling']['value'] ?? null)->toBe('fully_loaded')
                ->and($orgDef->definition_json['overhead_handling']['value'] ?? null)->toBe('fully_loaded');
        }
    });
});
